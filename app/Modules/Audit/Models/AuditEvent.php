<?php

declare(strict_types=1);

namespace App\Modules\Audit\Models;

use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * One entry in the append-only record of who changed what.
 *
 * Written only through `App\Modules\Audit\Support\AuditLogger`. Nothing else
 * should construct one: the logger is where redaction, the correlation id and
 * the actor are resolved, and a second write path is a second chance to forget
 * one of them.
 *
 * APPEND ONLY, ENFORCED HERE. Updates and deletes throw rather than return
 * false. A silent refusal would let a caller believe it had corrected a row and
 * leave the correction nowhere; a thrown exception makes the attempt visible in
 * the only place that matters, which is the change that tried it.
 *
 * THE LIMIT OF THAT GUARANTEE, stated plainly because a half-understood control
 * is worse than none. Eloquent model events do not fire on a MASS operation, so
 * `AuditEvent::query()->delete()` and `DB::table('audit_events')->delete()` both
 * walk past the hooks below. Nothing in application code can close that, and
 * pretending otherwise would be the dangerous part. The real control is at the
 * database: the application's own database user should hold INSERT and SELECT on
 * this table and NOT DELETE or UPDATE, so that even a compromised application
 * cannot rewrite its own trail. That is a deployment action, it has not been
 * applied to the production database, and it is carried as an open item in
 * doc/context/SECURITY_PRIVACY_DECISIONS.md rather than quietly assumed.
 *
 * The hooks below are still worth having: they stop the accident, which is the
 * common case, and they make the intent unmissable to the next person editing
 * this file.
 *
 * Retention is policy driven, not enforced by deletion from application code.
 * The repository's current baseline is seven years for audit and compliance
 * logs; when the retention sweep is built it will be a governed operation with
 * its own approval, not an `->delete()` behind a screen.
 *
 * @property int $id
 * @property int|null $organisation_id
 * @property Carbon $occurred_at
 * @property int|null $actor_user_id
 * @property string $actor_type
 * @property string|null $actor_label
 * @property string $action
 * @property string $module
 * @property string|null $resource_type
 * @property string|null $resource_id
 * @property AuditOutcome $outcome
 * @property array<array-key, mixed>|null $before_summary
 * @property array<array-key, mixed>|null $after_summary
 * @property string|null $reason
 * @property string|null $ip_address
 * @property string|null $correlation_id
 * @property string $environment
 */
class AuditEvent extends Model
{
    use BelongsToOrganisation;

    /** There is no `updated_at`: a row that can be updated is not evidence. */
    public const UPDATED_AT = null;

    /**
     * Not fillable at all. Every attribute is set explicitly by the logger, so
     * a request array can never reach this table by mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'outcome' => AuditOutcome::class,
            'before_summary' => 'array',
            'after_summary' => 'array',
        ];
    }

    /**
     * Laravel calls this once per model on boot.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Audit events are append only. An existing event cannot be changed.');
        });

        static::deleting(function (): never {
            throw new RuntimeException(
                'Audit events are append only. Removal is a governed retention operation, not an application delete.'
            );
        });
    }

    /**
     * An audit event may be written with no organisation.
     *
     * This is the one documented exception to the fail-closed stamping rule,
     * and it exists for events that happen BEFORE an organisation can be
     * resolved: a sign-in attempt against an address that belongs to nobody, or
     * a denial raised by middleware before any record is loaded. Refusing to
     * record those would lose exactly the evidence an incident review needs,
     * and losing evidence is a worse failure than an unscoped row in a table
     * that only administrators can read.
     *
     * No customer business data is ever written here, so a null owner leaks
     * nothing between customers.
     */
    public function organisationIsOptional(): bool
    {
        return true;
    }

    /**
     * The account that acted, where it still exists. Null once the account is
     * deleted, which is why `actor_label` is recorded alongside: the trail has
     * to outlive the row it describes.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
