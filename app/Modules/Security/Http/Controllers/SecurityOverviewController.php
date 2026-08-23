<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Security\Support\SecurityPosture;
use App\Modules\Security\Support\SecurityStorage;
use Illuminate\View\View;

/**
 * Security Overview.
 *
 * A READ-ONLY roll-up over ADM-009 to ADM-012, and the one screen in this group
 * with no feature of its own. Decision D5, approved 25 August 2026: the leaf
 * came from DEC-001's navigation shape rather than from the Release 1
 * specification, so it summarises what the other four own and invents nothing.
 *
 * There is no write route and no form. Everything an administrator can change
 * from here is changed on the screen that owns it, and the overview links to
 * that screen rather than duplicating its controls - the same filter-not-fork
 * rule the navigation follows.
 *
 * Gap M9 in the release plan stays open: a later requirement may say what this
 * screen should be. Until it does, this is the honest minimum.
 */
class SecurityOverviewController extends Controller
{
    public function __invoke(SecurityPosture $posture, SecurityStorage $storage): View
    {
        return view('pages.admin.security-overview', [
            /*
             * Passed so the expiring-credentials panel can say it cannot answer
             * rather than showing a green tick and "nothing expiring". During a
             * deployment window that tick would be a false healthy about the
             * one thing on this page that could take an integration down.
             */
            'storageReady' => $storage->secretReferencesAreReady(),
            'storageBlocker' => $storage->blocker(),
            'overall' => $posture->overall(),
            'authentication' => $posture->authentication(),
            'sessions' => $posture->sessions(),
            'application' => $posture->application(),
            'secrets' => $posture->secrets(),
            'expiring' => $posture->expiringReferences(),
            'rotationDue' => $posture->rotationDueReferences(),
            'warnings' => $posture->warnings(),
            'gaps' => $posture->configurationGaps(),
            'events' => $posture->recentEvents(),
        ]);
    }
}
