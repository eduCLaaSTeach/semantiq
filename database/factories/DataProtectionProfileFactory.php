<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DataProtectionProfile;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataProtectionProfile>
 */
class DataProtectionProfileFactory extends Factory
{
    protected $model = DataProtectionProfile::class;

    /**
     * The default is the unconfigured profile, matching what a real organisation
     * starts with: no geography stated and every permission withheld. A factory
     * that handed out an approved profile would make the deny-by-default tests
     * pass for the wrong reason.
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'version' => 1,
            'is_current' => true,
        ];
    }

    public function withApprovedGeographies(): static
    {
        return $this->state(fn (): array => [
            'approved_storage_geographies' => ['Southeast Asia'],
            'approved_processing_geographies' => ['Southeast Asia'],
        ]);
    }
}
