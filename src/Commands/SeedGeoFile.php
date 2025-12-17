<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\ProgressBar;
use Throwable;
use TwoFaces\LaravelCities\Helpers\Collection;
use TwoFaces\LaravelCities\Helpers\Item;

/**
 * Seed geo data from geonames files.
 */
class SeedGeoFile extends Command
{
    protected $signature = 'geo:seed {country? : Country code to import (optional)} {--append : Append to existing data} {--chunk=1000 : Batch size for processing} {--cleanup : Delete geo files after successful import}';

    protected $description = 'Seed geonames data into database from text files';

    public function __construct(
        private readonly Config $config,
        private readonly DatabaseManager $db
    ) {
        parent::__construct();
    }

    /**
     * Build database tree structure recursively.
     */
    private function buildDbTree(Item $item, int $count = 1, int $depth = 0): int
    {
        $item->left = $count++;
        $item->depth = $depth;

        foreach ($item->getChildren() as $child) {
            $count = $this->buildDbTree($child, $count, $depth + 1);
        }

        $item->right = $count++;

        return $count;
    }

    /**
     * Get table name.
     */
    private function getTableName(): string
    {
        return $this->config->get('laravel-cities.table_name', 'geo');
    }

    /**
     * Clean up geo files from storage.
     */
    private function cleanupFiles(?string $country): void
    {
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');
        $geoPath = storage_path($storagePath);

        if (!is_dir($geoPath)) {
            return;
        }

        $this->info('Cleaning up geo files...');

        $deletedCount = 0;
        $sourceName = $country ?? 'allCountries';

        // Files to delete
        $filesToDelete = [
            "$sourceName.txt",
            "$sourceName.zip",
            'hierarchy.txt',
            "hierarchy-$sourceName.txt",
        ];

        foreach ($filesToDelete as $file) {
            $filePath = "$geoPath/$file";
            if (file_exists($filePath)) {
                if (unlink($filePath)) {
                    $this->line("  Deleted: $file");
                    $deletedCount++;
                } else {
                    $this->warn("  Failed to delete: $file");
                }
            }
        }

        // If country is null (allCountries), delete all country-specific hierarchy files
        if ($country === null) {
            $files = glob("$geoPath/hierarchy-*.txt");
            if ($files !== false) {
                foreach ($files as $file) {
                    if (unlink($file)) {
                        $this->line('  Deleted: ' . basename($file));
                        $deletedCount++;
                    }
                }
            }
        }

        if ($deletedCount > 0) {
            $this->info("Cleanup completed: $deletedCount file(s) deleted");
        } else {
            $this->info('No files to cleanup');
        }
    }

    /**
     * Read and parse geonames file.
     *
     * @throws Exception
     */
    private function readFile(
        string $fileName,
        ?string $country,
        Collection $geoItems,
        int $chunkSize,
        int &$batch
    ): void {
        $this->info("Reading file: $fileName");

        $filesize = filesize($fileName);
        if ($filesize === false) {
            throw new Exception("Failed to get file size: $fileName");
        }

        $handle = fopen($fileName, 'r');
        if ($handle === false) {
            throw new Exception("Failed to open file: $fileName");
        }

        $count = 0;
        $importLevels = $this->config->get('laravel-cities.import_levels', [
            'PCLI', 'PPLC', 'ADM1', 'ADM2', 'ADM3', 'PPLA', 'PPLA2',
        ]);

        $progressBar = new ProgressBar($this->output, 100);
        $progressBar->start();

        while (($line = fgets($handle)) !== false) {
            // Ignore empty lines and comments
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Convert TAB separated line to array
            $lineData = explode("\t", $line);

            // Validate line format
            if (count($lineData) !== 19) {
                $this->warn('Invalid line format: ' . ($lineData[0] ?? 'unknown'));

                continue;
            }

            // Check if level should be imported
            if (in_array($lineData[7], $importLevels, true)) {
                $geoItems->add(new Item($lineData, $geoItems));
                $count++;
            }

            $position = ftell($handle);
            if ($position !== false) {
                $progress = ($position / $filesize) * 100;
                $progressBar->setProgress((int) $progress);
            }

            // Process chunk when size is reached
            if (count($geoItems->items) >= $chunkSize) {
                $this->processItems($country, $geoItems, $batch);
                $batch++;
            }
        }

        // Process remaining items
        if (count($geoItems->items) > 0) {
            $this->processItems($country, $geoItems, $batch);
            $batch++;
        }

        fclose($handle);
        $progressBar->finish();
        $this->newLine();
        $this->info("Finished reading file. $count items loaded");

    }

    /**
     * Process a batch of items.
     *
     * @throws Exception
     */
    private function processItems(
        ?string $country,
        Collection $geoItems,
        int $batch
    ): void {
        // Read hierarchy
        $this->readHierarchy($country, $geoItems);

        // Build Tree
        $this->buildTree($geoItems);

        // Write to persistent storage
        $this->writeToDb($geoItems);

        // Reset the chunk
        $geoItems->reset();

        $this->newLine();
        $this->info("Processed batch $batch");
    }

    /**
     * Execute the console command.
     *
     * @throws Exception
     * @throws Throwable
     */
    public function handle(): int
    {
        $tableName = $this->config->get('laravel-cities.table_name', 'geo');

        if (!Schema::hasTable($tableName)) {
            $this->error("Table '$tableName' does not exist. Please run migrations first.");

            return self::FAILURE;
        }

        $start = microtime(true);
        $countryArg = $this->argument('country');
        $country = ($countryArg && is_string($countryArg)) ? strtoupper($countryArg) : null;
        $sourceName = $country ?? 'allCountries';
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');
        $fileName = storage_path("$storagePath/$sourceName.txt");
        $isAppend = $this->option('append');
        $chunkSize = (int) $this->option('chunk');

        if (!file_exists($fileName)) {
            $this->error("File not found: $fileName. Please download it first using geo:download command.");

            return self::FAILURE;
        }

        $this->info("Starting seed for $sourceName");

        $driver = strtolower($this->db->connection()->getDriverName());
        $geoItems = new Collection;
        $batch = 0;

        $this->db->beginTransaction();

        try {
            // Clear table if not appending
            if (!$isAppend) {
                $this->info("Truncating '$tableName' table...");
                $this->db->table($tableName)->truncate();
            }

            // Disable foreign key checks for MySQL
            if ($driver === 'mysql') {
                $this->db->statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            // Read and process file
            $this->readFile($fileName, $country, $geoItems, $chunkSize, $batch);

            // Re-enable foreign key checks for MySQL
            if ($driver === 'mysql') {
                $this->db->statement('SET FOREIGN_KEY_CHECKS=1;');
                $this->info('Foreign key checks re-enabled');
            }

            $this->db->commit();

            $elapsed = round(microtime(true) - $start, 2);
            $this->info("Completed successfully in $elapsed seconds");

            // Cleanup files if option is set
            if ($this->option('cleanup')) {
                $this->cleanupFiles($country);
            }

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->error('Error: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Read hierarchy file and build relationships.
     *
     * @throws Exception
     */
    private function readHierarchy(?string $country, Collection $geoItems): void
    {
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');
        $fileName = storage_path("$storagePath/hierarchy.txt");

        if ($country) {
            $countryFile = storage_path("$storagePath/hierarchy-$country.txt");
            if (file_exists($countryFile)) {
                $fileName = $countryFile;
            }
        }

        if (!file_exists($fileName)) {
            $this->warn("Hierarchy file not found: $fileName");

            return;
        }

        $this->info("Reading hierarchy from: $fileName");

        $handle = fopen($fileName, 'r');
        if ($handle === false) {
            throw new Exception("Failed to open hierarchy file: $fileName");
        }

        $filesize = filesize($fileName);
        if ($filesize === false) {
            fclose($handle);
            throw new Exception("Failed to get file size: $fileName");
        }

        $count = 0;

        $progressBar = new ProgressBar($this->output, 100);
        $progressBar->start();

        while (($line = fgetcsv($handle, 0, "\t")) !== false) {
            if (count($line) < 2) {
                continue;
            }

            $parent = $geoItems->findGeoId($line[0]);
            $child = $geoItems->findGeoId($line[1]);

            if ($parent !== null && $child !== null) {
                $parent->addChild($line[1]);
                $child->setParent($line[0]);
                $count++;
            }

            $position = ftell($handle);
            if ($position !== false) {
                $progress = ($position / $filesize) * 100;
                $progressBar->setProgress((int) $progress);
            }
        }

        fclose($handle);
        $progressBar->finish();
        $this->newLine();
        $this->info("Hierarchy building completed. $count relationships loaded");
    }

    /**
     * Build tree structure for items.
     */
    private function buildTree(Collection $geoItems): void
    {
        $count = 0;
        $countOrphan = 0;

        $maxRight = $this->db->table($this->getTableName())->max('right');
        $maxBoundary = $maxRight ? (int) $maxRight + 1 : 0;

        foreach ($geoItems->items as $item) {
            if ($item->parentId === null) {
                // Skip orphan items that are not countries
                if ($item->data[7] !== 'PCLI') {
                    $countOrphan++;

                    continue;
                }

                $count++;
                $this->info("Building tree for country: {$item->data[2]} #{$item->data[0]}");

                $maxBoundary = $this->buildDbTree($item, $maxBoundary);
            }
        }

        $this->info("Finished: $count countries imported, $countOrphan orphan items skipped");
    }

    /**
     * Write items to database.
     */
    private function writeToDb(Collection $geoItems): void
    {
        $this->info('Writing to database...');

        $totalCount = count($geoItems->items);
        $progressBar = new ProgressBar($this->output, $totalCount);
        $progressBar->start();

        $data = [];
        foreach ($geoItems->items as $item) {
            $data[] = [
                'id' => $item->getId(),
                'parent_id' => $item->parentId,
                'left' => $item->left,
                'right' => $item->right,
                'depth' => $item->depth,
                'name' => substr($item->data[2], 0, 40),
                'alternames' => $item->data[3],
                'country' => $item->data[8],
                'a1code' => $item->data[10],
                'level' => $item->data[7],
                'population' => (int) $item->data[14],
                'lat' => (float) $item->data[4],
                'long' => (float) $item->data[5],
                'timezone' => $item->data[17],
            ];

            $progressBar->advance();
        }

        // Batch insert
        $this->db->table($this->getTableName())->insert($data);

        $progressBar->finish();
        $this->newLine();
        $this->info('Wrote ' . count($data) . ' items to database');
    }
}
