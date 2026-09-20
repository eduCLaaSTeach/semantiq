<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * D-153. ONE TEST MESSAGE, TO THE PERSON WHO ASKED FOR IT, AND NOWHERE ELSE.
 *
 * -------------------------------------------------------------------------
 * WHY THIS IS SEPARATE FROM Test connection
 * -------------------------------------------------------------------------
 * EmailConnectionTester proves SemantIQ can reach the server and that the
 * server accepts the username and password. It proves nothing about whether
 * the server will ACCEPT A MESSAGE from the configured From address - which is
 * a different permission, refused by a different rule, and the one that
 * actually fails in production. A deployment can authenticate perfectly and be
 * unable to send a single email, and until now nothing here would have said so.
 *
 * -------------------------------------------------------------------------
 * THERE IS NO RECIPIENT PARAMETER. ANYWHERE. IN ANY METHOD
 * -------------------------------------------------------------------------
 * This is the whole of D-153 and it is enforced by the SIGNATURE, not by
 * validation. send() takes the address it is given by the caller, and the
 * caller may only get that address from the authenticated principal - there is
 * no route parameter, no request field and no form input that reaches it.
 *
 * A test that could be pointed at an address is an open relay wearing a
 * diagnostic's clothes: it sends mail FROM the customer's own domain, THROUGH
 * their own authenticated server, to anywhere. It is the first thing found by
 * anyone scanning for one.
 *
 * THE SUBJECT AND BODY ARE FIXED for the same reason. An attacker who cannot
 * choose the recipient but can choose the text still has a way to send a
 * message of their choosing to an administrator, from a trusted address.
 *
 * -------------------------------------------------------------------------
 * WHAT COMES BACK
 * -------------------------------------------------------------------------
 * One of this class's own declared sentences, never the provider's. SMTP
 * rejections routinely echo the username and sometimes the credential, and a
 * setup screen is the single most likely place for one to be rendered straight
 * onto a page.
 */
final class TestEmailSender implements SendsTestEmail
{
    private const TIMEOUT_SECONDS = 10;

    /** The message. Fixed, and not composed from anything a caller passes. */
    public const SUBJECT = 'SemantIQ email delivery test';

    public const BODY = 'This message confirms that SemantIQ can send email using the configured '
        .'Email & Notifications connection.';

    public function __construct(private readonly IntegrationSecretStore $secrets) {}

    /**
     * @param  string  $recipient  the AUTHENTICATED principal's own address, resolved
     *                             server-side by the caller. There is no path by
     *                             which a request body reaches this argument.
     */
    public function send(string $recipient): ConnectionResult
    {
        $settings = $this->settings();

        $host = (string) ($settings['host'] ?? '');
        $port = (int) ($settings['port'] ?? 0);
        $from = trim((string) ($settings['from_address'] ?? ''));

        if ($host === '' || $port === 0) {
            return ConnectionResult::notChecked(
                'The mail server address and port have not been entered yet.',
            );
        }

        if ($from === '') {
            /*
             * THE CONFIGURED From IS REQUIRED, and saying so is half the value
             * of this action. Falling back to the username - which is the
             * obvious convenience - would send the test from an address the
             * deployment does not actually use, and a test that passes for a
             * sender nobody will ever send from proves nothing.
             */
            return ConnectionResult::notChecked(
                'The send from address has not been entered yet, so there is nothing to send from.',
            );
        }

        if ($recipient === '' || ! str_contains($recipient, '@')) {
            // Not a refusal about the configuration. The signed-in principal
            // has no usable address, which is a fact about them.
            return ConnectionResult::notChecked(
                'Your account has no email address, so there is nowhere to send the test.',
            );
        }

        $encryption = (string) ($settings['encryption'] ?? 'tls');
        $username = (string) ($settings['username'] ?? '');
        $password = (string) $this->secrets->get(IntegrationFamily::Email->value, 'password');
        $fromName = trim((string) ($settings['from_name'] ?? ''));

        try {
            $transport = new EsmtpTransport(
                host: $host,
                port: $port,
                tls: $encryption === 'ssl',
            );

            $transport->getStream()->setTimeout(self::TIMEOUT_SECONDS);

            if ($username !== '') {
                $transport->setUsername($username);
                $transport->setPassword($password);
            }

            $message = (new Email)
                ->from($fromName === '' ? new Address($from) : new Address($from, $fromName))
                // ONE RECIPIENT. No cc(), no bcc(), and no method call here
                // that takes a second address.
                ->to(new Address($recipient))
                ->subject(self::SUBJECT)
                ->text(self::BODY);

            (new Mailer($transport))->send($message);
        } catch (Throwable $e) {
            /*
             * INSPECTED FOR SHAPE, THEN DISCARDED - the same treatment
             * EmailConnectionTester gives it, for the same reason. The message
             * decides WHICH of this class's sentences to return and is never
             * itself returned, logged here or put in the explanation.
             */
            $message = $e->getMessage();

            if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
                return ConnectionResult::degraded(
                    'The mail server did not answer within ten seconds. The message was not sent.',
                );
            }

            if (str_contains($message, 'Authentication') || str_contains($message, 'authenticate')) {
                return ConnectionResult::unavailable(
                    'The mail server refused the username and password entered here.',
                );
            }

            return ConnectionResult::unavailable(
                'The mail server accepted the connection but refused to send the message. It may '
                .'not allow this account to send from the address entered here.',
            );
        }

        return ConnectionResult::available(
            'A test message was sent to your own email address. If it does not arrive within a few '
            .'minutes, check the spam folder and the mail server logs.',
        );
    }

    /** @return array<string, mixed> */
    private function settings(): array
    {
        $row = IntegrationConfiguration::query()
            ->where('family', IntegrationFamily::Email->value)
            ->first();

        return is_array($row?->settings) ? $row->settings : [];
    }
}
