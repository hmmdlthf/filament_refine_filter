<?php

declare(strict_types=1);

use Hmmdlthf\FilamentRefineFilter\FacetSources\EnumFacetSource;
use Hmmdlthf\FilamentRefineFilter\FacetSources\RelationshipFacetSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * These tests exercise FacetSource::getCounts() / applyQuery() directly
 * against a real (sqlite) query, without going through a full Filament
 * Livewire table — that integration layer (FacetCounter's "exclude self"
 * clone against $livewire->tableFilters) needs a Livewire test harness and
 * is the next test file to add once a real host app/resource exists to
 * test against.
 */

// --- fixture models -------------------------------------------------------

class CustomerGroup extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}

enum CustomerStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class Customer extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'status' => CustomerStatus::class,
    ];

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }
}

// --- tests ------------------------------------------------------------

it('counts customers per relationship facet value', function () {
    $gold = CustomerGroup::create(['name' => 'Gold']);
    $silver = CustomerGroup::create(['name' => 'Silver']);

    Customer::create(['name' => 'A', 'customer_group_id' => $gold->id, 'region' => 'EU']);
    Customer::create(['name' => 'B', 'customer_group_id' => $gold->id, 'region' => 'EU']);
    Customer::create(['name' => 'C', 'customer_group_id' => $silver->id, 'region' => 'US']);

    $source = new RelationshipFacetSource('customerGroup', 'name');

    $options = $source->getOptionsForQuery(Customer::query());
    expect($options->all())->toBe([
        (string) $gold->id => 'Gold',
        (string) $silver->id => 'Silver',
    ]);

    $counts = $source->getCounts(Customer::query());
    expect($counts->get((string) $gold->id))->toBe(2)
        ->and($counts->get((string) $silver->id))->toBe(1);
});

it('narrows relationship facet counts when the base query is already constrained', function () {
    $gold = CustomerGroup::create(['name' => 'Gold']);
    $silver = CustomerGroup::create(['name' => 'Silver']);

    Customer::create(['name' => 'A', 'customer_group_id' => $gold->id, 'region' => 'EU']);
    Customer::create(['name' => 'B', 'customer_group_id' => $gold->id, 'region' => 'US']);
    Customer::create(['name' => 'C', 'customer_group_id' => $silver->id, 'region' => 'EU']);

    $source = new RelationshipFacetSource('customerGroup', 'name');

    // Simulate "every OTHER active filter already applied" (region = EU),
    // which is what FacetCounter hands to getCounts() in real usage.
    $euOnly = Customer::query()->where('region', 'EU');

    $counts = $source->getCounts($euOnly);

    expect($counts->get((string) $gold->id))->toBe(1)
        ->and($counts->get((string) $silver->id))->toBe(1);
});

it('applies selected relationship values back onto the query', function () {
    $gold = CustomerGroup::create(['name' => 'Gold']);
    $silver = CustomerGroup::create(['name' => 'Silver']);

    Customer::create(['name' => 'A', 'customer_group_id' => $gold->id]);
    Customer::create(['name' => 'B', 'customer_group_id' => $silver->id]);

    $source = new RelationshipFacetSource('customerGroup', 'name');

    $filtered = $source->applyQuery(Customer::query(), [(string) $gold->id])->get();

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->name)->toBe('A');
});

it('counts customers per enum facet value', function () {
    Customer::create(['name' => 'A', 'status' => CustomerStatus::Active]);
    Customer::create(['name' => 'B', 'status' => CustomerStatus::Active]);
    Customer::create(['name' => 'C', 'status' => CustomerStatus::Inactive]);

    $source = new EnumFacetSource(CustomerStatus::class, 'status');

    $counts = $source->getCounts(Customer::query());

    expect($counts->get('active'))->toBe(2)
        ->and($counts->get('inactive'))->toBe(1);
});

it('throws when given a pure (non-backed) enum', function () {
    enum PureStatus
    {
        case Active;
        case Inactive;
    }

    new EnumFacetSource(PureStatus::class, 'status');
})->throws(InvalidArgumentException::class);
