<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HelpTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HelpTopic>
 */
class HelpTopicFactory extends Factory
{
    protected $model = HelpTopic::class;

    public function definition(): array
    {
        return [
            'topic_id' => 'HLP-TST-'.$this->faker->unique()->numberBetween(100, 999),
            'title' => 'Do the thing in the Microsoft portal',
            'summary' => 'A short task-oriented description.',
            'status' => 'published',
            'content_version' => '1',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft']);
    }

    public function citingMicrosoft(): static
    {
        return $this->state(fn (): array => [
            'microsoft_reference' => 'https://learn.microsoft.com/fabric/',
            'last_reviewed_at' => now()->subDays(30),
        ]);
    }
}
