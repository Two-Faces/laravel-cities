<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;

/**
 * Clear the geo database table.
 */
class ClearGeoDatabase extends Command
{
    protected $signature = 'geo:clear {--force : Force clear without confirmation}';

    protected $description = 'Clear all data from the geo database table';

    public function __construct(
        private readonly Config $config,
        private readonly DatabaseManager $db
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('Are you sure you want to truncate the geo table? This cannot be undone.')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $tableName = $this->config->get('laravel-cities.table_name', 'geo');

        Model::unguard();

        // Disable foreign key checks for MySQL
        if ($this->db->connection()->getDriverName() === 'mysql') {
            $this->db->statement('SET FOREIGN_KEY_CHECKS=0;');
            $this->info('Foreign key checks disabled');
        }

        $this->db->table($tableName)->truncate();
        $this->info("Table \"$tableName\" is now empty.");

        // Re-enable foreign key checks for MySQL
        if ($this->db->connection()->getDriverName() === 'mysql') {
            $this->db->statement('SET FOREIGN_KEY_CHECKS=1;');
            $this->info('Foreign key checks re-enabled');
        }

        Model::reguard();

        return self::SUCCESS;
    }
}
