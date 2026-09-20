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
            <IntegrationForm
                integration={integration}
                updateUrl={`/first-run/integration/${integration.family}`}
                testUrl={`/first-run/integration/${integration.family}/test`}
            />
        </SetupShell>
    )
}
