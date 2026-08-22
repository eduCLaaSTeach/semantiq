<?php

namespace App\Models;

use App\Enums\Role;
use App\Support\Tenancy\BelongsToOrganisation;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A person who signs in to SemantIQ.
 *
 * Identity is federated to Microsoft Entra ID, so a row here is a local mirror
 * of a directory account rather than a credential of its own. The password
 * column is nullable and stays null for every federated account.
 *
 * @property string|null $entra_object_id
 * @property string|null $entra_tenant_id
 * @property Role $role
 * @property Carbon|null $last_signed_in_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganisation, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_signed_in_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    /**
     * The organisation this account belongs to.
     *
     * Nullable: a user row exists as soon as Entra returns a profile, which can
     * precede any organisation assignment. A user with no organisation has no
     * access to customer-owned records, because the scope fails closed.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Whether this account holds at least the authority of the given role.
     *
     * Tiers are cumulative, so a System Administrator satisfies every check an
     * Administrator satisfies.
     */
    public function hasAtLeast(Role $minimum): bool
    {
        return $this->role->atLeast($minimum);
    }

    /**
     * Whether this account administers system configuration.
     */
    public function isSystemAdmin(): bool
    {
        return $this->role === Role::SystemAdmin;
    }
}
