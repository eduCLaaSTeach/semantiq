<?php

declare(strict_types=1);

namespace App\Modules\Administration\Support;

/**
 * THE READINESS VOCABULARY, AND IT IS EXACTLY THE APPROVED ONE.
 *
 * Organisation reads Configured / Not configured (D-179, and the PLAN's own
 * words). Business Domains reads Ready / Needs attention / Not configured
 * (D-181). There is no fourth word and no fifth.
 *
 * WHY READINESS IS NOT A HealthStatus. A health status answers "is this
 * service working" - a check ran, or nobody looked. An organisation is not
 * degraded and has not failed a check; it either exists or has not been created
 * yet. Rendering that as "Available" would be the implementation's vocabulary
 * reaching a user-facing surface, which is the last item on the
 * professional-polish gate.
 *
 * WHERE THE TWO VOCABULARIES OVERLAP, THE WORDS ARE IDENTICAL. "Needs
 * attention" and "Not configured" mean here exactly what HealthStatusBadge
 * makes them mean, because one deployment gets one word per status - the rule
 * OneStatusVocabularyTest already holds between the badge and Integrations.
 * AdministrationHomeIsAProjectionTest extends it to this enum, so the same word
 * cannot come to mean two things two inches apart on one screen.
 *
 * `tone` IS THE EXISTING sys-status CLASS SUFFIX, so this adds no colour
 * system: Configured and Ready wear what Available wears, Needs attention
 * wears what Degraded wears, Not configured wears its own neutral. No new
 * token, no new palette, no fourth tone.
 */
enum Readiness: string
{
    case Configured = 'configured';
    case Ready = 'ready';
    case NeedsAttention = 'needs_attention';
    case NotConfigured = 'not_configured';

    /** What a person reads. Never the case name, never the value. */
    public function inWords(): string
    {
        return match ($this) {
            self::Configured => 'Configured',
            self::Ready => 'Ready',
            self::NeedsAttention => 'Needs attention',
            self::NotConfigured => 'Not configured',
        };
    }

    /**
     * The shared visual treatment this reading borrows.
     *
     * DELIBERATELY NOT A COLOUR. It names an existing status class so the
     * stylesheet stays the one place colour is decided, and so a reading can
     * never acquire a shade nothing else on the product uses.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Configured, self::Ready => 'available',
            self::NeedsAttention => 'degraded',
            self::NotConfigured => 'not_configured',
        };
    }
}
