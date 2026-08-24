<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The structured privacy contact. SEC-DEC-043, resolved 24 August 2026.
 *
 * WHAT WAS ASKED FOR, and what these tests hold in place. The requirement was
 * explicitly NOT to be implemented by changing validation and letting the next
 * save break:
 *
 *   1. Existing organisations with no privacy contact are IDENTIFIED.
 *   2. The old free-text value is BACKFILLED, safely, into the name.
 *   3. Validation prevents a future save leaving the contact incomplete.
 *   4. Every change is AUDITED, with real values rather than "[redacted]".
 *   5. The administrator is TOLD before the save fails, not by it failing.
 *
 * The backfill is deliberately dumb: it moves the whole old value into the
 * name and parses nothing out of it. Splitting a free-text field into a name
 * and an email by guessing at its shape is how a wrong email address ends up on
 * a regulatory contact record.
 */
class PrivacyContactTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        $user = User::query()->create(['name' => 'Test Person', 'email' => uniqid().'@example.test']);

        $user->forceFill([
            'role' => Role::Admin,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        return $user->refresh();
    }

    /**
     * A complete, valid organisation post.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function wholeForm(array $overrides = []): array
    {
        $organisation = app(OrganisationContext::class)->require();

        return array_merge([
            'name' => $organisation->name,
            'privacy_contact_name' => 'Priya Nair',
            'privacy_contact_email' => 'privacy@example.test',
        ], $overrides);
    }

    #[Test]
    public function the_backfill_moves_the_old_value_into_the_name(): void
    {
        /*
         * Re-run the alter migration against a row that has the old value and
         * not the new one, which is the state every existing production row
         * was in when this shipped.
         */
        $organisation = app(OrganisationContext::class)->require();

        DB::table('organisations')->where('id', $organisation->getKey())->update([
            'privacy_contact' => 'Priya Nair, Data Protection Officer',
            'privacy_contact_name' => null,
            'privacy_contact_email' => null,
        ]);

        /* Put the schema back into the state the migration expects to find:
         * the old column present, the four new ones absent. */
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_contact_name',
                'privacy_contact_email',
                'privacy_contact_phone',
                'privacy_contact_role',
            ]);
        });

        DB::table('migrations')
            ->where('migration', '2026_08_27_090300_add_structured_privacy_contact_to_organisations_table')
            ->delete();

        Artisan::call('migrate', ['--force' => true]);

        $organisation->refresh();

        $this->assertSame('Priya Nair, Data Protection Officer', $organisation->privacy_contact_name);

        /* The original is KEPT. It is the source the backfill came from, and
         * dropping it in the same release would leave nothing to compare
         * against if the backfill took the wrong thing. */
        $this->assertSame('Priya Nair, Data Protection Officer', $organisation->privacy_contact);

        /* And NOTHING was guessed. No email was parsed out of the free text. */
        $this->assertNull($organisation->privacy_contact_email);
    }

    #[Test]
    public function the_screen_says_the_contact_is_incomplete_before_a_save_fails(): void
    {
        /*
         * Point 5. An organisation saved before this field was split up did
         * nothing wrong: the requirement is new. Meeting somebody with a red
         * error for a rule that did not exist when they last saved is how a
         * compliance improvement reads as a bug.
         */
        $organisation = app(OrganisationContext::class)->require();
        $organisation->forceFill([
            'privacy_contact' => 'Priya Nair',
            'privacy_contact_name' => null,
            'privacy_contact_email' => null,
        ])->save();

        $response = $this->actingAs($this->administrator())->get(route('admin.organisation'));

        $response->assertOk();
        $response->assertSee('The privacy contact is incomplete');
        /* And it quotes what IS recorded, so the reader can confirm it. */
        $response->assertSee('Priya Nair');
    }

    #[Test]
    public function the_warning_is_absent_once_the_contact_is_complete(): void
    {
        $organisation = app(OrganisationContext::class)->require();
        $organisation->forceFill([
            'privacy_contact_name' => 'Priya Nair',
            'privacy_contact_email' => 'privacy@example.test',
        ])->save();

        $this->actingAs($this->administrator())
            ->get(route('admin.organisation'))
            ->assertOk()
            ->assertDontSee('The privacy contact is incomplete');
    }

    #[Test]
    public function a_save_without_a_privacy_contact_name_is_refused(): void
    {
        $response = $this->actingAs($this->administrator())
            ->put(route('admin.organisation.update'), $this->wholeForm(['privacy_contact_name' => '']));

        $response->assertSessionHasErrors('privacy_contact_name');
    }

    #[Test]
    public function a_save_without_a_privacy_contact_email_is_refused(): void
    {
        $response = $this->actingAs($this->administrator())
            ->put(route('admin.organisation.update'), $this->wholeForm(['privacy_contact_email' => '']));

        $response->assertSessionHasErrors('privacy_contact_email');
    }

    #[Test]
    public function a_privacy_contact_email_that_is_not_an_address_is_refused(): void
    {
        /* The field exists so a data subject or a regulator can reach somebody.
         * A name in an email field defeats the whole point of splitting the
         * field up. */
        $response = $this->actingAs($this->administrator())
            ->put(route('admin.organisation.update'), $this->wholeForm([
                'privacy_contact_email' => 'Priya Nair',
            ]));

        $response->assertSessionHasErrors('privacy_contact_email');
    }

    #[Test]
    public function a_complete_contact_saves(): void
    {
        $this->actingAs($this->administrator())
            ->put(route('admin.organisation.update'), $this->wholeForm([
                'privacy_contact_phone' => '+65 6000 0000',
                'privacy_contact_role' => 'Data Protection Officer',
            ]))
            ->assertRedirect(route('admin.organisation'))
            ->assertSessionHasNoErrors();

        $organisation = Organisation::query()->first();

        $this->assertSame('Priya Nair', $organisation?->privacy_contact_name);
        $this->assertSame('privacy@example.test', $organisation?->privacy_contact_email);
        $this->assertSame('Data Protection Officer', $organisation?->privacy_contact_role);
    }

    #[Test]
    public function a_change_of_privacy_contact_is_audited_with_its_real_values(): void
    {
        /*
         * Point 4, and the redactor trap it could have walked into. Four field
         * names containing `privacy_contact` - none of which trips
         * `Redaction::isSensitiveKey()`, which is why the trail can quote them.
         * A field named `privacy_contact_key` would have recorded
         * "[redacted] -> [redacted]" for a compliance record. SEC-DEC-044.
         */
        $organisation = app(OrganisationContext::class)->require();
        $organisation->forceFill([
            'privacy_contact_name' => 'Old Contact',
            'privacy_contact_email' => 'old@example.test',
        ])->save();

        $this->actingAs($this->administrator())
            ->put(route('admin.organisation.update'), $this->wholeForm());

        $event = AuditEvent::query()->where('action', 'organisation.updated')->latest('id')->first();

        $this->assertNotNull($event);

        $before = (array) $event->before_summary;
        $after = (array) $event->after_summary;

        $this->assertSame('Old Contact', $before['privacy_contact_name'] ?? null);
        $this->assertSame('Priya Nair', $after['privacy_contact_name'] ?? null);
        $this->assertSame('privacy@example.test', $after['privacy_contact_email'] ?? null);
    }

    #[Test]
    public function the_legacy_free_text_field_cannot_be_overwritten_by_a_post(): void
    {
        /*
         * `privacy_contact` is the source the backfill read from and is kept so
         * that a wrong backfill can still be compared against the original. A
         * posted field that could overwrite it would destroy the one copy of
         * the thing it exists to preserve, so it is no longer accepted.
         */
        $organisation = app(OrganisationContext::class)->require();
        $organisation->forceFill(['privacy_contact' => 'The original value'])->save();

        $this->actingAs($this->administrator())
            ->put(route('admin.organisation.update'), $this->wholeForm([
                'privacy_contact' => 'Overwritten by a post',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('The original value', $organisation->refresh()->privacy_contact);
    }

    #[Test]
    public function an_organisation_nobody_edits_keeps_working(): void
    {
        /*
         * The whole reason the requirement is staged rather than retrospective.
         * The screen warns, the save enforces, and an organisation nobody
         * touches is not broken by a validation rule it never met.
         */
        $organisation = app(OrganisationContext::class)->require();
        $organisation->forceFill([
            'privacy_contact_name' => null,
            'privacy_contact_email' => null,
        ])->save();

        /* The signed-out check goes FIRST. `/sign-in` sits behind the `guest`
         * middleware, so asking for it while still authenticated redirects -
         * which says nothing about whether the screen works. */
        $this->get('/sign-in')->assertOk();

        /* Then one person for the authenticated pair. Switching identity
         * mid-session is not the scenario under test. */
        $actor = $this->administrator();

        $this->actingAs($actor)->get(route('admin.organisation'))->assertOk();
        $this->actingAs($actor)->get(route('home'))->assertOk();
    }
}
