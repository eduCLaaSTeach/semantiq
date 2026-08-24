<?php

declare(strict_types=1);

namespace App\Modules\Governance\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Whether the gate 4 tables exist yet.
 *
 * Covers R1.4a - ADM-014, ADM-015 and the personal data category register - and
 * R1.4b, which adds sovereignty exceptions and retention policies.
 *
 * THE PROBLEM THIS SOLVES is the one gate 3 met in production. The deploy
 * workflow ships code and does NOT run migrations, so every release opens a
 * window:
 *
 *     code deployed -> migration not yet run -> the new tables do not exist
 *
 * In gate 3 that window took the whole site down, because `SecurityHeaders`
 * runs on the web middleware stack and read a policy on every response. Gate 4
 * adds NO middleware, so the blast radius is smaller - a 500 would be confined
 * to the Compliance screens. That is a smaller defect, not an acceptable one,
 * and the same guard applies.
 *
 * WHY A SCHEMA CHECK AND NOT A `catch`. SEC-DEC-056. Catching a database
 * exception and falling back would also swallow a broken connection, a
 * permissions problem or a corrupt table, and would report all three as
 * "everything is fine, using defaults" - the exact failure mode a governance
 * control must not have. `Schema::hasTable()` asks one specific question and
 * answers only that. Everything else still fails loudly.
 *
 * THE TWO HALVES BEHAVE DIFFERENTLY, and the difference is deliberate.
 * SEC-DEC-072.
 *
 *   A PROFILE read falls back to the catalogue's safe defaults, because a safe
 *   default IS meaningful: with no table there can be no approved profile, so
 *   "nothing has been approved" is the true answer and the screens say so.
 *
 *   A REGISTER of real events - privacy requests, breach incidents, arriving in
 *   R1.4c - has no meaningful default. Inventing an empty one would say "no
 *   incidents" at exactly the moment the screen cannot see whether there are
 *   any. Those screens report Migration required instead.
 *
 *   EVERY WRITE fails closed, both halves. Accepting a change and discarding it
 *   would tell an administrator their data protection policy had changed when
 *   nothing had.
 *
 * MEMOISED PER REQUEST, and the class is a container SINGLETON, so the answer
 * costs one schema query per table per request rather than one per consumer. It
 * cannot change within a request: a migration does not run halfway through one.
 */
class GovernanceStorage
{
    /** @var array<string, bool> */
    private array $known = [];

    /** Whether `personal_data_categories` exists yet. */
    public function categoriesAreReady(): bool
    {
        return $this->tableExists('personal_data_categories');
    }

    /** Whether `data_protection_profiles` exists yet. */
    public function dataProtectionIsReady(): bool
    {
        return $this->tableExists('data_protection_profiles');
    }

    /** Whether `data_sovereignty_profiles` exists yet. */
    public function sovereigntyIsReady(): bool
    {
        return $this->tableExists('data_sovereignty_profiles');
    }

    /** Whether `sovereignty_exceptions` exists yet. Gate 4 batch R1.4b. */
    public function exceptionsAreReady(): bool
    {
        return $this->tableExists('sovereignty_exceptions');
    }

    /** Whether `retention_policies` exists yet. Gate 4 batch R1.4b. */
    public function retentionIsReady(): bool
    {
        return $this->tableExists('retention_policies');
    }

    /**
     * Whether every governance table exists.
     *
     * Used by the write middleware, which is why it is an AND across all of
     * them: a governance write that half-succeeded because one table happened
     * to exist would be worse than one refused outright.
     */
    public function isReady(): bool
    {
        return $this->categoriesAreReady()
            && $this->dataProtectionIsReady()
            && $this->sovereigntyIsReady()
            && $this->exceptionsAreReady()
            && $this->retentionIsReady();
    }

    /**
     * What to tell somebody, when it is not ready.
     *
     * One sentence naming the cause and the fix. "Something went wrong" during
     * a deployment window sends an administrator hunting through logs for a
     * condition that resolves itself the moment a known command is run.
     *
     * Does not open by restating "governance storage is not initialised": every
     * caller already leads with that as a heading, and repeating it reads as a
     * stutter. The same lesson the gate 3 banner taught, by looking at it.
     */
    public function blocker(): string
    {
        return 'The database migration for the Data Protection release has not been run on this '
            .'deployment yet, so the governance profiles, personal data categories, retention policies '
            .'and sovereignty exceptions cannot be read or changed. An administrator with server access '
            .'needs to run the outstanding migrations.';
    }

    /**
     * Forget what was cached.
     *
     * For tests that create or drop a table mid-run. Nothing in the application
     * calls it: within one request the answer cannot change.
     */
    public function forget(): void
    {
        $this->known = [];
    }

    private function tableExists(string $table): bool
    {
        return $this->known[$table] ??= Schema::hasTable($table);
    }
}
