<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use ZipArchive;

/**
 * Download geonames data files.
 */
class DownloadGeoData extends Command
{
    private const ALL_COUNTRIES = 'all';

    protected $signature = 'geo:download {--countries=all : Comma-separated country codes or "all"}';

    protected $description = 'Download geonames data files from geonames.org';

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
        $this->ensureStorageDirectoryExists();

        foreach ($this->getFileNames() as $fileName) {
            $this->downloadAndExtractFile($fileName);
        }

        $this->info('Download completed successfully!');

        return self::SUCCESS;
    }

    /**
     * Get the list of files to download.
     *
     * @return array<int, string>
     */
    private function getFileNames(): array
    {
        $countries = $this->option('countries');
        $files = $this->config->get('laravel-cities.files', []);

        if ($countries === self::ALL_COUNTRIES) {
            return [
                $files['all_countries'] ?? 'allCountries.zip',
                $files['hierarchy'] ?? 'hierarchy.zip',
            ];
        }

        // Ensure countries is a string before explode
        if (!is_string($countries)) {
            $countries = 'all';
        }

        $countryCodes = explode(',', $countries);
        $fileNames = [
            $files['hierarchy'] ?? 'hierarchy.zip',
            $files['admin1_codes'] ?? 'admin1CodesASCII.txt',
        ];

        foreach ($countryCodes as $country) {
            $fileNames[] = strtoupper(trim($country)) . '.zip';
        }

        return $fileNames;
    }

    /**
     * Download and extract a file.
     *
     * @throws Exception
     */
    private function downloadAndExtractFile(string $fileName): void
    {
        $baseUrl = $this->config->get('laravel-cities.geonames_url', 'https://download.geonames.org/export/dump');
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');

        $source = rtrim($baseUrl, '/') . '/' . $fileName;
        $target = storage_path("$storagePath/$fileName");
        $targetTxt = storage_path($storagePath . '/' . preg_replace('/\.zip$/', '.txt', $fileName));

        $this->info("Source: $source");
        $this->info("Target: $targetTxt");

        if (!file_exists($target) && !file_exists($targetTxt)) {
            $this->info("Downloading $fileName...");

            if (!copy($source, $target)) {
                throw new Exception("Failed to download the file: $source");
            }

            $this->info('Downloaded successfully!');
        } else {
            $this->info('File already exists, skipping download.');
        }

        if (file_exists($target) && !file_exists($targetTxt) && str_ends_with($fileName, '.zip')) {
            $this->extractZipFile($target);
        }
    }

    /**
     * Extract a zip file.
     *
     * @throws Exception
     */
    private function extractZipFile(string $filePath): void
    {
        $this->info("Extracting $filePath...");

        $zip = new ZipArchive;

        if ($zip->open($filePath) !== true) {
            throw new Exception("Failed to open zip file: $filePath");
        }

        $zip->extractTo(dirname($filePath));
        $zip->close();

        $this->info('Extracted successfully!');
    }

    /**
     * Ensure storage directory exists.
     */
    private function ensureStorageDirectoryExists(): void
    {
        $storagePath = $this->config->get('laravel-cities.storage_path', 'geo');
        $directory = storage_path($storagePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
            $this->info("Created storage directory: $directory");
        }
    }
}
