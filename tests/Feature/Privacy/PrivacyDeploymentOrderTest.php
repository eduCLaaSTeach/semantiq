<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Governance\Support\GovernanceStorage;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The window between a deploy and its migration.
 *
 * The deploy ships code and does not run migrations, so there is always a
 * period where the application is ahead of the database. R1.3 discovered what
 * that costs: a 500 on sign-in that took the whole site down. R1.4a and R1.4b
 * both survived it, and this batch must too.
 *
 * WHAT "SURVIVE" MEANS HERE. Not merely "does not crash". A register of real
 * events must never render as EMPTY when it cannot see whether there are any -
 * SEC-DEC-072. "No privacy requests" and "the table does not exist" are
 * different facts, and only one of them is safe to show somebody who is
 * checking whether an obligation is being met.
 */
class PrivacyDeploymentOrderTest extends TestCase
{
    use RefreshDatabase;

    private function dropPrivacyTables(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('privacy_correction_notes');
        Schema::dropIfExists('privacy_request_records');
        Schema::dropIfExists('privacy_requests');
        Schema::enableForeignKeyConstraints();

        app(GovernanceStorage::class)->forget();
    }

    private function administrator(): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => Role::SystemAdmin,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    #[Test]
    public function storage_reports_not_ready_when_any_of_the_three_tables_is_missing(): void
    {
        $storage = app(GovernanceStorage::class);
        $this->assertTrue($storage->privacyRequestsAreReady());

        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('privacy_correction_notes');
        Schema::enableForeignKeyConstraints();
        $storage->forget();

        $this->assertFalse(
            $storage->privacyRequestsAreReady(),
            'a partial answer is worse than none: a register that could list requests but not their '
            .'records would show a reviewer an empty response',
        );
    }

    #[Test]
    public function the_register_explains_itself_rather_than_rendering_empty(): void
    {
        $this->dropPrivacyTables();

        $response = $this->actingAs($this->administrator())->get('/admin/governance/privacy-requests');

        $response->assertOk();
        $response->assertSee('Migration required');
        $response->assertSee('It is not empty - it does not exist', false);
    }

    #[Test]
    public function every_privacy_route_survives_the_tables_being_absent(): void
    {
        $this->dropPrivacyTables();

        $admin = $this->administrator();

        $gets = ['/admin/governance/privacy-requests', '/admin/governance/privacy-requests/1'];

        foreach ($gets as $path) {
            $status = $this->actingAs($admin)->get($path)->getStatusCode();

            $this->assertLessThan(500, $status, "{$path} returned {$status} with the tables absent");
        }

        $posts = [
            '/admin/governance/privacy-requests',
            '/admin/governance/privacy-requests/1/verify',
            '/admin/governance/privacy-requests/1/assemble',
            '/admin/governance/privacy-requests/1/review',
            '/admin/governance/privacy-requests/1/note',
            '/admin/governance/privacy-requests/1/release',
            '/admin/governance/privacy-requests/1/refuse',
            '/admin/governance/privacy-requests/1/close',
        ];

        foreach ($posts as $path) {
            $status = $this->actingAs($admin)->post($path, [])->getStatusCode();

            $this->assertLessThan(500, $status, "{$path} returned {$status} with the tables absent");
        }
    }

    /**
     * A typed id must not reach the database before the guard.
     *
     * SEC-DEC-058: `SubstituteBindings` runs in the `web` middleware GROUP,
     * ahead of route middleware, so an implicit model binding would query the
     * table before the storage guard could refuse - and return a raw database
     * error during exactly the window this test is about.
     */
    #[Test]
    public function a_typed_id_does_not_query_a_missing_table(): void
    {
        $this->dropPrivacyTables();

        $status = $this->actingAs($this->administrator())
            ->get('/admin/governance/privacy-requests/4242')
            ->getStatusCode();

        $this->assertLessThan(500, $status);
    }

    /**
     * The route only accepts a number, so a crafted id cannot reach the
     * controller at all.
     */
    #[Test]
    public function a_non_numeric_id_does_not_resolve(): void
    {
        $response = $this->actingAs($this->administrator())
            ->get('/admin/governance/privacy-requests/not-a-number');

        $this->assertSame(404, $response->getStatusCode());
    }
}
