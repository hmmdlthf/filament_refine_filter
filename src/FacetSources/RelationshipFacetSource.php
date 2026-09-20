<?php

declare(strict_types=1);

namespace Hmmdlthf\FilamentRefineFilter\FacetSources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * A facet backed by a BelongsTo relationship, e.g. customers.customer_group_id
 * -> customer_groups.id, displayed using customer_groups.name.
 *
 * v1 supports BelongsTo only. BelongsToMany (pivot-table facets) is a v2 item
 * — it needs a different count strategy (join through the pivot rather than
 * a plain groupBy on the owning table's FK column).
 */
class RelationshipFacetSource implements FacetSource
{
    public function __construct(
        protected string $relationshipName,
        protected string $labelColumn,
        protected ?string $valueColumn = null,
    ) {
    }

    /**
     * Resolve the relationship off a fresh model instance of the parent
     * table, so we can read the FK column name and the related model class
     * without needing a live record.
     */
    protected function resolveRelation(Builder $query): BelongsTo
    {
        $relation = $query->getModel()->{$this->relationshipName}();

        if (! $relation instanceof BelongsTo) {
            throw new InvalidArgumentException(
                "RefineFilter: relationship [{$this->relationshipName}] must be a BelongsTo relation for v1. "
                . 'BelongsToMany support is planned for v2.'
            );
        }

        return $relation;
    }

    public function getOptions(): Collection
    {
        // We need a query to resolve the relationship's related model class,
        // but getOptions() is called without one — so this is resolved lazily
        // via a bound closure in RefineFilter instead. Left unimplemented
        // here on purpose; RefineFilter calls getOptionsForQuery() below.
        throw new \LogicException(
            'RelationshipFacetSource requires a base query to resolve its relationship. '
            . 'Call getOptionsForQuery() via RefineFilter, not getOptions() directly.'
        );
    }

    /**
     * @return Collection<string, string>
     */
    public function getOptionsForQuery(Builder $query): Collection
    {
        $relation = $this->resolveRelation($query);
        $related = $relation->getRelated();
        $valueColumn = $this->valueColumn ?? $related->getKeyName();

        return $related->newQuery()
            ->orderBy($this->labelColumn)
            ->get([$valueColumn, $this->labelColumn])
            ->mapWithKeys(fn ($row) => [
                (string) $row->getAttribute($valueColumn) => (string) $row->getAttribute($this->labelColumn),
            ]);
    }

    public function getCounts(Builder $query): Collection
    {
        $relation = $this->resolveRelation($query);
        $foreignKey = $relation->getForeignKeyName();

        return $query->clone()
            ->toBase()
            ->groupBy($foreignKey)
            ->selectRaw("{$foreignKey} as facet_value, count(*) as aggregate")
            ->pluck('aggregate', 'facet_value')
            ->mapWithKeys(fn ($count, $value) => [(string) $value => (int) $count]);
    }

    public function applyQuery(Builder $query, array $selected): Builder
    {
        $relation = $this->resolveRelation($query);
        $foreignKey = $relation->getForeignKeyName();

        return $query->whereIn($foreignKey, $selected);
    }

    public function getForeignKeyName(Builder $query): string
    {
        return $this->resolveRelation($query)->getForeignKeyName();
    }
}
