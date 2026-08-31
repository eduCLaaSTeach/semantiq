<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A single-use first-administrator grant.
 *
 * Only the SHA-256 of the grant is stored. The plaintext exists exactly once,
 * on the operator's terminal, and is never written to the database, a log, or
 * CI output.
 *
 * @property int $id
 * @property string $token_hash
 * @property string $expected_subject
 * @property string $expected_tenant
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 */
final class BootstrapGrant extends Model
{
    protected $table = 'bootstrap_grants';

    protected $fillable = [
        'token_hash',
        'expected_subject',
        'expected_tenant',
        'issued_by',
        'expires_at',
        'consumed_at',
        'consumed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public static function hashFor(string $grant): string
    {
        return hash('sha256', $grant);
    }
}
