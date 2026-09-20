import IntegrationForm from '../../Components/IntegrationForm'
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
 */
export default function Integrations({ productAreas, integrations }) {
    return (
        <AppShell productAreas={productAreas} title="Integrations">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Integrations</h1>
                    <p>
                        The services SemantIQ connects to. Only Microsoft sign-in is required —
                        the rest can be left unconfigured.
                    </p>
                </header>

                {integrations.map((integration) => (
                    <IntegrationForm
                        key={integration.family}
                        integration={integration}
                        updateUrl={`/console/integrations/${integration.family}`}
                        testUrl={`/console/integrations/${integration.family}/test`}
                    />
                ))}
            </div>
        </AppShell>
    )
}
