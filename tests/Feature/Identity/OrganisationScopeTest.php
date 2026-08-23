<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The organisation boundary, and the rule that it fails closed.
 *
 * CLAUDE.md requires customer-owned records to carry organisation scope and
 * cross-organisation access to be denied by default. The tests that matter here
 * are the negative ones: what happens when the context is MISSING, and what
 * happens when a second organisation exists. Both are the situations in which a
 * scoping mistake stops being theoretical.
 */
class OrganisationScopeTest extends TestCase
{
    use RefreshDatabase;

    private function context(): OrganisationContext
    {
        return app(OrganisationContext::class);
    }

    #[Test]
    public function the_bootstrap_organisation_exists_after_migration(): void
    {
        // Created by the migration rather than a seeder, because production runs
        // `migrate --force` and never runs seeders. Without it the context
        // resolves to nothing and, by design, everything scoped stops working.
        $organisation = Organisation::query()->where('code', config('platform.bootstrap_organisation_code'))->first();

        $this->assertNotNull($organisation);
        $this->assertTrue($organisation->isActive());
        $this->assertSame(LifecycleStatus::Active, $organisation->status);
    }

    #[Test]
    public function the_single_active_organisation_resolves_without_a_binding(): void
    {
        // The documented single-customer deployment baseline.
        $this->assertNotNull($this->context()->current());
        $this->assertSame('PRIMARY', $this->context()->current()?->code);
    }

    #[Test]
    public function a_second_organisation_stops_the_context_resolving_by_itself(): void
    {
        Organisation::query()->forceCreate(['code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1]);
        $this->context()->forget();

        // Deliberate. `first()` would silently pick one and run every scoped
        // query in the request against the wrong customer - the exact failure
        // the class exists to prevent. A multi-tenant instance must bind.
        $this->assertNull($this->context()->current());
        $this->assertNull($this->context()->currentId());
    }

    #[Test]
    public function an_explicit_binding_beats_automatic_resolution(): void
    {
        $second = Organisation::query()->forceCreate(['code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1]);

        $this->context()->bind($second);

        $this->assertSame($second->id, $this->context()->currentId());
    }

    #[Test]
    public function a_scoped_read_returns_nothing_when_no_context_is_in_force(): void
    {
        app(AuditLogger::class)->record('test.event', 'Platform');
        $this->assertSame(1, AuditEvent::query()->count());

        // The fail-closed rule. Losing the context must produce an empty screen
        // somebody investigates, never another customer's rows.
        Organisation::query()->forceCreate(['code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1]);
        $this->context()->forget();

        $this->assertSame(0, AuditEvent::query()->count());

        // The row is still there - it is hidden, not deleted.
        $this->assertSame(1, AuditEvent::withoutOrganisationScope()->count());
    }

    #[Test]
    public function a_scoped_write_is_refused_when_no_context_is_in_force(): void
    {
        Organisation::query()->forceCreate(['code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1]);
        $this->context()->forget();

        $this->expectException(RuntimeException::class);

        // An unattributable row in a configuration table is worse than no row.
        $this->context()->require();
    }

    #[Test]
    public function one_organisation_cannot_read_anothers_rows(): void
    {
        $first = $this->context()->require();
        app(AuditLogger::class)->record('first.event', 'Platform');

        $second = Organisation::query()->forceCreate(['code' => 'SECOND', 'name' => 'Second Customer', 'status' => 'active', 'version' => 1]);
        $this->context()->forget();
        $this->context()->bind($second);

        app(AuditLogger::class)->record('second.event', 'Platform');

        $this->assertSame(['second.event'], AuditEvent::query()->pluck('action')->all());

        $this->context()->forget();
        $this->context()->bind($first);

        $this->assertSame(['first.event'], AuditEvent::query()->pluck('action')->all());
    }

    #[Test]
    public function a_disabled_organisation_does_not_resolve_as_the_single_active_one(): void
    {
        Organisation::query()->where('code', 'PRIMARY')->update(['status' => LifecycleStatus::Disabled->value]);
        $this->context()->forget();

        // A disabled organisation is not soft-deleted: the evidence stays, but
        // nothing new may be written against it.
        $this->assertNull($this->context()->current());
    }
}
