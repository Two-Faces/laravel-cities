<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Geonames Download URL
    |--------------------------------------------------------------------------
    |
    | Base URL for downloading geonames.org data files
    |
    */
    'geonames_url' => env('GEONAMES_URL', 'https://download.geonames.org/export/dump'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Path where downloaded geo files will be stored
    |
    */
    'storage_path' => env('GEO_STORAGE_PATH', 'geo'),

    /*
    |--------------------------------------------------------------------------
    | Database Table Name
    |--------------------------------------------------------------------------
    |
    | The database table name for storing geo data
    |
    */
    'table_name' => env('GEO_TABLE_NAME', 'geo'),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | Default chunk size for batch processing
    |
    */
    'chunk_size' => env('GEO_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Geo Levels
    |--------------------------------------------------------------------------
    |
    | Geonames location level codes
    |
    */
    'levels' => [
        'country' => 'PCLI',
        'capital' => 'PPLC',
        'city' => 'PPL',
        'admin1' => 'ADM1',
        'admin2' => 'ADM2',
        'admin3' => 'ADM3',
        'ppla' => 'PPLA',
        'ppla2' => 'PPLA2',
    ],

    /*
    |--------------------------------------------------------------------------
    | Import Levels
    |--------------------------------------------------------------------------
    |
    | Which geo levels to import during seeding
    |
    */
    'import_levels' => [
        'PCLI',  // Country
        'PPLC',  // Capital
        'ADM1',  // Admin level 1
        'ADM2',  // Admin level 2
        'ADM3',  // Admin level 3
        'PPLA',  // Admin level 1 capital
        'PPLA2', // Admin level 2 capital
        // 'PPL', // All cities - uncomment to import all cities (slow)
    ],

    /*
    |--------------------------------------------------------------------------
    | File Names
    |--------------------------------------------------------------------------
    |
    | Default file names for geonames data
    |
    */
    'files' => [
        'all_countries' => 'allCountries.zip',
        'hierarchy' => 'hierarchy.zip',
        'admin1_codes' => 'admin1CodesASCII.txt',
    ],
];

