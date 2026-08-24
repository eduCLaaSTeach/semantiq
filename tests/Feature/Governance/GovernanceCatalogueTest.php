<?php

declare(strict_types=1);

namespace Tests\Feature\Governance;

use App\Modules\Audit\Support\Redaction;
use App\Modules\Governance\Enums\DataClassification;
use App\Modules\Governance\Enums\ProfileStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Structural tests over the gate 4 catalogue.
 *
 * These do not exercise a feature. They assert properties of the wiring that a
 * reviewer would otherwise have to hold in their head, and that would silently
 * stop holding the next time somebody adds a field.
 *
 * THE MOST IMPORTANT ONE IS THE REDACTOR TEST. `Redaction::isSensitiveKey()`
 * matches fragments anywhere in a name - `auth`, `cert`, `key`, `secret`,
 * `session`, `private` and a dozen more - and the same list drives the audit
 * summary. A governance field stored under a matched name would record every
 * change as "[redacted] -> [redacted]", degrading the trail for precisely the
 * settings an auditor comes looking for. Gate 3 shipped that defect twice and
 * both times a test caught it, not a review. SEC-DEC-044.
 */
class GovernanceCatalogueTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every column, config key and audit-summary key this gate introduces.
     *
     * Listed explicitly rather than reflected off the schema, because the point
     * is to catch a NEW name being added without thought - and a reflected list
     * would happily include the new name and assert nothing about it.
     *
     * @return list<string>
     */
    private function everyGovernanceKey(): array
    {
        return [
            /* data_protection_profiles */
            'applicable_regime', 'regime_basis', 'privacy_officer_designated',
            'breach_notification_due_days', 'breach_notification_basis', 'notes',
            'approved_at', 'superseded_at', 'version', 'status',

            /* data_sovereignty_profiles */
            'storage_geography', 'processing_geography', 'ai_processing_geography',
            'backup_geography', 'approved_geographies', 'external_replication',
            'cross_geo_storage', 'cross_geo_processing', 'cross_geo_ai',
            'cross_geo_conversation_history', 'source_note', 'evidence_reference',
            'crosses_a_border', 'replaced_by_version', 'copied_from_version',

            /* personal_data_categories */
            'code', 'name', 'description', 'classification', 'contains_sensitive',
            'source_tables', 'category_count',

            /* organisations, the structured privacy contact */
            'privacy_contact_name', 'privacy_contact_email',
            'privacy_contact_phone', 'privacy_contact_role',
        ];
    }

    #[Test]
    public function every_governance_key_survives_the_audit_redactor(): void
    {
        foreach ($this->everyGovernanceKey() as $key) {
            $this->assertFalse(
                Redaction::isSensitiveKey($key),
                "The governance key `{$key}` trips the audit redactor, so every change to it would be "
                .'recorded as "[redacted]" instead of its real value. Rename it. See SEC-DEC-044.'
            );
        }
    }

    #[Test]
    public function the_three_banned_names_would_have_been_caught(): void
    {
        /*
         * The traps this gate walked into and out of, asserted so the reasoning
         * in the catalogue comment is demonstrated rather than merely claimed.
         * If any of these ever stops tripping the redactor, the comments
         * explaining the odd names become wrong and should be revisited.
         */
        $this->assertTrue(Redaction::isSensitiveKey('authorised_geographies'));
        $this->assertTrue(Redaction::isSensitiveKey('authorized_regions'));
        $this->assertTrue(Redaction::isSensitiveKey('certification_reference'));
        $this->assertTrue(Redaction::isSensitiveKey('subject_key'));

        /* And the names actually used do not. */
        $this->assertFalse(Redaction::isSensitiveKey('approved_geographies'));
        $this->assertFalse(Redaction::isSensitiveKey('evidence_reference'));
        $this->assertFalse(Redaction::isSensitiveKey('subject_reference'));
    }

    #[Test]
    public function the_catalogue_declares_no_lawful_basis_and_no_retention_period(): void
    {
        /*
         * The standing rule from the approved decisions: engineering ships
         * configurable fields and does not invent compliance values. A default
         * appearing here would be a compliance claim nobody made.
         */
        $this->assertNull(config('governance.regime.basis_default'));
        $this->assertNull(config('governance.breach_notification.basis_default'));

        foreach ((array) config('governance.personal_data_categories') as $category) {
            $this->assertArrayNotHasKey('retention_period_months', $category);
            $this->assertArrayNotHasKey('lawful_basis', $category);
        }
    }

    #[Test]
    public function the_breach_deadline_default_is_three_days_and_is_configurable(): void
    {
        /* Decision D7: the figure is accepted for implementation. */
        $this->assertSame(3, config('governance.breach_notification.due_days_default'));
        $this->assertContains('integer', (array) config('governance.breach_notification.due_days_rules'));
    }

    #[Test]
    public function every_cross_geo_switch_is_seeded_off(): void
    {
        /* CLAUDE.md: cross-geo storage, processing and AI or conversation
         * history default OFF. Asserted at the catalogue as well as at the
         * database default, because the seed writes through the catalogue. */
        $seed = (array) config('governance.sovereignty_seed');

        foreach ([
            'cross_geo_storage',
            'cross_geo_processing',
            'cross_geo_ai',
            'cross_geo_conversation_history',
        ] as $switch) {
            $this->assertFalse($seed[$switch], "The seed turns `{$switch}` on. It must ship off.");
        }
    }

    #[Test]
    public function the_seed_matches_the_confirmed_production_facts(): void
    {
        /*
         * SEC-DEC-036 records three separately confirmed facts: server
         * Singapore, backups Singapore, no replication outside Singapore. If
         * the seed drifts from them it stops being a record of what was
         * confirmed and becomes a guess with a citation.
         */
        $seed = (array) config('governance.sovereignty_seed');

        $this->assertSame('sg', $seed['storage_geography']);
        $this->assertSame('sg', $seed['backup_geography']);
        $this->assertSame('none', $seed['external_replication']);
        $this->assertStringContainsString('SEC-DEC-036', $seed['source_note']);
        $this->assertStringContainsString('DRAFT', $seed['source_note']);
    }

    #[Test]
    public function every_seeded_category_names_at_least_one_table(): void
    {
        /*
         * A category claiming no table is invisible to the R1.4c coverage test
         * and would silently exclude its data from a subject access response.
         */
        foreach ((array) config('governance.personal_data_categories') as $category) {
            $this->assertNotEmpty(
                $category['tables'] ?? [],
                "The category `{$category['code']}` names no table, so nothing would ever be collected "
                .'for it.'
            );
            $this->assertInstanceOf(DataClassification::class, $category['classification']);
        }
    }

    #[Test]
    public function every_badge_class_the_enums_return_exists_in_the_stylesheet(): void
    {
        /*
         * A badge class the design system does not define renders as an
         * unstyled pill, which nobody notices in review and everybody notices
         * in production. The enums return WHOLE class strings for this reason,
         * and this asserts each one is real.
         */
        $css = file_get_contents(base_path('resources/css/app.css'));

        $classes = array_merge(
            array_map(static fn (DataClassification $c): string => $c->badge(), DataClassification::cases()),
            array_map(static fn (ProfileStatus $s): string => $s->badge(), ProfileStatus::cases()),
        );

        foreach ($classes as $class) {
            foreach (explode(' ', $class) as $single) {
                $this->assertStringContainsString(
                    '.'.$single,
                    $css,
                    "The badge class `{$single}` is not defined in the stylesheet."
                );
            }
        }
    }

    #[Test]
    public function the_warning_alert_variant_is_defined(): void
    {
        /*
         * It was NOT, while seven screens already used it - and the base
         * `.alert` defaults to the danger palette, so every advisory on those
         * screens rendered red. An advisory that reads as a failure is either
         * ignored or panicked over, and neither is what it was written for.
         */
        $css = file_get_contents(base_path('resources/css/app.css'));

        $this->assertStringContainsString('.alert-warning', $css);
    }
}
