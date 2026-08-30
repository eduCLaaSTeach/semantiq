<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

/**
 * Deny-by-default, proven before there is anything behind it.
 *
 * P1-BASE has no identity provider, so every authenticated route must refuse.
 * P1-00 replaces how identity is RESOLVED; it does not replace what happens when
 * resolution yields nothing, which stays deny.
 */
final class DenyByDefaultTest extends TestCase
{
    public function test_the_authenticated_area_refuses_an_anonymous_request(): void
    {
        $this->get('/app')->assertRedirect('/');
    }

    public function test_the_refusal_reveals_nothing_about_what_was_requested(): void
    {
        $response = $this->get('/app');

        $this->assertStringNotContainsString('System Administration', $response->getContent());
        $this->assertStringNotContainsString('shell-rail', $response->getContent());
    }

    public function test_a_json_client_is_refused_with_401_and_no_payload(): void
    {
        $this->getJson('/app')
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }
}
