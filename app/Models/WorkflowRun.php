<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkflowStatus;
use App\Support\Tenancy\BelongsToOrganisation;
use Database\Factories\WorkflowRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One attempt at a multi-step operation, and the record a worker restart reads
 * to work out where it got to.
 *
 * The orchestration behaviour that uses this - queued jobs, delayed re-queueing
 * for long-running Microsoft operations, resumption after an interruption -
 * arrives in work item W4. This class is the state those jobs move through, and
 * it is deliberately passive: it records where a run is, it does not decide.
 *
 * Requirement IDs: NFR-PERF-02, NFR-OBS-01. SRS sections 6.1, 17, 18.1.
 *
 * @property string $workflow_run_uid
 * @property string $workflow_type
 * @property WorkflowStatus $status
 * @property string|null $current_step
 * @property string $correlation_id
 * @property array<string, mixed>|null $state
 * @property int $attempts
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $resume_after
 */
class WorkflowRun extends Model
{
    /** @use HasFactory<WorkflowRunFactory> */
    use BelongsToOrganisation, HasFactory;

    /**
     * Restated for an instance that has not been round-tripped through the
     * database, so a caller counting attempts on a freshly created run reads
     * zero rather than null.
     */
    protected $attributes = [
        'attempts' => 0,
    ];

    protected $fillable = [
        'workflow_type',
        'status',
        'current_step',
        'state',
        'failure_category',
        'failure_message',
        'api_request_id',
        'started_at',
        'completed_at',
        'resume_after',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'state' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'resume_after' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Both identifiers are minted here rather than by callers. A run without
         * a correlation identifier is a run nobody can trace across the
         * application log, the Microsoft request and the audit trail, and making
         * that the caller's responsibility guarantees it will be forgotten once.
         */
        static::creating(function (self $run): void {
            $run->workflow_run_uid ??= (string) Str::uuid();
            $run->correlation_id ??= (string) Str::uuid();
            $run->status ??= WorkflowStatus::default();
        });
    }

    /**
     * Runs the orchestrator may still act on.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            WorkflowStatus::NotStarted->value,
            WorkflowStatus::InProgress->value,
            WorkflowStatus::Ready->value,
        ]);
    }

    /**
     * Runs waiting on a person rather than on the system.
     *
     * What the dashboard's "needs you" count is drawn from.
     */
    public function scopeAwaitingPerson(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (WorkflowStatus $status): string => $status->value,
            array_filter(WorkflowStatus::cases(), fn (WorkflowStatus $s): bool => $s->awaitsPerson()),
        ));
    }

    /**
     * A single value from the orchestration state.
     *
     * State holds step markers and Microsoft resource identifiers only. Never
     * customer business data: the control plane keeps references, not copies.
     */
    public function stateValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->state, $key, $default);
    }
}
