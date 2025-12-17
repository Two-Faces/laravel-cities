<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Helpers;

/**
 * Item helper for representing a single geo location during import.
 */
class Item
{
    /**
     * @var array<int, string>
     */
    public array $data;

    public string|int|null $parentId = null;

    /**
     * @var array<int, string|int>
     */
    public array $childrenGeoId = [];

    public int $depth = 0;

    public ?int $left = null;

    public ?int $right = null;

    private Collection $geoItems;

    /**
     * Create item from raw geonames data.
     *
     * @param array<int, string> $rawData
     */
    public function __construct(array $rawData, Collection $geoItems)
    {
        $encoded = json_encode(str_getcsv($rawData[3]), JSON_UNESCAPED_UNICODE);
        $rawData[3] = $encoded !== false ? $encoded : '[]';
        $this->data = $rawData;
        $this->geoItems = $geoItems;
    }

    /**
     * Get the geo ID.
     */
    public function getId(): string|int
    {
        return $this->data[0];
    }

    /**
     * Get the name.
     */
    public function getName(): string
    {
        return $this->data[2];
    }

    /**
     * Set the parent ID.
     */
    public function setParent(string|int $geoId): void
    {
        if ($this->geoItems->findGeoId($geoId)) {
            $this->parentId = $geoId;
        }
    }

    /**
     * Add a child ID.
     */
    public function addChild(string|int $geoId): void
    {
        $this->childrenGeoId[] = $geoId;
    }

    /**
     * Get all children items.
     *
     * @return array<int, Item>
     */
    public function getChildren(): array
    {
        $results = [];

        foreach ($this->childrenGeoId as $geoId) {
            if ($item = $this->geoItems->findGeoId($geoId)) {
                $results[] = $item;
            }
        }

        return $results;
    }
}
