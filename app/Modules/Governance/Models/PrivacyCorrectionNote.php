<?php

declare(strict_types=1);

namespace App\Modules\Governance\Models;

use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Governance\Enums\CorrectionOutcome;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * A data subject's assertion that a record about them is wrong, and what was
 * decided about it. Feature PDPA-01, decision D11, SEC-DEC-066.
 *
 * WHY THIS EXISTS AT ALL. `audit_events` cannot be edited - that is what makes
 * it evidence rather than a log. So when a subject says the trail is wrong
 * about them, the remedy is not to change the entry. It is to record the
 * dispute permanently beside it, so anyone reading the entry afterwards sees
 * both the original and the challenge.
 *
 * THIS TABLE IS APPEND-ONLY, AND THESE HOOKS ARE NOT THE CONTROL.
 *
 * They throw on update and delete, which stops the ordinary application paths.
 * They do NOT fire on a mass delete, a raw query, or anything that bypasses
 * Eloquent - the exact reasoning behind SEC-DEC-037 for `audit_events`. MySQL
 * has no DENY, so privileges cannot express this either.
 *
 * The control is a pair of BEFORE UPDATE and BEFORE DELETE triggers, installed
 * as a separately approved production step and deliberately NOT placed in a
 * migration, because a migration that can create a trigger can also drop it.
 * The exact SQL is in `doc/execution/R1.4c-PLAN.md` section 1.8.
 *
 * These hooks are defence in depth. Treat the triggers as the protection, and
 * R1.4c-i as unaccepted until they exist on production and are proved.
 */
class PrivacyCorrectionNote extends Model
{
    use BelongsToOrganisation;

    /**
     * `outcome` and every decision column are absent. Deciding a dispute is the
     * service's job, and it is recorded once - there is no second chance to
     * change it, by design.
     */
    protected $fillable = [
        'privacy_request_id',
        'audit_event_id',
        'subject_assertion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => CorrectionOutcome::class,
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Laravel calls this once per model on boot.
     *
     * `updating` rather than `saving`, so that CREATING a note still works.
     * A note is written once, complete, and never touched again.
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException(
                'Correction notes are append only. A note records what a data subject disputed, '
                .'and a dispute that the disputed party can edit is not evidence of anything.'
            );
        });

        static::deleting(function (): never {
            throw new RuntimeException(
                'Correction notes are append only. Removing a note would remove the record that '
                .'somebody challenged an entry, which is the one thing it exists to preserve.'
            );
        });
    }

    public function isDecided(): bool
    {
        return $this->decided_at !== null;
    }

    /**
     * Whether this note annotates a specific audit entry.
     *
     * A subject may dispute something that is not a single event - a stored
     * value on their own record - so the link is optional and the screens say
     * which kind of dispute they are showing.
     */
    public function annotatesAnEvent(): bool
    {
        return $this->audit_event_id !== null;
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PrivacyRequest::class, 'privacy_request_id');
    }

    public function auditEvent(): BelongsTo
    {
        return $this->belongsTo(AuditEvent::class, 'audit_event_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }
}
