<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

/**
 * Guards the Apache denial boundary that PR #38 proved works.
 *
 * Under D-08B the cPanel document root is public_html and the whole Laravel
 * tree is deployed inside it, so deployment/public_html.htaccess is the
 * security boundary rather than a defence-in-depth extra.
 *
 * The history matters, because it is what these tests are protecting against a
 * repeat of. For weeks every protected path answered 404 and none answered 403,
 * and the reasonable-looking conclusion was drawn that the deny rules were not
 * firing and the catch-all forwarder was doing all the protecting. That was
 * wrong. PR #38 measured four independent mechanisms and all four denied: the
 * rules had been executing the whole time. What was broken was the reported
 * status - a path-valued ErrorDocument re-entered the rewrite chain, met the
 * forwarder, reached Laravel and came back as a 404.
 *
 * Nothing was ever exposed. But for weeks nobody could tell the difference
 * between a working boundary and a broken one, which is its own kind of
 * failure. These tests keep that distinguishable:
 *
 *  - the denial must stay OBSERVABLE, so ErrorDocument 403 must remain a
 *    literal. Point it at a path and the masking returns;
 *  - the denial must stay SILENT, revealing no framework, path or internals;
 *  - the rules themselves must stay present and correctly ordered;
 *  - no synthetic diagnostic endpoint may return to production.
 */
final class ApacheDenialBoundaryTest extends TestCase
{
    private const HTACCESS = __DIR__.'/../../deployment/public_html.htaccess';

    private const WORKFLOW = __DIR__.'/../../.github/workflows/deploy.yml';

    /**
     * Paths the Apache boundary owns. The deployment gate requires 403 on every
     * one of them, and each was observed returning 403 in production before
     * that gate was tightened.
     *
     * @return list<string>
     */
    public static function protectedPaths(): array
    {
        return [
            '.env', '.env.example', '.gitignore', '.git/config',
            'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
            'artisan', 'phpunit.xml', 'vite.config.js',
            'app/', 'bootstrap/', 'config/', 'database/', 'resources/', 'routes/',
            'storage/', 'tests/', 'vendor/', 'deployment/', 'doc/',
            'README.md', 'deployment/public_html.htaccess', 'storage/logs/laravel.log',
        ];
    }

    private function htaccess(): string
    {
        return file_get_contents(self::HTACCESS);
    }

    private function workflow(): string
    {
        return file_get_contents(self::WORKFLOW);
    }

    /**
     * The single most important line in the file, and the least obvious.
     *
     * A quoted literal is answered from memory: no internal redirect, no second
     * pass through the rewrite engine, no Laravel. A path-valued ErrorDocument
     * is re-served through the forwarder and comes back as a 404 - which is the
     * exact defect that hid a working boundary for weeks.
     */
    public function test_the_403_error_document_is_a_literal_not_a_path(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\s*ErrorDocument 403 "[^"]*"\s*$/m',
            $this->htaccess(),
            'ErrorDocument 403 must be a quoted literal. A path is re-served through the '
            .'forwarder and returns 404, masking the denial and blinding the exposure gate.'
        );
    }

    /**
     * A denial is a refusal, not a disclosure. Anything naming the framework,
     * a server path or a version tells an attacker what to try next.
     */
    public function test_the_denial_response_reveals_nothing(): void
    {
        preg_match('/^\s*ErrorDocument 403 "([^"]*)"\s*$/m', $this->htaccess(), $m);

        $this->assertNotEmpty($m, 'No literal ErrorDocument 403 found.');

        $body = $m[1];

        foreach (['laravel', 'php', 'apache', 'semantiq', '/home/', 'public_html', 'vendor', 'stack'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                strtolower($body),
                "The 403 body mentions [{$forbidden}]. A denial must reveal nothing about what it is protecting."
            );
        }

        $this->assertLessThanOrEqual(
            64,
            strlen($body),
            'The 403 body is long enough to be carrying detail it should not.'
        );
    }

    /**
     * Losing one of these is silent: the exposure gate would still pass on
     * paths that no longer exist, and the loss would surface as a breach rather
     * than a test failure.
     */
    public function test_every_production_deny_rule_is_present(): void
    {
        $htaccess = $this->htaccess();

        foreach ([
            'RewriteRule ^\\.well-known/ - [L]',
            'RewriteRule (^|/)\\. - [F,L]',
            'RewriteRule ^(app|bootstrap|config|database|doc|deployment|node_modules|resources|routes|storage|tests|vendor)(/|$) - [F,L]',
            'Require all denied',
        ] as $rule) {
            $this->assertStringContainsString($rule, $htaccess, "Missing production rule: {$rule}");
        }

        $this->assertMatchesRegularExpression(
            '/RewriteRule \(\^\|\/\)\(composer.*artisan.*\)\$ - \[F,L\]/',
            $htaccess,
            'The sensitive-filename deny rule is missing.'
        );
    }

    /**
     * Order is not stylistic. A deny rule after the catch-all never runs, and
     * .well-known before the dotfile rule is what keeps TLS renewal working -
     * breaking it fails silently, weeks later.
     */
    public function test_the_rules_are_ordered_so_they_can_actually_fire(): void
    {
        $htaccess = $this->htaccess();

        $acme = strpos($htaccess, 'RewriteRule ^\\.well-known/');
        $dotfiles = strpos($htaccess, 'RewriteRule (^|/)\\. - [F,L]');
        $directories = strpos($htaccess, 'RewriteRule ^(app|bootstrap|config');
        $catchAll = strpos($htaccess, 'RewriteRule ^(.*)$ public/$1');

        $this->assertNotFalse($acme);
        $this->assertNotFalse($dotfiles);
        $this->assertNotFalse($directories);
        $this->assertNotFalse($catchAll);

        $this->assertLessThan($dotfiles, $acme, '.well-known must precede the dotfile deny, or ACME renewal breaks.');
        $this->assertLessThan($catchAll, $dotfiles, 'The dotfile deny must precede the catch-all, or it never runs.');
        $this->assertLessThan($catchAll, $directories, 'The directory deny must precede the catch-all, or it never runs.');
    }

    /**
     * The diagnostics answered their question and were removed. A synthetic
     * endpoint that survives into production is an unowned, undocumented
     * surface nobody is testing.
     */
    public function test_no_diagnostic_probe_survives_in_production_configuration(): void
    {
        foreach ([self::HTACCESS => 'the forwarder', self::WORKFLOW => 'the deploy workflow'] as $file => $label) {
            $this->assertStringNotContainsString(
                '__semantiq_',
                file_get_contents($file),
                "A synthetic diagnostic probe remains in {$label}."
            );
        }

        $this->assertStringNotContainsString(
            'SEMANTIQ_403_ERRORDOCUMENT_MARKER',
            $this->htaccess(),
            'The diagnostic marker is still the production 403 body.'
        );
    }

    /**
     * The gate is the only thing that observes the boundary in production, and
     * it is only as good as its strictness. Accepting 404 again would restore
     * the blind spot: a request that fell through Apache into Laravel would
     * pass, which is precisely the regression worth catching.
     */
    public function test_the_deployment_gate_requires_403_and_never_accepts_404(): void
    {
        $workflow = $this->workflow();

        $this->assertStringNotContainsString(
            '403|404)',
            $workflow,
            'The exposure gate still accepts 404 as a pass. A 404 means the request reached '
            .'Laravel instead of being denied by Apache.'
        );

        $this->assertMatchesRegularExpression(
            '/404\)\s*\n\s*echo "::error/',
            $workflow,
            'The exposure gate must treat 404 as an explicit failure.'
        );
    }

    /**
     * Every path the Apache boundary owns has to be in the gate. A protected
     * path nobody requests is a protected path nobody has verified.
     */
    public function test_the_deployment_gate_covers_every_protected_path(): void
    {
        $workflow = $this->workflow();

        foreach (self::protectedPaths() as $path) {
            $this->assertStringContainsString(
                '"'.$path.'"',
                $workflow,
                "The exposure gate does not request [{$path}]."
            );
        }
    }

    /**
     * Under D-08B this file is the boundary, so the server copy must be the
     * copy under review. Without this, every other test here reasons about a
     * file Apache might not be reading.
     */
    public function test_the_deployment_verifies_the_served_htaccess_matches_the_repository(): void
    {
        $workflow = $this->workflow();

        $this->assertStringContainsString(
            'sha256sum deployment/public_html.htaccess',
            $workflow,
            'The deployment no longer checksums the repository .htaccess.'
        );

        $this->assertMatchesRegularExpression(
            '/The deployed \.htaccess does NOT match.*\n\s*exit 1/',
            $workflow,
            'A checksum mismatch must fail the deployment.'
        );
    }
}
