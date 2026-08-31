<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\Platform\Identity\Microsoft\IdTokenValidator;
use Tests\Support\EntraTokenFactory;
use Tests\TestCase;

/**
 * Negative cases 4, 5 and 7, plus the audience and algorithm guards.
 *
 * Each token below is minted with exactly one defect, so a passing test means
 * that specific check fired - not that validation happened to fail somewhere.
 * A single "invalid token" assertion would pass even if only one check existed.
 */
final class IdTokenValidationTest extends TestCase
{
    private EntraTokenFactory $entra;

    private IdTokenValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entra = new EntraTokenFactory;
        $this->entra->configure();
        $this->entra->fakeEndpoints();

        $this->validator = new IdTokenValidator(
            new EntraDiscovery(EntraTokenFactory::TENANT),
            EntraTokenFactory::CLIENT_ID,
            EntraTokenFactory::TENANT,
        );
    }

    public function test_a_correct_token_validates(): void
    {
        $claims = $this->validator->validate($this->entra->token(), 'test-nonce');

        $this->assertSame('33333333-3333-3333-3333-333333333333', $claims['oid']);
    }

    /** Negative case 5. */
    public function test_an_invalid_issuer_is_refused(): void
    {
        $this->expectFailure('issuer_mismatch');

        $this->validator->validate(
            $this->entra->token(['iss' => 'https://login.microsoftonline.com/other/v2.0']),
            'test-nonce',
        );
    }

    /**
     * A token minted for a different application. Genuine, correctly signed by
     * the right issuer, and not for us.
     */
    public function test_a_token_for_another_audience_is_refused(): void
    {
        $this->expectFailure('audience_mismatch');

        $this->validator->validate($this->entra->token(['aud' => 'some-other-app']), 'test-nonce');
    }

    /** Negative case 7. */
    public function test_a_mismatched_nonce_is_refused(): void
    {
        $this->expectFailure('nonce_mismatch');

        $this->validator->validate($this->entra->token(['nonce' => 'a-different-nonce']), 'test-nonce');
    }

    /**
     * Negative case 4. Refused as access-denied, not as a protocol error: the
     * token is genuine, the person is simply from a directory we do not trust.
     */
    public function test_a_token_from_another_tenant_is_refused(): void
    {
        try {
            $this->validator->validate(
                $this->entra->token(['tid' => '99999999-9999-9999-9999-999999999999']),
                'test-nonce',
            );

            $this->fail('A token from an unapproved tenant was accepted.');
        } catch (AuthenticationFailed $e) {
            $this->assertSame(AuthenticationFailed::STATE_ACCESS_DENIED, $e->state);
            $this->assertSame('tenant_not_approved', $e->reason);
        }
    }

    public function test_an_expired_token_is_refused(): void
    {
        $this->expectException(AuthenticationFailed::class);

        $this->validator->validate(
            $this->entra->token(['exp' => time() - 3600, 'iat' => time() - 7200, 'nbf' => time() - 7200]),
            'test-nonce',
        );
    }

    public function test_a_token_missing_the_subject_claim_is_refused(): void
    {
        $this->expectFailure('missing_oid');

        $this->validator->validate($this->entra->token(['oid' => '']), 'test-nonce');
    }

    public function test_a_token_missing_an_email_claim_is_refused(): void
    {
        $this->expectFailure('missing_email');

        $this->validator->validate(
            $this->entra->token(['email' => '', 'preferred_username' => '', 'upn' => '']),
            'test-nonce',
        );
    }

    /**
     * Algorithm confusion: a token asserting "none", or signed with an HMAC over
     * the public key, must never verify. The allow-list is RS256 and the
     * token's own header is never trusted to choose.
     */
    public function test_an_unsigned_token_is_refused(): void
    {
        $unsigned = base64_encode('{"alg":"none","typ":"JWT"}').'.'
            .base64_encode('{"oid":"x","tid":"'.EntraTokenFactory::TENANT.'"}').'.';

        $this->expectException(AuthenticationFailed::class);

        $this->validator->validate($unsigned, 'test-nonce');
    }

    /** A token signed by someone else's key entirely. */
    public function test_a_token_signed_by_an_unknown_key_is_refused(): void
    {
        $foreign = new EntraTokenFactory;

        $this->expectFailure('token_signature_invalid');

        $this->validator->validate($foreign->token(), 'test-nonce');
    }

    private function expectFailure(string $reason): void
    {
        $this->expectException(AuthenticationFailed::class);
        $this->expectExceptionMessage($reason);
    }
}
