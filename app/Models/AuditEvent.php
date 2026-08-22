<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Tenancy\BelongsToOrganisation;
use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * An immutable record of a privileged action.
 *
 * Append-only is enforced, not merely intended. The model refuses to update or
 * delete a persisted row, so a well-meaning `$event->update([...])` fails loudly
 * during development rather than silently rewriting evidence in production.
 *
 * That guard covers writes through the model. Direct SQL and a raw query builder
 * can still reach the table; the database grant is the control for that, and it
 * is recorded in doc/context/SECURITY_PRIVACY_DECISIONS.md rather than pretended
 * away here.
 *
 * The writer that decides what gets audited, and hashes the before and after
 * values, arrives in work item W5. This class is the record it writes.
 *
 * Requirement IDs: NFR-COMP-01, NFR-SEC-01. SRS sections 13.2, 17.
 *
 * @property string $audit_uid
 * @property string $actor_label
 * @property string $action
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $before_hash
 * @property string|null $after_hash
 * @property string $result
 * @property Carbon $occurred_at
 */
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use BelongsToOrganisation, HasFactory;

    /**
     * There is no edit path, so there is nothing for an update timestamp to
     * describe. Laravel would otherwise expect a column the schema refuses to
     * carry, precisely so the table cannot imply an edit is possible.
     */
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'actor_label',
        'actor_entra_object_id',
        'action',
        'target_type',
        'target_id',
        'before_hash',
        'after_hash',
        'api_request_id',
        'correlation_id',
        'result',
        'source_ip',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->audit_uid ??= (string) Str::uuid();
            $event->occurred_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException(
                'Audit events are append-only. Record a new event describing the correction '
                .'rather than editing the original.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Audit events are append-only. Retention is applied by an approved policy '
                .'process, never by application code deleting rows.'
            );
        });
    }
}
