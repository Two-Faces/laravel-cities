<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Build PPL hierarchy files from admin codes.
 */
class BuildPplTree extends Command
{
    protected $signature = 'geo:build-ppl-tree {--countries=}';

    protected $description = 'Build a PPL* hierarchy-ppl-*.txt from admin1CodesASCII.txt';

    public function __construct(
        private readonly Config $config
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $countriesOption = $this->option('countries');

        if (empty($countriesOption) || !is_string($countriesOption)) {
            $this->error('Please specify countries with --countries option (e.g., --countries=US,GB)');

            return self::FAILURE;
        }

        $countries = array_map('trim', explode(',', $countriesOption));

        try {
            $this->downloadAdmin1CodesASCIIIfNotExists();

            foreach ($countries as $country) {
                $country = strtoupper($country);
                $this->info("Processing country: $country");

                $this->buildPplHierarchy($country);
                $this->mergeHierarchies($country);

                $this->info("Completed: $country");
            }

            $this->info('All countries processed successfully!');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Download admin1CodesASCII.txt if not exists.
     *
     * @throws Exception
     */
    private function downloadAdmin1CodesASCIIIfNotExists(): void
    {
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');
        $fileName = $this->config->get('laravel-cities.files.admin1_codes', 'admin1CodesASCII.txt');
        $baseUrl = $this->config->get('laravel-cities.geonames_url', 'https://download.geonames.org/export/dump');

        $localPath = storage_path("$storagePath/$fileName");
        $remotePath = rtrim($baseUrl, '/') . '/' . $fileName;

        if (!file_exists($localPath)) {
            $this->info("Downloading $fileName...");

            if (!copy($remotePath, $localPath)) {
                throw new Exception("Failed to download the file: $remotePath");
            }

            $this->info('Downloaded successfully!');
        }
    }

    /**
     * Build PPL hierarchy for a country.
     *
     * @throws Exception
     */
    private function buildPplHierarchy(string $country): void
    {
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');
        $hierarchyPplFilePath = storage_path("$storagePath/hierarchy-ppl-$country.txt");
        $countryFilePath = storage_path("$storagePath/$country.txt");

        if (!file_exists($countryFilePath)) {
            throw new Exception("Country file not found: $countryFilePath. Please download it first.");
        }

        $map = $this->mapAdmin1Codes();
        $rows = '';

        $lines = file($countryFilePath);
        if ($lines === false) {
            throw new Exception("Failed to read file: $countryFilePath");
        }

        foreach ($lines as $line) {
            $cols = explode("\t", trim($line));

            if (!str_contains($cols[7] ?? '', 'PPL')) {
                continue;
            }

            $geoId = $cols[0];
            $key = "$cols[8].$cols[10]";
            $geoParentId = $map[$key] ?? null;

            if ($geoParentId) {
                $rows .= "$geoParentId\t$geoId" . PHP_EOL;
            }
        }

        file_put_contents($hierarchyPplFilePath, $rows);
        $this->info("Created hierarchy file: $hierarchyPplFilePath");
    }

    /**
     * Merge hierarchies for a country.
     *
     * @throws Exception
     */
    private function mergeHierarchies(string $country): void
    {
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');

        $files = [
            storage_path("$storagePath/hierarchy.txt"),
            storage_path("$storagePath/hierarchy-ppl-$country.txt"),
        ];

        $lines = [];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                $this->warn("File not found: $file");

                continue;
            }

            $fileLines = file($file);
            if ($fileLines === false) {
                $this->warn("Failed to read file: $file");

                continue;
            }

            foreach ($fileLines as $line) {
                $lines[] = trim($line);
            }
        }

        $lines = array_unique(array_filter($lines));
        $content = implode(PHP_EOL, $lines);

        $outputFile = storage_path("$storagePath/hierarchy-$country.txt");
        file_put_contents($outputFile, $content);

        $this->info("Merged hierarchy file: $outputFile");
    }

    /**
     * Map admin1 codes to parent IDs.
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function mapAdmin1Codes(): array
    {
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');
        $fileName = $this->config->get('laravel-cities.files.admin1_codes', 'admin1CodesASCII.txt');
        $localPath = storage_path("$storagePath/$fileName");

        if (!file_exists($localPath)) {
            throw new Exception("Admin1 codes file not found: $localPath");
        }

        $map = [];
        $lines = file($localPath);

        if ($lines === false) {
            throw new Exception("Failed to read file: $localPath");
        }

        foreach ($lines as $line) {
            $cols = explode("\t", trim($line));

            if (count($cols) >= 4) {
                $key = $cols[0];
                $parentId = $cols[3];
                $map[$key] = $parentId;
            }
        }

        return $map;
    }
}
