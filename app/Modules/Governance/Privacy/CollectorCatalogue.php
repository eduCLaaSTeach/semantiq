<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy;

use App\Modules\Governance\Privacy\Collectors\AccessCollector;
use App\Modules\Governance\Privacy\Collectors\AccountCollector;
use App\Modules\Governance\Privacy\Collectors\ActivityCollector;
use App\Modules\Governance\Privacy\Collectors\AttributionCollector;
use App\Modules\Governance\Privacy\Collectors\NarrativeCollector;
use App\Modules\Governance\Privacy\Collectors\SecurityHoldingsCollector;
use RuntimeException;

/**
 * Every collector, and therefore every table in scope for a subject access
 * response.
 *
 * IN CODE, NOT IN A TABLE, for the same reason the permission registry is:
 * widening the scope of what SemantIQ will disclose about a person should
 * require a pull request that somebody reads, not an UPDATE nobody sees.
 *
 * Together with `ExclusionRegister` this must account for every table in the
 * live schema. `PrivacyCoverageTest` reconciles the two against the real
 * schema and fails the build for any table that is in neither - which is what
 * stops the scope silently going stale as gates 5, 6 and 7 add tables.
 *
 * The constructor refuses a duplicate claim. Two collectors returning the same
 * table would each believe they were authoritative for it, and the response
 * would contain that table twice under two different treatments.
 */
final class CollectorCatalogue
{
    /** @var list<SubjectCollector>|null */
    private ?array $collectors = null;

    /**
     * @return list<SubjectCollector>
     */
    public function all(): array
    {
        return $this->collectors ??= $this->build();
    }

    /**
     * Every table any collector claims.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $tables = [];

        foreach ($this->all() as $collector) {
            foreach ($collector->tables() as $table) {
                $tables[] = $table;
            }
        }

        return array_values(array_unique($tables));
    }

    public function covers(string $table): bool
    {
        return in_array($table, $this->tables(), true);
    }

    /**
     * @return list<SubjectCollector>
     */
    private function build(): array
    {
        $collectors = [
            new AccountCollector,
            new ActivityCollector,
            new AccessCollector,
            new AttributionCollector,
            new SecurityHoldingsCollector,
            new NarrativeCollector,
        ];

        $this->assertNoTableIsClaimedTwice($collectors);

        return $collectors;
    }

    /**
     * @param  list<SubjectCollector>  $collectors
     */
    private function assertNoTableIsClaimedTwice(array $collectors): void
    {
        $seen = [];

        foreach ($collectors as $collector) {
            foreach ($collector->tables() as $table) {
                if (isset($seen[$table])) {
                    throw new RuntimeException(
                        "The table `{$table}` is claimed by both ".$seen[$table].' and '.$collector::class
                        .'. Exactly one collector must be authoritative for a table, or the assembled '
                        .'response will contain it twice under two different treatments.'
                    );
                }

                $seen[$table] = $collector::class;
            }
        }
    }
}
