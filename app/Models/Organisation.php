<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A customer organisation, and the tenancy boundary every other record hangs from.
 *
 * Deliberately NOT scoped by BelongsToOrganisation. It is the boundary itself,
 * so scoping it by itself would be circular. Access to organisation records is
 * governed by role instead: only a Platform Administrator manages them.
 *
 * Requirement IDs: NFR-SEC-02, FR-DPS-001. SRS section 17.
 *
 * @property string $organisation_uid
 * @property string $name
 * @property string $status
 * @property string|null $region
 */
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use HasFactory;

    protected $fillable = ['name', 'status', 'region', 'owner_user_id'];

    protected static function booted(): void
    {
        /*
         * The external identifier is minted here rather than left to callers,
         * so no code path can create an organisation without one.
         */
        static::creating(function (self $organisation): void {
            $organisation->organisation_uid ??= (string) Str::uuid();
        });
    }

    /**
     * Everyone belonging to this organisation.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The workflow runs performed for this organisation.
     *
     * These relationships read through the organisation scope like any other
     * query, so they return rows only when this organisation is the active one.
     * That is deliberate: a relationship is not a way around the boundary.
     */
    public function workflowRuns(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    /**
     * The audit trail for this organisation.
     */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(AuditEvent::class);
    }

    /**
     * The Fabric items SemantIQ manages for this organisation.
     */
    public function fabricItems(): HasMany
    {
        return $this->hasMany(FabricItem::class);
    }

    /**
     * Every version of this organisation's data protection profile, current and
     * superseded.
     */
    public function dataProtectionProfiles(): HasMany
    {
        return $this->hasMany(DataProtectionProfile::class);
    }

    /**
     * The person accountable for this organisation's configuration.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * Whether this organisation may currently be operated on.
     *
     * A suspended organisation still exists and keeps its records; it simply
     * cannot be worked in. Deleting is a different decision entirely.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
