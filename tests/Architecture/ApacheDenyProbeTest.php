<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Guards the temporary Apache denial-capability matrix.
 *
 * The matrix answers one question: when the front controller moves to
 * public_html/index.php and the catch-all forwarder disappears, will any deny
 * rule in this file actually refuse a request? Today none of them has been
 * observed to fire - every protected path returns 404 from Laravel, never 403
 * from Apache - so the forwarder is doing all the protecting.
 *
 * Three mechanisms are measured:
 *
 *   A  mod_rewrite       RewriteRule ... [R=403,L]   (plus the [F,L] control)
 *   B  mod_alias         RedirectMatch 403
 *   C  mod_authz_core    <Files> + Require all denied
 *
 * A diagnostic that cannot fail proves nothing, and each of these is easy to
 * render meaningless by accident. These tests exist to stop that:
 *
 *  - a rule placed after the catch-all never runs, and reports a 404 that
 *    looks like a measurement but is only the forwarder;
 *  - a mechanism C probe file that the rewrite rules already deny would return
 *    403 from the wrong module, and be read as a success for <Files>;
 *  - a mechanism C probe file the forwarder cannot serve would return 404,
 *    which is indistinguishable from "the file is not there";
 *  - an application route on any probe path turns a critical 200 into what
 *    looks like ordinary routing;
 *  - without the literal ErrorDocument, a denial that IS firing can be masked
 *    back into a Laravel 404 and reported as a negative.
 */
final class ApacheDenyProbeTest extends TestCase
{
    private const REWRITE_403_PROBE = '__semantiq_rewrite_403_probe';

    private const REWRITE_F_PROBE = '__semantiq_apache_deny_probe';

    private const ALIAS_403_PROBE = '__semantiq_alias_403_probe';

    private const FILES_DENY_PROBE = '__semantiq_files_deny_probe.txt';

    private const FILES_CONTROL_PROBE = '__semantiq_files_control_probe.txt';

    private const ERROR_DOCUMENT_MARKER = 'SEMANTIQ_403_ERRORDOCUMENT_MARKER';

    private const FILE_BODY_MARKER = 'SEMANTIQ_DENY_DIAGNOSTIC_ONLY';

    private const HTACCESS = __DIR__.'/../../deployment/public_html.htaccess';

    private const WORKFLOW = __DIR__.'/../../.github/workflows/deploy.yml';

    private function htaccess(): string
    {
        return file_get_contents(self::HTACCESS);
    }

    private function workflow(): string
    {
        return file_get_contents(self::WORKFLOW);
    }

    /**
     * Every probe path must be distinct. Two mechanisms sharing a path would
     * measure whichever module happened to win and report it as both.
     */
    public function test_every_probe_path_is_distinct(): void
    {
        $paths = [
            self::REWRITE_403_PROBE,
            self::REWRITE_F_PROBE,
            self::ALIAS_403_PROBE,
            self::FILES_DENY_PROBE,
            self::FILES_CONTROL_PROBE,
        ];

        $this->assertSame(
            $paths,
            array_values(array_unique($paths)),
            'Two probes share a path, so the matrix cannot attribute a result to a mechanism.'
        );
    }

    // ---- mechanism A: mod_rewrite ---------------------------------------

    public function test_mechanism_a_denies_with_an_explicit_403_status(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\s*RewriteRule \^'.preg_quote(self::REWRITE_403_PROBE, '/').'\(\/\|\$\) - \[R=403,L\]\s*$/m',
            $this->htaccess(),
            'Mechanism A must deny with [R=403,L]. Any other flag tests a different thing.'
        );
    }

    public function test_the_f_flag_control_is_retained_for_same_run_comparison(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\s*RewriteRule \^'.preg_quote(self::REWRITE_F_PROBE, '/').'\(\/\|\$\) - \[F,L\]\s*$/m',
            $this->htaccess(),
            'The [F,L] control is what makes [R=403] a comparison rather than a fresh guess.'
        );
    }

    /**
     * Order is the whole point. A deny rule after the catch-all never runs, and
     * the probe would report 404 while proving nothing at all.
     */
    public function test_both_rewrite_probes_precede_the_catch_all_forwarder(): void
    {
        $htaccess = $this->htaccess();
        $catchAll = strpos($htaccess, 'RewriteRule ^(.*)$ public/$1');
        $this->assertNotFalse($catchAll, 'Catch-all forwarder not found.');

        foreach ([self::REWRITE_403_PROBE, self::REWRITE_F_PROBE] as $probe) {
            $position = strpos($htaccess, 'RewriteRule ^'.$probe);
            $this->assertNotFalse($position, "Probe rule for {$probe} not found.");
            $this->assertLessThan(
                $catchAll,
                $position,
                "The {$probe} rule sits after the catch-all forwarder, so it can never fire. "
                .'A 404 from that arrangement would prove nothing.'
            );
        }
    }

    // ---- mechanism B: mod_alias -----------------------------------------

    public function test_mechanism_b_uses_mod_alias(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\s*RedirectMatch 403 \^\/\?'.preg_quote(self::ALIAS_403_PROBE, '/').'\(\/\|\$\)\s*$/m',
            $this->htaccess(),
            'Mechanism B must be a mod_alias RedirectMatch 403 whose leading slash is optional. '
            .'.htaccess context can strip the directory prefix, so an anchor of "^/" alone would '
            .'miss the request and report a false negative.'
        );
    }

    /**
     * B is only an independent mechanism if it is genuinely in another module.
     * Inside the rewrite block it would be a second rewrite rule wearing a
     * different name, and a shared cause would look like two confirmations.
     */
    public function test_mechanism_b_sits_outside_the_rewrite_block(): void
    {
        $this->assertStringNotContainsString(
            'RedirectMatch',
            $this->rewriteBlock(),
            'Mechanism B is inside <IfModule mod_rewrite.c>, so it is not an independent module.'
        );
    }

    // ---- mechanism C: <Files> + Require all denied ------------------------

    public function test_mechanism_c_denies_the_probe_file(): void
    {
        $this->assertMatchesRegularExpression(
            '/<Files "'.preg_quote(self::FILES_DENY_PROBE, '/').'">\s*\n\s*Require all denied\s*\n\s*<\/Files>/',
            $this->htaccess(),
            'Mechanism C must be <Files> + Require all denied on the probe file.'
        );
    }

    public function test_mechanism_c_sits_outside_the_rewrite_block(): void
    {
        $this->assertStringNotContainsString(
            self::FILES_DENY_PROBE,
            $this->rewriteBlock(),
            'Mechanism C must not be expressed as a rewrite rule; it tests a different module.'
        );
    }

    /**
     * The control file must be reachable. If it were denied too, a 403 on the
     * guarded file could be some blanket refusal rather than this directive,
     * and the whole mechanism C result would be unattributable.
     */
    public function test_the_mechanism_c_control_file_carries_no_deny_directive(): void
    {
        $htaccess = $this->htaccess();

        $this->assertStringNotContainsString(
            '<Files "'.self::FILES_CONTROL_PROBE.'">',
            $htaccess,
            'The control file is denied, so it can no longer serve as a control.'
        );

        foreach ($this->denyPatterns() as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                self::FILES_CONTROL_PROBE,
                "A rewrite deny rule [{$pattern}] also blocks the control file."
            );
        }
    }

    /**
     * The heart of the design. Mechanism C is the only probe whose negative is
     * falsifiable, and that rests entirely on the guarded file being genuinely
     * servable when the deny fails. If any rewrite deny rule matched it first,
     * a 403 would come from mod_rewrite and be misread as a <Files> success -
     * exactly the confusion this whole diagnostic exists to end.
     */
    public function test_no_rewrite_deny_rule_shadows_the_mechanism_c_probe_file(): void
    {
        foreach ($this->denyPatterns() as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                self::FILES_DENY_PROBE,
                "The rewrite deny rule [{$pattern}] blocks the mechanism C probe file. "
                .'A 403 would then come from mod_rewrite, not from <Files>.'
            );
        }
    }

    /**
     * The probe file has to live where the forwarder resolves it - inside
     * public/ - or a failed deny returns 404 rather than the 200 that makes the
     * negative readable.
     */
    public function test_the_mechanism_c_probe_files_are_created_inside_public(): void
    {
        $workflow = $this->workflow();

        foreach ([self::FILES_DENY_PROBE, self::FILES_CONTROL_PROBE] as $file) {
            $this->assertStringContainsString(
                '$CPANEL_DEPLOY_PATH/public/'.$file,
                $workflow,
                "The workflow does not create {$file} inside public/, so the forwarder cannot serve it "
                .'and a failed deny would return 404 instead of 200.'
            );
        }

        $this->assertStringContainsString(
            self::FILE_BODY_MARKER,
            $workflow,
            'The probe file needs a known body, or a 200 cannot be told apart from any other 200.'
        );
    }

    // ---- the instrument ---------------------------------------------------

    /**
     * A literal-string ErrorDocument answers from memory: no internal redirect,
     * so no second pass through the forwarder, so a real 403 cannot be
     * laundered into Laravel's 404 page. A path-valued ErrorDocument would
     * reintroduce exactly the masking this measures.
     */
    public function test_the_error_document_instrument_is_a_literal_string(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\s*ErrorDocument 403 "'.preg_quote(self::ERROR_DOCUMENT_MARKER, '/').'"\s*$/m',
            $this->htaccess(),
            'ErrorDocument 403 must be a quoted literal. A URL path would be re-served through the '
            .'forwarder and could return as a 404, masking the very denial being measured.'
        );

        $this->assertStringNotContainsString(
            'ErrorDocument 404',
            $this->htaccess(),
            'Overriding 404 would replace Laravel\'s own not-found page.'
        );
    }

    // ---- blast radius -----------------------------------------------------

    /**
     * If the application answered a probe path, a 200 would read as ordinary
     * routing rather than the critical failure it would actually be.
     */
    public function test_no_application_route_answers_any_probe_path(): void
    {
        $probes = [
            self::REWRITE_403_PROBE,
            self::REWRITE_F_PROBE,
            self::ALIAS_403_PROBE,
            self::FILES_DENY_PROBE,
            self::FILES_CONTROL_PROBE,
        ];

        foreach (Route::getRoutes() as $route) {
            foreach ($probes as $probe) {
                $this->assertStringNotContainsString(
                    $probe,
                    $route->uri(),
                    "Route [{$route->uri()}] answers the probe path {$probe}."
                );
            }
        }

        foreach ($probes as $probe) {
            $this->get('/'.$probe)->assertNotFound();
        }
    }

    /**
     * Nothing diagnostic may be committed. The files exist only for the seconds
     * between the workflow creating them and the trap removing them.
     */
    public function test_no_probe_file_is_committed_to_the_repository(): void
    {
        $probes = [
            self::REWRITE_403_PROBE,
            self::REWRITE_F_PROBE,
            self::ALIAS_403_PROBE,
            self::FILES_DENY_PROBE,
            self::FILES_CONTROL_PROBE,
        ];

        foreach ($probes as $probe) {
            foreach ([base_path($probe), public_path($probe)] as $path) {
                $this->assertFileDoesNotExist($path, "A real file sits at the probe path {$probe}.");
            }
        }
    }

    /**
     * Cleanup is not optional and not conditional. A public diagnostic file
     * left behind is a worse outcome than an unanswered question, so it has to
     * survive a failing probe, a failing assertion and a cancelled job.
     */
    public function test_the_workflow_removes_the_probe_files_on_every_exit_path(): void
    {
        $workflow = $this->workflow();

        $this->assertMatchesRegularExpression(
            '/trap cleanup EXIT/',
            $workflow,
            'Probe file cleanup is not trapped, so a failure mid-diagnostic would leave the files public.'
        );

        $this->assertMatchesRegularExpression(
            '/rm -f .*deny_file.*ctrl_file/',
            $workflow,
            'The cleanup does not remove both probe files.'
        );
    }

    /**
     * .well-known carries the ACME challenge. If any probe were inserted above
     * it, TLS renewal would fail silently, weeks later.
     */
    public function test_no_probe_displaces_the_acme_passthrough(): void
    {
        $htaccess = $this->htaccess();
        $acme = strpos($htaccess, 'RewriteRule ^\\.well-known/');

        $this->assertNotFalse($acme, 'The .well-known passthrough is missing.');

        foreach ([self::REWRITE_403_PROBE, self::REWRITE_F_PROBE] as $probe) {
            $this->assertLessThan(
                strpos($htaccess, 'RewriteRule ^'.$probe),
                $acme,
                ".well-known must still be matched before {$probe}."
            );
        }
    }

    /**
     * The diagnostic may only ever add refusals. If it removed a production
     * deny rule the exposure suite would still pass on paths that no longer
     * exist, and the loss would surface as a breach rather than a test failure.
     */
    public function test_the_production_deny_rules_are_intact(): void
    {
        $htaccess = $this->htaccess();

        foreach ([
            'RewriteRule (^|/)\\. - [F,L]',
            'RewriteRule ^(app|bootstrap|config|database|doc|deployment|node_modules|resources|routes|storage|tests|vendor)(/|$) - [F,L]',
            'RewriteRule ^(.*)$ public/$1 [L]',
            'Require all denied',
        ] as $rule) {
            $this->assertStringContainsString(
                $rule,
                $htaccess,
                "The diagnostic removed a production rule: {$rule}"
            );
        }
    }

    /**
     * The body of the <IfModule mod_rewrite.c> block, used to prove that the
     * mechanisms claimed to be independent really are in other modules.
     */
    private function rewriteBlock(): string
    {
        $htaccess = $this->htaccess();

        $start = strpos($htaccess, '<IfModule mod_rewrite.c>');
        $this->assertNotFalse($start, 'The mod_rewrite block is missing.');

        $end = strpos($htaccess, '</IfModule>', $start);
        $this->assertNotFalse($end, 'The mod_rewrite block is not closed.');

        return substr($htaccess, $start, $end - $start);
    }

    /**
     * Every rewrite deny rule except the probes themselves, as PCRE, so the
     * mechanism C probe file can be tested against the rules that would
     * otherwise shadow it.
     *
     * @return list<string>
     */
    private function denyPatterns(): array
    {
        preg_match_all(
            '/^\s*RewriteRule (\S+) - \[(?:F|R=403),L\]\s*$/m',
            $this->htaccess(),
            $matches
        );

        $this->assertNotEmpty($matches[1], 'No rewrite deny rules were found to test against.');

        $probes = [self::REWRITE_403_PROBE, self::REWRITE_F_PROBE];

        $patterns = [];

        foreach ($matches[1] as $pattern) {
            foreach ($probes as $probe) {
                if (str_contains($pattern, $probe)) {
                    continue 2;
                }
            }

            $patterns[] = '/'.str_replace('/', '\\/', $pattern).'/';
        }

        return $patterns;
    }
}
