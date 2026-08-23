<?php

declare(strict_types=1);

namespace App\Modules\Audit\Support;

use App\Models\User;
use App\Modules\Audit\Enums\AuditOutcome;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Throwable;

/**
 * The single write path into the audit trail. Release 1 gate 1.
 *
 * Everything that must be auditable calls `record()`. Centralising it is what
 * makes the guarantees hold: the actor, the organisation, the correlation id,
 * the environment and - most importantly - the redaction are resolved once,
 * here, instead of being remembered correctly by every caller.
 *
 * Three decisions worth knowing before changing this class.
 *
 * REDACTION IS NOT OPTIONAL. `before` and `after` go through
 * `Redaction::summarise()` on the way in. There is no parameter to skip it. A
 * caller that needs to record a value it believes is safe is a caller about to
 * put a client secret in a database.
 *
 * A FAILED WRITE MUST NOT FAIL THE ACTION. If the trail cannot be written, the
 * failure is logged and the caller continues. The alternative - an exception
 * that rolls back the change - means a full disk or a locked table can stop an
 * administrator disabling a compromised account. The lost event is a real cost
 * and it is logged loudly enough to be noticed.
 *
 * DENIALS ARE EVENTS. `denied()` exists because a trail containing only
 * successes cannot show an attack that failed.
 */
class AuditLogger
{
    public function __construct(
        private readonly OrganisationContext $organisations,
    ) {}

    /**
     * Record one event.
     *
     * @param  string  $action  The catalogue's dotted name, `user.disabled`.
     * @param  string  $module  The owning module: Identity, Security, Platform.
     * @param  array<array-key, mixed>|null  $before  Redacted before it is stored.
     * @param  array<array-key, mixed>|null  $after  Redacted before it is stored.
     */
    public function record(
        string $action,
        string $module,
        AuditOutcome $outcome = AuditOutcome::Succeeded,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
    ): ?AuditEvent {
        try {
            $actor = Auth::user();

            $event = new AuditEvent;

            $event->forceFill([
                'organisation_id' => $this->organisations->currentId(),
                'occurred_at' => now()->utc(),
                'actor_user_id' => $actor?->getAuthIdentifier(),
                'actor_type' => $actor instanceof User ? 'user' : 'system',
                'actor_label' => $this->actorLabel($actor),
                'action' => $action,
                'module' => $module,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId === null ? null : (string) $resourceId,
                'outcome' => $outcome,
                'before_summary' => Redaction::summarise($before),
                'after_summary' => Redaction::summarise($after),
                'reason' => Redaction::scrub($reason),
                'ip_address' => $this->clientIp(),
                'correlation_id' => CorrelationId::current(),
                'environment' => (string) app()->environment(),
            ]);

            $event->save();

            return $event;
        } catch (Throwable $exception) {
            /*
             * The action itself must still complete - see the class docblock.
             * The message is scrubbed like any other external string, because a
             * driver error can quote the statement it failed on.
             */
            Log::error('Audit event could not be written.', [
                'action' => $action,
                'module' => $module,
                'correlation_id' => CorrelationId::current(),
                'reason' => Redaction::scrub($exception->getMessage()),
            ]);

            return null;
        }
    }

    /**
     * Record a refusal.
     *
     * A convenience over `record()` so the denial path is one short call at the
     * point it is refused. A denial that is awkward to record is a denial that
     * does not get recorded.
     */
    public function denied(
        string $action,
        string $module,
        ?string $resourceType = null,
        int|string|null $resourceId = null,
        ?string $reason = null,
    ): ?AuditEvent {
        return $this->record(
            action: $action,
            module: $module,
            outcome: AuditOutcome::Denied,
            resourceType: $resourceType,
            resourceId: $resourceId,
            reason: $reason,
        );
    }

    /**
     * A readable identifier for the actor that outlives their account row.
     *
     * The email address rather than the name, because an address is unique and
     * a name is not, and an investigator needs to know which of two people
     * called J Smith did this.
     */
    private function actorLabel(mixed $actor): ?string
    {
        if (! $actor instanceof User) {
            return null;
        }

        return $actor->email !== '' ? $actor->email : $actor->name;
    }

    /**
     * The client address, when there is a request at all.
     *
     * An IP is personal data under most regimes, so it is recorded because
     * Release 1's event schema requires it for security-relevant actions and it
     * inherits the same retention policy as the rest of the row. Console and
     * queue work has no address and records none rather than inventing one.
     */
    private function clientIp(): ?string
    {
        if (app()->runningInConsole()) {
            return null;
        }

        return Request::ip();
    }
}
