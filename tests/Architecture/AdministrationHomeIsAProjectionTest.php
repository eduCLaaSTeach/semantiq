<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Administration\Support\Readiness;
use App\Modules\Platform\Setup\Support\IntegrationView;
use App\Modules\SystemHealth\Report\HealthStatus;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P1-11 COMPOSES. IT DOES NOT QUERY, IT DOES NOT WRITE, AND IT CONTACTS
 * NOBODY.
 *
 * A rule in a docblock lasts until the next person is in a hurry. P1-10 proved
 * that twice in one unit, so every claim Administration Home makes about itself
 * is asserted here against the files rather than trusted.
 *
 * EVERY SOURCE SCAN STRIPS COMMENTS FIRST. P1-10's tab-class guard searched a
 * file for `org-tabs` and was satisfied by the docblock explaining why the
 * component uses `org-tabs` - a guard defeated, and satisfiable, by prose. The
 * notes in this module necessarily name DB, Eloquent, inspect() and
 * EntraDiscovery to explain why they are absent, so a guard reading them would
 * be failed by its own explanation and the "fix" would be to delete the
 * explanation. Code only.
 */
final class AdministrationHomeIsAProjectionTest extends TestCase
{
    private const MODULE = __DIR__.'/../../app/Modules/Administration';

    /** @return array<string, string> relative path => code with comments stripped */
    private function moduleCode(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::MODULE));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $files[basename($file->getPathname())] = $this->withoutComments(
                (string) file_get_contents($file->getPathname())
            );
        }

        ksort($files);

        $this->assertGreaterThanOrEqual(
            6,
            count($files),
            'Almost no P1-11 files were scanned, so every guard in this file would pass against '
            .'an empty directory.'
        );

        return $files;
    }

    private function withoutComments(string $source): string
    {
        $code = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * G3. NO ELOQUENT, NO QUERY BUILDER, NO TABLE, NO MODEL.
     *
     * The whole of P1-11's claim to compose rather than query rests on this. A
     * count written here would live in a dashboard rather than in the module
     * that owns the fact, and the screen that summarises Users & Groups could
     * then start disagreeing with Users & Groups itself.
     *
     * THE VIEWER IS THE ONE PERMITTED MODEL. Every authorising path in the
     * application names Platform\Models\User; a guard that banned it would have
     * forced the viewer to be passed as an untyped array, which is worse code
     * and a worse guard.
     *
     * Mutation: add `use Illuminate\Support\Facades\DB;`, or
     * `User::query()->count()`, or `Group::query()`, to
     * AdministrationHomeProjection.
     */
    public function test_no_p1_11_file_queries_anything(): void
    {
        $forbidden = [
            'Illuminate\\Database',
            'Illuminate\\Support\\Facades\\DB',
            'DB::',
            '::query(',
            'Eloquent',
            'AccessReviewItem',
            'BusinessDomain',
            'DomainOwnership',
            'business_domain',
            'GroupMembership',
            'group_memberships',
            'LegalEntity',
            'BusinessUnit',
            'Department',
            'Team',
            'whereNull',
            'whereHas',
            '->count()',
        ];

        foreach ($this->moduleCode() as $name => $code) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    "[{$name}] contains [{$needle}]. P1-11 composes; it does not query. Every fact "
                    .'on this screen belongs to the unit that owns it, and a query written here '
                    .'is that unit\'s scoping rule copied into a dashboard.'
                );
            }
        }
    }

    /**
     * G3 is not vacuous: the scanner finds what it is looking for when it is
     * there.
     *
     * A scanner pointed at the wrong directory, or one whose comment stripper
     * removed the whole file, passes by scanning nothing - the failure mode a
     * "no offenders" assertion cannot detect on its own.
     */
    public function test_the_scan_reads_real_code_and_would_catch_a_violation(): void
    {
        $code = $this->moduleCode();

        $this->assertArrayHasKey('AdministrationHomeProjection.php', $code);

        // Real code survived the stripper...
        $this->assertStringContainsString(
            'final class AdministrationHomeProjection',
            $code['AdministrationHomeProjection.php'],
        );

        // ...and the prose did not, which is what makes the scan about code.
        $this->assertStringNotContainsString(
            'A rule in a docblock lasts until',
            $code['AdministrationHomeProjection.php'],
            'Comments survived the stripper, so every guard here could be satisfied - or '
            .'defeated - by prose.'
        );

        // And a planted violation is caught.
        $this->assertStringContainsString(
            '::query(',
            $this->withoutComments('<?php User::query()->count();'),
        );
    }

    /**
     * G7. NO NETWORK NAME APPEARS ANYWHERE IN P1-11.
     *
     * The zero-network guarantee is structural rather than promised:
     * SystemHealthReport is built on inspectLocal() and storedReport(), which
     * P1-09 wrote precisely so a screen could contact nobody and mean it.
     * inspect() reaches report() -> trustAvailability(), which asks Microsoft
     * on a cold discovery cache.
     *
     * Mutation: call HealthInspector::inspect() instead of reading
     * SystemHealthReport::areas().
     */
    public function test_nothing_in_p1_11_can_reach_an_outside_service(): void
    {
        $forbidden = [
            'EntraDiscovery',
            'ConnectionTester',
            'semantiq:health',
            'Http::',
            '->inspect(',
            '->report(',
            'HealthInspector',
            'ProviderProbe',
            'curl',
            'file_get_contents',
        ];

        foreach ($this->moduleCode() as $name => $code) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $code,
                    "[{$name}] names [{$needle}]. Opening Administration Home must contact nobody, "
                    .'and there must be no code path by which it could.'
                );
            }
        }
    }

    /**
     * P1-11 WRITES NOTHING AND CACHES NOTHING - D-141, D-160.
     *
     * A stale summary is the failure this unit is most exposed to, and the
     * screen's answer is "right now". Rendering a summary also records nothing:
     * SecurityEventLogger's catalogue stays at fifteen keys.
     *
     * Mutation: wrap the composition in Cache::remember(), or log an event when
     * the screen is opened.
     */
    public function test_p1_11_writes_nothing_caches_nothing_and_records_nothing(): void
    {
        $forbidden = [
            'Cache::', 'cache(', 'remember(', 'SecurityEventLogger', 'Log::',
            '->save(', '->update(', '->create(', '->delete(', 'Schema::', 'Migration',
        ];

        foreach ($this->moduleCode() as $name => $code) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsString($needle, $code, "[{$name}] contains [{$needle}].");
            }
        }
    }

    /**
     * G9. THE ADMINISTRATION ROUTE SET IS EXACTLY ONE GET.
     *
     * AN EQUALITY, not "a GET exists". Naming the routes that do exist is
     * satisfied by any superset, and the claim is that nothing on this screen
     * can change anything - which only an equality states.
     *
     * Mutation: add any second verb or any second URI under console/
     * administration.
     */
    public function test_the_administration_route_set_is_exactly_one_get(): void
    {
        $found = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'console/administration')) {
                continue;
            }

            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD'], true)) {
                    continue;
                }

                $found[] = $method.' '.$route->uri();
            }
        }

        sort($found);

        $this->assertSame(
            ['GET console/administration'],
            $found,
            'The Administration route set is not exactly one GET. Nothing on this screen changes '
            .'anything, so there is nothing for a second verb to do.'
        );
    }

    /**
     * ONE DEPLOYMENT, ONE WORD PER STATUS - the rule
     * OneStatusVocabularyTest already holds between HealthStatusBadge and
     * Integrations, extended to the readiness vocabulary P1-11 introduces.
     *
     * Readiness is a DIFFERENT question from health - an organisation is not
     * "degraded", it either exists or has not been created - so it has its own
     * words. But where the two overlap they must be the SAME words, or "Needs
     * attention" would mean two things two inches apart on one screen.
     *
     * Mutation: change Readiness::NotConfigured to read "Not set up".
     */
    public function test_readiness_reuses_the_health_words_where_the_two_overlap(): void
    {
        $this->assertSame(
            IntegrationView::statusInWords(HealthStatus::NotConfigured),
            Readiness::NotConfigured->inWords(),
        );

        $this->assertSame(
            IntegrationView::statusInWords(HealthStatus::Degraded),
            Readiness::NeedsAttention->inWords(),
        );

        // And the vocabulary is exactly the approved four - D-179 gives
        // Organisation two, D-181 gives Business Domains three, and they share
        // Not configured. A fifth word would be a reading nobody approved.
        $this->assertSame(
            ['Configured', 'Ready', 'Needs attention', 'Not configured'],
            array_map(static fn (Readiness $r): string => $r->inWords(), Readiness::cases()),
        );
    }

    /**
     * EVERY READINESS TONE IS AN EXISTING STATUS CLASS, so the screen adds no
     * colour system.
     *
     * Asserted against the STYLESHEET, not against a list repeated here: a tone
     * that names a class the stylesheet does not declare renders as an unstyled
     * pill, which no build step and no test would otherwise catch.
     *
     * Mutation: give Readiness::Ready a tone of 'ready'.
     */
    public function test_every_readiness_tone_is_a_status_class_the_stylesheet_declares(): void
    {
        $css = (string) file_get_contents(__DIR__.'/../../resources/css/app.css');

        foreach (Readiness::cases() as $reading) {
            $this->assertStringContainsString(
                '.sys-status-'.$reading->tone(),
                $css,
                "Readiness [{$reading->value}] wears .sys-status-{$reading->tone()}, which the "
                .'stylesheet does not declare. An undeclared status class renders as an unstyled '
                .'pill and fails only when somebody looks.'
            );
        }
    }

    /**
     * G4. THE TWO NEW SEAMS LIVE IN THE MODULES THAT OWN THE FACTS.
     *
     * Adding a projection during P1-11's implementation does not transfer
     * ownership to P1-11. This is what makes that true a year from now rather
     * than only today.
     *
     * Mutation: move PeopleSummaryProjection into App\Modules\Administration.
     */
    public function test_the_read_seams_live_in_the_modules_that_own_them(): void
    {
        foreach ([
            'app/Modules/People/Projection/PeopleSummary.php',
            'app/Modules/People/Projection/PeopleSummaryProjection.php',
            'app/Modules/Domains/Projection/DomainSummary.php',
            'app/Modules/Domains/Projection/DomainSummaryProjection.php',
            'app/Modules/Reviews/Projection/ReviewSummary.php',
            'app/Modules/Reviews/Projection/ReviewSummaryProjection.php',
        ] as $path) {
            $this->assertFileExists(
                __DIR__.'/../../'.$path,
                "[{$path}] is not where its module owns it."
            );
        }

        foreach ($this->moduleCode() as $name => $code) {
            foreach (['class PeopleSummary', 'class DomainSummary', 'class ReviewSummary'] as $seam) {
                $this->assertStringNotContainsString(
                    $seam,
                    $code,
                    "[{$name}] declares [{$seam}]. A seam that moved into its consumer is not a "
                    .'seam - it is the consumer owning somebody else\'s fact.'
                );
            }
        }
    }

    /**
     * THE SCREEN RENDERS NOTHING IT WAS NOT GIVEN.
     *
     * There is no JavaScript test runner in this repository - no CI test
     * renders the DOM - so the component's claim to compute nothing is
     * asserted against its source.
     *
     * Mutation: add `tile.metrics.length ? ... : 0` to the tile, or derive a
     * status from a count in the component.
     */
    public function test_the_screen_derives_no_state_of_its_own(): void
    {
        $screen = (string) file_get_contents(
            __DIR__.'/../../resources/js/Pages/Administration/Home.jsx'
        );

        /*
         * COMMENTS STRIPPED, for the same reason every PHP scan above strips
         * them: the notes explaining why a verdict is never written here
         * necessarily name the verdicts, and a guard failed by its own
         * explanation teaches the wrong lesson.
         */
        $code = (string) preg_replace('#/\*[\s\S]*?\*/|//[^\n]*#', '', $screen);

        /*
         * WORD-BOUNDARY MATCHED, because "available" is inside "unavailable" -
         * and "Not available" is one of the three D-139 STATE WORDS this
         * component legitimately owns.
         *
         * The distinction is the point. `withheld` and `unavailable` are states
         * the SERVER decided and the component names, exactly as
         * HealthStatusBadge names the six health statuses. A VERDICT - healthy,
         * Configured, Ready, Act now - is a judgement about a source, and if it
         * appeared here it would be one the server never sent.
         */
        foreach ([
            'healthy', 'Configured', 'Ready', 'Act now', 'critical',
            'not_configured', 'degraded', 'Needs attention',
        ] as $verdict) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b'.preg_quote($verdict, '/').'\b/',
                $code,
                "The screen contains the literal [{$verdict}]. Every status on this page was "
                .'decided by the unit that owns the fact; a verdict written into the component is '
                .'one the server never sent.'
            );
        }

        // The stripper did not eat the file, so the absences above mean
        // something.
        $this->assertStringContainsString('export default function Home', $code);

        // ...and the screen renders no number of its own: a count only ever
        // comes out of the metrics array the server built.
        $this->assertStringNotContainsString('?? 0', $code);
        $this->assertStringNotContainsString('|| 0', $code);
    }
}
