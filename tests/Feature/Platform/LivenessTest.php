<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

final class LivenessTest extends TestCase
{
    /**
     * An allowlist, not a secret denylist.
     *
     * A denylist has to anticipate every secret a future edit might add. An
     * allowlist cannot be outgrown: adding any field at all fails this test,
     * which is how these endpoints stop drifting into version and hostname
     * disclosure.
     */
    public function test_the_body_is_one_of_exactly_two_permitted_words(): void
    {
        $body = trim($this->get('/up')->getContent());

        $this->assertContains($body, ['ok', 'unhealthy'], "/up returned unexpected content: [{$body}]");
    }

    /**
     * The reason /up is registered outside the web middleware group.
     *
     * The session driver is `database`. A liveness route inside the web group
     * starts a session, so it cannot answer when the database is down - which is
     * exactly when a monitor needs an answer. Local verification caught this as a
     * 500 with a stack trace where a plain 503 belonged.
     */
    public function test_it_reports_503_rather_than_crashing_when_the_database_is_down(): void
    {
        config(['database.connections.broken' => [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1,
            'database' => 'nope', 'username' => 'nope', 'password' => 'nope',
        ]]);
        config(['database.default' => 'broken']);

        $response = $this->get('/up');

        $this->assertSame(503, $response->getStatusCode(), '/up must degrade to 503, never 500.');
        $this->assertSame('unhealthy', trim($response->getContent()));
    }

    public function test_it_holds_no_session_and_sets_no_cookie(): void
    {
        $response = $this->get('/up');

        $this->assertEmpty(
            $response->headers->getCookies(),
            '/up set a cookie, which means it is inside the session middleware group.'
        );
    }

    public function test_it_discloses_no_version_host_path_or_environment(): void
    {
        $response = $this->get('/up');
        $body = $response->getContent();

        foreach ([app()->version(), PHP_VERSION, base_path(), config('app.env')] as $secret) {
            $this->assertStringNotContainsString((string) $secret, $body);
        }
    }
}
