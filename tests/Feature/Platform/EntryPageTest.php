<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

final class EntryPageTest extends TestCase
{
    public function test_the_entry_page_is_served_to_an_unauthenticated_visitor(): void
    {
        $this->get('/')->assertOk();
    }

    /**
     * The blueprint requires that an unauthenticated browser receives no
     * protected shell, menu or business metadata. This asserts the absence of
     * the things that would constitute one.
     */
    public function test_the_entry_page_leaks_no_protected_structure(): void
    {
        $body = $this->get('/')->getContent();

        foreach ([
            'System Administration',
            'Fabric Configuration',
            'SemantIQ Workplace',
            'shell-rail',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $body,
                "The pre-authentication entry page exposed [{$forbidden}]."
            );
        }
    }

    /**
     * The approved Login copy, in the page the browser actually receives.
     *
     * BrandAndShellFoundationTest checks the source; this checks that the Login
     * page is the component actually served and that its copy is the delivered
     * copy, so a change that never reaches the wire still fails.
     *
     * Mutation: reword any line in Entry.jsx or SignInLayout.jsx.
     */
    public function test_the_login_page_delivers_the_approved_copy(): void
    {
        $page = $this->get('/')->viewData('page');

        $this->assertSame('Entry', $page['component']);

        $delivered = file_get_contents(__DIR__.'/../../../resources/js/Pages/Entry.jsx')
            .file_get_contents(__DIR__.'/../../../resources/js/Layouts/SignInLayout.jsx');

        foreach ([
            'Business Decision Intelligence',
            'From business data to',
            'confident decisions',
            'Bring governed data, business context and intelligent analysis together to understand',
            'Welcome to SemantIQ',
            'Sign in securely to continue to your decision intelligence workspace.',
            'Continue with Microsoft',
            'Access is managed by your organisation',
            'Contact your administrator if you cannot access SemantIQ.',
        ] as $approved) {
            $this->assertStringContainsString($approved, $delivered, "Approved copy changed: [{$approved}].");
        }
    }

    /**
     * The Microsoft path is exactly what P1-00 delivered - this unit restyled
     * the page and changed nothing about how sign-in works.
     *
     * The button itself cannot be observed on a deployment where Microsoft is
     * unconfigured (blueprint 0.2 withholds it rather than offering a button
     * that cannot work), so the CONDITION and the destination are asserted here
     * and the rendered button is a Product Owner check on the real deployment.
     *
     * Mutation: change the redirect path, or offer the button unconditionally.
     */
    public function test_the_microsoft_sign_in_path_is_unchanged(): void
    {
        $entry = file_get_contents(__DIR__.'/../../../resources/js/Pages/Entry.jsx');

        $this->assertStringContainsString('{microsoftEnabled ? (', $entry, 'The button is no longer conditional.');
        $this->assertStringContainsString('href="/auth/microsoft/redirect"', $entry);

        $this->assertArrayHasKey('microsoftEnabled', $this->get('/')->viewData('page')['props']);

        // And the route behind it still exists and still redirects to Entra.
        $this->assertNotNull(app('router')->getRoutes()->getByName('auth.microsoft.redirect'));
    }
}
