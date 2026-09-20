<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Setup\IntegrationFamily;
use Tests\TestCase;

/**
 * EVERY INTEGRATION FAMILY DECLARES EXACTLY ONE SECRET, and the D-159
 * confirmation path depends on it.
 *
 * IntegrationController::confirmThroughMicrosoft() stages ONE staged change per
 * step-up, because one confirmation authorises one exact credential - that is
 * the property the whole two-stage design rests on. It therefore takes the
 * first entry of the replacement set.
 *
 * WHICH IS CORRECT ONLY WHILE THIS IS TRUE. A family with two secrets would
 * have its second one silently dropped: the administrator types two
 * credentials, confirms with Microsoft, and one is simply never saved -
 * discoverable at the next connection test, and attributable to nothing.
 *
 * The controller now REFUSES that submission rather than dropping half of it,
 * so the failure is loud either way. This test is the other half: it makes the
 * day a family gains a second secret a red build, at the moment somebody adds
 * it, rather than a refusal a user meets later.
 *
 * Mutation: give any family a second secret. This fails, and names it.
 */
final class OneSecretPerFamilyTest extends TestCase
{
    public function test_every_family_declares_exactly_one_secret(): void
    {
        foreach (IntegrationFamily::cases() as $family) {
            $this->assertCount(
                1,
                $family->secrets(),
                "[{$family->value}] declares ".count($family->secrets()).' secrets. The D-159 '
                .'confirmation stages ONE credential per step-up, so a second one would be '
                .'refused at the point of saving. Give the staging path a way to carry more than '
                .'one - or split the family - before adding it.',
            );
        }
    }

    /** ...and the set of families has not quietly grown either. */
    public function test_the_families_are_exactly_these_four(): void
    {
        $this->assertSame(
            ['identity', 'email', 'ai', 'fabric'],
            array_map(
                static fn (IntegrationFamily $f): string => $f->value,
                IntegrationFamily::cases(),
            ),
        );
    }
}
