<?php

/**
 * Laravel Cities Usage Examples
 *
 * This file demonstrates various ways to use the Laravel Cities package.
 */

use TwoFaces\LaravelCities\Models\Geo;

// ============================================================================
// Basic Queries
// ============================================================================

// Get all countries
$countries = Geo::getCountries();

// Get a specific country by code
$usa = Geo::getCountry('US');
$uk = Geo::getCountry('GB');

// Find by exact name
$newYork = Geo::findName('New York');

// Get multiple locations by IDs
$locations = Geo::getByIds([390903, 3175395]);

// ============================================================================
// Search Operations
// ============================================================================

// Search by name (case-insensitive, searches in name and alternames)
$yorkLocations = Geo::searchNames('york');

// Search within a specific parent (e.g., cities in USA containing "San")
$usaSanCities = Geo::searchNames('San', Geo::getCountry('US'));

// ============================================================================
// Tree Navigation
// ============================================================================

// Get direct children
$usStates = $usa->getChildren();

// Get parent
$parent = $newYork->getParent();

// Get all ancestors (from country to immediate parent)
$ancestors = $newYork->getAncensors();

// Get all descendants (all children at any level)
$descendants = $usa->getDescendants();

// ============================================================================
// Query Scopes
// ============================================================================

// Get all capitals
$capitals = Geo::capital()->get();

// Get all countries
$allCountries = Geo::level(Geo::LEVEL_COUNTRY)->get();

// Get all first-level administrative divisions (states, provinces, etc.)
$adminLevel1 = Geo::level(Geo::LEVEL_1)->get();

// Get all locations in a specific country
$germanLocations = Geo::country('DE')->get();

// ============================================================================
// Advanced Queries
// ============================================================================

// Get US states sorted alphabetically
$usStatesAlpha = Geo::query()
    ->children()
    ->where('country', 'US')
    ->orderBy('name')
    ->get();

// Get European capitals
$europeanCapitals = Geo::capital()
    ->whereIn('country', ['DE', 'FR', 'IT', 'ES', 'GB'])
    ->orderBy('name')
    ->get();

// Get largest cities by population
$largeCities = Geo::where('population', '>', 1000000)
    ->orderBy('population', 'desc')
    ->take(100)
    ->get();

// Get all descendants of California
$california = Geo::findName('California');
if ($california) {
    $californiaCities = Geo::areDescentants($california)
        ->orderBy('population', 'desc')
        ->get();
}

// ============================================================================
// Relationship Checks
// ============================================================================

// Check if one location is parent of another
$isParent = $usa->isParentOf($california);

// Check if one location is child of another
$isChild = $california->isChildOf($usa);

// Check if one location is ancestor of another (any level up)
$isAncestor = $usa->isAncenstorOf($newYork);

// Check if one location is descendant of another (any level down)
$isDescendant = $newYork->isDescendantOf($usa);

// ============================================================================
// Filtering JSON Output
// ============================================================================

// Show only specific fields in JSON
$location = Geo::findName('London');
$filtered = $location->filterFields(['id', 'name', 'lat', 'long']);
// Now when you return $filtered as JSON, only these fields will be shown

// Show all fields
$location->filterFields(null);

// ============================================================================
// Working with Alternames
// ============================================================================

// Alternames are stored as JSON array
$location = Geo::findName('United States');
$alternames = $location->alternames; // Returns array of alternative names

// Search in alternames
$results = Geo::search('america')->get(); // Will find "United States" if "America" is in alternames

// ============================================================================
// Tree Rebuilding (Advanced)
// ============================================================================

// If you manually modify parent_id relationships, rebuild the tree structure
// Warning: This can take a long time for large datasets
Geo::rebuildTree();

// ============================================================================
// Example Controller Usage
// ============================================================================

/**
 * Example API Controller
 */
class GeoController
{
    /**
     * Get all countries
     */
    public function countries()
    {
        return response()->json(Geo::getCountries());
    }

    /**
     * Get states/provinces for a country
     */
    public function states(string $countryCode)
    {
        $country = Geo::getCountry(strtoupper($countryCode));

        if (!$country) {
            return response()->json(['error' => 'Country not found'], 404);
        }

        return response()->json($country->getChildren());
    }

    /**
     * Search locations
     */
    public function search(Request $request)
    {
        $query = $request->input('q');
        $countryCode = $request->input('country');

        if ($countryCode) {
            $country = Geo::getCountry(strtoupper($countryCode));
            $results = Geo::searchNames($query, $country);
        } else {
            $results = Geo::searchNames($query);
        }

        return response()->json($results);
    }

    /**
     * Get location details with ancestors
     */
    public function show(int $id)
    {
        $location = Geo::find($id);

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        return response()->json([
            'location' => $location,
            'ancestors' => $location->getAncensors(),
            'children' => $location->getChildren(),
        ]);
    }
}

