<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Helper\ProgressBar;
use TwoFaces\LaravelCities\Models\Geo;

/**
 * Import geo data from JSON file.
 */
class ImportJsonFile extends Command
{
    protected $signature = 'geo:import-json {file? : The JSON file name without extension}';

    protected $description = 'Import geo data from a JSON file';

    public function __construct(
        private readonly Config $config
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle(): int
    {
        $tableName = $this->config->get('laravel-cities.table_name', 'geo');

        if (!Schema::hasTable($tableName)) {
            $this->error("Table '$tableName' does not exist. Please run migrations first.");

            return self::FAILURE;
        }

        $start = microtime(true);
        $filename = $this->getFileName();

        if (!$filename) {
            return self::FAILURE;
        }

        $data = $this->loadJsonData($filename);

        if ($data === null) {
            return self::FAILURE;
        }

        $this->processData($data);

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("Completed in $elapsed seconds");

        return self::SUCCESS;
    }

    /**
     * Get the file name to process.
     */
    private function getFileName(): ?string
    {
        $filename = $this->argument('file');
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');

        if (empty($filename)) {
            $this->info('Available JSON files:');
            $this->info('---------------------');

            $files = $this->getAvailableJsonFiles($storagePath);

            if (empty($files)) {
                $this->error('No JSON files found in storage directory.');

                return null;
            }

            foreach ($files as $file) {
                $this->comment(' ' . $file);
            }

            $this->info('---------------------');
            $filename = $this->ask('Choose file to restore (without .json extension):');
        }

        return storage_path("$storagePath/$filename.json");
    }

    /**
     * Get available JSON files from storage.
     *
     * @return array<int, string>
     */
    private function getAvailableJsonFiles(string $storagePath): array
    {
        $directory = storage_path($storagePath);

        if (!is_dir($directory)) {
            return [];
        }

        $dirContents = scandir($directory);
        if ($dirContents === false) {
            return [];
        }

        $files = array_diff($dirContents, ['.', '..']);
        $jsonFiles = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.json')) {
                $jsonFiles[] = substr($file, 0, -5);
            }
        }

        return $jsonFiles;
    }

    /**
     * Load and decode JSON data.
     */
    private function loadJsonData(string $filename): ?array
    {
        if (!file_exists($filename)) {
            $this->error("File not found: $filename");

            return null;
        }

        $this->info("Loading file: $filename");

        $contents = file_get_contents($filename);
        if ($contents === false) {
            $this->error("Failed to read file: $filename");

            return null;
        }

        $data = json_decode($contents, true);

        if ($data === null) {
            $this->error('Error decoding JSON file. Check for syntax errors.');

            return null;
        }

        return $data;
    }

    /**
     * Process the JSON data.
     *
     * @throws Exception
     */
    private function processData(array $data): void
    {
        $progressBar = new ProgressBar($this->output, count($data));
        $progressBar->start();

        $count = 0;
        $rebuildTree = false;

        foreach ($data as $item) {
            if (isset($item['id'])) {
                $geo = Geo::query()->find($item['id']);
                if ($geo instanceof Geo) {
                    $geo->update($item);
                    $count++;
                    $progressBar->advance();

                    continue;
                }
            }

            // Create new record
            $item = array_merge([
                'alternames' => [],
                'country' => '',
                'level' => '',
                'population' => 0,
                'lat' => 0,
                'long' => 0,
            ], $item);

            Geo::query()->create($item);
            $rebuildTree = true;
            $count++;
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Processed $count items");

        if ($rebuildTree) {
            $this->info('Rebuilding tree structure...');

            Geo::rebuildTree($this->output);
        }
    }
}
