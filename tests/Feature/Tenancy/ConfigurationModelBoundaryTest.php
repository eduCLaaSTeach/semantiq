<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\AuditEvent;
use App\Models\DataProtectionProfile;
use App\Models\FabricItem;
use App\Models\HelpTopic;
use App\Models\Organisation;
use App\Models\User;
use App\Models\WorkflowRun;
use App\Support\Tenancy\BelongsToOrganisation;
use App\Support\Tenancy\OrganisationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every customer-owned table added by the configuration data model, held to the
 * same boundary the users table is held to.
 *
 * OrganisationBoundaryTest proves the mechanism on one table. This proves the
 * mechanism was actually applied to each new one, which is the failure mode that
 * matters in practice: the scope is a trait somebody has to remember, and a
 * table that forgets it leaks silently rather than erroring.
 *
 * Written as a data provider over the model classes so that adding a table to
 * the list is one line, and so a future table with no entry here is a visible
 * omission rather than an invisible one.
 *
 * Requirement IDs: NFR-SEC-02.
 */
class ConfigurationModelBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $alpha;

    private Organisation $beta;

    private User $alphaUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = Organisation::factory()->create(['name' => 'Alpha Group']);
        $this->beta = Organisation::factory()->create(['name' => 'Beta Holdings']);

        $this->alphaUser = User::query()->create([
            'name' => 'Alpha Person', 'email' => 'alpha@example.test', 'password' => null,
        ]);
        $this->alphaUser->forceFill(['organisation_id' => $this->alpha->id])->save();
        $this->alphaUser->refresh();
    }

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function customerOwnedModels(): array
    {
        return [
            'workflow runs' => [WorkflowRun::class],
            'audit events' => [AuditEvent::class],
            'fabric items' => [FabricItem::class],
            'data protection profiles' => [DataProtectionProfile::class],
        ];
    }

    #[Test]
    #[DataProvider('customerOwnedModels')]
    public function a_customer_owned_model_carries_the_organisation_trait(string $model): void
    {
        $this->assertContains(
            BelongsToOrganisation::class,
            class_uses_recursive($model),
            $model.' is customer-owned but is not scoped by organisation'
        );
    }

    #[Test]
    #[DataProvider('customerOwnedModels')]
    public function another_organisations_rows_are_invisible(string $model): void
    {
        $mine = $model::factory()->create(['organisation_id' => $this->alpha->id]);
        $theirs = $model::factory()->create(['organisation_id' => $this->beta->id]);

        $this->actingAs($this->alphaUser);

        $visible = $model::query()->pluck('id')->all();

        $this->assertSame([$mine->id], $visible);
        $this->assertNull($model::query()->find($theirs->id), 'Reachable by primary key across the boundary');
    }

    #[Test]
    #[DataProvider('customerOwnedModels')]
    public function the_scope_fails_closed_with_no_organisation_context(string $model): void
    {
        $model::factory()->create(['organisation_id' => $this->alpha->id]);

        // Nobody signed in and nothing bound: no rows, never all rows.
        $this->assertSame(0, $model::query()->count());
    }

    #[Test]
    #[DataProvider('customerOwnedModels')]
    public function a_new_row_is_stamped_with_the_active_organisation(string $model): void
    {
        $this->actingAs($this->alphaUser);

        $attributes = $model::factory()->definition();
        unset($attributes['organisation_id']);

        $created = new $model;
        $created->forceFill($attributes)->save();

        $this->assertSame(
            $this->alpha->id,
            $created->organisation_id,
            $model.' was created without an organisation, which the scope would then hide from everyone'
        );
    }

    #[Test]
    public function help_topics_are_deliberately_not_scoped(): void
    {
        // The one exception, and it is a decision rather than an oversight:
        // help content is product documentation, identical for every customer.
        $this->assertNotContains(BelongsToOrganisation::class, class_uses_recursive(HelpTopic::class));

        HelpTopic::factory()->create(['topic_id' => 'HLP-TST-001']);

        $this->actingAs($this->alphaUser);
        $this->assertSame(1, HelpTopic::query()->count());

        // And still readable with nobody signed in, because help is not
        // customer data and a reader may need it before an organisation exists.
        app(OrganisationContext::class)->forget();
        $this->assertSame(1, HelpTopic::query()->count());
    }
}
