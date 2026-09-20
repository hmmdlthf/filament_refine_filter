<?php

declare(strict_types=1);

namespace Hmmdlthf\FilamentRefineFilter\Tests\Fixtures;

use BadMethodCallException;
use Closure;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * A minimal HasTable double for testing FacetCounter and RefineFilter without
 * booting a real Livewire component. Only the members those two classes
 * actually touch (getTable(), getFilteredTableQuery(), $tableFilters) are
 * meaningfully implemented; everything else the interface requires but this
 * package doesn't use throws, so a test fails loudly if it starts relying
 * on machinery this double doesn't support.
 */
class FakeHasTable implements HasTable
{
    /** @var array<string, mixed> */
    public array $tableFilters = [];

    public int $filteredTableQueryCallCount = 0;

    protected ?Table $table = null;

    protected ?Closure $filteredTableQueryResolver = null;

    public function setTable(Table $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function resolveFilteredTableQueryUsing(Closure $resolver): static
    {
        $this->filteredTableQueryResolver = $resolver;

        return $this;
    }

    public function getTable(): Table
    {
        return $this->table;
    }

    public function getFilteredTableQuery(): ?Builder
    {
        $this->filteredTableQueryCallCount++;

        if ($this->filteredTableQueryResolver) {
            return ($this->filteredTableQueryResolver)($this->tableFilters);
        }

        return $this->getTable()->getQuery();
    }

    protected function unsupported(string $method): never
    {
        throw new BadMethodCallException(
            "FakeHasTable::{$method}() is not supported by this test double."
        );
    }

    public function callTableColumnAction(string $name, string $recordKey): mixed
    {
        $this->unsupported(__FUNCTION__);
    }

    public function deselectAllTableRecords(): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getActiveTableLocale(): ?string
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getAllSelectableTableRecordKeys(): array
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getAllTableRecordsCount(): int
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getAllSelectableTableRecordsCount(): int
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableFilterState(string $name): ?array
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableFilterFormState(string $name): ?array
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getSelectedTableRecords(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): EloquentCollection | Collection | LazyCollection
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getSelectedTableRecordsQuery(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): Builder
    {
        $this->unsupported(__FUNCTION__);
    }

    public function parseTableFilterName(string $name): string
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableGrouping(): ?Group
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getMountedTableAction(): ?Action
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getMountedTableActionForm(): ?Schema
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getMountedTableActionRecord(): ?Model
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getMountedTableBulkAction(): ?Action
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getMountedTableBulkActionForm(): ?Schema
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableFiltersForm(): Schema
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableRecords(): Collection | Paginator | CursorPaginator
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableRecordsPerPage(): int | string | null
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTablePage(): int | string
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableSortColumn(): ?string
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableSortDirection(): ?string
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getAllTableSummaryQuery(): ?Builder
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getPageTableSummaryQuery(): ?Builder
    {
        $this->unsupported(__FUNCTION__);
    }

    public function isTableColumnToggledHidden(string $name): bool
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableRecord(?string $key): Model | array | null
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableRecordKey(Model | array $record): string
    {
        $this->unsupported(__FUNCTION__);
    }

    public function toggleTableReordering(): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function isTableReordering(): bool
    {
        $this->unsupported(__FUNCTION__);
    }

    public function isTableLoaded(): bool
    {
        $this->unsupported(__FUNCTION__);
    }

    public function hasTableSearch(): bool
    {
        $this->unsupported(__FUNCTION__);
    }

    public function resetTableSearch(): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function resetTableColumnSearch(string $column): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableSearchIndicator(): Indicator
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableColumnSearchIndicators(): array
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getFilteredSortedTableQuery(): ?Builder
    {
        $this->unsupported(__FUNCTION__);
    }

    public function getTableQueryForExport(): Builder
    {
        $this->unsupported(__FUNCTION__);
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        $this->unsupported(__FUNCTION__);
    }

    public function callMountedTableAction(array $arguments = []): mixed
    {
        $this->unsupported(__FUNCTION__);
    }

    public function mountTableAction(string $name, ?string $record = null, array $arguments = []): mixed
    {
        $this->unsupported(__FUNCTION__);
    }

    public function replaceMountedTableAction(string $name, ?string $record = null, array $arguments = []): void
    {
        $this->unsupported(__FUNCTION__);
    }

    public function mountTableBulkAction(string $name, ?array $selectedRecords = null): mixed
    {
        $this->unsupported(__FUNCTION__);
    }

    public function replaceMountedTableBulkAction(string $name, ?array $selectedRecords = null): void
    {
        $this->unsupported(__FUNCTION__);
    }
}
