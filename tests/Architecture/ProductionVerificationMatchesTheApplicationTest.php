<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use Tests\TestCase;

/**
 * THE READ-ONLY PRODUCTION VERIFICATION WORKFLOWS MUST DESCRIBE THIS
 * APPLICATION, NOT A REMEMBERED ONE.
 *
 * A verification workflow is the only thing that can answer "what is actually
 * true on the server", so it is also the only thing whose being wrong is
 * invisible. It holds hard-coded expectations - the approved identity write
 * set, the six P1-10 tables, the size of the Audit key catalogue - and every
 * one of them is a SECOND COPY of something the application already decides.
 *
 * P1-10 produced the case that makes this worth a test. Gate C round 3 added
 * `PUT console/identity/entra`, and verify-identity.yml still carried a guard
 * that failed on ANY PUT under console/identity. Nothing in CI ran it, because
 * it is dispatched by hand, so the first sign would have been a red run on a
 * perfectly healthy deployment - and a gate that fails on a healthy system is
 * a gate people learn to ignore.
 *
 * These tests do not relax any of those expectations. They pin each one to the
 * thing it is a copy of, so drift fails here, in CI, in the same commit that
 * causes it.
 */
final class ProductionVerificationMatchesTheApplicationTest extends TestCase
{
    use RefreshDatabase;

    private const IDENTITY_WORKFLOW = '.github/workflows/verify-identity.yml';

    private const SETUP_WORKFLOW = '.github/workflows/verify-platform-setup.yml';

    /**
     * Mutation: add a second write route under console/identity, or change the
     * one in the workflow's list.
     */
    public function test_the_identity_verification_names_exactly_the_write_routes_that_exist(): void
    {
        $workflow = $this->workflow(self::IDENTITY_WORKFLOW);

        $this->assertSame(
            1,
            preg_match('/APPROVED_IDENTITY_WRITES = \[(.*?)\]/s', $workflow, $matches),
            'verify-identity.yml no longer declares APPROVED_IDENTITY_WRITES. The guard that keeps '
            .'Platform Integrations from growing an Identity write route lives there.',
        );

        preg_match_all("/'([^']+)'/", $matches[1], $quoted);

        $declared = $quoted[1];
        sort($declared);

        $actual = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/identity')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                /*
                 * GET and POST are not writes for this purpose, and that is the
                 * rule the workflow encodes rather than an oversight: the two
                 * POSTs under this prefix - reveal and re-check - change no
                 * identity configuration. A POST that DID would be a defect
                 * caught by test_the_identity_routes_are_exactly_the_approved_set,
                 * which asserts the whole set rather than only the writes.
                 */
                if (in_array($method, ['HEAD', 'OPTIONS', 'GET', 'POST'], true)) {
                    continue;
                }

                $actual[] = $method.' '.$route->uri();
            }
        }

        sort($actual);

        $this->assertSame(
            $actual,
            $declared,
            'The identity write routes and the set verify-identity.yml approves have diverged. '
            .'Dispatching that workflow would now fail on a healthy deployment, or pass over a '
            .'write route nobody approved.',
        );
    }

    /**
     * Mutation: rename any P1-10 table, or drop one from the workflow's list.
     */
    public function test_the_setup_verification_names_six_tables_that_all_exist(): void
    {
        $workflow = $this->workflow(self::SETUP_WORKFLOW);

        $this->assertSame(
            1,
            preg_match('/THE SIX TABLES, NAMED HERE RATHER THAN COUNTED\.(.*?)\] as /s', $workflow, $matches),
            'verify-platform-setup.yml no longer names the P1-10 tables it checks for.',
        );

        preg_match_all('/\\\\"([a-z_]+)\\\\"/', $matches[1], $quoted);

        $named = $quoted[1];

        $this->assertCount(
            6,
            $named,
            'The DESIGN named five tables and the implementation has six, because a whole privileged '
            .'change lives in one staged row. If that number changes again the verification workflow '
            .'and the amendment record in the verification document both have to say so.',
        );

        foreach ($named as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "verify-platform-setup.yml checks for a table named `{$table}`, which this "
                .'application does not create. The workflow would report a healthy deployment as '
                .'broken.',
            );
        }
    }

    /**
     * Mutation: change the 15 in the workflow, or add a key to the catalogue.
     */
    public function test_the_setup_verification_expects_the_real_audit_key_count(): void
    {
        $workflow = $this->workflow(self::SETUP_WORKFLOW);

        $this->assertSame(
            1,
            preg_match('/^\s*ALLOWED_KEYS = (\d+)$/m', $workflow, $matches),
            'verify-platform-setup.yml no longer pins the size of the Audit key catalogue.',
        );

        /** @var array<int, string> $keys */
        $keys = (new ReflectionClass(SecurityEventLogger::class))->getConstant('ALLOWED_KEYS');

        $this->assertSame(
            count($keys),
            (int) $matches[1],
            'D-160. The Audit key catalogue is closed at 15. The production verification workflow '
            .'and the catalogue itself must not be able to disagree about that.',
        );
    }

    private function workflow(string $path): string
    {
        $full = base_path($path);

        $this->assertFileExists($full, "The read-only verification workflow {$path} is missing.");

        return (string) file_get_contents($full);
    }
}
