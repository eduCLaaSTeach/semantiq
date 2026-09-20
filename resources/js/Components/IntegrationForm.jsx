import { useForm } from '@inertiajs/react'
import HealthStatusBadge from './HealthStatusBadge'

/**
 * One integration's form. Shared by First-Run and Platform Integrations.
 *
 * ONE COMPONENT, TWO SURFACES, ON PURPOSE. Two forms would be two validation
 * shapes and two ways of describing the same field — and "saving" would come
 * to mean something slightly different depending on which screen you were on.
 *
 * A SECRET FIELD IS ALWAYS BLANK AND NEVER PRE-FILLED. The server sends a
 * BOOLEAN saying whether one is set; there is no value in the props to render.
 * Leaving the field empty leaves the stored secret alone — which is why the
 * hint says so, rather than leaving an administrator to guess whether opening
 * this page to change a port has just wiped their password.
 *
 * "Last tested" IS ONLY SHOWN WHEN THERE IS A RESULT TO ATTACH IT TO. A
 * timestamp beside "Not checked" is a timestamp with no statement attached,
 * and it is exactly what misleads: the server clears both together when the
 * configuration changes, and this must not reintroduce half of it.
 */
export default function IntegrationForm({ integration, updateUrl, testUrl }) {
    const initial = {}

    for (const [field, value] of Object.entries(integration.fields)) {
        initial[field] = value ?? ''
    }

    for (const name of Object.keys(integration.secrets)) {
        initial[`secret_${name}`] = ''
    }

    const form = useForm(initial)
    const test = useForm({})

    const save = (event) => {
        event.preventDefault()
        form.put(updateUrl, { preserveScroll: true })
    }

    const runTest = (event) => {
        event.preventDefault()
        test.post(testUrl, { preserveScroll: true })
    }

    const showTestedAt = integration.lastTestedAt && integration.status !== 'not_checked'

    return (
        <section className="setup-panel">
            <div className="setup-panel-head">
                <div>
                    <h2>{integration.name}</h2>
                    <p className="setup-description">
                        {integration.explanation ?? 'This has not been checked yet.'}
                    </p>
                </div>

                <HealthStatusBadge status={integration.status} />
            </div>

            {showTestedAt ? (
                <p className="setup-meta">
                    Last checked {new Date(integration.lastTestedAt).toLocaleString()}
                </p>
            ) : null}

            <form onSubmit={save} className="setup-form">
                {Object.keys(integration.fields).map((field) => {
                    const choices = integration.choices?.[field]

                    return (
                        <div className="setup-field" key={field}>
                            <label htmlFor={`${integration.family}-${field}`}>{labelFor(field)}</label>

                            {/*
                             * A CHOICE GETS A SELECT, NOT A TEXT BOX.
                             *
                             * These were text inputs whose LABEL carried the
                             * permitted values - "AI service (azure_openai or
                             * openai)" - which put a raw enum value on a
                             * customer's screen and let somebody type
                             * "Azure OpenAI" and learn nothing until the
                             * connection test failed. The stored value is
                             * still the machine name; only what is shown
                             * changed.
                             */}
                            {choices ? (
                                <select
                                    id={`${integration.family}-${field}`}
                                    value={form.data[field] ?? ''}
                                    onChange={(event) => form.setData(field, event.target.value)}
                                >
                                    <option value="">Not chosen yet</option>
                                    {Object.entries(choices).map(([value, words]) => (
                                        <option value={value} key={value}>
                                            {words}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <input
                                    id={`${integration.family}-${field}`}
                                    type="text"
                                    autoComplete="off"
                                    value={form.data[field] ?? ''}
                                    onChange={(event) => form.setData(field, event.target.value)}
                                />
                            )}

                            {form.errors[field] ? (
                                <p className="setup-error" role="alert">
                                    {form.errors[field]}
                                </p>
                            ) : null}
                        </div>
                    )
                })}

                {Object.entries(integration.secrets).map(([name, configured]) => (
                    <div className="setup-field" key={name}>
                        <label htmlFor={`${integration.family}-${name}`}>{labelFor(name)}</label>
                        <input
                            id={`${integration.family}-${name}`}
                            type="password"
                            autoComplete="new-password"
                            placeholder={configured ? 'Saved — leave blank to keep it' : ''}
                            value={form.data[`secret_${name}`] ?? ''}
                            onChange={(event) => form.setData(`secret_${name}`, event.target.value)}
                        />
                        <p className="setup-hint">
                            {configured
                                ? 'A value is saved. SemantIQ never shows it again. Leave this blank to keep it.'
                                : 'No value is saved yet.'}
                        </p>
                    </div>
                ))}

                <div className="setup-form-actions">
                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save'}
                    </button>

                    <button
                        type="button"
                        className="org-action org-action-quiet"
                        onClick={runTest}
                        disabled={test.processing}
                    >
                        {test.processing ? 'Checking…' : 'Test connection'}
                    </button>
                </div>

                {test.errors.test ? (
                    <p className="setup-error" role="alert">
                        {test.errors.test}
                    </p>
                ) : null}

                <p className="setup-hint">
                    Testing checks that SemantIQ can reach this service with the details entered. It
                    does not send anything, read any data or change anything.
                </p>
            </form>
        </section>
    )
}

/**
 * Field names in a person's words.
 *
 * THE MAP IS EXHAUSTIVE BY DESIGN AND THE FALLBACK IS A LAST RESORT. A field
 * added without a label here would otherwise render as `workspace_id` on a
 * customer's screen — a raw database key on a user-facing surface, which is
 * the first item on the professional-polish gate.
 */
function labelFor(field) {
    const labels = {
        tenant_id: 'Directory (tenant) ID',
        client_id: 'Application (client) ID',
        client_secret: 'Client secret',
        redirect_uri: 'Redirect address',
        host: 'Mail server address',
        port: 'Port',
        encryption: 'Connection security',
        username: 'Username',
        password: 'Password',
        from_address: 'Send from address',
        from_name: 'Send from name',
        provider: 'AI service',
        endpoint: 'Service address',
        deployment: 'Model or deployment name',
        api_key: 'API key',
        workspace_id: 'Workspace ID',
    }

    return labels[field] ?? field.replace(/_/g, ' ')
}
