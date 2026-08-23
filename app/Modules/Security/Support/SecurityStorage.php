<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Whether the gate 3 tables exist yet. Features ADM-009 to ADM-012.
 *
 * THE PROBLEM THIS SOLVES, stated plainly because it is a production incident
 * rather than a theoretical one.
 *
 * The deploy workflow ships code and does NOT run migrations. That is a
 * deliberate arrangement - a deployment that migrates a production database on
 * its own is a deployment that can lose data unattended - but it means every
 * release opens a window:
 *
 *     code deployed -> migration not yet run -> the new tables do not exist
 *
 * Gate 3's middleware runs on the WEB stack, not just on `/admin/security`.
 * `SecurityHeaders` reads a policy on every single response, and
 * `EnforceSessionPolicy` reads two more. Without this class, the first request
 * after a deploy queries a table that does not exist and the whole site returns
 * 500 - including the sign-in screen, so nobody can get in to notice. That was
 * measured, not guessed: `GET /sign-in` returned 500.
 *
 * WHY A SCHEMA CHECK AND NOT A `catch`. Catching a database exception and
 * falling back would also swallow a genuinely broken connection, a permissions
 * problem, or a corrupt table - and would report all of them as "everything is
 * fine, using defaults". This asks one specific question, "does this table
 * exist", and answers only that. Anything else still fails loudly, which is
 * what should happen.
 *
 * MEMOISED PER REQUEST, and the class is a container SINGLETON, so the answer
 * costs one schema query per request rather than one per middleware. It cannot
 * change within a request: a migration does not run halfway through one.
 */
class SecurityStorage
{
    /** @var array<string, bool> */
    private array $known = [];

    /** Whether `security_policies` exists yet. */
    public function policiesAreReady(): bool
    {
        return $this->tableExists('security_policies');
    }

    /** Whether `secret_references` exists yet. */
    public function secretReferencesAreReady(): bool
    {
        return $this->tableExists('secret_references');
    }

    /** Whether both gate 3 tables exist. */
    public function isReady(): bool
    {
        return $this->policiesAreReady() && $this->secretReferencesAreReady();
    }

    /**
     * What to tell somebody, when it is not ready.
     *
     * One sentence naming the cause and the fix, because "something went wrong"
     * during a deployment window sends somebody hunting through logs for a
     * condition that resolves itself the moment a known command is run.
     */
    public function blocker(): string
    {
        /*
         * Does NOT open by restating "security storage has not been
         * initialised". Every caller already leads with that as a heading or a
         * bold lead, so a sentence that repeats it reads as a stutter. Found by
         * looking at the rendered banner.
         */
        return 'The database migration for the Security release has not been run on this deployment yet, so '
            .'security policy and secret references cannot be stored or changed. An administrator with server '
            .'access needs to run the outstanding migrations.';
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
