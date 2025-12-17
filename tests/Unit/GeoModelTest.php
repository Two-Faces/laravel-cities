<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Tests\Unit;

use TwoFaces\LaravelCities\Models\Geo;
use TwoFaces\LaravelCities\Tests\TestCase;

class GeoModelTest extends TestCase
{
    public function test_can_create_geo_model(): void
    {
        $geo = Geo::create([
            'id' => 1,
            'name' => 'Test Country',
            'level' => Geo::LEVEL_COUNTRY,
            'country' => 'TC',
            'alternames' => ['Test', 'Country'],
            'population' => 1000000,
            'lat' => 12.34,
            'long' => 56.78,
        ]);

        $this->assertInstanceOf(Geo::class, $geo);
        $this->assertEquals('Test Country', $geo->name);
        $this->assertEquals(Geo::LEVEL_COUNTRY, $geo->level);
    }

    public function test_get_countries_returns_collection(): void
    {
        // Create test countries
        Geo::create([
            'id' => 1,
            'name' => 'United States',
            'level' => Geo::LEVEL_COUNTRY,
            'country' => 'US',
        ]);

        Geo::create([
            'id' => 2,
            'name' => 'United Kingdom',
            'level' => Geo::LEVEL_COUNTRY,
            'country' => 'GB',
        ]);

        $countries = Geo::getCountries();

        $this->assertCount(2, $countries);
        $this->assertEquals('United Kingdom', $countries->first()->name);
        $this->assertEquals('United States', $countries->last()->name);
    }

    public function test_get_country_by_code(): void
    {
        Geo::create([
            'id' => 1,
            'name' => 'United States',
            'level' => Geo::LEVEL_COUNTRY,
            'country' => 'US',
        ]);

        $country = Geo::getCountry('US');

        $this->assertNotNull($country);
        $this->assertEquals('United States', $country->name);
        $this->assertEquals('US', $country->country);
    }

    public function test_find_by_name(): void
    {
        Geo::create([
            'id' => 1,
            'name' => 'New York',
            'level' => Geo::LEVEL_1,
            'country' => 'US',
        ]);

        $geo = Geo::findName('New York');

        $this->assertNotNull($geo);
        $this->assertEquals('New York', $geo->name);
    }

    public function test_search_names(): void
    {
        Geo::create([
            'id' => 1,
            'name' => 'New York',
            'alternames' => ['NYC', 'Big Apple'],
            'level' => Geo::LEVEL_1,
            'country' => 'US',
        ]);

        Geo::create([
            'id' => 2,
            'name' => 'York',
            'level' => Geo::LEVEL_1,
            'country' => 'GB',
        ]);

        $results = Geo::searchNames('york');

        $this->assertGreaterThanOrEqual(2, $results->count());
    }

    public function test_get_by_ids(): void
    {
        Geo::create(['id' => 1, 'name' => 'Location 1', 'level' => Geo::LEVEL_1]);
        Geo::create(['id' => 2, 'name' => 'Location 2', 'level' => Geo::LEVEL_1]);
        Geo::create(['id' => 3, 'name' => 'Location 3', 'level' => Geo::LEVEL_1]);

        $results = Geo::getByIds([1, 3]);

        $this->assertCount(2, $results);
        $this->assertTrue($results->contains('id', 1));
        $this->assertTrue($results->contains('id', 3));
        $this->assertFalse($results->contains('id', 2));
    }

    public function test_alternames_cast_to_array(): void
    {
        $geo = Geo::create([
            'id' => 1,
            'name' => 'Test',
            'alternames' => ['Alt1', 'Alt2'],
            'level' => Geo::LEVEL_1,
        ]);

        $this->assertIsArray($geo->alternames);
        $this->assertEquals(['Alt1', 'Alt2'], $geo->alternames);
    }

    public function test_filter_fields(): void
    {
        $geo = Geo::create([
            'id' => 1,
            'name' => 'Test City',
            'level' => Geo::LEVEL_1,
            'country' => 'US',
            'population' => 100000,
            'lat' => 12.34,
            'long' => 56.78,
        ]);

        $geo->filterFields(['id', 'name']);

        $array = $geo->toArray();

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayNotHasKey('population', $array);
    }

    public function test_level_constants_exist(): void
    {
        $this->assertEquals('PCLI', Geo::LEVEL_COUNTRY);
        $this->assertEquals('PPLC', Geo::LEVEL_CAPITAL);
        $this->assertEquals('ADM1', Geo::LEVEL_1);
        $this->assertEquals('ADM2', Geo::LEVEL_2);
        $this->assertEquals('ADM3', Geo::LEVEL_3);
    }
}

