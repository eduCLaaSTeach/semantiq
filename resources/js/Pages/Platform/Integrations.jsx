import { usePage } from '@inertiajs/react'
import IntegrationForm from '../../Components/IntegrationForm'
import IntegrationSummaryCard from '../../Components/IntegrationSummaryCard'
import HealthStatusBadge from '../../Components/HealthStatusBadge'
import IntegrationsTabs from '../../Components/IntegrationsTabs'
import AppShell from '../../Layouts/AppShell'

/**
 * Platform Integrations — the same settings First-Run writes, after setup.
 *
 * GATE D UI CORRECTION. THE SAME SHAPE AS EVERY OTHER FEATURE.
 *
 *   FEATURE   Integrations, with what the feature is for
 *   TAB       the integration you are looking at, route-backed
 *   CONTENT   that integration's own heading and body
 *
 * This screen used to be a column of four detached cards on one URL — the only
 * System Administration feature that had invented its own information
 * architecture. The Product Owner compared it with Organisation and it did not
 * look like the same product. It now uses the same chrome, the same `org-`
 * classes and the same Pattern B strip as Organisation, Users and groups,
 * Identity & SSO, Security Status and Audit.
 *
 * THE HEADING STAYS "Integrations", because that is the approved menu wording
 * in ApprovedMenu and a screen whose title disagrees with the menu item that
 * opened it is worse than one whose title is short.
 *
 * ONE TAB'S CONFIGURATION AT A TIME. The server sends `integration` OR
 * `summary`, never both and never all four, so the three tabs you are not
 * looking at are not in this page's source at all.
 *
 * NOTHING HERE SHOWS A SECRET. The server sends a boolean saying whether one is
 * set; there is no value in the props to render, and no route that would return
 * one.
 *
 * MICROSOFT SIGN-IN IS A SUMMARY AND A LINK — D-148, Gate C correction 3. It
 * arrives in a different prop, from a different projection method, with no
 * fields and no secrets, and there is no route that would accept a write for it
 * from here. The split is the server's, not this component's choice.
 */
export default function Integrations({ productAreas, tabs, integration, summary }) {
    const { url } = usePage()

    // One of the two is always present: the server sends the writable view or
    // the summary for the active family, never both and never neither.
    const active = integration ?? summary

    return (
        <AppShell productAreas={productAreas} title="Integrations">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Integrations</h1>
                    <p>
                        Configure and monitor the external services SemantIQ uses for sign-in,
                        notifications, AI services and Microsoft Fabric. Only Microsoft sign-in is
                        required — the rest can be left unconfigured.
                    </p>
                </header>

                <IntegrationsTabs path={url} tabs={tabs} />

                {/*
                 * SECTION HEAD, exactly where Organisation, System Health and
                 * six others put theirs: between the strip and the content,
                 * naming the section you opened, with its status as the section
                 * action. The cards below are told not to repeat the heading.
                 *
                 * `describedAs` is what the integration is FOR and comes from
                 * IntegrationFamily. `explanation` is what the last check SAID
                 * and belongs to the status beside it - rendering the second
                 * one here would put a stale test result where a description
                 * should be.
                 */}
                <div className="org-section-head">
                    <div>
                        <h2>{active.name}</h2>
                        <p className="org-description">{active.describedAs}</p>
                    </div>

                    <div className="org-section-actions">
                        <HealthStatusBadge status={active.status} />
                    </div>
                </div>

                {summary ? (
                    <IntegrationSummaryCard
                        integration={summary}
                        manageUrl="/console/identity"
                        manageLabel="Manage Identity & SSO"
                        showHeading={false}
                    />
                ) : null}

                {integration ? (
                    <IntegrationForm
                        key={integration.family}
                        integration={integration}
                        showHeading={false}
                        updateUrl={`/console/integrations/${integration.family}`}
                        testUrl={`/console/integrations/${integration.family}/test`}
                        removeUrlFor={(name) =>
                            `/console/integrations/${integration.family}/secret/${name}`
                        }
                        // D-153. Only Email can send, so only Email is offered it.
                        sendTestUrl={
                            integration.family === 'email'
                                ? '/console/integrations/email/send-test'
                                : undefined
                        }
                    />
                ) : null}
            </div>
        </AppShell>
    )
}
