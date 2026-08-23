<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable authority profile. Feature ADM-006.
 *
 * NAMED `AccessRole`, NOT `Role`, ON PURPOSE. `App\Enums\Role` is the six-tier
 * enum that has been the coarse gate since Phase 00 and remains it. Two classes
 * called Role, one an enum of tiers and one a database record, would be
 * confused at a glance by everyone who ever reads this codebase - and confusing
 * the ceiling with the grant is exactly the mistake that turns an authorization
 * model into a hole. The table is still `roles`, because that is what the
 * specification calls it and what an administrator sees.
 *
 * A role NARROWS its tier. It can never widen it: `Authorization::allows()`
 * filters every effective permission through the holder's own tier, so a
 * permission recorded here above that tier is inert.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property Role $tier
 * @property bool $is_system
 * @property LifecycleStatus $status
 * @property int $version
 */
class AccessRole extends Model
{
    use BelongsToOrganisation;

    protected $table = 'roles';

    /**
     * `code`, `is_system` and `tier` are all absent from mass assignment.
     *
     * `code` because VAL-ROLE-SYSTEM-001 protects built-in codes. `is_system`
     * because a posted field must never be able to declare a role built in and
     * thereby protected. `tier` because it is the ceiling, and a ceiling that
     * can be raised by a form field is not a ceiling.
     */
    protected $fillable = ['name', 'description', 'status', 'updated_by_user_id'];

    protected function casts(): array
    {
        return [
            'tier' => Role::class,
            'is_system' => 'boolean',
            'status' => LifecycleStatus::class,
            'version' => 'integer',
        ];
    }

    /**
     * A built-in role may exist for every organisation at once.
     *
     * The shared built-ins carry a null organisation, and the scope trait shows
     * platform-wide rows alongside the current organisation's own. Without this
     * the six seeded roles would be invisible.
     */
    public function organisationIsOptional(): bool
    {
        return true;
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class, 'role_id');
    }

    public function holders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === LifecycleStatus::Active;
    }

    /**
     * Whether this role's identity is protected.
     *
     * VAL-ROLE-SYSTEM-001: a built-in code appears in migrations, tests and
     * documentation, so renaming or deleting one breaks references invisible
     * from the screen where it was renamed. The display NAME stays editable -
     * a customer may call an Administrator whatever they like - and only the
     * code, the tier and existence are frozen.
     */
    public function isProtected(): bool
    {
        return $this->is_system;
    }

    /**
     * The permission keys this role carries.
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->permissions()->pluck('permission_key')->all();
    }
}
