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
 * @property array<string, scalar|null>|null $fields
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
final class StagedIntegrationChange extends Model
{
    public const OPERATION_REPLACE = 'replace';

    public const OPERATION_REMOVE = 'remove';

    /**
     * Gate C round 3. A change that may carry FIELDS, a secret, or both.
     *
     * `replace` stays what it was - one named secret, no fields - because the
     * completion handler's simplest path should keep being the simplest path.
     * A reconfigure is the general case: an SMTP host moving with or without a
     * new password, an AI endpoint moving, a Fabric directory moving.
     */
    public const OPERATION_RECONFIGURE = 'reconfigure';

    protected $table = 'staged_integration_changes';

    protected $fillable = [
        'family',
        'secret_name',
        'operation',
        'ciphertext',
        'fields',
        'requested_by_user_id',
        'expires_at',
        'consumed_at',
    ];

    /** @var list<string> */
    protected $hidden = ['ciphertext'];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
