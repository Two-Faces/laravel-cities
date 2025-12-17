<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Models;

use Exception;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base model for nested set tree structure.
 *
 * @property int|null $id
 * @property int|null $parent_id
 * @property int|null $left
 * @property int|null $right
 * @property int $depth
 * @property string $name
 * @property string $level
 * @property self|null $parent
 * @property array<int, self> $children
 */
class EloquentItemTree extends Eloquent
{
    /**
     * Temporary parent for tree building.
     */
    public ?EloquentItemTree $parent;

    /**
     * Temporary children array for tree building.
     *
     * @var array<int, self>
     */
    public array $children = [];

    /**
     * Rebuild the entire tree structure.
     *
     * @throws Exception
     */
    public static function rebuildTree(?OutputInterface $output = null, bool $printTree = false): void
    {
        /** @var array<int, self> $items */
        $items = [];

        // Create associative array of all elements
        self::printMessage('Create associative array', $output);
        foreach (self::all() as $item) {
            /** @var self $item */
            if ($item->id !== null) {
                $items[$item->id] = $item;
                $item->parent = null;
                $item->children = [];
            }
        }

        // Fill parent/children attributes
        self::printMessage('Create parent/children relations', $output);
        foreach ($items as $item) {
            if ($item->parent_id && isset($items[$item->parent_id])) {
                $item->parent = $items[$item->parent_id];
                $items[$item->parent_id]->addChild($item);
            }
        }

        // Build Tree for each Country (root) item
        self::printMessage('Build Tree', $output);
        $count = 1;
        foreach ($items as $item) {
            if ($item->level === Geo::LEVEL_COUNTRY) {
                $count = self::buildTreeStructure($item, $count);
                if ($printTree) {
                    $item->printTree();
                }
            }
        }

        // Save in DB
        self::printMessage('Save in DB', $output);
        foreach ($items as $item) {
            $item->save();
        }
    }

    /**
     * Print a message to output if available.
     */
    private static function printMessage(string $msg, ?OutputInterface $output): void
    {
        $output?->writeln('<info>- ' . $msg . '</info>');
    }

    /**
     * Add an item as a child.
     */
    private function addChild(self $item): void
    {
        $this->children[] = $item;
    }

    /**
     * Build tree structure recursively.
     */
    private static function buildTreeStructure(self $item, int $count = 1, int $depth = 0): int
    {
        $item->left = $count++;
        $item->depth = $depth;

        foreach ($item->children as $child) {
            $count = self::buildTreeStructure($child, $count, $depth + 1);
        }

        $item->right = $count++;

        return $count;
    }

    /**
     * Print the tree structure.
     */
    public function printTree(): void
    {
        $levelStr = str_repeat('-', $this->depth);
        $output = sprintf("%s %s [%d,%d]\n", $levelStr, $this->name, $this->left ?? 0, $this->right ?? 0);

        if (PHP_SAPI === 'cli') {
            echo $output;
        }

        foreach ($this->children as $child) {
            $child->printTree();
        }
    }
}
