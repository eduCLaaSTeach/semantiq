<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Secrets;

use App\Modules\Platform\Setup\Models\IntegrationSecret;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * THE one place a secret is encrypted or decrypted.
 *
 * Concentrating it here is what makes the guard cheap to write and impossible
 * to satisfy accidentally: SecretsAreDecryptedInOnePlace asserts that
 * Crypt::decrypt appears nowhere else under app/. If decryption were spread
 * across four adapters, "no secret escapes" would be four separate claims.
 *
 * NOTHING HERE RETURNS A SECRET TO A PERSON. `has()` answers presence, which is
 * what a screen is allowed to know; `reveal()` does not exist, there is no
 * route that calls anything like it, and the projection has no field a value
 * could occupy.
 *
 * A FAILED DECRYPT RETURNS NULL AND SAYS NOTHING ELSE. A DecryptException
 * carries the payload shape, and a deployment whose APP_KEY changed would
 * otherwise turn every adapter error into a description of its own ciphertext.
 * Null is the honest answer: the value is unavailable. The caller reports the
 * integration as unavailable with a CHOSEN sentence.
 *
 * KEY VERSION IS RECORDED, NOT RELIED UPON. It says which key encrypted a row.
 * It cannot decrypt a row whose key is gone, and no method here pretends
 * otherwise.
 */
final class IntegrationSecretStore
{
    public const KEY_VERSION = 1;

    public function put(string $family, string $name, string $value, ?int $actorId = null): void
    {
        IntegrationSecret::query()->updateOrCreate(
            ['family' => $family, 'name' => $name],
            [
                'ciphertext' => Crypt::encryptString($value),
                'key_version' => self::KEY_VERSION,
                'last_changed_at' => now(),
                'last_changed_by_user_id' => $actorId,
            ],
        );
    }

    /**
     * Presence, which is all a screen is permitted to learn.
     *
     * It does NOT decrypt to answer. A row that exists but cannot be decrypted
     * is still a configured secret - the operator set one - and saying "not
     * configured" would send them to re-enter a value that is already there
     * when the real fault is the key.
     */
    public function has(string $family, string $name): bool
    {
        return IntegrationSecret::query()
            ->where('family', $family)
            ->where('name', $name)
            ->exists();
    }

    /**
     * For an adapter that is about to make a provider call, and for nothing
     * else.
     */
    public function get(string $family, string $name): ?string
    {
        $row = IntegrationSecret::query()
            ->where('family', $family)
            ->where('name', $name)
            ->first();

        if ($row === null) {
            return null;
        }

        try {
            return Crypt::decryptString($row->ciphertext);
        } catch (DecryptException) {
            // Deliberately swallowed whole. See the class docblock.
            return null;
        }
    }

    /**
     * Adopt a credential that was staged for a D-159 confirmation.
     *
     * THE CIPHERTEXT COMES IN AND THE DECRYPTION HAPPENS HERE, which is the
     * whole reason this method exists rather than StagedChangeStore simply
     * decrypting its own column and calling put().
     *
     * That version worked and was wrong: it made a SECOND place in the
     * application where a secret is turned back into plaintext, and
     * "no secret escapes" stopped being one claim to check and became two.
     * SecretsAreDecryptedInOnePlace caught it, which is exactly what it is for.
     *
     * FALSE, NOT AN EXCEPTION, when the payload cannot be decrypted - the same
     * silence as get(), for the same reason: a DecryptException describes the
     * ciphertext it failed on. The caller rolls back rather than writing an
     * empty credential over a working one.
     */
    public function adoptStaged(string $family, string $name, string $ciphertext, ?int $actorId = null): bool
    {
        try {
            $value = Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            // Deliberately swallowed whole. See the class docblock.
            return false;
        }

        if ($value === '') {
            return false;
        }

        $this->put($family, $name, $value, $actorId);

        return true;
    }

    public function forget(string $family, string $name): void
    {
        IntegrationSecret::query()
            ->where('family', $family)
            ->where('name', $name)
            ->delete();
    }
}
