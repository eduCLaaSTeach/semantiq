import IntegrationForm from '../../Components/IntegrationForm'
import SetupShell from '../../Layouts/SetupShell'

/** Steps 3 to 6. One screen per integration, the same form as the console. */
export default function Integration({ integration, steps }) {
    return (
        <SetupShell
            title={integration.name}
            lead={
                integration.required
                    ? 'Required. SemantIQ cannot finish setting up without this.'
                    : 'Optional. Setup finishes without this, and you can add it at any time.'
            }
            steps={steps}
            current={integration.family}
        >
            {/*
             * reconfirm — D-159, the Bootstrap half.
             *
             * Replacing or removing a credential that is already saved asks
             * for the setup password again. It is NOT Microsoft step-up,
             * which is what the console uses: this screen exists precisely
             * because Microsoft may not be configured yet, so requiring it
             * here would make setup unsatisfiable.
             *
             * removeUrlFor is absent for Microsoft sign-in, because
             * /first-run/integration/identity/secret/... is not a route.
             * Setup may establish and replace it; removing it belongs to
             * Identity & SSO, once somebody can sign in to do so.
             */}
            <IntegrationForm
                integration={integration}
                updateUrl={`/first-run/integration/${integration.family}`}
                testUrl={`/first-run/integration/${integration.family}/test`}
                removeUrlFor={
                    integration.family === 'identity'
                        ? undefined
                        : (name) => `/first-run/integration/${integration.family}/secret/${name}`
                }
                sendTestUrl={
                    integration.family === 'email'
                        ? '/first-run/integration/email/send-test'
                        : undefined
                }
                reconfirm
            />
        </SetupShell>
    )
}
