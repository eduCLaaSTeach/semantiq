<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * A person who signs in to CLaaS SemantiQ.
 *
 * An account can arrive two ways. Federated accounts are mirrors of a Microsoft
 * Entra directory account: the directory proves who they are and this row exists
 * only so the application has something to attach authorisation and audit to.
 * Their password column stays null, because there is no password to hold.
 *
 * Local accounts carry a hashed password and are the fallback for people the
 * customer's directory does not hold.
 *
 * @property string|null $entra_object_id
 * @property string|null $entra_tenant_id
 * @property Carbon|null $last_signed_in_at
 * @property Role $role
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
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
     * Initials for the avatar.
     *
     * First and last word, so "Salil Mhatre" reads SM rather than SMH for a
     * middle name. A one-word name still yields one letter rather than nothing,
     * and an empty name falls back to the address, because an avatar with no
     * character in it looks like a rendering fault.
     */
    public function initials(): string
    {
        $source = trim($this->name) !== '' ? trim($this->name) : $this->email;
        $words = preg_split('/\s+/', $source) ?: [];
        $words = array_values(array_filter($words));

        if ($words === []) {
            return '?';
        }

        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr((string) end($words), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * Whether this account holds at least the authority of the given tier.
     *
     * Tiers are cumulative, so a System Administrator satisfies every check an
     * Administrator satisfies.
     */
    public function hasAtLeast(Role $minimum): bool
    {
        return $this->role->atLeast($minimum);
    }

    /**
     * Whether this account's identity is proved by the directory rather than by
     * a password held here.
     */
    public function isFederated(): bool
    {
        return $this->entra_object_id !== null;
    }
}
