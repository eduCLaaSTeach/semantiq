<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\Identity\Health\IdentityHealthReport;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;

/**
 * P1-02's nine health rows, mapped into P1-06's five states.
 *
 * IT CALLS report(), NEVER recheck(). P1-02's own docblock says "Rendering this
 * NEVER touches the network by choice: it reads the cache and the stored probe
 * result." Calling recheck() here would make opening a posture screen probe
 * Microsoft, which is exactly what D-81 forbids. N-SS37 asserts it.
 *
 * IT NEVER CALLS IdentityHealthReport::state(). P1-02's aggregate treats
 * NotChecked as contributing nothing, which is right for its screen and fatal
 * here. This adapter maps ROW states and lets Aggregation do the rest.
 *
 * THE CLIENT SECRET IS EVIDENCE BY PRESENCE ONLY. P1-02's own screen has a
 * reveal POST; this adapter reads the row's state and finding and never touches
 * the configuration value. There is no reveal anywhere in P1-06.
 */
final class IdentityAdapter implements SourceAdapter
{
    /**
     * Which P1-02 row backs which catalogued control.
     *
     * B-1 takes THREE rows, because "everybody signs in with Microsoft" is only
     * true if the provider is configured, its configuration is valid and the
     * trust material is actually available. Reporting it from one row would let
     * two thirds of the control fail silently.
     */
    private const ROWS = [
        ControlCatalogue::IDENTITY_TRUST => ['provider_configured', 'configuration_valid', 'identity_trust', 'client_secret', 'directory_identity'],
        ControlCatalogue::APPROVED_PROVIDERS => ['approved_providers'],
        ControlCatalogue::SIGN_IN_REACHABLE => ['microsoft_reachable'],
        ControlCatalogue::SESSION_POLICY => ['session_policy'],
    ];

    public function __construct(private readonly IdentityHealthCheck $health) {}

    public function answers(): array
    {
        return array_keys(self::ROWS);
    }

    public function evidence(): array
    {
        $report = $this->health->report();

        /** @var array<string, array{key: string, label: string, state: string, finding: string, action: string|null}> $byKey */
        $byKey = [];

        foreach ($report->checks as $check) {
            $byKey[$check['key']] = $check;
        }

        $evidence = [];

        foreach (self::ROWS as $control => $keys) {
            $evidence[] = $this->fromRows($control, $keys, $byKey);
        }

        return $evidence;
    }

    /**
     * @param  list<string>  $keys
     * @param  array<string, array{key: string, label: string, state: string, finding: string, action: string|null}>  $byKey
     */
    private function fromRows(string $control, array $keys, array $byKey): Evidence
    {
        $states = [];
        $findings = [];

        foreach ($keys as $key) {
            if (! array_key_exists($key, $byKey)) {
                // A row P1-06 expects and P1-02 did not produce. Unverified,
                // never Healthy - and the control still renders.
                return Evidence::unavailable(
                    $control,
                    'Sign-in reported fewer checks than expected.'
                );
            }

            $states[] = $this->map($byKey[$key]['state']);
            $findings[] = $byKey[$key]['finding'];
        }

        // The worst row wins WITHIN one control, using the same applicable
        // precedence as the aggregate - so a control made of several rows
        // cannot report better than its weakest part.
        foreach ([PostureState::Critical, PostureState::Attention, PostureState::Unverified] as $state) {
            $index = array_search($state, $states, true);

            if ($index !== false) {
                return Evidence::state($control, $state, $findings[$index]);
            }
        }

        return Evidence::state($control, PostureState::Healthy, $findings[0]);
    }

    /**
     * P1-02's four row states into P1-06's five. THE DIVERGENCE IS NOT_CHECKED.
     *
     * P1-02 lets it contribute nothing. Here it is Unverified and contributes
     * fully, because nothing was measured and so nothing may be claimed.
     */
    private function map(string $rowState): PostureState
    {
        return match ($rowState) {
            IdentityHealthReport::FAILED => PostureState::Critical,
            IdentityHealthReport::DEGRADED => PostureState::Attention,
            IdentityHealthReport::HEALTHY => PostureState::Healthy,
            IdentityHealthReport::NOT_CHECKED => PostureState::Unverified,

            // A stored value this code does not recognise fails CLOSED, and
            // records NOTHING. N-SS8 breaks it with a match that has no default.
            default => PostureState::Unverified,
        };
    }
}
