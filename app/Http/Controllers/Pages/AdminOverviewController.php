<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * The Administration landing page.
 *
 * The ten-step setup journey from PHASE-00-UI-SHELL.md section 8. Held here
 * rather than in the view because the same list will drive real status checks
 * once the steps can report one, and a list living in a template is a list that
 * gets copied.
 */
class AdminOverviewController extends Controller
{
    /**
     * `automated` records whether SemantIQ can complete the step through a
     * supported API, or whether Microsoft requires a manual action that
     * SemantIQ can only guide and then validate. It is the first thing an
     * administrator needs to know: one costs a click, the other costs a trip to
     * a portal and a tenant admin's time.
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

    public function __invoke(): View
    {
        return view('pages.admin-overview', ['steps' => self::STEPS]);
    }
}
