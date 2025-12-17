<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Helpers;

/**
 * Collection helper for managing geo items during import.
 */
class Collection
{
    /**
     * @var array<string|int, Item>
     */
    public array $items = [];

    /**
     * Add an item to the collection.
     */
    public function add(Item $item): void
    {
        $this->items[$item->getId()] = $item;
    }

    /**
     * Find an item by geo ID.
     */
    public function findGeoId(string|int $geoId): ?Item
    {
        return $this->items[$geoId] ?? null;
    }

    /**
     * Find an item by ID.
     */
    public function findId(string|int $id): Item|false
    {
        foreach ($this->items as $item) {
            if ($item->getId() == $id) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Find an item by name.
     */
    public function findName(string $name): Item|false
    {
        foreach ($this->items as $item) {
            if ($item->data[2] == $name) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Reset the collection.
     */
    public function reset(): self
    {
        $this->items = [];

        return $this;
    }
}
