<?php

declare(strict_types=1);

namespace App\Models;

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
        ];
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
