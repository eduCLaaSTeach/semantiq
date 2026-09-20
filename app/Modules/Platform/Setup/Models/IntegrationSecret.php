<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An encrypted integration secret.
 *
 * THE MODEL DOES NOT DECRYPT. There is no accessor, no cast and no method here
 * that turns `ciphertext` back into a value, so no caller can obtain one by
 * touching a property - not a listing, not a serialiser, not a debug dump.
 * Decryption happens in IntegrationSecretStore, which is the one place the
 * guard has to read.
 *
 * `ciphertext` IS HIDDEN FROM ARRAY AND JSON FORMS for the same reason. A model
 * that lands in a response by accident should carry nothing worth having.
 *
 * key_version RECORDS WHICH APPLICATION KEY ENCRYPTED THE ROW. It does not make
 * rotation safe: it cannot decrypt a row whose key is gone. Rotation is
 * unsupported in Release 1 while rows exist here unless the old key is
 * available to an approved re-encryption procedure.
 *
 * @property int $id
 * @property string $family
 * @property string $name
 * @property string $ciphertext
 * @property int $key_version
 * @property Carbon|null $last_changed_at
 */
final class IntegrationSecret extends Model
{
    protected $table = 'integration_secrets';

    protected $fillable = [
        'family',
        'name',
        'ciphertext',
        'key_version',
        'last_changed_at',
        'last_changed_by_user_id',
    ];

    /**
     * Not a convenience. A secret that cannot be serialised cannot be
     * serialised by accident.
     *
     * @var list<string>
     */
    protected $hidden = ['ciphertext'];

    protected function casts(): array
    {
        return [
            'key_version' => 'integer',
            'last_changed_at' => 'datetime',
        ];
    }
}
