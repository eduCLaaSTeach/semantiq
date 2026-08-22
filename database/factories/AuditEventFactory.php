<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    protected $model = AuditEvent::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'actor_label' => $this->faker->name(),
            'action' => 'organisation.updated',
            'target_type' => 'Organisation',
            'target_id' => '1',
            'result' => 'succeeded',
            'occurred_at' => now(),
        ];
    }

    public function denied(): static
    {
        return $this->state(fn (): array => ['result' => 'denied']);
    }
}
