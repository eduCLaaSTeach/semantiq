<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission key held by one role. Feature ADM-007.
 *
 * The key is a plain string with no foreign key behind it, because there is no
 * `permissions` table - the catalogue is `PermissionRegistry`, in code. See
 * that class for why, and the migration for the trade-off accepted.
 *
 * The consequence worth stating here: a row whose key is no longer declared
 * grants NOTHING. `Authorization` filters every key through the registry on the
 * way out, so a permission removed from the code stops working on deploy rather
 * than on a data migration.
 *
 * @property int $role_id
 * @property string $permission_key
 */
class RolePermission extends Model
{
    /**
     * Nothing is fillable. Both columns are set explicitly by the service that
     * validates the key against the registry first, and neither should ever
     * arrive from a request array.
     *
     * @var list<string>
     */
    protected $fillable = [];

    public function role(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class, 'role_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }
}
