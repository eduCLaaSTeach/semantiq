<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Models\User;
use App\Modules\Governance\Enums\DataClassification;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One kind of personal data this application holds about people. ADM-014.
 *
 * The register PDPA-01 will answer from in R1.4c. `source_tables` is what makes
 * that possible and is the reason this is a table rather than a config list: a
 * customer may add a category the catalogue never anticipated, and the coverage
 * test needs to see it.
 *
 * ORGANISATION SCOPED, fail-closed, through `BelongsToOrganisation`. There is no
 * platform-wide category: the register describes a customer's data.
 *
 * RETIRED RATHER THAN DELETED. A category somebody classified data under is part
 * of the record of how that data was treated. Deleting it removes the
 * explanation without removing the data.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $code
 * @property string $name
 * @property string $description
 * @property DataClassification $classification
 * @property bool $contains_sensitive
 * @property list<string>|null $source_tables
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PersonalDataCategory extends Model
{
    use BelongsToOrganisation;

    /**
     * `code` is absent on purpose. It is the stable identifier the R1.4c
     * collector resolves against, so changing it is a deliberate line of code
     * rather than a form field that happened to be posted.
     */
    protected $fillable = [
        'name',
        'description',
        'classification',
        'contains_sensitive',
        'source_tables',
        'status',
        'updated_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification' => DataClassification::class,
            'contains_sensitive' => 'boolean',
            'source_tables' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * The tables this category says it lives in.
     *
     * Always a list, never null, so callers - including the R1.4c coverage
     * test - do not each have to handle the empty case differently.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        return array_values(array_filter((array) ($this->source_tables ?? [])));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
