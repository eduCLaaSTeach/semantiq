<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The non-secret, typed half of one integration's configuration.
 *
 * This is the table every listing reads, so it holds no ciphertext: a status
 * card must render without the application key.
 *
 * `status` AND `last_tested_at` ARE EVIDENCE ABOUT A PARTICULAR
 * CONFIGURATION. When that configuration changes they stop being evidence
 * about anything, and are cleared in the same transaction as the change.
 * Keeping the timestamp is worse than clearing it: it is accurate, and the
 * claim it appears to support is false.
 *
 * @property int $id
 * @property string $family
 * @property array<string, scalar|null> $settings
 * @property string $status
 * @property string|null $explanation
 * @property Carbon|null $last_tested_at
 * @property Carbon|null $last_changed_at
 */
final class IntegrationConfiguration extends Model
{
    protected $table = 'integration_configurations';

    protected $fillable = [
        'family',
        'settings',
        'status',
        'explanation',
        'last_tested_at',
        'last_changed_at',
        'last_changed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'last_tested_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }
}
