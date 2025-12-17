[![License](http://img.shields.io/badge/license-MIT-orange.svg)](https://tldrlegal.com/license/mit-license)

# Introduction

What you get:
- Deploy and use geonames.org (ex MaxCDN) database localy to query countries/cities
- Get information like lattitude/longtityde, population etc 
- Optimized [DB tree structure](https://en.wikipedia.org/wiki/Nested_set_model) for searching and traversing the tree.
- Provides an Eloquent model (geo) with multiple query-scopes to help you build your queries.
- Exposes a simple API that you can use to create AJAX calls. (Eg search while typing etc).

What you dont get:
- geoIP & Postalcodes (not included in free sets)
- Map elements smaller than "3rd Administration Division" (=Cities)

# Instructions
	
- Install with copmoser. Run:

`composer require two-faces/laravel-cities`

The Service provider will be autodiscovered and registered by Laravel. If you are using Laravel version <5.5 then you  must manually add the Service Provider in app.php:

```php
'providers' => [
    //...
    TwoFaces\LaravelCities\GeoServiceProvider::class,
];
```

- Create a folder `geo` into app's storage folder ('\storage\geo'). Download & unzip "hieararcy.txt" & "allCountries.txt" from geonames.org (http://download.geonames.org/export/dump)

[Tip] Quick script to download on your remote server with:

```
mkdir -p storage/geo && cd storage/geo
wget http://download.geonames.org/export/dump/allCountries.zip && unzip allCountries.zip && rm allCountries.zip
wget http://download.geonames.org/export/dump/hierarchy.zip && unzip hierarchy.zip && rm hierarchy.zip
```

or otherwise you can use 
```
artisan geo:download
```

Download a *.txt files from geonames.org By default it will download allcountries and hierarchy files otherwise you can pass flag --countries for specific countries

- Migrate and Seed. Run:

```
artisan migrate
artisan geo:seed
```

you can increase the memory limit for the cli invocation on demand to have process the command at once
```
php -d memory_limit=8000M artisan geo:seed --chunk=100000
```
So this will increase the memory limit for the command to 8GB with large chunk for each batches

You can also pass `--chunk` argument to specify how much chunk you want to process at once suppose you want `3000` records to be processed at once you can pass.
This gives flexibility to make the import with low memory footprints
```
artisan geo:seed --chunk=3000
```
by default it is `1000`

Note: If you don't want all the countries, you can download only country specific files (eg US.txt) and import each one of them with:

```
artisan geo:seed US --append
```

# Seed with custom data

Create a json file with custom data at `storage\geo` and run the following command to pick a file to seed:

```bash
php artisan geo:import-json
```

If an item exists in the DB (based on the 'id' value), then it will be updated else a new entry will be inserted. For example the following json file will rename `United States` to `USA` and it will add a child item (set by the parent_id value)

```json
[
  {
    "id": 6252001,
    "name": "USA"
  },
  {
    "name": "USA Child Item",
    "parent_id": 6252001,
    "alternames": ["51st State", "dummy name"],
    "population": 310232863,
    "lat": "39.760000",
    "long": "-98.500000"
  }
]
```
Please note that adding new items to the DB will reindex ALL items to rebuild the tree structure. Please be patient...

An example file is provided: [countryNames.json](https://github.com/Two-Faces/laravel-cities/blob/master/data/countryNames.json) which updates the official  country names with a most popular simplified version.

Tip: You can get a json representation from the DB by quering the API (see below)

# Geo Model:

You can use `TwoFaces\LaravelCities\Geo` Model to access the database. List of available properties:

```php
$geo->name;       // name of geographical point in plain ascii
$geo->alternames; // Array of alternate names (Stored as Json)
$geo->country;    // 2-letter country code (ISO-3166)
$geo->id;         // Original id from geonames.org database (geonameid)
$geo->population; // Population (Where provided)
$geo->lat;        // latitude in decimal degrees (wgs84)
$geo->long;       // longitude in decimal degrees (wgs84)
$geo->level;      // Administrator level code (feature code)
// parent_id, left, right, depth: Used to build hierarcy tree
```

Visit http://www.geonames.org > Info, for a more detailed description.

# Usage

## Searching:

```php
use TwoFaces\LaravelCities\Models\Geo;

Geo::getCountries();               // Get a Collection of all countries
Geo::getCountry('US');             // Get item by Country code
Geo::findName('Nomos Kerkyras');   // Find item by (ascii) name
Geo::searchNames('york');          // Search item by all alternative names. Case insensitive 
Geo::searchNames('vegas', Geo::getCountry('US'));  // ... and belongs to an item
Geo::getByIds([390903,3175395]);   // Get a Collection of items by Ids
```

## Traverse tree
```php
$children    = $geo->getChildren();    // Get direct Children of $geo (Collection)
$parent      = $geo->getParent();      // Get single Parent of $geo (Geo)
$ancenstors  = $geo->getAncensors();   // Get Ancenstors tree of $geo from top->bottom (Collection)
$descendants = $geo->getDescendants(); // Get all Descentants of $geo alphabetic (Collection)
```


## Check Hierarchy Relations:
```php
$geo1->isParentOf($geo2);       // (Bool) Check if $geo2 is direct Parent of $geo1
$geo2->isChildOf($geo1);        // (Bool) Check if $geo2 is direct Child of $geo1
$geo1->isAncenstorOf($geo2);    // (Bool) Check if $geo2 is Ancenstor of $geo1
$geo2->isDescendantOf($geo1);   // (Bool) Check if $geo2 is Descentant of $geo1
```

### Query Scopes

Build custom queries using these powerful scopes:

```php
use TwoFaces\LaravelCities\Models\Geo;

// Filter by administration level
Geo::level(Geo::LEVEL_COUNTRY)->get();  // All countries
Geo::level(Geo::LEVEL_CAPITAL)->get();  // All capitals
Geo::level(Geo::LEVEL_1)->get();        // Admin level 1
Geo::level(Geo::LEVEL_2)->get();        // Admin level 2
Geo::level(Geo::LEVEL_3)->get();        // Admin level 3

// Filter by country code
Geo::country('US')->get();

// Filter capitals only
Geo::capital()->get();

// Search by name (case-insensitive, includes alternames)
Geo::search('new york')->get();

// Get descendants of a location
Geo::areDescentants($parentGeo)->get();

// Instance scopes
$geo->ancenstors();     // Query ancestors
$geo->descendants();    // Query descendants
$geo->children();       // Query direct children
```

**Advanced Query Examples:**

```php
// Get all US states alphabetically
$usStates = Geo::getCountry('US')
    ->children()
    ->orderBy('name')
    ->get();

// Get all cities in California with population > 100,000
$californiaCity = Geo::findName('California');
$largeCities = Geo::areDescentants($californiaCity)
    ->where('population', '>', 100000)
    ->orderBy('population', 'desc')
    ->get();

// Get all European capitals
$europeanCountries = Geo::country('DE')
    ->orWhere('country', 'FR')
    ->orWhere('country', 'IT')
    ->get();

$capitals = Geo::capital()
    ->whereIn('country', $europeanCountries->pluck('country'))
    ->get();

// Search for cities starting with 'San' in USA
$sanCities = Geo::country('US')
    ->where('name', 'like', 'San%')
    ->orderBy('population', 'desc')
    ->get();
```

// Get the 3 biggest cities of Greece
Geo::getCountry('GR')
	->level(Geo::LEVEL_3)
	->orderBy('population','DESC')
	->limit(3)
	->get();
```

If you need more functionality you can extend `TwoFaces\LaravelCities\Geo` model and add your methods.

# HTTP API

This package defines some API routes that can be used to query the DB through simple HTTP requests. To use them insert in your routes file:

```php
\TwoFaces\LaravelCities\Models\Geo::ApiRoutes();
```

For example if you insert them in your `routes\api.php` (recomended) then the following URLs will be registered:


| URL Endpoind (GET)                | Description                                               | Returns (JSON) |
|-----------------------------------|-----------------------------------------------------------|----------------|
|api/geo/search/{name}/{parent-id?} | Search items containing 'name', (and belong to parent-id) | Collection     |
|api/geo/item/{id}                  | Get item by id                                            | Geo            |
|api/geo/items/{ids}                | Get multiple items by ids (comma seperated list)          | Collection     |
|api/geo/children/{id}              | Get children of item                                      | Collection     |
|api/geo/parent/{id}                | Get parent of item                                        | Geo            |
|api/geo/country/{code}             | get country by two-letter code                            | Geo            |
|api/geo/countries                  | list of countries                                         | Collection     |

The response is always a JSON representation of either a Geo class or a Collection.

To reduce bandwith, all Geo model attributes will be returned except from `alternames`, `left`, `right` and `depth`. You can change this behavior by passing an optional parameter on any request:

| URL Params (aplly to all routes)  | Description                             | Example                         |
|-----------------------------------|-----------------------------------------|---------------------------------|
|fields=field1,field2               | Returns only the specified attributes   | api/geo/countries?fields=id,name|
|fields=all                         | Returns all attributes                  | api/geo/countries?fields=all    |

Alternative you may publish the component with

`artisan vendor:publish --provider="TwoFaces\LaravelCities\GeoServiceProvider"`