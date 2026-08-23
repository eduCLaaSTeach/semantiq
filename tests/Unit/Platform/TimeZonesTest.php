<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Support\TimeZones;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The time zone list offered on the settings and organisation screens.
 *
 * The property worth guarding is that the list is DERIVED, not copied. A
 * hand-maintained list is wrong the next time a country changes its rules, and
 * nobody notices until somebody's dates are an hour out.
 */
class TimeZonesTest extends TestCase
{
    #[Test]
    public function the_list_is_derived_from_php_rather_than_copied(): void
    {
        $offered = [];

        foreach (TimeZones::grouped() as $zones) {
            $offered = array_merge($offered, array_keys($zones));
        }

        sort($offered);

        $fromPhp = DateTimeZone::listIdentifiers();
        sort($fromPhp);

        // Exactly PHP's set: nothing missing, nothing invented.
        $this->assertSame($fromPhp, $offered);
    }

    #[Test]
    public function utc_leads_and_asia_follows(): void
    {
        // UTC is the default and is not a place. Asia is next because the
        // deployment baseline is a Singapore-hosted instance, and a list that
        // opens on Africa makes every administrator scroll.
        $regions = array_keys(TimeZones::grouped());

        $this->assertSame('Coordinated Universal Time', $regions[0]);
        $this->assertSame('Asia', $regions[1]);
    }

    #[Test]
    public function a_label_carries_the_city_and_its_current_offset(): void
    {
        // "Asia/Singapore" means nothing to most people; "UTC+08:00" means
        // something to everyone.
        $label = TimeZones::grouped()['Asia']['Asia/Singapore'];

        $this->assertSame('Singapore (UTC+08:00)', $label);
    }

    #[Test]
    public function an_underscore_in_an_identifier_reads_as_a_space(): void
    {
        $this->assertStringStartsWith('Kuala Lumpur (', TimeZones::grouped()['Asia']['Asia/Kuala_Lumpur']);
    }

    #[Test]
    public function a_negative_offset_is_signed_correctly(): void
    {
        // The sign is easy to get wrong, and getting it wrong is invisible
        // until somebody in New York reads UTC+05:00.
        $this->assertMatchesRegularExpression(
            '/\(UTC-\d{2}:\d{2}\)$/',
            TimeZones::grouped()['America']['America/New_York'],
        );
    }

    #[Test]
    public function validity_is_answered_by_the_same_source_that_built_the_list(): void
    {
        // A select is not an authorization control, so the post is validated
        // too - and by the same authority, or the two could disagree.
        $this->assertTrue(TimeZones::isValid('Asia/Singapore'));
        $this->assertTrue(TimeZones::isValid('UTC'));
        $this->assertFalse(TimeZones::isValid('Asia/Singpore'));
        $this->assertFalse(TimeZones::isValid(''));
    }
}
