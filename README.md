# Filament Refine Filter

A ScriptCase-style **Refine Search** facet filter for [Filament](https://filamentphp.com) v5 tables — checkbox facets with **live, faceted counts** ("Gold (128)"), for both `BelongsTo` relationships and backed PHP enum columns.

Unlike a plain `SelectFilter`, counts on a `RefineFilter` are **faceted**: they reflect every *other* active filter and search term on the table, while ignoring the facet's own current selection — so checking "Region: EU" narrows the Customer Group counts, but checking a Customer Group box never makes its sibling options vanish.

## Requirements

- PHP 8.4+
- Laravel 12
- Filament ^5.0

## Installation

```bash
composer require hmmdlthf/filament_refine_filter
```

No panel plugin registration needed — `RefineFilter` is a plain `Filament\Tables\Filters\Filter` subclass, used directly in any resource's `table()` method.

## Usage

### Relationship facet (BelongsTo)

```php
use Hmmdlthf\FilamentRefineFilter\Filters\RefineFilter;
use Filament\Tables\Enums\FiltersLayout;

public static function table(Table $table): Table
{
    return $table
        ->filters([
            RefineFilter::make('customer_group')
                ->relationship('customerGroup', 'name')
                ->searchable(),

            RefineFilter::make('status')
                ->enum(App\Enums\OrderStatus::class),
        ], layout: FiltersLayout::AboveContentCollapsible);
}
```

`relationship()` expects a `BelongsTo` method on the model (e.g. `Customer::customerGroup()`), and the column on the related model to use as the checkbox label (e.g. `name`).

### Enum facet (backed PHP enums)

```php
RefineFilter::make('status')
    ->enum(OrderStatus::class) // column defaults to the filter's own name

RefineFilter::make('order_status')
    ->enum(OrderStatus::class, column: 'status') // explicit column, if it differs from the filter name
```

Only **backed** enums (`enum X: string` / `enum X: int`) are supported — a pure enum has no persisted scalar value to filter or count on, and `->enum()` throws immediately if you pass one.

If the enum implements Filament's `HasLabel` contract, its `getLabel()` is used for the checkbox text; otherwise the case name is used.

### Options

| Method | Default | Description |
|---|---|---|
| `->showCounts(bool $condition = true)` | `true` | Append `(count)` to each option's label |
| `->hideZeroCounts(bool $condition = true)` | `false` | Drop options whose faceted count is currently 0 |
| `->searchable(bool $condition = true)` | `false` | Add a search box inside the checkbox list (useful for long option lists) |
| `->collapsible(bool $condition = true)` | `false` | Wrap this facet's checkboxes in their own collapsible section, independent of every other facet and of the whole filter panel's own `FiltersLayout` collapse |
| `->collapsed(bool $condition = true)` | `false` | Start collapsed. Implies `->collapsible()` |
| `->persistCollapsed(bool $condition = true)` | `false` | Remember the collapsed/expanded state client-side across reloads. Implies `->collapsible()` |

## How faceted counting works

1. `RefineFilter` clones the table's real, currently-filtered query — but first blanks out *this* filter's own selection, so its counts aren't self-referential.
2. The clone is handed to a `FacetSource` (`RelationshipFacetSource` or `EnumFacetSource`), which groups by the relevant column/FK and counts rows per value.
3. Multi-tenancy and other global scopes on the resource are respected automatically, because the base query comes from the table's own scoped query rather than a fresh, unscoped `Model::query()`.

Counts are memoized per request per filter, so re-renders within the same Livewire request don't re-run the same count query.

**Cost note:** each `RefineFilter` on a page runs its own count query on every table re-render. This is cheap for a handful of facets on reasonably indexed tables; if you add many facets to a very large table, index the FK/enum columns you're faceting on and watch query counts in Laravel Debugbar/Telescope.

## Running tests

This package tests itself standalone via `orchestra/testbench`, which boots a
throwaway in-memory Laravel app — you do **not** need an existing Laravel or
Filament project to run the suite:

```bash
composer install
./vendor/bin/pest
```

The current suite (`tests/Feature/FacetCountingTest.php`) exercises
`FacetSource` implementations directly against a real sqlite database. It
does not yet cover `FacetCounter`'s Livewire-integration path (the
"clone the table's filtered query, excluding this facet's own selection"
logic) — that needs a Livewire test harness around a real `HasTable`
component and is the next test file to add.

## Current limitations (v1)

- `BelongsTo` relationships only — `BelongsToMany` (pivot-table facets) is planned for v2.
- No "+ View All" modal for facets with very long option lists yet.
- No active-filter tag chips yet.

## Roadmap

- [ ] `BelongsToMany` facets
- [x] Per-facet independent collapse
- [ ] "+ View All" modal for long option lists
- [ ] Active-filter tag chips (removable, shown above the table)
- [ ] Configurable option sort (count desc vs. label asc)

## License

MIT
