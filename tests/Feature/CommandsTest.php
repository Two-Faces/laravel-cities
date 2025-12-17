<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use TwoFaces\LaravelCities\Models\Geo;
use TwoFaces\LaravelCities\Tests\TestCase;

class CommandsTest extends TestCase
{
    public function test_geo_table_exists_after_migration(): void
    {
        $this->assertTrue(Schema::hasTable('geo'));
    }

    public function test_geo_table_has_correct_columns(): void
    {
        $this->assertTrue(Schema::hasColumn('geo', 'id'));
        $this->assertTrue(Schema::hasColumn('geo', 'parent_id'));
        $this->assertTrue(Schema::hasColumn('geo', 'left'));
        $this->assertTrue(Schema::hasColumn('geo', 'right'));
        $this->assertTrue(Schema::hasColumn('geo', 'depth'));
        $this->assertTrue(Schema::hasColumn('geo', 'name'));
        $this->assertTrue(Schema::hasColumn('geo', 'alternames'));
        $this->assertTrue(Schema::hasColumn('geo', 'country'));
        $this->assertTrue(Schema::hasColumn('geo', 'a1code'));
        $this->assertTrue(Schema::hasColumn('geo', 'level'));
        $this->assertTrue(Schema::hasColumn('geo', 'population'));
        $this->assertTrue(Schema::hasColumn('geo', 'lat'));
        $this->assertTrue(Schema::hasColumn('geo', 'long'));
        $this->assertTrue(Schema::hasColumn('geo', 'timezone'));
    }

    public function test_clear_command_truncates_table(): void
    {
        // Add test data
        Geo::create([
            'id' => 1,
            'name' => 'Test',
            'level' => Geo::LEVEL_1,
        ]);

        $this->assertCount(1, Geo::all());

        // Run clear command with force flag
        Artisan::call('geo:clear', ['--force' => true]);

        $this->assertCount(0, Geo::all());
    }

    public function test_import_json_fails_without_file(): void
    {
        $exitCode = Artisan::call('geo:import-json', ['file' => 'non-existent-file']);

        $this->assertEquals(1, $exitCode);
    }

    public function test_download_command_requires_valid_countries_option(): void
    {
        // Skip this test as it requires network access
        $this->markTestSkipped('Download command requires network access');
    }

    public function test_seed_command_is_registered(): void
    {
        // Test command exists by checking help
        $exitCode = Artisan::call('help', ['command_name' => 'geo:seed']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_build_ppl_tree_command_is_registered(): void
    {
        // Test command exists by checking help
        $exitCode = Artisan::call('help', ['command_name' => 'geo:build-ppl-tree']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_all_commands_are_registered(): void
    {
        $commands = [
            'geo:download',
            'geo:seed',
            'geo:import-json',
            'geo:clear',
            'geo:build-ppl-tree',
        ];

        foreach ($commands as $command) {
            $exitCode = Artisan::call('help', ['command_name' => $command]);

            $this->assertEquals(
                0,
                $exitCode,
                "Command {$command} is not registered"
            );
        }
    }
}
