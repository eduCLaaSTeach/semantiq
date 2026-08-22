<?php

declare(strict_types=1);

namespace Tests\Feature\Data;

use App\Enums\WorkflowStatus;
use App\Models\AuditEvent;
use App\Models\DataProtectionProfile;
use App\Models\FabricItem;
use App\Models\HelpTopic;
use App\Models\Organisation;
use App\Models\WorkflowRun;
use App\Support\Tenancy\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The behaviour the configuration data model is supposed to guarantee, as
 * opposed to the columns it happens to have.
 *
 * Three guarantees are load-bearing and each has a reason to be tested rather
 * than trusted:
 *
 *  - Audit rows cannot be edited or deleted through the model. A schema comment
 *    saying "append-only" is not a control.
 *  - A data protection profile that nobody has configured permits nothing. The
 *    dangerous version of this bug is silent: an unset geography read as
 *    unrestricted rather than as a refusal.
 *  - Publishing a new profile version leaves exactly one current version. The
 *    invariant is held by code, not by a unique index, so it needs proving.
 */
class ConfigurationDataModelTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        app(OrganisationContext::class)->set($this->organisation);
    }

    /* -- Workflow runs ---------------------------------------------------- */

    #[Test]
    public function a_workflow_run_mints_its_own_identifiers(): void
    {
        $run = WorkflowRun::factory()->create(['organisation_id' => $this->organisation->id]);

        $this->assertNotEmpty($run->workflow_run_uid);
        $this->assertNotEmpty($run->correlation_id, 'A run with no correlation ID cannot be traced');
        $this->assertNotSame($run->workflow_run_uid, $run->correlation_id);
    }

    #[Test]
    public function a_workflow_run_starts_not_started(): void
    {
        $run = new WorkflowRun(['workflow_type' => 'fabric.readiness_assessment']);
        $run->save();

        $this->assertSame(WorkflowStatus::NotStarted, $run->status);
        $this->assertSame(0, $run->attempts);
    }

    #[Test]
    public function orchestration_state_survives_a_round_trip(): void
    {
        $run = WorkflowRun::factory()->create([
            'organisation_id' => $this->organisation->id,
            'state' => ['completed_steps' => ['check_capacity'], 'operation_url' => 'https://api.fabric.microsoft.com/v1/operations/abc'],
        ]);

        // What a resumed run reads to avoid repeating work it already did.
        $this->assertSame(['check_capacity'], $run->fresh()->stateValue('completed_steps'));
        $this->assertNull($run->fresh()->stateValue('missing_key'));
    }

    #[Test]
    public function the_active_and_awaiting_scopes_select_what_they_claim(): void
    {
        WorkflowRun::factory()->create(['organisation_id' => $this->organisation->id, 'status' => WorkflowStatus::InProgress]);
        WorkflowRun::factory()->create(['organisation_id' => $this->organisation->id, 'status' => WorkflowStatus::ApprovalRequired]);
        WorkflowRun::factory()->create(['organisation_id' => $this->organisation->id, 'status' => WorkflowStatus::Succeeded]);

        $this->assertSame(1, WorkflowRun::query()->active()->count());
        $this->assertSame(1, WorkflowRun::query()->awaitingPerson()->count());
    }

    /* -- Audit events ----------------------------------------------------- */

    #[Test]
    public function an_audit_event_cannot_be_updated(): void
    {
        $event = AuditEvent::factory()->create(['organisation_id' => $this->organisation->id]);

        $this->expectException(LogicException::class);

        $event->update(['action' => 'something.else']);
    }

    #[Test]
    public function an_audit_event_cannot_be_deleted(): void
    {
        $event = AuditEvent::factory()->create(['organisation_id' => $this->organisation->id]);

        try {
            $event->delete();
            $this->fail('An audit event was deleted through the model');
        } catch (LogicException) {
            // Expected.
        }

        $this->assertDatabaseHas('audit_events', ['id' => $event->id]);
    }

    #[Test]
    public function an_audit_event_records_who_acted_independently_of_the_user_row(): void
    {
        $event = AuditEvent::factory()->create([
            'organisation_id' => $this->organisation->id,
            'actor_label' => 'Salil Mhatre',
            'actor_user_id' => null,
        ]);

        // The label is written at the time of the event and no later deletion
        // can rewrite it, which is the whole point of denormalising it.
        $this->assertSame('Salil Mhatre', $event->fresh()->actor_label);
    }

    #[Test]
    public function an_audit_event_stamps_when_it_happened(): void
    {
        $event = AuditEvent::factory()->create(['organisation_id' => $this->organisation->id]);

        $this->assertNotNull($event->occurred_at);
        $this->assertNotEmpty($event->audit_uid);

        // No update timestamp exists, because no edit path exists.
        $this->assertArrayNotHasKey('updated_at', $event->fresh()->getAttributes());
    }

    /* -- Fabric items ----------------------------------------------------- */

    #[Test]
    public function a_fabric_item_is_unconfirmed_until_microsoft_has_been_seen_to_agree(): void
    {
        $confirmed = FabricItem::factory()->create(['organisation_id' => $this->organisation->id]);
        $pending = FabricItem::factory()->unconfirmed()->create(['organisation_id' => $this->organisation->id]);

        $this->assertTrue($confirmed->isConfirmed());
        $this->assertFalse($pending->isConfirmed());
    }

    #[Test]
    public function two_organisations_may_hold_the_same_fabric_item_identifier(): void
    {
        $other = Organisation::factory()->create();
        $itemId = '11111111-2222-3333-4444-555555555555';

        FabricItem::factory()->create(['organisation_id' => $this->organisation->id, 'item_id' => $itemId]);
        FabricItem::factory()->create(['organisation_id' => $other->id, 'item_id' => $itemId]);

        // Separate tenants are separate identifier spaces. A global unique index
        // here would let one customer's row block another customer's insert.
        $this->assertSame(2, app(OrganisationContext::class)->withoutScoping(
            fn (): int => FabricItem::query()->where('item_id', $itemId)->count()
        ));
    }

    /* -- Data protection profile ------------------------------------------ */

    #[Test]
    public function an_unconfigured_profile_permits_nothing(): void
    {
        $profile = DataProtectionProfile::factory()->create(['organisation_id' => $this->organisation->id]);

        $this->assertNull($profile->approved_storage_geographies);
        $this->assertNull($profile->approved_processing_geographies);
        $this->assertFalse($profile->cross_geo_processing_allowed);
        $this->assertFalse($profile->cross_geo_storage_allowed);
        $this->assertFalse($profile->conversation_history_outside_geo_allowed);
        $this->assertFalse($profile->public_internet_access_allowed);
        $this->assertFalse($profile->production_payload_logging);
        $this->assertFalse($profile->data_export_allowed);
        $this->assertFalse($profile->support_data_capture_allowed);
    }

    #[Test]
    public function the_model_defaults_match_what_the_database_stores(): void
    {
        // The model restates the migration's column defaults so a profile is
        // deny-by-default before it has been round-tripped. Two copies of the
        // same posture can drift, so this compares them directly.
        $profile = new DataProtectionProfile;
        $profile->forceFill(['organisation_id' => $this->organisation->id, 'version' => 1])->save();

        $stored = $profile->fresh()->getAttributes();

        foreach ((new DataProtectionProfile)->getAttributes() as $column => $default) {
            $this->assertEquals(
                $default,
                $stored[$column],
                $column.' differs between the model default and the column default'
            );
        }
    }

    #[Test]
    public function unset_geographies_are_a_refusal_not_an_absence_of_restrictions(): void
    {
        $unconfigured = DataProtectionProfile::factory()->create(['organisation_id' => $this->organisation->id]);
        $this->assertFalse($unconfigured->hasApprovedGeographies());

        $configured = DataProtectionProfile::factory()
            ->withApprovedGeographies()
            ->make(['organisation_id' => $this->organisation->id]);
        $this->assertTrue($configured->hasApprovedGeographies());

        // An emptied list reads the same as one never set. Both are gaps.
        $configured->approved_storage_geographies = [];
        $this->assertFalse($configured->hasApprovedGeographies());
    }

    #[Test]
    public function retention_defaults_come_from_policy_not_from_a_constant_in_code(): void
    {
        $profile = DataProtectionProfile::factory()->create(['organisation_id' => $this->organisation->id]);

        // Seven years for audit per the CLAUDE.md project baseline, ninety days
        // for operational metadata per the standard. Both overridable.
        $this->assertSame(2555, $profile->audit_retention_days);
        $this->assertSame(90, $profile->operational_retention_days);

        $overridden = DataProtectionProfile::publishFor($this->organisation, ['audit_retention_days' => 3650]);
        $this->assertSame(3650, $overridden->audit_retention_days);
    }

    #[Test]
    public function publishing_a_version_leaves_exactly_one_in_force(): void
    {
        $first = DataProtectionProfile::publishFor($this->organisation);
        $second = DataProtectionProfile::publishFor($this->organisation, ['data_export_allowed' => true]);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);

        $this->assertFalse($first->fresh()->is_current, 'The superseded version is still marked current');
        $this->assertTrue($second->fresh()->is_current);

        $this->assertSame(1, DataProtectionProfile::query()->where('is_current', true)->count());
        $this->assertSame($second->id, DataProtectionProfile::currentFor($this->organisation)->id);
    }

    #[Test]
    public function a_superseded_version_is_kept_rather_than_overwritten(): void
    {
        DataProtectionProfile::publishFor($this->organisation, ['notes' => 'Initial policy']);
        DataProtectionProfile::publishFor($this->organisation, ['notes' => 'Widened for the AI pilot']);

        // Which policy was in force when something was provisioned has to stay
        // answerable, and an updated row cannot answer it.
        $this->assertSame(2, DataProtectionProfile::query()->count());
        $this->assertSame('Initial policy', DataProtectionProfile::query()->where('version', 1)->value('notes'));
    }

    #[Test]
    public function an_organisation_with_no_profile_has_no_current_policy(): void
    {
        // Null is a real answer, and the sovereignty check reads it as a refusal.
        $this->assertNull(DataProtectionProfile::currentFor($this->organisation));
    }

    #[Test]
    public function support_data_capture_closes_when_its_window_expires(): void
    {
        $open = DataProtectionProfile::factory()->make([
            'support_data_capture_allowed' => true,
            'support_data_capture_expires_at' => now()->addDay(),
        ]);
        $expired = DataProtectionProfile::factory()->make([
            'support_data_capture_allowed' => true,
            'support_data_capture_expires_at' => now()->subDay(),
        ]);
        $noExpiry = DataProtectionProfile::factory()->make([
            'support_data_capture_allowed' => true,
            'support_data_capture_expires_at' => null,
        ]);

        $this->assertTrue($open->allowsSupportDataCapture());
        $this->assertFalse($expired->allowsSupportDataCapture(), 'An expired exception is still being honoured');
        $this->assertFalse($noExpiry->allowsSupportDataCapture(), 'A standing exception was allowed');
    }

    /* -- Help topics ------------------------------------------------------ */

    #[Test]
    public function only_published_topics_are_offered_to_a_reader(): void
    {
        HelpTopic::factory()->create(['topic_id' => 'HLP-TST-101']);
        HelpTopic::factory()->draft()->create(['topic_id' => 'HLP-TST-102']);

        $this->assertSame(['HLP-TST-101'], HelpTopic::query()->published()->pluck('topic_id')->all());
    }

    #[Test]
    public function a_microsoft_topic_that_has_never_been_reviewed_counts_as_stale(): void
    {
        $reviewed = HelpTopic::factory()->citingMicrosoft()->create(['topic_id' => 'HLP-TST-201']);
        $unreviewed = HelpTopic::factory()->create([
            'topic_id' => 'HLP-TST-202',
            'microsoft_reference' => 'https://learn.microsoft.com/fabric/',
            'last_reviewed_at' => null,
        ]);
        $old = HelpTopic::factory()->create([
            'topic_id' => 'HLP-TST-203',
            'microsoft_reference' => 'https://learn.microsoft.com/fabric/',
            'last_reviewed_at' => now()->subDays(400),
        ]);
        $noMicrosoftClaim = HelpTopic::factory()->create(['topic_id' => 'HLP-TST-204']);

        $this->assertFalse($reviewed->isStale());
        $this->assertTrue($unreviewed->isStale(), 'A missing review date was read as freshness');
        $this->assertTrue($old->isStale());
        $this->assertFalse($noMicrosoftClaim->isStale());
    }

    #[Test]
    public function the_help_topic_template_sections_are_all_storable(): void
    {
        // SRS section 15.1 is a contract: a topic missing "Who can do it" or the
        // Microsoft reference is incomplete, so every section needs a home.
        $topic = HelpTopic::factory()->create([
            'topic_id' => 'HLP-TST-301',
            'why_required' => 'why',
            'who_can_do_it' => 'who',
            'prerequisites' => 'prereq',
            'where_to_go' => 'where',
            'steps' => 'steps',
            'values_to_copy' => [['label' => 'Redirect URI', 'token' => '{app_base_url}/auth/microsoft/callback']],
            'security_note' => 'security',
            'expected_result' => 'expected',
            'verify_in_semantiq' => 'verify',
            'troubleshooting' => 'trouble',
            'microsoft_reference' => 'https://learn.microsoft.com/entra/',
            'last_reviewed_at' => now(),
        ])->fresh();

        foreach (['why_required', 'who_can_do_it', 'prerequisites', 'where_to_go', 'steps',
            'security_note', 'expected_result', 'verify_in_semantiq', 'troubleshooting'] as $section) {
            $this->assertNotNull($topic->{$section}, $section.' did not survive the round trip');
        }

        $this->assertSame('Redirect URI', $topic->values_to_copy[0]['label']);
    }
}
