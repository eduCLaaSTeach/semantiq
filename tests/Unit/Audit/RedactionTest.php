<?php

declare(strict_types=1);

namespace Tests\Unit\Audit;

use App\Modules\Audit\Support\Redaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The one definition of what counts as a secret.
 *
 * This is the highest-value test file in the release. CLAUDE.md forbids a
 * credential reaching a committed file, a log or a screen, and every writer in
 * the application relies on this class to hold that line. A regression here is
 * silent: the trail keeps being written, it just starts containing the thing it
 * exists to avoid containing.
 */
class RedactionTest extends TestCase
{
    #[Test]
    public function a_key_that_reads_as_a_credential_is_recognised_in_any_spelling(): void
    {
        foreach (['password', 'Password', 'client_secret', 'clientSecret', 'CLIENT-SECRET', 'api_key', 'accessToken', 'Authorization', 'private_key', 'connection_string', 'dsn'] as $key) {
            $this->assertTrue(Redaction::isSensitiveKey($key), $key.' was not recognised as sensitive');
        }
    }

    #[Test]
    public function an_ordinary_key_is_left_alone(): void
    {
        foreach (['name', 'email', 'status', 'organisation_id', 'display_name'] as $key) {
            $this->assertFalse(Redaction::isSensitiveKey($key), $key.' was over-redacted');
        }
    }

    #[Test]
    public function a_sensitive_value_never_reaches_the_summary(): void
    {
        $summary = Redaction::summarise([
            'name' => 'Acme Ltd',
            'client_secret' => 'the-actual-secret-value',
            'nested' => ['api_key' => 'another-secret', 'region' => 'southeastasia'],
        ]);

        $this->assertSame('Acme Ltd', $summary['name']);
        $this->assertSame(Redaction::PLACEHOLDER, $summary['client_secret']);
        $this->assertSame(Redaction::PLACEHOLDER, $summary['nested']['api_key']);
        $this->assertSame('southeastasia', $summary['nested']['region']);

        // The strongest form of the assertion: the value must not appear
        // anywhere in the encoded result, under any key.
        $encoded = json_encode($summary);
        $this->assertStringNotContainsString('the-actual-secret-value', (string) $encoded);
        $this->assertStringNotContainsString('another-secret', (string) $encoded);
    }

    #[Test]
    public function a_credential_hiding_in_free_text_is_swept_out(): void
    {
        $cases = [
            'Request failed: Authorization: Bearer abcdef1234567890abcdef',
            'token=sk-live-9f8e7d6c5b4a3210',
            'Could not connect to mysql://admin:hunter2@db.internal/app',
            'Rejected id token eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.signature',
        ];

        foreach ($cases as $text) {
            $scrubbed = (string) Redaction::scrub($text);

            $this->assertStringContainsString(Redaction::PLACEHOLDER, $scrubbed, 'nothing was redacted in: '.$text);
        }

        // Named specifically, because these are the exact strings that would
        // force a credential rotation if they escaped.
        $this->assertStringNotContainsString('hunter2', (string) Redaction::scrub('mysql://admin:hunter2@db.internal/app'));
        $this->assertStringNotContainsString('sk-live-9f8e7d6c5b4a3210', (string) Redaction::scrub('token=sk-live-9f8e7d6c5b4a3210'));
    }

    #[Test]
    public function an_ordinary_message_survives_scrubbing_intact(): void
    {
        // Over-redaction is the safe direction but not a free one: a trail that
        // says nothing useful is a trail nobody reads.
        $message = 'The organisation name was changed from Acme Ltd to Acme Group.';

        $this->assertSame($message, Redaction::scrub($message));
    }

    #[Test]
    public function a_long_value_is_fingerprinted_rather_than_stored(): void
    {
        $long = str_repeat('a', 500);

        $summary = Redaction::summarise(['note' => $long]);

        $this->assertStringContainsString('500 characters', (string) $summary['note']);
        $this->assertStringContainsString('sha256:', (string) $summary['note']);
        $this->assertStringNotContainsString($long, (string) $summary['note']);
    }

    #[Test]
    public function a_deeply_nested_structure_is_bounded(): void
    {
        // An audit writer handed an unbounded structure would otherwise write an
        // unbounded blob into a column meant to hold a summary.
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => 'too deep']]]]];

        $summary = Redaction::summarise($deep);

        $this->assertStringContainsString('[nested', json_encode($summary) ?: '');
    }

    #[Test]
    public function a_fingerprint_is_short_and_not_the_value(): void
    {
        $fingerprint = Redaction::fingerprint('a-secret');

        $this->assertStringStartsWith('sha256:', $fingerprint);
        $this->assertSame(23, strlen($fingerprint));
        $this->assertStringNotContainsString('a-secret', $fingerprint);
    }
}
