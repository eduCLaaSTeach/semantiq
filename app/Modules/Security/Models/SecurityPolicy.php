<?php

declare(strict_types=1);

namespace App\Modules\Security\Models;

use App\Models\User;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored override of a security policy. Features ADM-009 to ADM-011.
 *
 * The row holds a key, a string and why it was changed. Everything that gives
 * the string meaning - its type, default, validation, editing tier and whether
 * a reason is compulsory - lives in `config/security.php`, because that is
 * reviewed code and this is editable data.
 *
 * Read and written through `App\Modules\Security\Support\SecurityPolicies`,
 * which is where the catalogue is consulted, the tier is checked, the reason is
 * demanded and the audit event is written. Reaching this model directly skips
 * all four, so nothing in the application does.
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property string $key
 * @property string|null $value
 * @property string|null $reason
 * @property int|null $updated_by_user_id
 */
class SecurityPolicy extends Model
{
    use BelongsToOrganisation;

    /**
     * Neither `key` nor `organisation_id` is fillable.
     *
     * A key is only ever chosen from the catalogue by the writer and never
     * taken from a request, and the organisation comes from the context rather
     * than the payload. Mass assignment has no legitimate reason to set either,
     * and `updateOrCreate` would silently drop both.
     *
     * @var list<string>
     */
    protected $fillable = ['value', 'reason', 'updated_by_user_id'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
