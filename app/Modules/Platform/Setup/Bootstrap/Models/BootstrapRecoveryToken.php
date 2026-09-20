<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single-use token that reopens local password login after bootstrap closed.
 *
 * ONLY THE SHA-256 IS STORED. The plaintext exists once, on the operator's
 * terminal, and is never written to the database, a log, an audit context or CI
 * output - the same discipline as BootstrapGrant, which P1-00 verified.
 *
 * SHA-256 IS CORRECT HERE AND WOULD BE WRONG FOR A PASSWORD. The token is 64
 * characters of generated entropy, so there is nothing to brute-force and an
 * adaptive hash would only make every verification slower. A human-chosen
 * password gets Hash::make; this does not.
 *
 * @property int $id
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property string|null $issued_by
 */
final class BootstrapRecoveryToken extends Model
{
    protected $table = 'bootstrap_recovery_tokens';

    protected $fillable = [
        'token_hash',
        'expires_at',
        'consumed_at',
        'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public static function hashFor(string $token): string
    {
        return hash('sha256', $token);
    }
}
