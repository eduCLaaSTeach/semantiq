<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Secrets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A credential change held between "the administrator asked" and "Microsoft
 * confirmed it was really them".
 *
 * THE MODEL DOES NOT DECRYPT, for the same reason IntegrationSecret does not:
 * there is no accessor, no cast and no method here that turns `ciphertext` back
 * into a value, so no listing, serialiser or debug dump can produce one.
 * StagedChangeStore is the one place that decrypts, which is what keeps
 * SecretsAreDecryptedInOnePlace checkable.
 *
 * `ciphertext` IS HIDDEN from array and JSON forms. A pending-confirmation
 * screen that accidentally serialised one of these should carry nothing worth
 * having.
 *
 * @property int $id
 * @property string $family
 * @property string $secret_name
 * @property string $operation
 * @property string|null $ciphertext
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
final class StagedIntegrationChange extends Model
{
    public const OPERATION_REPLACE = 'replace';

    public const OPERATION_REMOVE = 'remove';

    protected $table = 'staged_integration_changes';

    protected $fillable = [
        'family',
        'secret_name',
        'operation',
        'ciphertext',
        'requested_by_user_id',
        'expires_at',
        'consumed_at',
    ];

    /** @var list<string> */
    protected $hidden = ['ciphertext'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
