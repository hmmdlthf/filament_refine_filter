<?php

declare(strict_types=1);

namespace Hmmdlthf\FilamentRefineFilter\Support;

use Filament\Tables\Contracts\HasTable;
use Hmmdlthf\FilamentRefineFilter\FacetSources\FacetSource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Builds the "every OTHER active filter/search applied, but not this
 * facet's own selection" query, then hands it to a FacetSource to
 * group + count.
 *
 * Standard faceted-search rule: a facet's own checkboxes must not exclude
 * their sibling options from the count (checking "Gold" shouldn't make
 * "Silver" disappear from the same list) — but every OTHER active filter,
 * quick search, and tenant/global scope on the table SHOULD narrow the
 * counts. We get this by cloning the table's real filtered query, but
 * temporarily blanking out this one filter's own form state before the
 * clone is taken.
 *
 * NOTE: This relies on the public $tableFilters property and
 * getFilteredTableQuery() exposed by Filament\Tables\Concerns\InteractsWithTable.
 * These are stable across recent Filament versions but are not a formally
 * versioned public contract — pin filament/filament and re-check this class
 * on major Filament upgrades.
 *
 * Results are memoized per Livewire request (per filter name), since a
 * page with N RefineFilters would otherwise run N nearly-identical count
 * queries on every single facet's own render pass.
 */
class FacetCounter
{
    /** @var array<string, Collection<string, int>> */
    protected static array $cache = [];

    public static function countsFor(HasTable $livewire, string $filterName, FacetSource $source): Collection
    {
        $cacheKey = spl_object_id($livewire) . ':' . $filterName;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $originalState = Arr::get($livewire->tableFilters, $filterName);

        // Blank this facet's own selection, take the clone, restore state —
        // scoped tightly so we never leave the live Livewire property mutated
        // for longer than this one query build.
        Arr::set($livewire->tableFilters, $filterName, null);
        $queryExcludingSelf = $livewire->getFilteredTableQuery()->clone();
        Arr::set($livewire->tableFilters, $filterName, $originalState);

        $counts = $source->getCounts($queryExcludingSelf);

        self::$cache[$cacheKey] = $counts;

        return $counts;
    }

    /**
     * Call at the start of each request/test if you need a clean slate
     * (the static cache is otherwise scoped for the lifetime of the PHP
     * process handling one request, which is normally exactly what you want).
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
