<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy;

/**
 * Tables deliberately NOT collected into a subject access response, each with
 * the reason it is out of scope.
 *
 * THIS IS HALF OF THE COVERAGE CLAIM. The other half is `CollectorCatalogue`.
 * Between them every table in the live schema must be accounted for, and
 * `PrivacyCoverageTest` fails the build for any table that is in neither.
 *
 * WHY EXCLUSIONS NEED A WRITTEN REASON. "We do not collect that" is not a
 * position anybody can defend to a regulator, and it is not something a future
 * reader can check. "We do not collect the permission map because it is a
 * targeting list, and an access request must not become a route around
 * SEC-DEC-052" is both.
 *
 * A REASON IS NOT A LICENCE. Two of the entries below - the security ones - are
 * excluded from the DETAIL only. The subject is still told, at band C, that
 * they changed a security policy on a date; what they are not told is which
 * policy or what value. That distinction lives in the collectors, and the
 * entries here say so, because an exclusion register that reads as "invisible"
 * would be misleading in the safe-sounding direction.
 */
final class ExclusionRegister
{
    /**
     * Table name => why it is not collected.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return [
            'role_permissions' => 'The permission map is a targeting list: it states exactly which role can '
                .'reach which capability. SEC-DEC-052 established that such a map is more dangerous than the '
                .'rows it describes, and a subject access request must not become a way around that. The table '
                .'holds attribution only - who last changed a mapping - and that attribution is disclosed at '
                .'band C through the roles collector instead.',

            'security_policies' => 'Policy VALUES describe the control surface of the deployment: how sign-in '
                .'works, how long a session lasts, which API controls are on. Disclosing them to anybody who '
                .'files a request would hand out a survey of the defences. The fact that this person changed a '
                .'security policy on a date IS disclosed, at band C, by its own collector - what is withheld is '
                .'which policy and what value.',

            'secret_references' => 'The credential map is a targeting list (SEC-DEC-052): where every secret '
                .'this system depends on lives, and when it lapses. The subject is told they own secret '
                .'references AS A COUNT, never as a list and never with a pointer, which answers the request '
                .'without publishing the map.',

            'cache' => 'Ephemeral infrastructure. Values are written and evicted continuously and are not '
                .'records about a person; anything personal in there is a transient copy of data collected '
                .'properly from its owning table. Declared as a known incidental surface rather than collected, '
                .'because collecting it would return a snapshot that is wrong by the time it is read.',

            'cache_locks' => 'Ephemeral infrastructure. Holds lock names and expiry, no personal data.',

            'jobs' => 'Queue infrastructure. This deployment runs QUEUE_CONNECTION=sync with no worker, so the '
                .'table is empty. A payload could name a person if a queue were ever enabled; declared '
                .'incidental, and revisited if a worker is ever approved.',

            'job_batches' => 'Queue infrastructure. Batch bookkeeping only, and empty on this deployment for '
                .'the same reason as `jobs`.',

            'failed_jobs' => 'Queue infrastructure. An exception trace could incidentally contain a name; empty '
                .'on this deployment, and declared incidental rather than collected because a stack trace is '
                .'not a record about a person and returning one would disclose the internals of the system.',

            'migrations' => 'Schema bookkeeping. Migration file names and batch numbers. No personal data of '
                .'any kind.',
        ];
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return array_keys($this->all());
    }

    /**
     * The two tables that are excluded in their DETAIL but still produce a
     * band C item, so they legitimately appear in the collector catalogue too.
     *
     * DECLARED, NOT MERELY TOLERATED. `PrivacyCoverageTest` asserts that the
     * overlap between this register and the catalogue is EXACTLY this set. A
     * new overlap therefore fails the build rather than passing quietly as an
     * assumed intention - which matters, because an accidental overlap is how
     * a table ends up excluded on paper and collected in fact.
     *
     * @return list<string>
     */
    public function detailOnly(): array
    {
        return ['security_policies', 'secret_references'];
    }

    public function covers(string $table): bool
    {
        return array_key_exists($table, $this->all());
    }

    public function reasonFor(string $table): ?string
    {
        return $this->all()[$table] ?? null;
    }
}
