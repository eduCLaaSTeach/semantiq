<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WorkflowStatus;
use App\Models\FabricItem;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FabricItem>
 */
class FabricItemFactory extends Factory
{
    protected $model = FabricItem::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'item_id' => (string) Str::uuid(),
            'workspace_id' => (string) Str::uuid(),
            'type' => 'Lakehouse',
            'display_name' => 'Bronze Lakehouse',
            'environment' => 'DEV',
            'status' => WorkflowStatus::Succeeded,
            'last_seen_at' => now(),
        ];
    }

    public function unconfirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => WorkflowStatus::InProgress,
            'last_seen_at' => null,
        ]);
    }
}
