<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The IANA time zone list, grouped and labelled for a select.
 *
 * BUILT FROM PHP AT RUNTIME, never copied into this file. That is the whole
 * point: the identifier set belongs to PHP's bundled tzdata and changes when a
 * country changes its rules, so a hand-maintained list would be wrong within a
 * release or two and nobody would notice until somebody's dates were an hour
 * out.
 *
 * The original field was free text validated with Laravel's `timezone` rule.
 * That was correct and unhelpful: it accepted `Asia/Singapore` and rejected
 * `Asia/Singpore` with no way for the administrator to tell which spelling was
 * wanted. A select removes the class of error entirely rather than reporting it.
 *
 * The offset is shown alongside each name because "Asia/Singapore" means
 * nothing to most people and "UTC+08:00" means something to everyone. It is the
 * offset in force RIGHT NOW - a zone observing daylight saving will show a
 * different one in six months, which is correct, because that is what the zone
 * is actually doing today.
 */
class TimeZones
{
    /** Memoised. Building the list walks ~400 zones and constructs a date for each. */
    private static ?array $grouped = null;

    /**
     * Every identifier, grouped by region for `<optgroup>`.
     *
     * Regions come first in the order an administrator is most likely to want
     * them for this product - Asia leads, because the deployment baseline is a
     * Singapore-hosted instance - and the rest follow alphabetically. UTC sits
     * on its own at the top, since it is the default and is not a region.
     *
     * @return array<string, array<string, string>> region => [identifier => label]
     */
    public static function grouped(): array
    {
        if (self::$grouped !== null) {
            return self::$grouped;
        }

        $now = new DateTimeImmutable('now');
        $regions = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $region = str_contains($identifier, '/')
                ? explode('/', $identifier)[0]
                : 'Other';

            /* UTC is the default and is not a place. It gets its own group so it
             * is the first thing in the list rather than buried under "Other". */
            if ($identifier === 'UTC') {
                $region = 'Coordinated Universal Time';
            }

            $regions[$region][$identifier] = self::label($identifier, $now);
        }

        foreach ($regions as $region => $zones) {
            asort($regions[$region]);
        }

        $regions = self::inPreferredOrder($regions);

        return self::$grouped = $regions;
    }

    /**
     * Whether an identifier is one PHP recognises.
     *
     * Used by the validation layer as well as the select, so a crafted post
     * carrying an unlisted identifier is refused by the same authority that
     * built the list.
     */
    public static function isValid(string $identifier): bool
    {
        return in_array($identifier, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * "Singapore (UTC+08:00)" from "Asia/Singapore".
     *
     * The city, not the whole path, because the region is already the group
     * heading and repeating it in every option makes the list unreadable.
     */
    private static function label(string $identifier, DateTimeImmutable $now): string
    {
        $city = str_contains($identifier, '/')
            ? substr($identifier, strrpos($identifier, '/') + 1)
            : $identifier;

        $city = str_replace('_', ' ', $city);

        return $city.' ('.self::offset($identifier, $now).')';
    }

    /**
     * The offset in force right now, as UTC+HH:MM.
     */
    private static function offset(string $identifier, DateTimeImmutable $now): string
    {
        $seconds = (new DateTimeZone($identifier))->getOffset($now);

        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('UTC%s%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    /**
     * UTC first, then Asia, then everything else alphabetically.
     *
     * @param  array<string, array<string, string>>  $regions
     * @return array<string, array<string, string>>
     */
    private static function inPreferredOrder(array $regions): array
    {
        $ordered = [];

        foreach (['Coordinated Universal Time', 'Asia'] as $first) {
            if (isset($regions[$first])) {
                $ordered[$first] = $regions[$first];
                unset($regions[$first]);
            }
        }

        ksort($regions);

        return $ordered + $regions;
    }
}
