<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Tests\Unit;

use TwoFaces\LaravelCities\Helpers\Collection;
use TwoFaces\LaravelCities\Helpers\Item;
use TwoFaces\LaravelCities\Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_collection_can_add_items(): void
    {
        $collection = new Collection;
        $itemData = ['1', 'Test', 'Test Item', '', '', '', '', 'ADM1'];

        $item = new Item($itemData, $collection);
        $collection->add($item);

        $this->assertCount(1, $collection->items);
    }

    public function test_collection_can_find_by_geo_id(): void
    {
        $collection = new Collection;
        $itemData = ['123', 'Test', 'Test Item', '', '', '', '', 'ADM1'];

        $item = new Item($itemData, $collection);
        $collection->add($item);

        $found = $collection->findGeoId('123');

        $this->assertInstanceOf(Item::class, $found);
        $this->assertEquals('123', $found->getId());
    }

    public function test_collection_reset_clears_items(): void
    {
        $collection = new Collection;
        $itemData = ['1', 'Test', 'Test Item', '', '', '', '', 'ADM1'];

        $item = new Item($itemData, $collection);
        $collection->add($item);

        $this->assertCount(1, $collection->items);

        $collection->reset();

        $this->assertCount(0, $collection->items);
    }

    public function test_item_can_get_id(): void
    {
        $collection = new Collection;
        $itemData = ['999', 'Test', 'Test Item', '', '', '', '', 'ADM1'];

        $item = new Item($itemData, $collection);

        $this->assertEquals('999', $item->getId());
    }

    public function test_item_can_get_name(): void
    {
        $collection = new Collection;
        $itemData = ['1', 'Test', 'My City', '', '', '', '', 'ADM1'];

        $item = new Item($itemData, $collection);

        $this->assertEquals('My City', $item->getName());
    }

    public function test_item_can_add_child(): void
    {
        $collection = new Collection;
        $itemData = ['1', 'Test', 'Parent', '', '', '', '', 'ADM1'];

        $item = new Item($itemData, $collection);
        $item->addChild('child123');

        $this->assertContains('child123', $item->childrenGeoId);
    }

    public function test_item_can_set_parent(): void
    {
        $collection = new Collection;

        // Add parent to collection first
        $parentData = ['parent456', 'Parent', 'Parent City', '', '', '', '', 'ADM1'];
        $parent = new Item($parentData, $collection);
        $collection->add($parent);

        // Now set parent
        $itemData = ['1', 'Test', 'Child', '', '', '', '', 'ADM2'];
        $item = new Item($itemData, $collection);
        $item->setParent('parent456');

        $this->assertEquals('parent456', $item->parentId);
    }

    public function test_item_has_children(): void
    {
        $collection = new Collection;
        $itemData = ['1', 'Test', 'Parent', '', '', '', '', 'ADM1'];

        $item = new Item($itemData, $collection);

        $this->assertEmpty($item->childrenGeoId);
        $this->assertCount(0, $item->getChildren());

        $item->addChild('child1');

        $this->assertNotEmpty($item->childrenGeoId);
        $this->assertCount(0, $item->getChildren()); // Still 0 because child not in collection
    }

    public function test_item_get_children(): void
    {
        $collection = new Collection;

        // Create parent
        $parentData = ['1', 'Parent', 'Parent City', '', '', '', '', 'ADM1'];
        $parent = new Item($parentData, $collection);
        $collection->add($parent);

        // Create children
        $child1Data = ['2', 'Child1', 'Child City 1', '', '', '', '', 'ADM2'];
        $child1 = new Item($child1Data, $collection);
        $collection->add($child1);

        $child2Data = ['3', 'Child2', 'Child City 2', '', '', '', '', 'ADM2'];
        $child2 = new Item($child2Data, $collection);
        $collection->add($child2);

        // Link children to parent
        $parent->addChild('2');
        $parent->addChild('3');

        $children = $parent->getChildren();

        $this->assertCount(2, $children);
    }
}
