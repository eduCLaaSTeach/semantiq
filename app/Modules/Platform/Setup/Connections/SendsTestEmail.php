<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

/**
 * The seam for D-153's send.
 *
 * WHY AN INTERFACE AND NOT A NON-FINAL CLASS. TestEmailSender is final on
 * purpose: it is the one place that opens an SMTP connection and builds a
 * message, and a subclass could override the method that decides where that
 * message goes. Dropping `final` to make a test easier would remove the
 * guarantee the test exists to check.
 *
 * So the seam is declared rather than taken. What a test substitutes is
 * something that implements this; what production runs is still final.
 *
 * ONE METHOD, ONE STRING PARAMETER, AND IT IS THE RECIPIENT. The shape IS the
 * decision: there is nowhere to pass a subject, a body or a second address,
 * because there is no parameter for one - and a test asserts that signature so
 * that adding one is a red build rather than a quiet feature.
 */
interface SendsTestEmail
{
    /**
     * @param  string  $recipient  the AUTHENTICATED principal's own address,
     *                             resolved server-side. Never from a request.
     */
    public function send(string $recipient): ConnectionResult;
}
