<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sign-in screen and the credential path behind it.
 *
 * The error-shape tests matter as much as the happy path: the validation
 * contract in section 8 is specific about which errors are field errors and
 * which belong to the form, and getting that wrong is invisible until someone
 * is standing in front of a form that points at the wrong input.
 */
class SignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('sign-in|person@example.test|127.0.0.1');
    }

    private function person(string $password = 'correct-horse-battery'): User
    {
        return User::query()->create([
            'name' => 'Test Person',
            'email' => 'person@example.test',
            'password' => Hash::make($password),
        ]);
    }

    #[Test]
    public function the_sign_in_screen_renders(): void
    {
        $this->get('/sign-in')
            ->assertOk()
            ->assertSee('Sign in with Microsoft')
            ->assertSee('Work email')
            ->assertSee('CLaaS SemantiQ');
    }

    #[Test]
    public function the_screen_carries_both_theme_variants_of_the_brand_mark(): void
    {
        // The mark is a per-theme pair. Shipping only the light one is the bug
        // that makes a dark-theme sign-in screen look broken.
        $this->get('/sign-in')
            ->assertSee('logo-full-light.png')
            ->assertSee('logo-full-dark.png');
    }

    #[Test]
    public function a_guest_visiting_a_protected_page_is_redirected_rather_than_erroring(): void
    {
        // Laravel's default points at a route named "login", which does not
        // exist here. Without the redirect override this is a 500, not a 302.
        $this->get('/')->assertRedirect('/sign-in');
    }

    #[Test]
    public function an_empty_submission_reports_both_fields(): void
    {
        $this->from('/sign-in')
            ->post('/sign-in', [])
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors(['email', 'password']);
    }

    #[Test]
    public function a_malformed_address_is_a_field_error(): void
    {
        $this->from('/sign-in')
            ->post('/sign-in', ['email' => 'not-an-address', 'password' => 'whatever'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function wrong_credentials_report_against_the_form_not_a_field(): void
    {
        $this->person();

        $response = $this->from('/sign-in')->post('/sign-in', [
            'email' => 'person@example.test',
            'password' => 'wrong',
        ]);

        /*
         * Neither input is individually wrong - the pair is - so the message
         * belongs at the form foot. Hanging it on the password field would
         * tell an attacker the address was right.
         */
        $response->assertSessionHasErrors('form');
        $response->assertSessionDoesntHaveErrors(['email', 'password']);
        $this->assertGuest();
    }

    #[Test]
    public function the_failure_message_never_says_which_half_was_wrong(): void
    {
        $this->person();

        $unknown = $this->from('/sign-in')->post('/sign-in', [
            'email' => 'nobody@example.test', 'password' => 'wrong',
        ])->assertSessionHasErrors('form');

        $known = $this->from('/sign-in')->post('/sign-in', [
            'email' => 'person@example.test', 'password' => 'wrong',
        ])->assertSessionHasErrors('form');

        // An unknown address and a wrong password must be indistinguishable, or
        // the form becomes a way to confirm who has an account.
        $this->assertSame(
            $unknown->getSession()->get('errors')->first('form'),
            $known->getSession()->get('errors')->first('form'),
        );
    }

    #[Test]
    public function correct_credentials_sign_a_person_in(): void
    {
        $person = $this->person();

        $this->post('/sign-in', [
            'email' => 'person@example.test',
            'password' => 'correct-horse-battery',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($person);
    }

    #[Test]
    public function signing_in_issues_a_new_session_id(): void
    {
        $this->person();

        $before = session()->getId();

        $this->post('/sign-in', [
            'email' => 'person@example.test',
            'password' => 'correct-horse-battery',
        ]);

        // A session fixed before authentication must not be the one that ends
        // up signed in.
        $this->assertNotSame($before, session()->getId());
    }

    #[Test]
    public function repeated_failures_are_throttled(): void
    {
        $this->person();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/sign-in', ['email' => 'person@example.test', 'password' => 'wrong']);
        }

        $response = $this->from('/sign-in')->post('/sign-in', [
            'email' => 'person@example.test', 'password' => 'wrong',
        ]);

        $response->assertSessionHasErrors('form');
        $this->assertStringContainsString(
            'Too many sign-in attempts',
            $response->getSession()->get('errors')->first('form'),
        );
    }

    #[Test]
    public function a_successful_sign_in_clears_the_throttle(): void
    {
        $this->person();

        $this->post('/sign-in', ['email' => 'person@example.test', 'password' => 'wrong']);
        $this->post('/sign-in', [
            'email' => 'person@example.test', 'password' => 'correct-horse-battery',
        ]);

        $this->assertSame(0, RateLimiter::attempts('sign-in|person@example.test|127.0.0.1'));
    }

    #[Test]
    public function microsoft_sign_in_fails_closed_with_an_explanation(): void
    {
        // Not built yet. It must say so rather than 500.
        $this->from('/sign-in')
            ->post('/sign-in/microsoft')
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors('form');
    }

    #[Test]
    public function password_help_explains_rather_than_offering_a_reset_form(): void
    {
        $this->get('/sign-in/password')
            ->assertOk()
            ->assertSee('Password help')
            ->assertDontSee('name="password"', false);
    }

    #[Test]
    public function signing_out_ends_the_session(): void
    {
        $this->actingAs($this->person())
            ->post('/sign-out')
            ->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    #[Test]
    public function a_signed_in_person_is_sent_away_from_the_sign_in_screen(): void
    {
        $this->actingAs($this->person())->get('/sign-in')->assertRedirect('/');
    }
}
