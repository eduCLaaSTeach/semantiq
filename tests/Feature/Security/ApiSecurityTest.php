<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\SecurityStatus;
use App\Modules\Security\Http\Middleware\SecurityHeaders;
use App\Modules\Security\Support\ApiSecurityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * ADM-011 API Security.
 *
 * The headers are asserted on a REAL RESPONSE rather than by checking that the
 * middleware is registered: a middleware that is registered and has stopped
 * setting a header is the failure this is for.
 *
 * The control report is asserted for honesty as much as for content. Two
 * properties matter more than any individual check: nothing is hard-coded to
 * pass, and nothing that could not be established is reported as Healthy.
 */
class ApiSecurityTest extends TestCase
{
    use ControlsSecurityPolicy, RefreshDatabase;

    private function admin(): User
    {
        $user = User::query()->create([
            'name' => 'Ada Admin',
            'email' => 'ada@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $user->forceFill([
            'role' => Role::SystemAdmin,
            'authentication_source' => 'local',
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /* ---- Headers -------------------------------------------------------- */

    #[Test]
    public function every_response_carries_the_base_security_headers(): void
    {
        $response = $this->get('/sign-in')->assertOk();

        foreach (SecurityHeaders::BASE_HEADERS as $header => $value) {
            $this->assertSame($value, $response->headers->get($header), $header.' was not sent.');
        }
    }

    #[Test]
    public function the_content_security_policy_is_report_only_by_default(): void
    {
        // An enforcing policy that is slightly wrong breaks the shell for
        // everybody at once, so the safe order is report, read, then enforce.
        $response = $this->get('/sign-in')->assertOk();

        $this->assertNotNull($response->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertNull($response->headers->get('Content-Security-Policy'));
    }

    #[Test]
    public function the_content_security_policy_can_be_enforced_or_turned_off(): void
    {
        $this->withSecurityPolicy('api.content_policy_mode', 'enforce');
        $enforcing = $this->get('/sign-in')->assertOk();
        $this->assertNotNull($enforcing->headers->get('Content-Security-Policy'));

        $this->withSecurityPolicy('api.content_policy_mode', 'off');
        $off = $this->get('/sign-in')->assertOk();
        $this->assertNull($off->headers->get('Content-Security-Policy'));
        $this->assertNull($off->headers->get('Content-Security-Policy-Report-Only'));
    }

    #[Test]
    public function hsts_is_not_sent_by_default(): void
    {
        // Gate 3 rule 8. HSTS cannot be withdrawn from a browser that has seen
        // it, so it stays off until separately approved for production.
        $this->assertNull($this->get('/sign-in')->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function hsts_is_still_not_sent_over_plain_http_when_switched_on(): void
    {
        // Sending it over plain HTTP is ignored by browsers anyway, but sending
        // it from a development server would poison a developer's browser for
        // every other project on localhost.
        $this->withSecurityPolicy('api.hsts_enabled', true);

        $this->assertNull($this->get('/sign-in')->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function hsts_is_sent_over_https_when_switched_on_with_the_configured_duration(): void
    {
        $this->withSecurityPolicy('api.hsts_enabled', true);
        $this->withSecurityPolicy('api.hsts_max_age_days', 2);

        $response = $this->get('https://localhost/sign-in');

        $this->assertSame('max-age='.(2 * 86400), $response->headers->get('Strict-Transport-Security'));
    }

    #[Test]
    public function turning_the_headers_off_removes_all_of_them(): void
    {
        // The switch exists so a header that breaks an embedded view can be
        // turned off deliberately from a screen rather than by editing code.
        $this->withSecurityPolicy('api.security_headers', false);

        $response = $this->get('/sign-in')->assertOk();

        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('Content-Security-Policy-Report-Only'));
    }

    /* ---- Payload limit -------------------------------------------------- */

    #[Test]
    public function a_request_larger_than_the_limit_is_refused_with_a_413(): void
    {
        $this->withSecurityPolicy('api.max_payload_kilobytes', 64);

        $this->call(
            'POST',
            '/sign-in',
            ['email' => 'a@b.test', 'password' => 'x'],
            [],
            [],
            ['CONTENT_LENGTH' => (string) (65 * 1024)],
        )->assertStatus(413);
    }

    #[Test]
    public function a_request_inside_the_limit_is_not_refused(): void
    {
        // The guard must refuse an oversized request, not every request.
        $this->post('/sign-in', ['email' => 'a@b.test', 'password' => 'x'])
            ->assertRedirect();
    }

    /* ---- The control report --------------------------------------------- */

    #[Test]
    public function the_report_covers_every_control_adm_011_names(): void
    {
        $keys = array_column(app(ApiSecurityAudit::class)->run(), 'key');

        $this->assertSame([
            'authentication',
            'authorization',
            'csrf',
            'correlation',
            'rate_limiting',
            'headers',
            'payload',
            'errors',
        ], $keys);
    }

    #[Test]
    public function the_report_is_healthy_on_a_correctly_configured_application(): void
    {
        // If this ever fails, something in the application actually regressed -
        // which is the whole reason the screen exists.
        config(['app.debug' => false]);

        foreach (app(ApiSecurityAudit::class)->run() as $control) {
            $this->assertSame(
                SecurityStatus::Healthy,
                $control['status'],
                $control['name'].' is not healthy: '.$control['detail'],
            );
        }
    }

    #[Test]
    public function making_the_local_disk_public_turns_the_storage_routes_into_a_finding(): void
    {
        // Laravel's local-disk routes carry no middleware and are gated by a
        // signed URL inside the handler - but only while the disk is private.
        // Making it public turns that check off, and the report has to notice.
        config(['filesystems.disks.local.visibility' => 'public']);

        $control = collect(app(ApiSecurityAudit::class)->run())->firstWhere('key', 'authentication');

        $this->assertSame(SecurityStatus::Critical, $control['status']);
        $this->assertStringContainsString('storage.local', $control['detail']);
    }

    #[Test]
    public function the_report_notices_when_a_control_is_actually_turned_off(): void
    {
        // Proof that nothing is hard-coded to pass.
        $this->withSecurityPolicy('api.security_headers', false);

        $headers = collect(app(ApiSecurityAudit::class)->run())->firstWhere('key', 'headers');

        $this->assertSame(SecurityStatus::NotConfigured, $headers['status']);
        $this->assertStringContainsString('No security headers are being sent', $headers['detail']);
    }

    #[Test]
    public function debug_mode_is_reported_as_a_problem_rather_than_ignored(): void
    {
        // A stack trace rendered to a browser carries configuration, and
        // ADM-011's last control is "no secrets in errors".
        config(['app.debug' => true]);

        $errors = collect(app(ApiSecurityAudit::class)->run())->firstWhere('key', 'errors');

        $this->assertSame(SecurityStatus::Warning, $errors['status']);
        $this->assertStringContainsString('APP_DEBUG', $errors['detail']);
    }

    #[Test]
    public function a_loose_rate_limit_is_reported_as_a_warning_rather_than_healthy(): void
    {
        $this->withSecurityPolicy('sign_in.failed_attempt_threshold', 50);

        $limit = collect(app(ApiSecurityAudit::class)->run())->firstWhere('key', 'rate_limiting');

        $this->assertSame(SecurityStatus::Warning, $limit['status']);
    }

    #[Test]
    public function nothing_that_cannot_be_established_is_reported_as_healthy(): void
    {
        // Gate 3 rule 9, as a property of the whole report rather than of one
        // check: no control may claim Healthy while its detail says it could
        // not be verified.
        foreach (app(ApiSecurityAudit::class)->run() as $control) {
            if ($control['status'] === SecurityStatus::Healthy) {
                $this->assertStringNotContainsStringIgnoringCase('could not', $control['detail'], $control['name']);
                $this->assertStringNotContainsStringIgnoringCase('cannot be confirmed', $control['detail'], $control['name']);
            }
        }
    }

    #[Test]
    public function the_screen_renders_the_report_and_the_switches(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.security.api'))
            ->assertOk()
            ->assertSee('Controls in force')
            ->assertSee('Authentication enforcement')
            ->assertSee('Secret-safe error handling')
            ->assertSee('Strict-Transport-Security');
    }

    #[Test]
    public function the_status_enum_reports_not_verified_as_worse_than_not_configured(): void
    {
        // "We do not know" is worse than "we decided not to", and the roll-up
        // has to order them that way or an unverifiable control disappears
        // behind a deliberate choice.
        $this->assertSame(
            SecurityStatus::NotVerified,
            SecurityStatus::worst([SecurityStatus::Healthy, SecurityStatus::NotConfigured, SecurityStatus::NotVerified]),
        );

        $this->assertSame(SecurityStatus::NotVerified, SecurityStatus::worst([]));
        $this->assertTrue(SecurityStatus::NotVerified->needsAttention());
        $this->assertFalse(SecurityStatus::NotAvailable->needsAttention());
    }
}
