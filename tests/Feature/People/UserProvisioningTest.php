<?php

declare(strict_types=1);

namespace Tests\Feature\People;

use App\Modules\People\Services\UserDirectoryService;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\IdentityResolver;
use App\Modules\Platform\Identity\VerifiedIdentity;
use App\Modules\Platform\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\OrganisationFactory;
use Tests\Support\ScreenSource;
use Tests\TestCase;

/**
 * D-33 = A: an administrator types a Microsoft Entra Object ID, and that is the
 * whole of provisioning. No directory lookup, no invitation, no pending record,
 * no email binding.
 *
 * The rule that carries the most weight is the one that is easiest to break by
 * being helpful: email and display name are PROVISIONAL until the person first
 * signs in, and must never be used to match, authorise or de-duplicate.
 */
final class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private const OBJECT_ID = '3f2504e0-4f89-11d3-9a0c-0305e82c3301';

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;

        // A real tenant value, so that a guard reading the configured tenant
        // gives a different answer from one reading an empty string.
        config()->set('identity.microsoft.tenant_id', '99999999-9999-9999-9999-999999999999');
    }

    /**
     * Negative case 7. A duplicate identity is refused, IN BUSINESS LANGUAGE.
     *
     * Both halves matter. The refusal is the easy part; that the administrator
     * is told what to do instead is the part that gets dropped.
     *
     * Mutation: drop users_identity_uq, or return the raw exception message.
     */
    public function test_a_duplicate_identity_is_refused_in_business_language(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->provision($admin)->assertRedirect();

        $response = $this->provision($admin, email: 'someone.else@example.test');

        $response->assertSessionHasErrors('people');

        $message = session('errors')->get('people')[0];

        $this->assertSame(
            'That person is already in SemantIQ. Open their record instead of adding them again.',
            $message
        );

        $this->assertStringNotContainsStringIgnoringCase('constraint', $message);
        $this->assertStringNotContainsStringIgnoringCase('sql', $message);
        $this->assertStringNotContainsStringIgnoringCase('users_identity_uq', $message);

        $this->assertSame(1, User::query()->where('external_subject', self::OBJECT_ID)->count());
    }

    /**
     * Negative case 8. Two administrators provisioning the same person
     * concurrently produce ONE user and ONE refusal.
     *
     * The service reads before inserting so the refusal is a sentence - but the
     * read is not the guard. This proves the DATABASE is: the constraint is
     * asked to reject the second insert directly, with no service read in front
     * of it, which is what a real race reaches.
     *
     * Mutation: replace the unique constraint with a check-then-insert.
     */
    public function test_the_database_refuses_a_second_identical_identity(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->provision($admin)->assertRedirect();

        $existing = User::query()->where('external_subject', self::OBJECT_ID)->sole();

        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'organisation_id' => $organisation->id,
            'provider' => $existing->provider,
            'external_subject' => $existing->external_subject,
            'tenant_id' => $existing->tenant_id,
            'email' => 'racing@example.test',
            'display_name' => 'Racing',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Negative case 9. A malformed Object ID is refused SERVER-SIDE.
     *
     * Browser validation is a convenience; a crafted request never sees it.
     *
     * Mutation: remove the regex rule from the store request.
     */
    public function test_a_malformed_object_id_is_refused_by_the_server(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        foreach ([
            'not-a-guid',
            '3f2504e0-4f89-11d3-9a0c',
            '3f2504e04f8911d39a0c0305e82c3301',
            '3f2504e0-4f89-11d3-9a0c-0305e82c3301-extra',
            'zzzzzzzz-4f89-11d3-9a0c-0305e82c3301',
            '',
            '   ',
        ] as $candidate) {
            $this->actingAsUser($admin)
                ->post('/console/people/users', [
                    'object_id' => $candidate,
                    'email' => 'candidate@example.test',
                ])
                ->assertSessionHasErrors('object_id');
        }

        $this->assertSame(
            1,
            User::query()->count(),
            'A malformed Object ID created a user. Only the administrator exists.'
        );
    }

    /**
     * Negative case 10. THE IDENTITY KEY CANNOT BE EDITED AFTER CREATION.
     *
     * Every write route is tried with every part of the key in the payload. A
     * changed key would silently rebind a SemantIQ record to a different person
     * in the directory, which is the worst thing this unit could do.
     *
     * Mutation: make external_subject fillable and accept it in update().
     */
    public function test_the_identity_key_cannot_be_edited_through_any_route(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->provision($admin);

        $person = User::query()->where('external_subject', self::OBJECT_ID)->sole();

        $before = [
            'provider' => $person->provider,
            'external_subject' => $person->external_subject,
            'tenant_id' => $person->tenant_id,
        ];

        $payload = [
            'organisation_id' => $organisation->id,
            'provider' => 'google',
            'external_subject' => 'aaaaaaaa-0000-0000-0000-000000000000',
            'tenant_id' => '00000000-dead-beef-0000-000000000000',
            'email' => 'rebound@example.test',
            'display_name' => 'Rebound',
        ];

        $this->actingAsUser($admin)->put("/console/people/users/{$person->id}", $payload);
        $this->actingAsUser($admin)->patch("/console/people/users/{$person->id}/deactivate", $payload);
        $this->actingAsUser($admin)->patch("/console/people/users/{$person->id}/reactivate", $payload);

        $after = $person->fresh();

        $this->assertSame($before['provider'], $after->provider);
        $this->assertSame($before['external_subject'], $after->external_subject);
        $this->assertSame($before['tenant_id'], $after->tenant_id);

        // Negative case 37: the directory-owned display fields are read-only too.
        $this->assertNotSame('rebound@example.test', $after->email);
        $this->assertNotSame('Rebound', $after->display_name);
    }

    /**
     * Negative case 11. provider AND tenant_id POSTED IN THE REQUEST ARE
     * IGNORED.
     *
     * They are not sanitised, they are not accepted: the service reads them from
     * configuration and they never arrive from outside. The distinction matters
     * because a sanitiser is one refactor away from being bypassed.
     *
     * Asserted against the CONFIGURED value rather than against "not what was
     * posted", so a path that ignored the input and then wrote something else
     * wrong would still fail.
     *
     * Mutation: accept provider and tenant_id from input in store().
     */
    public function test_provider_and_tenant_come_from_configuration_not_from_the_request(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => self::OBJECT_ID,
            'email' => 'configured@example.test',
            'provider' => 'google',
            'tenant_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ])->assertRedirect();

        $person = User::query()->where('email', 'configured@example.test')->sole();

        $this->assertSame('microsoft', $person->provider);
        $this->assertSame(config('identity.microsoft.tenant_id'), $person->tenant_id);
    }

    /**
     * Negative case 12. PROVISIONAL EMAIL NEVER BINDS A SIGN-IN.
     *
     * The behavioural half of negative case 6, and the reason it matters in
     * P1-03 specifically: an administrator types an email for somebody who has
     * never signed in, so for the first time there is a record whose email is a
     * guess. If authentication ever fell back to email, the next person to sign
     * in with that address would inherit the record.
     *
     * Mutation: add an email fallback to IdentityResolver.
     */
    public function test_a_sign_in_whose_email_matches_but_whose_subject_does_not_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->provision($admin, email: 'expected.person@example.test');

        $provisioned = User::query()->where('email', 'expected.person@example.test')->sole();

        $identity = new VerifiedIdentity(
            provider: 'microsoft',
            subject: 'bbbbbbbb-1111-1111-1111-111111111111',
            tenant: (string) config('identity.microsoft.tenant_id'),
            email: 'expected.person@example.test',
            displayName: 'Somebody Else',
        );

        try {
            app(IdentityResolver::class)->resolve($identity);

            $this->fail('An identity with a matching email but a different subject was accepted.');
        } catch (\Throwable $refusal) {
            $this->assertStringNotContainsStringIgnoringCase('expected.person', $refusal->getMessage());
        }

        $unchanged = $provisioned->fresh();

        $this->assertSame(self::OBJECT_ID, $unchanged->external_subject);
        $this->assertNull($unchanged->last_signed_in_at, 'A refused sign-in was recorded as one.');

        $this->assertNotSame(
            'Somebody Else',
            $unchanged->display_name,
            'The refused identity still overwrote the record\'s display name, so it was matched '
            .'after all and only the response was refused.'
        );

        // And no record was created for the unknown subject either.
        $this->assertSame(
            0,
            User::query()->where('external_subject', 'bbbbbbbb-1111-1111-1111-111111111111')->count(),
            'A refused sign-in provisioned a user. P1-03 provisions by administrator action only.'
        );
    }

    /**
     * Negative case 13. "Not signed in yet" is the words, not an empty cell -
     * and the real value once there is one.
     *
     * TWO INSTRUMENTS, because neither alone would prove it.
     *
     * The screens are Inertia, so a response body carries the props and a
     * <script src> - not the rendered text. An assertSee for the phrase would
     * therefore be asserting against a page that does not contain any screen
     * copy at all, and would fail (or pass) for reasons unrelated to the claim.
     * So: the PROP is asserted here in both directions against real responses,
     * and the RENDERING of the null case is asserted against the screen source.
     * The observation of the actual pixels belongs to the browser verification,
     * and is recorded there rather than implied here.
     *
     * Mutation: render an empty cell when last_signed_in_at is NULL.
     */
    public function test_a_person_who_has_never_signed_in_is_described_in_words(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->provision($admin, email: 'newcomer@example.test');

        $person = User::query()->where('email', 'newcomer@example.test')->sole();

        $props = $this->actingAsUser($admin)
            ->get("/console/people/users/{$person->id}")
            ->viewData('page')['props'];

        $this->assertNull($props['person']['lastSignedIn']);

        $list = $this->actingAsUser($admin)
            ->get('/console/people/users')
            ->viewData('page')['props'];

        $row = collect($list['users']['data'])->firstWhere('email', 'newcomer@example.test');

        $this->assertNull($row['lastSignedIn'], 'The list carried a signed-in value for somebody who has not.');

        // The real value afterwards, on both surfaces.
        $person->forceFill(['last_signed_in_at' => now()->subDay()])->save();

        $props = $this->actingAsUser($admin)
            ->get("/console/people/users/{$person->id}")
            ->viewData('page')['props'];

        $this->assertNotNull($props['person']['lastSignedIn']);

        $list = $this->actingAsUser($admin)
            ->get('/console/people/users')
            ->viewData('page')['props'];

        $row = collect($list['users']['data'])->firstWhere('email', 'newcomer@example.test');

        $this->assertSame(now()->subDay()->toDateString(), $row['lastSignedIn']);

        /*
         * And the screens turn the null into words rather than into a blank.
         *
         * COMMENTS ARE STRIPPED FIRST. Both screens explain this rule in a
         * docblock that quotes the phrase, so a check against the raw file
         * matched the explanation rather than the rendering - it passed with the
         * cell mutated to render nothing at all, which is the defect it exists
         * to catch. Found by the mutation, not by reading the test.
         */
        foreach (['Pages/People/User.jsx', 'Pages/People/Users.jsx'] as $screen) {
            $rendered = ScreenSource::rendered($screen);

            $this->assertStringContainsString(
                'Not signed in yet',
                $rendered,
                "{$screen} no longer renders \"Not signed in yet\". A blank cell reads as a missing "
                .'value rather than as a person who has been added and has not arrived.'
            );
        }
    }

    /**
     * Provisional name and email are LABELLED as provisional until the person
     * first signs in.
     *
     * The value is a guess an administrator typed. Presenting it identically to
     * a value Microsoft confirmed would be presenting a guess as a fact.
     *
     * Mutation: give both cases the same note.
     */
    public function test_provisional_directory_values_are_labelled_as_provisional(): void
    {
        $source = ScreenSource::rendered('Pages/People/User.jsx');

        $this->assertStringContainsString('Provisional.', $source);
        $this->assertStringContainsString('have not been confirmed by Microsoft', $source);

        // And the screen distinguishes the two cases rather than always saying it.
        $this->assertStringContainsString('refreshed each time this person signs in', $source);
    }

    /**
     * Negative case 14. THE FORM DOES NOT CLAIM THE DIRECTORY WAS CHECKED.
     *
     * SemantIQ has no Microsoft Graph permission by decision, so it cannot
     * confirm that an Object ID names a real person. The screen says so. A tick
     * meaning "the format is right" that reads as "we found them" would be worse
     * than saying nothing, because the administrator would stop checking.
     *
     * Mutation: add "Verified" beside the Object ID field; or delete the
     * statement that the directory was not checked.
     */
    public function test_the_add_user_form_says_the_directory_was_not_checked(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $screen = ScreenSource::rendered('Pages/People/Users.jsx');

        $this->assertStringContainsString(
            'SemantIQ cannot check that this ID exists in Microsoft Entra',
            $screen,
            'The Add User form no longer states that the directory was not checked.'
        );

        foreach (['Verified', 'verified', 'Found in', 'Confirmed', 'Looked up', 'Exists in'] as $overclaim) {
            $this->assertStringNotContainsString(
                $overclaim,
                $screen,
                "The Add User form claims [{$overclaim}]. SemantIQ has no Graph permission and "
                .'cannot confirm anything about the person behind an Object ID.'
            );
        }

        // The screen the copy belongs to is actually served.
        $this->actingAsUser($admin)->get('/console/people/users')->assertOk();
    }

    /**
     * The Object ID is stored in one case, so two administrators typing the same
     * identifier differently produce one person rather than two.
     *
     * Entra prints GUIDs lower-case; a value pasted from elsewhere may not be.
     * Without normalisation the unique constraint would not see them as equal.
     */
    public function test_object_ids_differing_only_in_case_are_the_same_person(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->provision($admin);

        $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => strtoupper(self::OBJECT_ID),
            'email' => 'shouting@example.test',
        ])->assertSessionHasErrors('people');

        $this->assertSame(1, User::query()->where('external_subject', self::OBJECT_ID)->count());
    }

    /** The service itself refuses, not only the route. */
    public function test_the_service_refuses_a_duplicate_identity_directly(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $directory = app(UserDirectoryService::class);

        $attributes = ['object_id' => self::OBJECT_ID, 'email' => 'first@example.test'];

        $directory->provision($organisation, $attributes, $admin);

        $this->expectException(PeopleViolation::class);

        $directory->provision($organisation, ['object_id' => self::OBJECT_ID, 'email' => 'second@example.test'], $admin);
    }

    private function provision(User $admin, string $email = 'provisioned@example.test'): TestResponse
    {
        return $this->actingAsUser($admin)->post('/console/people/users', [
            'object_id' => self::OBJECT_ID,
            'email' => $email,
        ]);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
