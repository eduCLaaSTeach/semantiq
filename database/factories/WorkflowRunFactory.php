<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\Organisation;
use App\Models\WorkflowRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowRun>
 */
class WorkflowRunFactory extends Factory
{
    protected $model = WorkflowRun::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'workflow_type' => 'fabric.readiness_assessment',
            'status' => WorkflowStatus::NotStarted,
            'current_step' => null,
            'state' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkflowStatus::InProgress,
            'current_step' => 'check_tenant_settings',
            'started_at' => now(),
            'attempts' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkflowStatus::Failed,
            'failure_category' => 'fabric_permission',
            'failure_message' => 'The service principal is not a workspace member.',
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'attempts' => 1,
        ]);
    }
}
