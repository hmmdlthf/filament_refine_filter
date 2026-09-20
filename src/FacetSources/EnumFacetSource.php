<?php

declare(strict_types=1);

namespace Hmmdlthf\FilamentRefineFilter\FacetSources;

use BackedEnum;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use UnitEnum;

/**
 * A facet backed directly by a database column that casts to a backed PHP
 * enum, e.g. orders.status -> App\Enums\OrderStatus.
 *
 * Only backed enums (enum X: string / enum X: int) are supported, since a
 * pure enum has no persisted scalar to whereIn()/groupBy() against.
 */
class EnumFacetSource implements FacetSource
{
    /** @var class-string<BackedEnum> */
    protected string $enumClass;

    protected string $column;

    /**
     * @param class-string<UnitEnum> $enumClass
     */
    public function __construct(string $enumClass, string $column)
    {
        if (! is_subclass_of($enumClass, BackedEnum::class)) {
            throw new InvalidArgumentException(
                "RefineFilter: [{$enumClass}] must be a backed enum (enum X: string|int). "
                . 'Pure enums have no persisted value to filter or count on.'
            );
        }

        $this->enumClass = $enumClass;
        $this->column = $column;
    }

    public function getOptions(): Collection
    {
        return collect($this->enumClass::cases())
            ->mapWithKeys(fn (BackedEnum $case) => [
                (string) $case->value => $case instanceof HasLabel
                    ? ($case->getLabel() ?? $case->name)
                    : $case->name,
            ]);
    }

    public function getCounts(Builder $query): Collection
    {
        $column = $query->getModel()->qualifyColumn($this->column);

        return $query->toBase()
            ->cloneWithout(['columns', 'orders'])
            ->cloneWithoutBindings(['select', 'order'])
            ->groupBy($column)
            ->selectRaw("{$column} as facet_value, count(*) as aggregate")
            ->pluck('aggregate', 'facet_value')
            ->mapWithKeys(fn ($count, $value) => [(string) $value => (int) $count]);
    }

    public function applyQuery(Builder $query, array $selected): Builder
    {
        $column = $query->getModel()->qualifyColumn($this->column);

        return $query->whereIn($column, $selected);
    }

    public function getColumn(): string
    {
        return $this->column;
    }
}
