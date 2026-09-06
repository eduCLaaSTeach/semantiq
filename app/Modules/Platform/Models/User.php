<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Organisation\Models\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SemantIQ principal, mapped from a verified external identity.
 *
 * The identity key is (provider, external_subject, tenant_id) - never email.
 * Email is mutable and reassignable, so keying on it would let a reassigned
 * mailbox inherit someone else's SemantIQ identity. Email is carried for
 * display and administrator correlation only.
 *
 * organisation_id is the D-16 seam, owned by P1-01. It is NOT Entra tenant_id:
 * tenant_id is a directory boundary, organisation_id is a SemantIQ tenancy
 * boundary, and they coincide in single-tenant Release 1 only by accident.
 * Nothing may substitute one for the other. NULL means "not yet associated" and
 * fails closed - such a user cannot join a team or a management chain.
 *
 * It grants nothing. Association is not entitlement.
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property string $provider
 * @property string $external_subject
 * @property string $tenant_id
 * @property string $email
 * @property string $display_name
 * @property UserStatus $status
 */
final class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        // P1-01 owns this column (D-16). It is written at exactly one place:
        // Company Profile creation, which associates the creating administrator.
        'organisation_id',
        'provider',
        'external_subject',
        'tenant_id',
        'email',
        'display_name',
        'status',
        'last_signed_in_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'last_signed_in_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * D-16, fail closed.
     *
     * A user with no organisation may not join a team or a management chain.
     * This is deliberately a question about organisation_id and never about
     * tenant_id.
     */
    public function belongsToOrganisation(): bool
    {
        return $this->organisation_id !== null;
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }
}
