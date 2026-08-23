<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Modules\Security\Http\Requests\UpdateSecurityPolicyRequest;
use App\Modules\Security\Support\ApiSecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * API Security. Feature ADM-011.
 *
 * Half a settings screen and half a verification report, and the second half is
 * the reason it exists. Release 1 adds no public API surface, so a page of
 * switches for an API that does not exist would configure nothing. What ADM-011
 * actually asks for is a list of CONTROLS, and the useful thing to do with a
 * list of controls is check them against the running application.
 *
 * The controls are checked live by `ApiSecurityAudit` - the route table, the
 * middleware stack, the redactor, the policy values. Nothing on this screen is
 * hard-coded to pass, and a control that cannot be established reports Not
 * Verified rather than Healthy.
 */
class ApiSecurityController extends SecurityPolicyController
{
    protected function screen(): string
    {
        return 'api';
    }

    public function edit(ApiSecurityAudit $audit): View
    {
        return view('pages.admin.security-policy', array_merge($this->screenData(), [
            'controls' => $audit->run(),
            'controlsOverall' => $audit->overall(),
        ]));
    }

    public function update(UpdateSecurityPolicyRequest $request): RedirectResponse
    {
        return $this->save($request);
    }
}
