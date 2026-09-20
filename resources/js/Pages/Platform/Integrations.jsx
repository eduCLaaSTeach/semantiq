import IntegrationForm from '../../Components/IntegrationForm'
import IntegrationSummaryCard from '../../Components/IntegrationSummaryCard'
import AppShell from '../../Layouts/AppShell'

/**
 * Platform Integrations — the same settings First-Run writes, after setup.
 *
 * THE SAME FORM COMPONENT AS FIRST-RUN, deliberately. Two forms would be two
 * ways of describing the same field, and "saving" would come to mean something
 * slightly different depending on which screen you were on.
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
export default function Integrations({ productAreas, integrations, summaries }) {
    return (
        <AppShell productAreas={productAreas} title="Integrations">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Integrations</h1>
                    <p>
                        The services SemantIQ connects to. Only Microsoft sign-in is required — the
                        rest can be left unconfigured.
                    </p>
                </header>

                {(summaries ?? []).map((integration) => (
                    <IntegrationSummaryCard
                        key={integration.family}
                        integration={integration}
                        manageUrl="/console/identity"
                        manageLabel="Manage Identity & SSO"
                    />
                ))}

                {integrations.map((integration) => (
                    <IntegrationForm
                        key={integration.family}
                        integration={integration}
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
                ))}
            </div>
        </AppShell>
    )
}
