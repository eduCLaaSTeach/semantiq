<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Connections;

use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\IntegrationConfiguration;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;

/**
 * SMTP. Connect, negotiate TLS, EHLO, authenticate, QUIT.
 *
 * SMTP RATHER THAN GRAPH - D-151. Graph would need a mail-send scope, which
 * walks straight into D-04's scope fence: the deployment's Entra application
 * would gain the ability to send mail as people, permanently, to satisfy a
 * setup screen. SMTP needs one credential, reaches one server, and can do
 * exactly one thing.
 *
 * THERE IS NO RECIPIENT FIELD ANYWHERE IN THIS CLASS, and that is the whole of
 * D-153. A test that could be pointed at an address is an open relay wearing a
 * diagnostic's clothes - the first thing found by anyone scanning for one, and
 * a way to send mail from the deployment's own domain. The optional test
 * message goes to the SIGNED-IN PRINCIPAL'S OWN ADDRESS, resolved server-side,
 * and there is no parameter that changes that.
 *
 * start() ALONE IS THE TEST. EsmtpTransport::start() opens the socket,
 * negotiates TLS per the configured mode, sends EHLO and authenticates. If the
 * credentials are wrong it throws there. Nothing is sent.
 */
final class EmailConnectionTester implements ConnectionTester
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private readonly IntegrationSecretStore $secrets) {}

    public function family(): IntegrationFamily
    {
        return IntegrationFamily::Email;
    }

    public function test(): ConnectionResult
    {
        $settings = $this->settings();

        $host = (string) ($settings['host'] ?? '');
        $port = (int) ($settings['port'] ?? 0);

        if ($host === '' || $port === 0) {
            return ConnectionResult::notChecked(
                'The mail server address and port have not been entered yet.',
            );
        }

        $encryption = (string) ($settings['encryption'] ?? 'tls');
        $username = (string) ($settings['username'] ?? '');
        $password = (string) $this->secrets->get(IntegrationFamily::Email->value, 'password');

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

            $transport->start();
            $transport->stop();
        } catch (Throwable $e) {
            /*
             * THE CAUGHT VALUE IS INSPECTED FOR SHAPE AND THEN DISCARDED.
             *
             * str_contains on the message decides only WHICH of this class's
             * own sentences to return. The message itself is never returned,
             * never logged here and never put in the explanation: an SMTP
             * server's rejection routinely echoes the username, and some echo
             * the credential.
             */
            $message = $e->getMessage();

            if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
                return ConnectionResult::degraded(
                    'The mail server did not answer within ten seconds. It may be slow or unreachable from this server.',
                );
            }

            if (str_contains($message, 'Authentication') || str_contains($message, 'authenticate')) {
                return ConnectionResult::unavailable(
                    'The mail server refused the username and password entered here.',
                );
            }

            return ConnectionResult::unavailable(
                'The mail server could not be reached with the address, port and security setting entered here.',
            );
        }

        return ConnectionResult::available(
            'The mail server accepted the connection and the credentials entered here.',
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
