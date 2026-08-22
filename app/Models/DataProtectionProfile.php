<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\BelongsToOrganisation;
use Database\Factories\DataProtectionProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One version of an organisation's data protection and sovereignty policy.
 *
 * Versioned by construction: a policy change writes a new row and demotes the
 * previous one, so "which policy was in force when this was provisioned" stays
 * answerable years after the fact. Editing a profile in place would erase
 * exactly the evidence a compliance review asks for.
 *
 * Every permissive setting defaults to false and both geography lists default to
 * null. Deny by default is not a stance the reading code takes; it is what the
 * column defaults already say, so a profile nobody has configured cannot become
 * a profile that permits everything.
 *
 * The sovereignty check itself, `VAL-SOV-GEO-001`, arrives in work item W7. This
 * class is the policy that check reads. It carries no verdict logic of its own
 * beyond `hasApprovedGeographies()`, which is a statement about the data rather
 * than a decision about a data flow.
 *
 * Requirement IDs: FR-DPS-001, FR-DPS-003, FR-DPS-007, NFR-COMP-02.
 * Reference: doc/reference/DATA_PROTECTION_SOVEREIGNTY_STANDARD.md section 4.
 *
 * @property int $version
 * @property bool $is_current
 * @property array<int, string>|null $approved_storage_geographies
 * @property array<int, string>|null $approved_processing_geographies
 * @property bool $cross_geo_processing_allowed
 * @property bool $cross_geo_storage_allowed
 * @property bool $conversation_history_outside_geo_allowed
 * @property bool $production_payload_logging
 * @property bool $support_data_capture_allowed
 * @property Carbon|null $support_data_capture_expires_at
 */
class DataProtectionProfile extends Model
{
    /** @use HasFactory<DataProtectionProfileFactory> */
    use BelongsToOrganisation, HasFactory;

    /**
     * The deny-by-default posture, restated for a model instance that has not
     * been round-tripped through the database.
     *
     * This duplicates the column defaults in the migration, which is deliberate.
     * Without it, a profile read back straight after creation reports null for
     * every flag, and null is not false: `! $profile->cross_geo_storage_allowed`
     * happens to be safe today, but a check written as `=== false` would not be,
     * and a screen rendering a tri-state checkbox would show neither on nor off.
     * `the_model_defaults_match_what_the_database_stores` guards the duplication
     * against drifting.
     */
    protected $attributes = [
        'is_current' => false,
        'cross_geo_processing_allowed' => false,
        'cross_geo_storage_allowed' => false,
        'conversation_history_outside_geo_allowed' => false,
        'public_internet_access_allowed' => false,
        'customer_managed_key_required' => false,
        'purview_sensitivity_labels_required' => false,
        'dlp_policy_required' => false,
        'default_retention_class' => 'operational-90-day',
        'operational_retention_days' => 90,
        'audit_retention_days' => 2555,
        'production_payload_logging' => false,
        'data_export_allowed' => false,
        'support_data_capture_allowed' => false,
    ];

    protected $fillable = [
        'approved_storage_geographies',
        'approved_processing_geographies',
        'cross_geo_processing_allowed',
        'cross_geo_storage_allowed',
        'conversation_history_outside_geo_allowed',
        'public_internet_access_allowed',
        'customer_managed_key_required',
        'purview_sensitivity_labels_required',
        'dlp_policy_required',
        'default_retention_class',
        'operational_retention_days',
        'audit_retention_days',
        'production_payload_logging',
        'data_export_allowed',
        'support_data_capture_allowed',
        'support_data_capture_expires_at',
        'created_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'approved_storage_geographies' => 'array',
            'approved_processing_geographies' => 'array',
            'cross_geo_processing_allowed' => 'boolean',
            'cross_geo_storage_allowed' => 'boolean',
            'conversation_history_outside_geo_allowed' => 'boolean',
            'public_internet_access_allowed' => 'boolean',
            'customer_managed_key_required' => 'boolean',
            'purview_sensitivity_labels_required' => 'boolean',
            'dlp_policy_required' => 'boolean',
            'production_payload_logging' => 'boolean',
            'data_export_allowed' => 'boolean',
            'support_data_capture_allowed' => 'boolean',
            'support_data_capture_expires_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The version in force for an organisation, or null if none has been set.
     *
     * Null is a real answer and callers must treat it as such. An organisation
     * with no profile has stated no approved geography, which the sovereignty
     * check reads as a refusal rather than as an absence of restrictions.
     */
    public static function currentFor(Organisation $organisation): ?self
    {
        return static::query()
            ->where('organisation_id', $organisation->id)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Write a new version and make it the one in force.
     *
     * Promotion and demotion happen in one transaction because the invariant is
     * "at most one current version per organisation", and MySQL cannot express
     * that as a unique index. A half-applied change would leave either two
     * current profiles or none, and both are worse than the change failing.
     *
     * Auditing this call is W5's job. It is a privileged change under the
     * standard's section 4 and must not ship unaudited.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function publishFor(Organisation $organisation, array $attributes = []): self
    {
        return DB::transaction(function () use ($organisation, $attributes): self {
            $nextVersion = (int) static::query()
                ->where('organisation_id', $organisation->id)
                ->max('version') + 1;

            static::query()
                ->where('organisation_id', $organisation->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $profile = new self($attributes);
            $profile->organisation_id = $organisation->id;
            $profile->version = $nextVersion;
            $profile->is_current = true;
            $profile->save();

            return $profile;
        });
    }

    /**
     * Whether the customer has actually stated where their data may live and be
     * processed.
     *
     * An empty list counts as unstated, not as "nowhere approved". Both mean the
     * same thing to the sovereignty check - production activation is blocked -
     * and treating them differently would only invite a bug where a list that
     * was cleared reads as a decision rather than as a gap.
     */
    public function hasApprovedGeographies(): bool
    {
        return ! empty($this->approved_storage_geographies)
            && ! empty($this->approved_processing_geographies);
    }

    /**
     * Whether support data capture is permitted right now.
     *
     * The flag alone is not enough. The standard allows support capture only as
     * a time-bound exception, so an expired window is a closed window even
     * though nobody remembered to turn the flag off.
     */
    public function allowsSupportDataCapture(): bool
    {
        if (! $this->support_data_capture_allowed) {
            return false;
        }

        return $this->support_data_capture_expires_at !== null
            && $this->support_data_capture_expires_at->isFuture();
    }
}
