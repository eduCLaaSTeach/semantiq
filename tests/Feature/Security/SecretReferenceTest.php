<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Security\Enums\SecretProvider;
use App\Modules\Security\Enums\SecretStatus;
use App\Modules\Security\Enums\SecretType;
use App\Modules\Security\Enums\SecurityStatus;
use App\Modules\Security\Exceptions\CredentialShapedValue;
use App\Modules\Security\Models\SecretReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ControlsSecurityPolicy;
use Tests\TestCase;

/**
 * ADM-012 Secret References.
 *
 * The rule the whole feature exists to keep is that a secret never reaches the
 * database, and the field it would arrive in is called "reference identifier".
 * These tests attack that field, and every other free-text field, from both
 * directions: through the form, and directly at the model where a console
 * command or a queued job would arrive.
 */
class SecretReferenceTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Fabric automation client secret',
            'reference_type' => SecretType::ClientSecret->value,
            'provider' => SecretProvider::AzureKeyVault->value,
            'reference_identifier' => 'kv-semantiq/fabric-automation',
            'purpose' => 'Used by the Fabric provisioning workflow.',
            'environment' => 'production',
            'expires_on' => Carbon::today()->addYear()->toDateString(),
            'rotation_due_on' => Carbon::today()->addMonths(10)->toDateString(),
        ], $overrides);
    }

    /* ---- The rule ------------------------------------------------------- */

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function credentialShapedValues(): array
    {
        return [
            'a bearer token' => ['Bearer abcdefghijklmnopqrstuvwxyz012345'],
            'a JSON web token' => ['eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.abcdef'],
            'an inline secret' => ['client_secret=Q~8fJ2kLmNoPqRsTuVwXyZ'],
            'a connection string with a password' => ['mysql://root:hunter2@db.internal/semantiq'],
        ];
    }

    #[Test]
    public function the_form_refuses_a_credential_shaped_identifier(): void
    {
        $admin = $this->admin();

        foreach (self::credentialShapedValues() as $label => [$value]) {
            $this->actingAs($admin)
                ->withSession($this->confirmedIdentity())
                ->from(route('admin.security.secrets.create'))
                ->post(route('admin.security.secrets.store'), $this->payload([
                    'reference_identifier' => $value,
                ]))
                ->assertSessionHasErrors('reference_identifier');

            $this->assertSame(0, SecretReference::query()->count(), $label.' was stored.');
        }
    }

    #[Test]
    public function the_form_refuses_a_credential_pasted_into_any_other_field(): void
    {
        // Somebody pasting a client secret pastes it into whichever box they
        // are looking at, and a leaked secret in a "purpose" column has leaked
        // just as thoroughly.
        $admin = $this->admin();

        foreach (['name', 'purpose', 'environment'] as $field) {
            $this->actingAs($admin)
                ->withSession($this->confirmedIdentity())
                ->from(route('admin.security.secrets.create'))
                ->post(route('admin.security.secrets.store'), $this->payload([
                    $field => 'password=hunter2-and-a-long-tail-of-entropy',
                ]))
                ->assertSessionHasErrors($field);

            $this->assertSame(0, SecretReference::query()->count(), $field.' accepted a credential.');
        }
    }

    #[Test]
    public function the_model_refuses_one_even_when_no_request_is_involved(): void
    {
        // The layer that covers a console command, a seeder and a queued job.
        // "We validated it at the boundary" is only true of the boundaries
        // somebody remembered.
        $this->actingAs($this->admin());

        $this->expectException(CredentialShapedValue::class);

        SecretReference::query()->create([
            'name' => 'Direct write',
            'reference_type' => SecretType::ClientSecret,
            'provider' => SecretProvider::AzureKeyVault,
            'reference_identifier' => 'Bearer abcdefghijklmnopqrstuvwxyz012345',
            'purpose' => 'Bypassing the form.',
            'environment' => 'production',
        ]);
    }

    #[Test]
    public function the_refusal_never_quotes_the_value_it_refused(): void
    {
        // Echoing it back would write the credential into a validation bag, a
        // rendered page and possibly a log - the exact outcome the check exists
        // to prevent.
        $admin = $this->admin();
        $secret = 'Bearer zzzzzzzzzzzzzzzzzzzzzzzzzz999';

        $response = $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->from(route('admin.security.secrets.create'))
            ->post(route('admin.security.secrets.store'), $this->payload([
                'reference_identifier' => $secret,
            ]));

        $response->assertSessionHasErrors('reference_identifier');

        $page = $this->actingAs($admin)->get(route('admin.security.secrets.create'));
        $page->assertDontSee('zzzzzzzzzzzzzzzzzzzzzzzzzz999');
    }

    #[Test]
    public function an_ordinary_pointer_is_accepted(): void
    {
        // The guard must refuse a credential, not the feature. Without this the
        // suite would pass just as well with the whole screen broken.
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->post(route('admin.security.secrets.store'), $this->payload())
            ->assertRedirect(route('admin.security.secrets'));

        $reference = SecretReference::query()->firstOrFail();

        $this->assertSame('kv-semantiq/fabric-automation', $reference->reference_identifier);
        $this->assertSame(SecretType::ClientSecret, $reference->reference_type);
    }

    /* ---- The lifecycle -------------------------------------------------- */

    #[Test]
    public function status_is_derived_from_the_dates_rather_than_stored(): void
    {
        // A status somebody can set independently of the dates goes stale the
        // moment nobody remembers to update it, and a stale "Active" beside a
        // lapsed certificate is worse than no status at all.
        $today = Carbon::parse('2026-08-23');

        $this->assertSame(
            SecretStatus::Expired,
            SecretStatus::derive($today->copy()->subDay(), null, null, $today),
        );

        $this->assertSame(
            SecretStatus::ExpiringSoon,
            SecretStatus::derive($today->copy()->addDays(10), null, null, $today),
        );

        $this->assertSame(
            SecretStatus::RotationDue,
            SecretStatus::derive($today->copy()->addYear(), $today->copy()->subDay(), null, $today),
        );

        $this->assertSame(
            SecretStatus::Active,
            SecretStatus::derive($today->copy()->addYear(), $today->copy()->addMonths(6), null, $today),
        );

        // Retired beats everything, whatever the dates say.
        $this->assertSame(
            SecretStatus::Retired,
            SecretStatus::derive($today->copy()->subYear(), null, $today, $today),
        );
    }

    #[Test]
    public function a_reference_with_no_dates_is_unknown_rather_than_active(): void
    {
        // Gate 3 rule 9 applied to a credential: one nobody gave an expiry to
        // is one nobody is tracking, and calling that healthy is the false
        // green the whole screen exists to avoid.
        $status = SecretStatus::derive(null, null, null, Carbon::parse('2026-08-23'));

        $this->assertSame(SecretStatus::Unknown, $status);
        $this->assertSame(
            SecurityStatus::NotVerified,
            $status->overviewStatus(),
        );
    }

    #[Test]
    public function a_reference_is_retired_and_never_deleted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->post(route('admin.security.secrets.store'), $this->payload());

        $reference = SecretReference::query()->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->post(route('admin.security.secrets.retire', $reference))
            ->assertRedirect(route('admin.security.secrets'));

        // The row survives, because a credential that used to exist is part of
        // the history an incident review reads.
        $this->assertSame(1, SecretReference::query()->count());
        $this->assertTrue($reference->refresh()->isRetired());
        $this->assertSame(SecretStatus::Retired, $reference->status());
    }

    #[Test]
    public function there_is_no_delete_route_at_all(): void
    {
        $names = collect(Route::getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter()
            ->filter(fn (string $name): bool => str_starts_with($name, 'admin.security.secrets'))
            ->values()
            ->all();

        $this->assertNotContains('admin.security.secrets.destroy', $names);
    }

    #[Test]
    public function rotation_after_expiry_is_refused(): void
    {
        // A reminder that arrives once the credential has already stopped
        // working is the one time it is useless.
        $this->actingAs($this->admin())
            ->withSession($this->confirmedIdentity())
            ->from(route('admin.security.secrets.create'))
            ->post(route('admin.security.secrets.store'), $this->payload([
                'expires_on' => Carbon::today()->addMonth()->toDateString(),
                'rotation_due_on' => Carbon::today()->addMonths(2)->toDateString(),
            ]))
            ->assertSessionHasErrors('rotation_due_on');
    }

    /* ---- The trail ------------------------------------------------------ */

    #[Test]
    public function creating_and_changing_a_reference_is_audited_readably(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->post(route('admin.security.secrets.store'), $this->payload());

        $created = AuditEvent::query()->where('action', 'security.secret_reference.created')->firstOrFail();

        // `reference_type` rather than `secret_type`: a key containing "secret"
        // would be replaced by the audit redactor, and the trail would say
        // "[redacted] -> [redacted]" for a change between two public words.
        // SEC-DEC-044.
        $this->assertSame('client_secret', $created->after_summary['reference_type']);
        $this->assertSame('kv-semantiq/fabric-automation', $created->after_summary['reference_identifier']);

        $reference = SecretReference::query()->firstOrFail();

        $this->actingAs($admin)
            ->withSession($this->confirmedIdentity())
            ->put(route('admin.security.secrets.update', $reference), $this->payload([
                'reference_identifier' => 'kv-semantiq/fabric-automation-v2',
            ]));

        $updated = AuditEvent::query()->where('action', 'security.secret_reference.updated')->firstOrFail();

        $this->assertSame('kv-semantiq/fabric-automation', $updated->before_summary['reference_identifier']);
        $this->assertSame('kv-semantiq/fabric-automation-v2', $updated->after_summary['reference_identifier']);
    }

    /* ---- Tenancy and access -------------------------------------------- */

    #[Test]
    public function one_organisation_cannot_see_anothers_references(): void
    {
        $ours = app(OrganisationContext::class)->require();
        $admin = $this->admin();

        $other = Organisation::query()->forceCreate([
            'code' => 'OTHER', 'name' => 'Other Customer', 'status' => 'active', 'version' => 1,
        ]);

        SecretReference::query()->forceCreate([
            'organisation_id' => $other->getKey(),
            'name' => 'Their Key Vault secret',
            'reference_type' => SecretType::ClientSecret->value,
            'provider' => SecretProvider::AzureKeyVault->value,
            'reference_identifier' => 'their-vault/their-secret',
            'purpose' => 'Not ours.',
            'environment' => 'production',
        ]);

        app(OrganisationContext::class)->forget();
        app(OrganisationContext::class)->bind($ours);

        $this->actingAs($admin)
            ->get(route('admin.security.secrets'))
            ->assertOk()
            ->assertDontSee('Their Key Vault secret')
            ->assertDontSee('their-vault/their-secret');

        $this->assertSame(0, SecretReference::query()->count());
    }

    #[Test]
    public function an_administrator_below_system_administrator_cannot_reach_the_screen(): void
    {
        // The credential map is a targeting list. Reading it is not the
        // harmless half, so `admin.secrets.view` sits as high as the write.
        $person = User::query()->create(['name' => 'Adam Admin', 'email' => 'adam@example.test']);
        $person->forceFill([
            'role' => Role::Admin,
            'organisation_id' => app(OrganisationContext::class)->require()->getKey(),
        ])->save();

        $this->actingAs($person->refresh())
            ->get(route('admin.security.secrets'))
            ->assertForbidden();
    }

    #[Test]
    public function the_empty_state_says_what_the_screen_is_for(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.security.secrets'))
            ->assertOk()
            ->assertSee('No secret references yet')
            ->assertSee('No credential value is ever stored here');
    }
}
