<?php

declare(strict_types=1);

namespace App\Modules\Security\Exceptions;

use RuntimeException;

/**
 * Thrown when a directory account has no SemantIQ account and policy says not
 * to create one. Feature ADM-009, "Auto-create Users".
 *
 * Its own class rather than a boolean return, because `resolve()` returns a
 * `User` and there is no honest `User` to return here. A null would have to be
 * checked by every caller, and the one that forgot would create the account
 * this policy exists to prevent.
 *
 * Carries no message: the sentence shown to the person is the controller's
 * decision, and a message here would eventually leak into a log or an error
 * page with more detail than the refusal should disclose.
 */
class SelfProvisioningRefused extends RuntimeException {}
