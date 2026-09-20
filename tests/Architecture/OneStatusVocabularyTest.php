<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Setup\Support\IntegrationView;
use App\Modules\SystemHealth\Report\HealthStatus;
use PHPUnit\Framework\TestCase;

/**
 * ONE DEPLOYMENT, ONE WORD PER STATUS.
 *
 * P1-09's HealthStatusBadge already names every status. The first draft of
 * IntegrationView invented a second set - "Working", "Slow to answer", "Not
 * working" - which read perfectly well and meant the SAME status appeared as
 * "Working" in the setup step list and "Available" in the badge two inches
 * away. A customer would reasonably ask which was right, and there would be no
 * answer.
 *
 * This reads the WORDS map out of the component source rather than restating
 * it, so the assertion is about the two artefacts and not about a third copy
 * that could drift from both.
 *
 * Mutation: change one word in either place.
 */
final class OneStatusVocabularyTest extends TestCase
{
    public function test_the_server_and_the_badge_use_the_same_words(): void
    {
        $source = (string) file_get_contents(
            __DIR__.'/../../resources/js/Components/HealthStatusBadge.jsx',
        );

        $start = strpos($source, 'const WORDS = {');
        $this->assertNotFalse($start, 'HealthStatusBadge no longer declares a WORDS map.');

        $end = strpos($source, '}', $start);
        $block = substr($source, $start, $end - $start);

        preg_match_all("/([a-z_]+):\s*'([^']+)'/", $block, $matches, PREG_SET_ORDER);

        $badge = [];

        foreach ($matches as $match) {
            $badge[$match[1]] = $match[2];
        }

        $this->assertCount(6, $badge, 'The badge no longer names all six statuses.');

        foreach (HealthStatus::cases() as $status) {
            $this->assertArrayHasKey($status->value, $badge,
                "The badge has no word for [{$status->value}], so it would render a fallback.");

            $this->assertSame(
                $badge[$status->value],
                IntegrationView::statusInWords($status),
                "[{$status->value}] reads as \"{$badge[$status->value]}\" in the badge and \""
                .IntegrationView::statusInWords($status).'" on the integrations surface. One '
                .'deployment, one status, two vocabularies.',
            );
        }
    }
}
