<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Enums\BusinessDomain;
use App\Http\Controllers\Controller;
use App\Models\DomainEntitlement;
use App\Modules\Identity\Services\UserRegistry;
use Illuminate\View\View;

/**
 * Domain entitlements, seen by domain rather than by person. MENU_STRUCTURE 12.2.
 *
 * THE SECOND DIMENSION OF THE ACCESS MODEL, and this screen exists because of
 * the question it answers: "who can read Finance". The Users screen answers the
 * other direction - what one person can read - and neither view can be derived
 * from the other by eye when there are two hundred accounts.
 *
 * It is READ ONLY. Granting and revoking happen on the account, through
 * `UserRegistry`, where the elevation checks and the audit event live. A bulk
 * grant screen would be the obvious way around both, and the convenience is not
 * worth it: `ROLE_MODEL.md` section 1 makes a domain grant a deliberate,
 * individually recorded decision, not a checkbox matrix.
 *
 * Nothing here reads a platform role as implying a domain, and nothing may be
 * changed to. A System Administrator with no entitlement appears in no column.
 */
class EntitlementController extends Controller
{
    public function __construct(
        private readonly UserRegistry $registry,
    ) {}

    public function __invoke(): View
    {
        $scopedUserIds = $this->registry->query()->pluck('id');

        $entitlements = DomainEntitlement::query()
            /* Explicitly scoped: `users` carries no global scope - see
             * User::scopeInCurrentOrganisation() - so the join has to be
             * restricted here or another customer's grants could appear. */
            ->whereIn('user_id', $scopedUserIds)
            ->with(['user:id,name,email,status,role', 'grantedBy:id,name'])
            ->get()
            ->groupBy(fn (DomainEntitlement $entitlement): string => $entitlement->domain->value);

        return view('pages.admin.entitlements', [
            'domains' => BusinessDomain::cases(),
            'entitlements' => $entitlements,
        ]);
    }
}
