<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Support\ActionClass;
use App\Modules\Access\Support\DecisionReason;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;

/**
 * B-7 and PR-10 - the two global gates, asked of the ENGINE ITSELF.
 *
 * NOT re-implemented. A gate "checked" by a second copy of its own logic is a
 * gate nobody is watching: the copy would keep agreeing with itself after the
 * real one was removed. Instead this asks AccessEngine the question that the
 * gate exists to answer, with an in-memory subject, and reads the reason it
 * gives back.
 *
 * IT WRITES NOTHING. The User below is constructed in memory and never saved;
 * the engine's administration path reads role assignments for it, finds none,
 * and the global gate fires first in any case.
 *
 * PR-10 IS THE POINT OF PR-5. An inactive person keeping their assignments is
 * P1-05 behaving as designed - P1-03 preserves relationships and this gate
 * removes effective access. The COUNT is therefore informational, and the GATE
 * is what carries a state. If the gate fails, preserved assignments stop being
 * harmless. N-SS13 breaks it by removing the gate and relying on PR-5 to notice.
 */
final class EngineGateAdapter implements SourceAdapter
{
    public function __construct(private readonly AccessEngine $engine) {}

    public function answers(): array
    {
        return [ControlCatalogue::GRANT_REQUIRED, ControlCatalogue::INACTIVE_GATE];
    }

    public function evidence(): array
    {
        return [$this->grantRequired(), $this->inactiveGate()];
    }

    /**
     * B-7 - a business-data question with no grant path is refused.
     *
     * Asked with an ACTIVE subject holding nothing, so the inactive gate cannot
     * be what produces the denial. That distinction matters: without it this row
     * would still pass after the grant-path requirement was removed, as long as
     * some other gate happened to deny.
     */
    private function grantRequired(): Evidence
    {
        $subject = $this->subject(UserStatus::Active);

        $decision = $this->engine->decide(AccessQuestion::administration(
            $subject,
            'security.posture.probe',
            ActionClass::BusinessData,
        ));

        if ($decision->allowed) {
            return Evidence::state(
                ControlCatalogue::GRANT_REQUIRED,
                PostureState::Critical,
                'Business information was allowed to somebody with no role, no entitlement, no '
                .'scope and no sensitivity limit. Nothing is holding it closed.',
            );
        }

        return Evidence::state(
            ControlCatalogue::GRANT_REQUIRED,
            PostureState::Healthy,
            'Business information is refused unless somebody has a role, an entitlement to the '
            .'domain, a scope saying which records, and a sensitivity limit.',
        );
    }

    /**
     * PR-10 - an inactive account is refused BY THE GATE, and for that reason.
     *
     * The reason is checked, not merely the refusal. "Denied" is satisfied by
     * any denial at all, which is exactly the assertion CLAUDE.md §2 warns
     * about: a test satisfied by any refusal reports a gate that may not exist.
     */
    private function inactiveGate(): Evidence
    {
        $subject = $this->subject(UserStatus::Inactive);

        $decision = $this->engine->decide(AccessQuestion::administration(
            $subject,
            'security.posture.probe',
            ActionClass::EvidenceRead,
        ));

        return self::interpretInactiveDecision($decision->allowed, $decision->reason);
    }

    /**
     * WHAT THE ENGINE'S ANSWER MEANS FOR PR-10. A pure function, deliberately.
     *
     * Split out so all three branches can be driven directly. Through the real
     * engine only ONE of them is reachable: an inactive subject is refused by
     * the inactive gate before any query runs, so no fixture can produce a
     * refusal for a different reason. A mutation that stopped checking the
     * reason therefore survived the whole suite - the test could not tell the
     * difference, because the case it distinguishes cannot be built.
     *
     * Making it a pure function is not a testability trick: the interpretation
     * is the part with the judgement in it, and "refused, but not by the gate I
     * was asking about" is exactly the answer that must not be read as healthy.
     */
    public static function interpretInactiveDecision(bool $allowed, ?DecisionReason $reason): Evidence
    {
        if ($allowed) {
            return Evidence::state(
                ControlCatalogue::INACTIVE_GATE,
                PostureState::Critical,
                'Somebody whose account is not active was allowed through. Deactivating a person '
                .'would not remove their access.',
            );
        }

        if ($reason !== DecisionReason::DeniedInactiveUser) {
            /*
             * Refused, but not BY THIS GATE. Something else denied first, so
             * this control has no evidence either way - Unverified, never
             * Healthy. Reporting Healthy here is how the gate could be deleted
             * without the screen noticing: every request would still be
             * refused, for a different reason, and the row would stay green.
             */
            return Evidence::unavailable(
                ControlCatalogue::INACTIVE_GATE,
                'The refusal came from a different check, so this one could not be confirmed.',
            );
        }

        return Evidence::state(
            ControlCatalogue::INACTIVE_GATE,
            PostureState::Healthy,
            'Somebody whose account is not active is refused by the access check itself, before '
            .'any role or grant is considered.',
        );
    }

    /**
     * An in-memory subject. NEVER SAVED, and never given an identifier, so it
     * cannot collide with a real person or be mistaken for one.
     */
    private function subject(UserStatus $status): User
    {
        $user = new User;

        $user->forceFill([
            'status' => $status,
            'organisation_id' => null,
        ]);

        return $user;
    }
}
