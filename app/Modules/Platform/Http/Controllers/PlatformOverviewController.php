<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Identity\Support\OrganisationContext;
use App\Modules\Platform\Support\HealthProbe;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The Administration landing page. Feature ADM-001.
 *
 * One screen answering two questions an administrator has in this order: is the
 * platform working, and what do I have to do next.
 *
 * MENU_STRUCTURE.md section 12.1 lists eight things under Platform Overview -
 * Setup Progress, Environment Health, Data Health, Intelligence Health,
 * Security and Sovereignty Status, Pending Actions, Failed Automations and
 * Recent Changes. All eight are SECTIONS OF THIS PAGE rather than eight rail
 * entries. They are eight views of one question, and eight clicks to assemble
 * one answer is not a control plane. Nothing in the menu structure is dropped:
 * the sync table in doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md records
 * where each one lands.
 *
 * NOTHING ON THIS PAGE MAY EXPOSE A CREDENTIAL. Every detail string comes from
 * `HealthProbe`, which redacts, and the audit summaries are already redacted at
 * write time. ADM-001's acceptance criteria name this explicitly.
 */
class PlatformOverviewController extends Controller
{
    /**
     * The ten-step setup journey.
     *
     * Held in code rather than in the view because the same list drives real
     * status checks as each step becomes checkable, and a list living in a
     * template is a list that gets copied.
     *
     * `automated` is the first thing an administrator needs to know about a
     * step: one costs a click, the other costs a trip to a Microsoft portal and
     * a tenant administrator's time. CLAUDE.md requires that a portal-only
     * action be presented as a guided workflow rather than as a dead end.
     */
    private const STEPS = [
        ['name' => 'Connect organisation and Microsoft tenant', 'detail' => 'Register the organisation and link the Entra tenant SemantIQ will work in.', 'automated' => false, 'role' => 'Microsoft tenant administrator'],
        ['name' => 'Configure identity and permissions', 'detail' => 'Single sign-on, the automation identity and the API permissions it needs.', 'automated' => false, 'role' => 'Microsoft tenant administrator'],
        ['name' => 'Validate the Fabric environment', 'detail' => 'Check capacity, tenant settings and service principal access before anything is built.', 'automated' => true, 'role' => 'System Administrator'],
        ['name' => 'Configure workspaces and geography', 'detail' => 'Create the DEV, TEST and PROD workspaces inside an approved data geography.', 'automated' => true, 'role' => 'System Administrator'],
        ['name' => 'Connect data sources', 'detail' => 'Register each source, authenticate it and confirm the connection works.', 'automated' => true, 'role' => 'Administrator'],
        ['name' => 'Build the data foundation', 'detail' => 'Ingestion, the medallion layers and the schedules that keep them current.', 'automated' => true, 'role' => 'Administrator'],
        ['name' => 'Configure the business model', 'detail' => 'Business entities, keys, relationships and the glossary that names them.', 'automated' => true, 'role' => 'Domain Owner'],
        ['name' => 'Configure semantic intelligence', 'detail' => 'Measures, KPIs and the security that governs who sees which rows.', 'automated' => true, 'role' => 'Domain Owner'],
        ['name' => 'Prepare AI and data agents', 'detail' => 'Approved data, business instructions, verified answers and validation.', 'automated' => true, 'role' => 'Administrator'],
        ['name' => 'Validate and go live', 'detail' => 'Acceptance, sovereignty evidence and the production release.', 'automated' => false, 'role' => 'System Administrator'],
    ];

    /** How many recent changes the page shows before it stops being a summary. */
    private const RECENT_CHANGE_LIMIT = 8;

    public function __invoke(HealthProbe $probe, OrganisationContext $organisations): View
    {
        $checks = $probe->run();

        return view('pages.admin.platform-overview', [
            'organisation' => $organisations->current(),
            'version' => $probe->applicationVersion(),
            'environment' => (string) app()->environment(),
            'checks' => $checks,
            'overall' => $probe->overall($checks),
            /* The warnings, restated as a to-do list. The same facts twice on
             * purpose: the checks answer "is it working", this answers "what do
             * I do", and an administrator arrives wanting one or the other. */
            'pending' => array_values(array_filter($checks, fn ($check): bool => $check->needsAttention())),
            'steps' => self::STEPS,
            'recentChanges' => $this->recentChanges(),
        ]);
    }

    /**
     * The last few audited changes.
     *
     * Scoped to the current organisation by the model's global scope, so no
     * cross-customer row can appear here even if this instance later holds
     * more than one. The summaries were redacted when they were written, so
     * nothing sensitive can reach the page through them.
     *
     * @return Collection<int, AuditEvent>
     */
    private function recentChanges(): Collection
    {
        return AuditEvent::query()
            ->with('actor:id,name,email')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::RECENT_CHANGE_LIMIT)
            ->get();
    }
}
