<?php

declare(strict_types=1);

namespace App\Modules\Access\StepUp;

use App\Modules\Platform\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A privileged action held server-side while its owner re-authenticates.
 *
 * The reference is stored HASHED, the same shape as BootstrapGrant. The
 * plaintext exists only in the redirect and is never written anywhere.
 *
 * @property int $id
 * @property string $reference_hash
 * @property int $user_id
 * @property string $session_id
 * @property StepUpAction $action
 * @property Carbon $requested_at
 * @property Carbon $expires_at
 * @property Carbon|null $consumed_at
 * @property string|null $outcome
 */
final class PendingStepUp extends Model
{
    protected $table = 'pending_step_ups';

    /**
     * Minutes, not hours. A privileged action left pending for an hour is one
     * somebody can return to from a machine they have since walked away from.
     */
    public const LIFETIME_MINUTES = 5;

    /**
     * How far in the past the provider's auth_time may be and still count as
     * fresh. It allows for the round trip and modest clock skew, and nothing
     * more.
     */
    public const FRESHNESS_TOLERANCE_SECONDS = 300;

    protected $fillable = [
        'reference_hash',
        'user_id',
        'session_id',
        'action',
        'subject_user_id',
        'business_domain_id',
        'domain_entitlement_id',
        'role_code',
        'sensitivity',
        'organisation_id',
        'requested_at',
        'expires_at',
        'consumed_at',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'action' => StepUpAction::class,
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public static function newReference(): string
    {
        return Str::random(64);
    }

    public static function hashFor(string $reference): string
    {
        return hash('sha256', $reference);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->consumed_at === null && $this->expires_at->isFuture();
    }
}
