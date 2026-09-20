<?php

declare(strict_types=1);

namespace Hmmdlthf\FilamentRefineFilter\FacetSources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A FacetSource knows three things about one facet (e.g. "Customer Group",
 * "Order Status"):
 *
 *  1. The full list of possible options (label + value), independent of
 *     any filtering — this is what gets rendered as checkboxes even before
 *     counts are known.
 *  2. How to turn a query (already scoped to "everything except this
 *     facet's own selection") into value => count pairs.
 *  3. How to apply the user's selected values back onto the table query.
 *
 * RelationshipFacetSource and EnumFacetSource are the two v1 implementations.
 */
interface FacetSource
{
    /**
     * @return Collection<string, string> value (scalar, used in whereIn) => label
     */
    public function getOptions(): Collection;

    /**
     * @param Builder $query A clone of the table's query, with every OTHER
     *                       active filter/search applied, but NOT this
     *                       facet's own selection. See FacetCounter.
     * @return Collection<string, int> value => row count
     */
    public function getCounts(Builder $query): Collection;

    /**
     * Apply the user's selected option values onto the real table query.
     *
     * @param array<int, string> $selected
     */
    public function applyQuery(Builder $query, array $selected): Builder;
}
