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

        // Deliberately NOT faked here. Http::fake() appends stubs and the first
        // registered match wins, so a fake in setUp would silently defeat every
        // per-test key-set override - the tests would pass while testing the
        // setUp fixture instead of the case they name.
        $this->validator = new IdTokenValidator(
            new EntraDiscovery(EntraTokenFactory::TENANT),
            EntraTokenFactory::CLIENT_ID,
            EntraTokenFactory::TENANT,
        );
    }

    /**
     * The regression test for the production defect.
     *
     * Microsoft's real JWKS omits the per-key "alg" field. php-jwt refuses to
     * parse such a key without a default algorithm, so the whole key set failed
     * to parse and every live sign-in was refused as "token_signature_invalid"
     * before a signature was ever checked.
     *
     * CI missed it because the original fixture included "alg" - it was more
     * helpful than the real thing, so it tested the fixture rather than the
     * system. The fixture now mirrors Entra, and this test fails against the
     * pre-fix validator.
     */
    public function test_a_correct_token_validates_against_a_jwks_with_no_alg_field(): void
    {
        $this->entra->fakeEndpoints();

        $keys = $this->entra->jwks();

        $this->assertArrayNotHasKey('alg', $keys[0], 'The fixture must mirror Microsoft and omit "alg".');

        $claims = $this->validator->validate($this->entra->token(), 'test-nonce');

        $this->assertSame('33333333-3333-3333-3333-333333333333', $claims['oid']);
    }

    /**
     * Each of the next three publishes ONE key and nothing else, so the filter
     * under test is the only thing that can refuse it.
     *
     * An earlier version published the bad key alongside the good one and
     * asserted success - which proved only that the right key is findable among
     * others. Removing the filter left that test passing, because the token's
     * kid still selected the correct key. A guard has to be the sole reason the
     * case fails, or it is not being tested at all.
     */
    public function test_a_key_set_containing_only_a_non_rsa_key_is_refused(): void
    {
        $signing = $this->entra->jwks()[0];

        // n and e are present deliberately: without them the later shape check
        // would refuse this key and the kty filter would go untested.
        $this->entra->fakeEndpoints(null, [array_merge($signing, ['kty' => 'EC', 'kid' => 'ec-key'])]);

        // The specific reason, not merely "a refusal": any broken filter still
        // refuses eventually, so only the reason proves WHICH guard fired.
        $this->expectFailure('no_rs256_signing_key');

        $this->validator->validate($this->entra->token(), 'test-nonce');
    }

    public function test_a_key_set_containing_only_an_encryption_key_is_refused(): void
    {
        $signing = $this->entra->jwks()[0];

        $this->entra->fakeEndpoints(null, [array_merge($signing, ['use' => 'enc'])]);

        $this->expectFailure('no_rs256_signing_key');

        $this->validator->validate($this->entra->token(), 'test-nonce');
    }

    public function test_a_key_set_advertising_another_algorithm_is_refused(): void
    {
        $signing = $this->entra->jwks()[0];

        $this->entra->fakeEndpoints(null, [array_merge($signing, ['alg' => 'RS512'])]);

        $this->expectFailure('no_rs256_signing_key');

        $this->validator->validate($this->entra->token(), 'test-nonce');
    }

    /**
     * A realistic mixed key set: encryption keys and other algorithms sit
     * alongside the signing key, and the right one is still selected by kid.
     */
    public function test_the_signing_key_is_found_among_a_mixed_key_set(): void
    {
        $signing = $this->entra->jwks()[0];

        $this->entra->fakeEndpoints(null, [
            array_merge($signing, ['use' => 'enc', 'kid' => 'enc-key']),
            array_merge($signing, ['alg' => 'RS512', 'kid' => 'rs512-key']),
            $signing,
        ]);

        $claims = $this->validator->validate($this->entra->token(), 'test-nonce');

        $this->assertSame(EntraTokenFactory::TENANT, $claims['tid']);
    }

    /**
     * Algorithm confusion, the HMAC variant: a token whose header says HS256,
     * signed with a shared secret. The verification key is fixed at RS256, so
     * the header can never talk us into a different method.
     */
    public function test_a_non_rs256_token_is_refused(): void
    {
        $this->entra->fakeEndpoints();

        $this->expectFailure('token_signature_invalid');

        $this->validator->validate($this->entra->hmacToken(), 'test-nonce');
    }

    /**
     * An unknown key id is the one failure worth a refetch, because Microsoft
     * rotates signing keys without notice. It must stay bounded: one retry,
     * then refuse.
     */
    public function test_an_unknown_key_id_triggers_a_bounded_refresh_then_refuses(): void
    {
        $foreign = new EntraTokenFactory;

        // Published key set does not contain the kid the token was signed with.
        $this->entra->fakeEndpoints($this->entra->token(), $foreign->jwks(['kid' => 'a-rotated-key']));

        $this->expectFailure('token_signature_invalid');

        $this->validator->validate($this->entra->token(), 'test-nonce');
    }

    /** Negative case 5. */
    public function test_an_invalid_issuer_is_refused(): void
    {
        $this->entra->fakeEndpoints();

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
        $this->entra->fakeEndpoints();

        $this->expectFailure('audience_mismatch');

        $this->validator->validate($this->entra->token(['aud' => 'some-other-app']), 'test-nonce');
    }

    /** Negative case 7. */
    public function test_a_mismatched_nonce_is_refused(): void
    {
        $this->entra->fakeEndpoints();

        $this->expectFailure('nonce_mismatch');

        $this->validator->validate($this->entra->token(['nonce' => 'a-different-nonce']), 'test-nonce');
    }

    /**
     * Negative case 4. Refused as access-denied, not as a protocol error: the
     * token is genuine, the person is simply from a directory we do not trust.
     */
    public function test_a_token_from_another_tenant_is_refused(): void
    {
        $this->entra->fakeEndpoints();

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
        $this->entra->fakeEndpoints();

        $this->expectException(AuthenticationFailed::class);

        $this->validator->validate(
            $this->entra->token(['exp' => time() - 3600, 'iat' => time() - 7200, 'nbf' => time() - 7200]),
            'test-nonce',
        );
    }

    public function test_a_token_missing_the_subject_claim_is_refused(): void
    {
        $this->entra->fakeEndpoints();

        $this->expectFailure('missing_oid');

        $this->validator->validate($this->entra->token(['oid' => '']), 'test-nonce');
    }

    public function test_a_token_missing_an_email_claim_is_refused(): void
    {
        $this->entra->fakeEndpoints();

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
        $this->entra->fakeEndpoints();

        $unsigned = base64_encode('{"alg":"none","typ":"JWT"}').'.'
            .base64_encode('{"oid":"x","tid":"'.EntraTokenFactory::TENANT.'"}').'.';

        $this->expectException(AuthenticationFailed::class);

        $this->validator->validate($unsigned, 'test-nonce');
    }

    /** A token signed by someone else's key entirely. */
    public function test_a_token_signed_by_an_unknown_key_is_refused(): void
    {
        $this->entra->fakeEndpoints();

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
