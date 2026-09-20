<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Hmmdlthf\FilamentRefineFilter\FacetSources\EnumFacetSource;
use Hmmdlthf\FilamentRefineFilter\FacetSources\RelationshipFacetSource;
use Hmmdlthf\FilamentRefineFilter\Filters\RefineFilter;
use Hmmdlthf\FilamentRefineFilter\Tests\Fixtures\FakeHasTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// --- fixture models ---------------------------------------------------

class RefineFilterCustomerGroup extends Model
{
    protected $table = 'customer_groups';

    public $timestamps = false;

    protected $guarded = [];
}

enum RefineFilterStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class RefineFilterCustomer extends Model
{
    protected $table = 'customers';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'status' => RefineFilterStatus::class,
    ];

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(RefineFilterCustomerGroup::class, 'customer_group_id');
    }
}

/**
 * Invokes RefineFilter's protected static resolveOptions() directly, since
 * it's the method the CheckboxList's ->options() closure delegates to.
 *
 * @return array<string, string>
 */
function invokeResolveOptions(FakeHasTable $livewire, $source, string $filterName, bool $showCounts, bool $hideZeroCounts): array
{
    $method = new ReflectionMethod(RefineFilter::class, 'resolveOptions');
    $method->setAccessible(true);

    return $method->invoke(null, $livewire, $source, $filterName, $showCounts, $hideZeroCounts);
}

// --- ->relationship() / ->enum() wiring --------------------------------

it('throws when neither relationship() nor enum() has been configured', function () {
    RefineFilter::make('status')->showCounts();
})->throws(LogicException::class);

it('applies selected relationship values back onto the query via apply()', function () {
    $gold = RefineFilterCustomerGroup::create(['name' => 'Gold']);
    $silver = RefineFilterCustomerGroup::create(['name' => 'Silver']);

    RefineFilterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);
    RefineFilterCustomer::create(['name' => 'B', 'customer_group_id' => $silver->id]);

    $filter = RefineFilter::make('customer_group')->relationship('customerGroup', 'name');

    $filtered = $filter->apply(RefineFilterCustomer::query(), ['values' => [(string) $gold->id]])->get();

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->name)->toBe('A');
});

it('leaves the query untouched when no values are selected', function () {
    RefineFilterCustomer::create(['name' => 'A']);
    RefineFilterCustomer::create(['name' => 'B']);

    $filter = RefineFilter::make('customer_group')->relationship('customerGroup', 'name');

    $filtered = $filter->apply(RefineFilterCustomer::query(), ['values' => []])->get();

    expect($filtered)->toHaveCount(2);
});

it('applies selected enum values back onto the query via apply()', function () {
    RefineFilterCustomer::create(['name' => 'A', 'status' => RefineFilterStatus::Active]);
    RefineFilterCustomer::create(['name' => 'B', 'status' => RefineFilterStatus::Inactive]);

    $filter = RefineFilter::make('status')->enum(RefineFilterStatus::class);

    $filtered = $filter->apply(RefineFilterCustomer::query(), ['values' => ['active']])->get();

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->name)->toBe('A');
});

it('renders a searchable CheckboxList when ->searchable() is called', function () {
    $filter = RefineFilter::make('status')->enum(RefineFilterStatus::class)->searchable();

    $checkboxList = $filter->getSchemaComponents()[0];

    expect($checkboxList->isSearchable())->toBeTrue();
});

it('does not mark the CheckboxList searchable by default', function () {
    $filter = RefineFilter::make('status')->enum(RefineFilterStatus::class);

    $checkboxList = $filter->getSchemaComponents()[0];

    expect($checkboxList->isSearchable())->toBeFalse();
});

it('shows the filter\'s own label as a heading above its checkboxes', function () {
    $filter = RefineFilter::make('status')->label('Order Status')->enum(RefineFilterStatus::class);

    $checkboxList = $filter->getSchemaComponents()[0];

    expect($checkboxList->isLabelHidden())->toBeFalse()
        ->and($checkboxList->getLabel())->toBe('Order Status');
});

// --- resolveOptions() label formatting ----------------------------------

it('formats resolved options as "Label (count)" when showCounts is enabled', function () {
    $gold = RefineFilterCustomerGroup::create(['name' => 'Gold']);
    $silver = RefineFilterCustomerGroup::create(['name' => 'Silver']);

    RefineFilterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);
    RefineFilterCustomer::create(['name' => 'B', 'customer_group_id' => $gold->id]);

    $livewire = new FakeHasTable();
    $livewire->setTable(Table::make($livewire)->query(RefineFilterCustomer::query()));

    $options = invokeResolveOptions(
        $livewire,
        new RelationshipFacetSource('customerGroup', 'name'),
        'customer_group',
        showCounts: true,
        hideZeroCounts: false,
    );

    expect($options)->toBe([
        (string) $gold->id => 'Gold (2)',
        (string) $silver->id => 'Silver (0)',
    ]);
});

it('returns plain labels with no counts when showCounts is disabled', function () {
    $gold = RefineFilterCustomerGroup::create(['name' => 'Gold']);

    RefineFilterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);

    $livewire = new FakeHasTable();
    $livewire->setTable(Table::make($livewire)->query(RefineFilterCustomer::query()));

    $options = invokeResolveOptions(
        $livewire,
        new RelationshipFacetSource('customerGroup', 'name'),
        'customer_group',
        showCounts: false,
        hideZeroCounts: false,
    );

    expect($options)->toBe([(string) $gold->id => 'Gold']);
});

it('hides zero-count options when hideZeroCounts is enabled', function () {
    $gold = RefineFilterCustomerGroup::create(['name' => 'Gold']);
    $silver = RefineFilterCustomerGroup::create(['name' => 'Silver']);

    RefineFilterCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);

    $livewire = new FakeHasTable();
    $livewire->setTable(Table::make($livewire)->query(RefineFilterCustomer::query()));

    $options = invokeResolveOptions(
        $livewire,
        new RelationshipFacetSource('customerGroup', 'name'),
        'customer_group',
        showCounts: true,
        hideZeroCounts: true,
    );

    expect($options)->toBe([(string) $gold->id => 'Gold (1)'])
        ->and($options)->not->toHaveKey((string) $silver->id);
});

it('resolves enum options with counts through resolveOptions()', function () {
    RefineFilterCustomer::create(['name' => 'A', 'status' => RefineFilterStatus::Active]);
    RefineFilterCustomer::create(['name' => 'B', 'status' => RefineFilterStatus::Active]);
    RefineFilterCustomer::create(['name' => 'C', 'status' => RefineFilterStatus::Inactive]);

    $livewire = new FakeHasTable();
    $livewire->setTable(Table::make($livewire)->query(RefineFilterCustomer::query()));

    $options = invokeResolveOptions(
        $livewire,
        new EnumFacetSource(RefineFilterStatus::class, 'status'),
        'status',
        showCounts: true,
        hideZeroCounts: false,
    );

    expect($options)->toBe([
        'active' => 'Active (2)',
        'inactive' => 'Inactive (1)',
    ]);
});
