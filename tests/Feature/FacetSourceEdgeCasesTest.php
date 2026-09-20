<?php

declare(strict_types=1);

use Filament\Support\Contracts\HasLabel;
use Hmmdlthf\FilamentRefineFilter\FacetSources\EnumFacetSource;
use Hmmdlthf\FilamentRefineFilter\FacetSources\RelationshipFacetSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// --- fixture models ------------------------------------------------------

class EdgeCaseCustomerGroup extends Model
{
    protected $table = 'customer_groups';

    public $timestamps = false;

    protected $guarded = [];

    public function customers(): HasMany
    {
        return $this->hasMany(EdgeCaseCustomer::class, 'customer_group_id');
    }
}

class EdgeCaseCustomer extends Model
{
    protected $table = 'customers';

    public $timestamps = false;

    protected $guarded = [];

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(EdgeCaseCustomerGroup::class, 'customer_group_id');
    }
}

enum EdgeCaseLabelledStatus: string implements HasLabel
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Currently Active',
            self::Inactive => 'No Longer Active',
        };
    }
}

enum EdgeCaseUnlabelledStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

// --- EnumFacetSource -------------------------------------------------------

it('labels enum options using HasLabel when the enum implements it', function () {
    $source = new EnumFacetSource(EdgeCaseLabelledStatus::class, 'status');

    expect($source->getOptions()->all())->toBe([
        'active' => 'Currently Active',
        'inactive' => 'No Longer Active',
    ]);
});

it('falls back to the case name when the enum does not implement HasLabel', function () {
    $source = new EnumFacetSource(EdgeCaseUnlabelledStatus::class, 'status');

    expect($source->getOptions()->all())->toBe([
        'active' => 'Active',
        'inactive' => 'Inactive',
    ]);
});

it('applies selected enum values back onto the query', function () {
    EdgeCaseCustomer::create(['name' => 'A', 'status' => 'active']);
    EdgeCaseCustomer::create(['name' => 'B', 'status' => 'inactive']);

    $source = new EnumFacetSource(EdgeCaseUnlabelledStatus::class, 'status');

    $filtered = $source->applyQuery(EdgeCaseCustomer::query(), ['active'])->get();

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->name)->toBe('A');
});

// --- RelationshipFacetSource -----------------------------------------------

it('throws when the named relationship is not a BelongsTo', function () {
    $source = new RelationshipFacetSource('customers', 'name');

    $source->getOptionsForQuery(EdgeCaseCustomerGroup::query());
})->throws(InvalidArgumentException::class);

it('throws when getOptions() is called directly instead of getOptionsForQuery()', function () {
    (new RelationshipFacetSource('customerGroup', 'name'))->getOptions();
})->throws(LogicException::class);

it('supports a custom value column distinct from the related model\'s primary key', function () {
    Schema::table('customer_groups', fn (Blueprint $table) => $table->string('slug')->nullable());

    $gold = EdgeCaseCustomerGroup::create(['name' => 'Gold', 'slug' => 'gold']);

    EdgeCaseCustomer::create(['name' => 'A', 'customer_group_id' => $gold->id]);

    $source = new RelationshipFacetSource('customerGroup', 'name', valueColumn: 'slug');

    $options = $source->getOptionsForQuery(EdgeCaseCustomer::query());

    expect($options->all())->toBe(['gold' => 'Gold']);
});

it('exposes the resolved relationship\'s foreign key name', function () {
    $source = new RelationshipFacetSource('customerGroup', 'name');

    expect($source->getForeignKeyName(EdgeCaseCustomer::query()))->toBe('customer_group_id');
});
