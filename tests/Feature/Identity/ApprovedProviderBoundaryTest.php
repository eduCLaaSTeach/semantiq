<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Health\IdentityHealthReport;
use App\Modules\Identity\Support\ApprovedProviders;
use App\Modules\Identity\Support\ProviderInventory;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\VerifiedIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\Support\EntraTokenFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * An approved provider is a DECISION, not a fact about the code.
 *
 * These cases exist because the ones without them were not enough, and the way
 * they were not enough is worth recording. Every other guard in this unit
 * asserts the healthy shape: one provider bound, one provider approved, the two
 * agreeing. Mutations that made the screen read the container instead of the
 * catalogue, and that made unapprovedKeys() always return empty, BOTH SURVIVED
 * that suite - because with only Microsoft registered, the two sources give the
 * same answer and nothing could tell them apart.
 *
 * So this file registers a provider nobody approved, which is the only condition
 * under which the distinction is observable at all.
 */
final class ApprovedProviderBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        (new EntraTokenFactory)->configure();
        $this->make = new OrganisationFactory;
    }

    /** Bind a provider the Product Owner has never approved. */
    private function givenAnUnapprovedProviderIsRegistered(): void
    {
        $stub = new class implements IdentityProvider
        {
            public function key(): string
            {
                return 'unapproved-provider';
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function beginAuthorization(): RedirectResponse
            {
                return new RedirectResponse('/');
            }

            public function completeAuthorization(Request $request): VerifiedIdentity
            {
                throw new \RuntimeException('not used');
            }
        };

        $this->app->bind('test.unapproved.provider', fn () => $stub);
        $this->app->tag(['test.unapproved.provider'], 'identity.providers');
    }

    /** A5 / A9. The inventory notices, which is what fails the build. */
    public function test_an_unapproved_runtime_provider_is_reported(): void
    {
        $this->givenAnUnapprovedProviderIsRegistered();

        $inventory = app(ProviderInventory::class);

        $this->assertContains('unapproved-provider', $inventory->runtimeKeys());
        $this->assertSame(['unapproved-provider'], $inventory->unapprovedKeys());
        $this->assertFalse(ApprovedProviders::isApproved('unapproved-provider'));
    }

    /**
     * A5b. THE CASE THE SCREEN EXISTS FOR.
     *
     * A provider that is merely present in the software must not appear to an
     * administrator as an approved way to sign in. This is the assertion that
     * makes "approved" a decision rather than a description of the code.
     *
     * Mutation: have the controller enumerate the container instead of the
     * catalogue. CAUGHT here and nowhere else.
     */
    public function test_the_screen_never_lists_an_unapproved_provider_as_approved(): void
    {
        $this->givenAnUnapprovedProviderIsRegistered();

        $body = $this->actingAsAdministrator()->get('/console/identity/providers')->getContent();

        $this->assertStringContainsString('Microsoft Entra ID', $body);
        $this->assertStringNotContainsString('unapproved-provider', $body);
    }

    /**
     * ...and health says so, so it is visible in production and not only in CI.
     *
     * Mutation: drop the approved-providers check from the report.
     */
    public function test_health_reports_an_unapproved_provider_as_an_outage(): void
    {
        $this->givenAnUnapprovedProviderIsRegistered();

        $rows = [];

        foreach (app(IdentityHealthCheck::class)->report()->checks as $row) {
            $rows[$row['key']] = $row;
        }

        $this->assertSame(IdentityHealthReport::FAILED, $rows['approved_providers']['state']);
        $this->assertNotNull($rows['approved_providers']['action']);

        // The finding names no provider key: it says a provider is present that
        // was not approved, which is what an administrator can act on.
        $this->assertStringNotContainsString('unapproved-provider', $rows['approved_providers']['finding']);
    }

    /** And with nothing extra bound, the same guard reports clean. */
    public function test_the_delivered_build_registers_only_approved_providers(): void
    {
        $inventory = app(ProviderInventory::class);

        $this->assertSame(['microsoft'], $inventory->runtimeKeys());
        $this->assertSame([], $inventory->unapprovedKeys());
    }

    private function actingAsAdministrator(): self
    {
        $admin = $this->make->user(administrator: true);

        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $admin->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
