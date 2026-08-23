<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored override of a catalogue setting. Feature ADM-021.
 *
 * The row holds a key and a string. Everything that gives the string meaning -
 * its type, its default, its validation, who may change it - lives in
 * `config/platform.php`, because that is reviewed code and this is editable
 * data.
 *
 * Read and written through `App\Modules\Platform\Support\SystemSettings`, which
 * is where the catalogue is consulted and the audit event is written. Reaching
 * this model directly skips both.
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property string $key
 * @property string|null $value
 */
class SystemSetting extends Model
{
    use BelongsToOrganisation;

    /**
     * `key` is not fillable. A key is only ever chosen from the catalogue by
     * the writer, never taken from a request, so mass assignment has no
     * legitimate reason to set one.
     *
     * @var list<string>
     */
    protected $fillable = ['value', 'updated_by_user_id'];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
