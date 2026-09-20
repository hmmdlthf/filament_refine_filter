<?php

declare(strict_types=1);

namespace Hmmdlthf\FilamentRefineFilter\Filters;

use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Hmmdlthf\FilamentRefineFilter\FacetSources\EnumFacetSource;
use Hmmdlthf\FilamentRefineFilter\FacetSources\FacetSource;
use Hmmdlthf\FilamentRefineFilter\FacetSources\RelationshipFacetSource;
use Hmmdlthf\FilamentRefineFilter\Support\FacetCounter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;
use UnitEnum;

/**
 * ScriptCase-style "Refine Search" facet: a checkbox list of every possible
 * option for a column/relationship, each labelled with a live, faceted
 * count, that ANDs together with every other active table filter.
 *
 * Usage:
 *
 *   RefineFilter::make('customer_group')
 *       ->relationship('customerGroup', 'name')
 *       ->searchable()
 *
 *   RefineFilter::make('status')
 *       ->enum(OrderStatus::class)
 *
 * Drop it into ->filters([...], layout: FiltersLayout::AboveContentCollapsible)
 * alongside normal Filament filters — it's a plain Filter subclass.
 */
class RefineFilter extends Filter
{
    protected ?FacetSource $source = null;

    protected bool $showCounts = true;

    protected bool $hideZeroCounts = false;

    protected bool $searchableList = false;

    protected bool $collapsibleFacet = false;

    protected bool $collapsedByDefault = false;

    protected bool $persistCollapsedState = false;

    /** The name of the CheckboxList field inside this filter's schema. */
    protected string $fieldName = 'values';

    /**
     * Register a BelongsTo relationship facet.
     *
     * @param string $relationshipName e.g. 'customerGroup'
     * @param string $labelColumn      Column on the related model to display, e.g. 'name'
     * @param string|null $valueColumn Defaults to the related model's primary key
     */
    public function relationship(string $relationshipName, string $labelColumn, ?string $valueColumn = null): static
    {
        $this->source = new RelationshipFacetSource($relationshipName, $labelColumn, $valueColumn);

        return $this->buildSchema();
    }

    /**
     * Register a backed-PHP-enum column facet.
     *
     * @param class-string<UnitEnum> $enumClass
     * @param string|null $column Defaults to this filter's own name
     */
    public function enum(string $enumClass, ?string $column = null): static
    {
        $this->source = new EnumFacetSource($enumClass, $column ?? $this->getName());

        return $this->buildSchema();
    }

    public function showCounts(bool $condition = true): static
    {
        $this->showCounts = $condition;

        return $this->buildSchema();
    }

    public function hideZeroCounts(bool $condition = true): static
    {
        $this->hideZeroCounts = $condition;

        return $this->buildSchema();
    }

    public function searchable(bool $condition = true): static
    {
        $this->searchableList = $condition;

        return $this->buildSchema();
    }

    /**
     * Wrap this facet's checkboxes in their own collapsible section,
     * independent of the whole filter panel's own FiltersLayout collapse.
     */
    public function collapsible(bool $condition = true): static
    {
        $this->collapsibleFacet = $condition;

        return $this->buildSchema();
    }

    /** Implies ->collapsible() so this can be used on its own. */
    public function collapsed(bool $condition = true): static
    {
        $this->collapsedByDefault = $condition;
        $this->collapsibleFacet = true;

        return $this->buildSchema();
    }

    /**
     * Remembers the collapsed/expanded state client-side across reloads.
     * Implies ->collapsible() so this can be used on its own.
     */
    public function persistCollapsed(bool $condition = true): static
    {
        $this->persistCollapsedState = $condition;
        $this->collapsibleFacet = true;

        return $this->buildSchema();
    }

    protected function requireSource(): FacetSource
    {
        if (! $this->source) {
            throw new LogicException(
                "RefineFilter::make('{$this->getName()}') needs ->relationship(...) or ->enum(...) "
                . 'before it can be used.'
            );
        }

        return $this->source;
    }

    /**
     * (Re)builds the underlying Filter's schema + query() every time a
     * config method is called, so fluent chains in any order end up with
     * a fully wired filter.
     */
    protected function buildSchema(): static
    {
        $source = $this->requireSource();
        $filterName = $this->getName();
        $fieldName = $this->fieldName;
        $showCounts = $this->showCounts;
        $hideZeroCounts = $this->hideZeroCounts;

        $checkboxList = CheckboxList::make($fieldName)
            ->label($this->getLabel())
            ->options(function (HasTable $livewire) use ($source, $filterName, $showCounts, $hideZeroCounts): array {
                return self::resolveOptions($livewire, $source, $filterName, $showCounts, $hideZeroCounts);
            })
            ->live(); // re-run ->options() + sibling facets' counts on every toggle

        if ($this->searchableList) {
            $checkboxList->searchable();
        }

        $schemaComponents = [$checkboxList];

        if ($this->collapsibleFacet) {
            $checkboxList->hiddenLabel(); // the wrapping Section shows the heading instead

            $section = Section::make($this->getLabel())
                ->schema($schemaComponents)
                ->collapsible()
                ->collapsed($this->collapsedByDefault);

            if ($this->persistCollapsedState) {
                $section->persistCollapsed();
            }

            $schemaComponents = [$section];
        }

        $this->schema($schemaComponents);

        $this->query(function (Builder $query, array $data) use ($source, $fieldName): Builder {
            $selected = $data[$fieldName] ?? [];

            if (blank($selected)) {
                return $query;
            }

            return $source->applyQuery($query, $selected);
        });

        return $this;
    }

    /**
     * @return array<string, string> value => "Label (count)"
     */
    protected static function resolveOptions(
        HasTable $livewire,
        FacetSource $source,
        string $filterName,
        bool $showCounts,
        bool $hideZeroCounts,
    ): array {
        $baseQuery = $livewire->getTable()->getQuery();

        $options = $source instanceof RelationshipFacetSource
            ? $source->getOptionsForQuery($baseQuery)
            : $source->getOptions();

        if (! $showCounts) {
            return $options->all();
        }

        $counts = FacetCounter::countsFor($livewire, $filterName, $source);

        return $options
            ->mapWithKeys(function (string $label, string $value) use ($counts): array {
                $count = (int) ($counts->get($value) ?? 0);

                return [$value => "{$label} ({$count})"];
            })
            ->when(
                $hideZeroCounts,
                fn (Collection $opts) => $opts->filter(
                    fn (string $label, string $value) => (int) ($counts->get($value) ?? 0) > 0
                ),
            )
            ->all();
    }
}
