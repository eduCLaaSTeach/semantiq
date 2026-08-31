<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A SemantIQ principal, mapped from a verified external identity.
 *
 * The identity key is (provider, external_subject, tenant_id) - never email.
 * Email is mutable and reassignable, so keying on it would let a reassigned
 * mailbox inherit someone else's SemantIQ identity. Email is carried for
 * display and administrator correlation only.
 *
 * @property int $id
 * @property string $provider
 * @property string $external_subject
 * @property string $tenant_id
 * @property string $email
 * @property string $display_name
 * @property UserStatus $status
 * @property PlatformRole|null $platform_role
 */
final class User extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'provider',
        'external_subject',
        'tenant_id',
        'email',
        'display_name',
        'status',
        'platform_role',
        'last_signed_in_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'platform_role' => PlatformRole::class,
            'last_signed_in_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSystemAdministrator(): bool
    {
        return $this->platform_role === PlatformRole::SystemAdministrator;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeActiveSystemAdministrators(Builder $query): Builder
    {
        return $query
            ->where('platform_role', PlatformRole::SystemAdministrator->value)
            ->where('status', UserStatus::Active->value);
    }
}
