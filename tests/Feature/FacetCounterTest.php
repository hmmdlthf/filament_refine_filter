<?php

declare(strict_types=1);

use Hmmdlthf\FilamentRefineFilter\FacetSources\RelationshipFacetSource;
use Hmmdlthf\FilamentRefineFilter\Support\FacetCounter;
use Hmmdlthf\FilamentRefineFilter\Tests\Fixtures\FakeHasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

// --- fixture models (same shape as FacetCountingTest.php) -----------------

class FacetCounterCustomerGroup extends Model
{
    protected $table = 'customer_groups';

    public $timestamps = false;

    protected $guarded = [];
}

class FacetCounterCustomer extends Model
{
    protected $table = 'customers';

    public $timestamps = false;

    protected $guarded = [];

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(FacetCounterCustomerGroup::class, 'customer_group_id');
    }
}

beforeEach(function () {
    FacetCounter::flush();
});

it('excludes the facet\'s own current selection when building counts', function () {
    $gold = FacetCounterCustomerGroup::create(['name' => 'Gold']);
    $silver = FacetCounterCustomerGroup::create(['name' => 'Silver']);

    FacetCounterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id, 'region' => 'EU']);
    FacetCounterCustomer::create(['name' => 'B', 'customer_group_id' => $silver->id, 'region' => 'EU']);

    $livewire = new FakeHasTable();
    $livewire->tableFilters = [
        'customer_group' => ['values' => [(string) $gold->id]],
    ];
    $livewire->resolveFilteredTableQueryUsing(function (array $filters) {
        $query = FacetCounterCustomer::query();

        if ($values = Arr::get($filters, 'customer_group.values')) {
            $query->whereIn('customer_group_id', $values);
        }

        return $query;
    });

    $source = new RelationshipFacetSource('customerGroup', 'name');

    $counts = FacetCounter::countsFor($livewire, 'customer_group', $source);

    // Both options are present with real counts, proving the facet's own
    // ['values' => [$gold->id]] selection was blanked out before counting.
    expect($counts->get((string) $gold->id))->toBe(1)
        ->and($counts->get((string) $silver->id))->toBe(1);
});

it('still respects every OTHER active filter while excluding its own', function () {
    $gold = FacetCounterCustomerGroup::create(['name' => 'Gold']);
    $silver = FacetCounterCustomerGroup::create(['name' => 'Silver']);

    FacetCounterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id, 'region' => 'EU']);
    FacetCounterCustomer::create(['name' => 'B', 'customer_group_id' => $gold->id, 'region' => 'US']);
    FacetCounterCustomer::create(['name' => 'C', 'customer_group_id' => $silver->id, 'region' => 'EU']);

    $livewire = new FakeHasTable();
    $livewire->tableFilters = [
        'customer_group' => ['values' => [(string) $gold->id]],
        'region' => ['value' => 'EU'],
    ];
    $livewire->resolveFilteredTableQueryUsing(function (array $filters) {
        $query = FacetCounterCustomer::query();

        if ($values = Arr::get($filters, 'customer_group.values')) {
            $query->whereIn('customer_group_id', $values);
        }

        if ($region = Arr::get($filters, 'region.value')) {
            $query->where('region', $region);
        }

        return $query;
    });

    $source = new RelationshipFacetSource('customerGroup', 'name');

    $counts = FacetCounter::countsFor($livewire, 'customer_group', $source);

    expect($counts->get((string) $gold->id))->toBe(1)
        ->and($counts->get((string) $silver->id))->toBe(1);
});

it('restores the facet\'s own filter state on $livewire after counting', function () {
    $gold = FacetCounterCustomerGroup::create(['name' => 'Gold']);
    FacetCounterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);

    $livewire = new FakeHasTable();
    $livewire->tableFilters = [
        'customer_group' => ['values' => [(string) $gold->id]],
    ];
    $livewire->resolveFilteredTableQueryUsing(fn () => FacetCounterCustomer::query());

    FacetCounter::countsFor($livewire, 'customer_group', new RelationshipFacetSource('customerGroup', 'name'));

    expect($livewire->tableFilters)->toBe([
        'customer_group' => ['values' => [(string) $gold->id]],
    ]);
});

it('memoizes counts per $livewire + filter name for the lifetime of the cache', function () {
    $gold = FacetCounterCustomerGroup::create(['name' => 'Gold']);
    FacetCounterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);

    $livewire = new FakeHasTable();
    $livewire->resolveFilteredTableQueryUsing(fn () => FacetCounterCustomer::query());

    $source = new RelationshipFacetSource('customerGroup', 'name');

    FacetCounter::countsFor($livewire, 'customer_group', $source);
    FacetCounter::countsFor($livewire, 'customer_group', $source);
    FacetCounter::countsFor($livewire, 'customer_group', $source);

    expect($livewire->filteredTableQueryCallCount)->toBe(1);
});

it('recomputes counts after flush()', function () {
    $gold = FacetCounterCustomerGroup::create(['name' => 'Gold']);
    FacetCounterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);

    $livewire = new FakeHasTable();
    $livewire->resolveFilteredTableQueryUsing(fn () => FacetCounterCustomer::query());

    $source = new RelationshipFacetSource('customerGroup', 'name');

    FacetCounter::countsFor($livewire, 'customer_group', $source);
    FacetCounter::flush();
    FacetCounter::countsFor($livewire, 'customer_group', $source);

    expect($livewire->filteredTableQueryCallCount)->toBe(2);
});
