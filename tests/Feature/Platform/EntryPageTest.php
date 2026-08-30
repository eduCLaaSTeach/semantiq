<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

final class EntryPageTest extends TestCase
{
    public function test_the_entry_page_is_served_to_an_unauthenticated_visitor(): void
    {
        $this->get('/')->assertOk();
    }

    /**
     * The blueprint requires that an unauthenticated browser receives no
     * protected shell, menu or business metadata. This asserts the absence of
     * the things that would constitute one.
     */
    public function test_the_entry_page_leaks_no_protected_structure(): void
    {
        $body = $this->get('/')->getContent();

        foreach ([
            'System Administration',
            'Fabric Configuration',
            'SemantIQ Workplace',
            'shell-rail',
        ] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $body,
                "The pre-authentication entry page exposed [{$forbidden}]."
            );
        }
    }
}
