<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stored override of a catalogue flag. Feature ADM-021.
 *
 * Same shape and same reasoning as `SystemSetting`: the declaration is code,
 * the deviation is data. A flag with no row here reads as its declared default,
 * and a flag with no declaration reads as off.
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property string $key
 * @property bool $enabled
 * @property string|null $reason
 */
class FeatureFlag extends Model
{
    use BelongsToOrganisation;

    /** @var list<string> */
    protected $fillable = ['enabled', 'reason', 'updated_by_user_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
