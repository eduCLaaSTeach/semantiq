<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The customer organisation that owns this SemantIQ instance.
 *
 * The current deployment baseline is one organisation per instance, but the
 * product must stay multi-tenant-ready, so nothing in the code assumes there is
 * only one. Scoped records reach this row through
 * `App\Modules\Identity\Support\OrganisationContext`, never through a constant
 * and never through `first()`.
 *
 * ADM-002 in gate 2 owns the editable profile; this class carries only what the
 * scope machinery needs in gate 1.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property LifecycleStatus $status
 * @property int $version
 * @property string|null $privacy_contact
 * @property string|null $privacy_contact_name
 * @property string|null $privacy_contact_email
 * @property string|null $privacy_contact_phone
 * @property string|null $privacy_contact_role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Organisation extends Model
{
    /**
     * `code` is absent on purpose. VAL-ORG-CODE-001 makes it immutable once
     * dependencies exist, and the safe way to enforce that is to keep it off
     * the mass-assignable list entirely: a deliberate change is a deliberate
     * line of code, not a form field that happened to be posted.
     */
    protected $fillable = ['name', 'status', 'updated_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LifecycleStatus::class,
            'version' => 'integer',
        ];
    }

    /**
     * Whether this organisation may be used as a scope at all.
     *
     * A disabled organisation is not a soft-deleted one: the rows stay, the
     * evidence stays, but nothing new may be written against it. Callers ask
     * here rather than comparing statuses themselves, so the meaning of
     * "usable" lives in one place.
     */
    public function isActive(): bool
    {
        return $this->status === LifecycleStatus::Active;
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
