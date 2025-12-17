<?php

declare(strict_types=1);

namespace TwoFaces\LaravelCities\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Geo model for geographical locations.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property int|null $left
 * @property int|null $right
 * @property int|null $depth
 * @property string $name
 * @property array|null $alternames
 * @property string|null $country
 * @property string|null $a1code
 * @property string|null $level
 * @property int|null $population
 * @property float|null $lat
 * @property float|null $long
 * @property string|null $timezone
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Geo country(string $countryCode)
 * @method static \Illuminate\Database\Eloquent\Builder|Geo capital()
 * @method static \Illuminate\Database\Eloquent\Builder|Geo level(string $level)
 * @method static \Illuminate\Database\Eloquent\Builder|Geo search(string $search)
 * @method static \Illuminate\Database\Eloquent\Builder|Geo areDescentants(Geo $parent)
 * @method \Illuminate\Database\Eloquent\Builder|Geo descendants()
 * @method \Illuminate\Database\Eloquent\Builder|Geo ancenstors()
 * @method \Illuminate\Database\Eloquent\Builder|Geo children()
 */
class Geo extends EloquentItemTree
{
    protected $table = 'geo';

    protected $guarded = [];

    public $timestamps = false;

    public const LEVEL_COUNTRY = 'PCLI';

    public const LEVEL_CAPITAL = 'PPLC';

    public const LEVEL_PPL = 'PPL';

    public const LEVEL_1 = 'ADM1';

    public const LEVEL_2 = 'ADM2';

    public const LEVEL_3 = 'ADM3';

    protected $casts = [
        'alternames' => 'array',
        'population' => 'integer',
        'lat' => 'float',
        'long' => 'float',
        'left' => 'integer',
        'right' => 'integer',
        'depth' => 'integer',
    ];

    protected $hidden = ['alternames', 'left', 'right', 'depth'];

    // ----------------------------------------------
    //  Scopes
    // ----------------------------------------------

    /**
     * Filter by country code.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeCountry(Builder $query, string $countryCode): Builder
    {
        return $query->where('country', $countryCode);
    }

    /**
     * Filter capital cities.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeCapital(Builder $query): Builder
    {
        return $query->where('level', self::LEVEL_CAPITAL);
    }

    /**
     * Filter by level.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /**
     * Get all descendants of current item.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeDescendants(Builder $query): Builder
    {
        return $query->where('left', '>', $this->left)
            ->where('right', '<', $this->right);
    }

    /**
     * Get all ancestors of current item.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeAncenstors(Builder $query): Builder
    {
        return $query->where('left', '<', $this->left)
            ->where('right', '>', $this->right);
    }

    /**
     * Get immediate children of current item.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeChildren(Builder $query): Builder
    {
        return $query->where('left', '>', $this->left)
            ->where('right', '<', $this->right)
            ->where('depth', $this->depth + 1);
    }

    /**
     * Search in name and alternames.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $search = '%' . mb_strtolower($search) . '%';

        return $query->where(function (Builder $query) use ($search) {
            $query->whereRaw('LOWER(alternames) LIKE ?', [$search])
                ->orWhereRaw('LOWER(name) LIKE ?', [$search]);
        });
    }

    /**
     * Filter descendants of a parent.
     *
     * @param Builder<static> $query
     *
     * @return Builder<static>
     */
    public function scopeAreDescentants(Builder $query, Geo $parent): Builder
    {
        return $query->where('left', '>', $parent->left)
            ->where('right', '<', $parent->right);
    }

    // ----------------------------------------------
    //  Methods
    // ----------------------------------------------

    /**
     * Search in `name` and `alternames`.
     */
    public static function searchNames(string $name, ?Geo $parent = null): Collection
    {
        $query = self::query()->search($name)->orderBy('name', 'ASC');

        if ($parent) {
            $query->areDescentants($parent);
        }

        return $query->get();
    }

    /**
     * Get all countries.
     */
    public static function getCountries(): Collection
    {
        return self::query()
            ->level(self::LEVEL_COUNTRY)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get country by country code (e.g., US, GR).
     */
    public static function getCountry(string $countryCode): ?self
    {
        return self::query()
            ->level(self::LEVEL_COUNTRY)
            ->country($countryCode)
            ->first();
    }

    /**
     * Get multiple items by IDs.
     *
     * @param array<int> $ids
     *
     * @return Collection<int, static>
     *
     * @phpstan-return Collection<int, static>
     */
    public static function getByIds(array $ids = []): Collection
    {
        /** @var Collection<int, static> */
        return self::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if this is an immediate child of given item.
     */
    public function isChildOf(Geo $item): bool
    {
        return ($this->left > $item->left)
            && ($this->right < $item->right)
            && ($this->depth == $item->depth + 1);
    }

    /**
     * Check if this is an immediate parent of given item.
     */
    public function isParentOf(Geo $item): bool
    {
        return ($this->left < $item->left)
            && ($this->right > $item->right)
            && ($this->depth == $item->depth - 1);
    }

    /**
     * Check if this is a descendant of given item (any depth).
     */
    public function isDescendantOf(Geo $item): bool
    {
        return ($this->left > $item->left) && ($this->right < $item->right);
    }

    /**
     * Check if this is an ancestor of given item (any depth).
     */
    public function isAncenstorOf(Geo $item): bool
    {
        return ($this->left < $item->left) && ($this->right > $item->right);
    }

    /**
     * Find item by name.
     */
    public static function findName(string $name): ?self
    {
        return self::query()->where('name', $name)->first();
    }

    /**
     * Get all immediate children.
     */
    public function getChildren(): Collection
    {
        return $this->newQuery()
            ->descendants()
            ->where('depth', $this->depth + 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get parent.
     */
    public function getParent(): ?self
    {
        return $this->newQuery()
            ->ancenstors()
            ->where('depth', $this->depth - 1)
            ->first();
    }

    /**
     * Get all ancestors ordered by level (Country -> City).
     */
    public function getAncensors(): Collection
    {
        return $this->newQuery()
            ->ancenstors()
            ->orderBy('depth')
            ->get();
    }

    /**
     * Get all descendants alphabetically.
     */
    public function getDescendants(): Collection
    {
        return $this->newQuery()
            ->descendants()
            ->orderBy('level')
            ->orderBy('name')
            ->get();
    }

    /**
     * Filter fields for JSON output. null = Show all.
     *
     * @param string|array<string>|null $fields
     */
    public function filterFields(string|array|null $fields = null): self
    {
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        if (empty($fields)) {
            $this->hidden = [];
        } else {
            $allFields = [
                'id', 'parent_id', 'left', 'right', 'depth',
                'name', 'alternames', 'country', 'level',
                'population', 'lat', 'long', 'timezone',
            ];

            $this->hidden = array_values(array_diff($allFields, $fields));
        }

        return $this;
    }
}
