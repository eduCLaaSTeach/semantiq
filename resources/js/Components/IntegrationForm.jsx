import { useForm } from '@inertiajs/react'
import { useState } from 'react'
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
export default function IntegrationForm({
    integration,
    updateUrl,
    testUrl,
    removeUrlFor,
    reconfirm = false,
}) {
    const initial = {}

    for (const [field, value] of Object.entries(integration.fields)) {
        initial[field] = value ?? ''
    }

    for (const name of Object.keys(integration.secrets)) {
        initial[`secret_${name}`] = ''
    }

    if (reconfirm) {
        initial.password = ''
    }

    const form = useForm(initial)
    const test = useForm({})
    const removal = useForm(reconfirm ? { password: '' } : {})

    // WHICH CREDENTIAL THE ADMINISTRATOR HAS ASKED TO REMOVE, if any. Removal
    // is never one click: the confirmation says which credential, by name, and
    // says what it will cost.
    const [removing, setRemoving] = useState(null)

    const save = (event) => {
        event.preventDefault()
        form.put(updateUrl, { preserveScroll: true })
    }

    const runTest = (event) => {
        event.preventDefault()
        test.post(testUrl, { preserveScroll: true })
    }

    /*
     * REMOVAL IS ITS OWN VERB, ITS OWN ROUTE AND ITS OWN CONFIRMATION.
     *
     * It is NEVER inferred from a blank password box. The hint two lines above
     * promises that leaving the field empty keeps the saved value, and it has
     * to keep meaning that: anybody who opened this page to change a port would
     * otherwise destroy a working integration without being asked a thing.
     */
    const confirmRemoval = (name) => {
        removal.delete(removeUrlFor(name), {
            preserveScroll: true,
            onSuccess: () => {
                setRemoving(null)
                removal.reset()
            },
        })
    }

    const showTestedAt = integration.lastTestedAt && integration.status !== 'not_checked'

    const savedSecrets = Object.entries(integration.secrets).filter(([, saved]) => saved)
    const hasSavedSecret = savedSecrets.length > 0
    const canRemove = typeof removeUrlFor === 'function'

    return (
        <section className="setup-panel">
            <div className="setup-panel-head">
                <div>
                    <h2>{integration.name}</h2>
                    <p className="setup-description">
                        {integration.explanation ?? describe(integration.status)}
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
                            <label htmlFor={`${integration.family}-${field}`}>
                                {labelFor(field)}
                            </label>

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

                {/*
                 * THE RE-CONFIRMATION FIELD, DURING SETUP ONLY.
                 *
                 * Replacing an established credential asks for the setup
                 * password again — Microsoft step-up is what the console
                 * uses, and Microsoft may not exist yet on a deployment that
                 * is still being set up.
                 *
                 * It is shown only when there IS something to replace.
                 * Asking for a password to establish the first value would be
                 * a confirmation with nothing to confirm, and teaches people
                 * to type it without reading.
                 */}
                {reconfirm && hasSavedSecret ? (
                    <div className="setup-field">
                        <label htmlFor={`${integration.family}-reconfirm`}>
                            Confirm with your setup password
                        </label>
                        <input
                            id={`${integration.family}-reconfirm`}
                            type="password"
                            autoComplete="current-password"
                            value={form.data.password ?? ''}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                        <p className="setup-hint">
                            Replacing a credential that is already saved needs your setup password
                            again.
                        </p>

                        {form.errors.password ? (
                            <p className="setup-error" role="alert">
                                {form.errors.password}
                            </p>
                        ) : null}
                    </div>
                ) : null}

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

            {/*
             * SAVED CREDENTIALS — Gate C correction 4B.
             *
             * OUTSIDE THE SAVE FORM, not nested in it. HTML has no nested
             * forms, and a confirmation that shared the save form's submit
             * would mean pressing Enter in the removal box saved the
             * settings instead of removing anything.
             *
             * IT IS SHOWN ONLY WHEN THERE IS SOMETHING TO REMOVE. An empty
             * "Saved credentials" heading over a blank space on an
             * integration nobody has configured is a section that exists to
             * say nothing.
             */}
            {canRemove && hasSavedSecret ? (
                <div className="setup-removal">
                    <h3>Saved credentials</h3>

                    {savedSecrets.map(([name]) => (
                        <div className="setup-removal-item" key={name}>
                            <p className="setup-removal-name">
                                {labelFor(name)}
                                <span className="setup-removal-state"> — saved</span>
                            </p>

                            {removing === name ? (
                                <div className="setup-removal-confirm">
                                    <p className="setup-hint">
                                        Removing this deletes the saved{' '}
                                        {labelFor(name).toLowerCase()}. SemantIQ cannot recover it,
                                        and {integration.name} will stop working until a new one is
                                        entered.
                                    </p>

                                    {reconfirm ? (
                                        <div className="setup-field">
                                            <label htmlFor={`${integration.family}-${name}-remove`}>
                                                Confirm with your setup password
                                            </label>
                                            <input
                                                id={`${integration.family}-${name}-remove`}
                                                type="password"
                                                autoComplete="current-password"
                                                value={removal.data.password ?? ''}
                                                onChange={(event) =>
                                                    removal.setData('password', event.target.value)
                                                }
                                            />
                                        </div>
                                    ) : (
                                        <p className="setup-hint">
                                            You will be asked to sign in with Microsoft again before
                                            this takes effect.
                                        </p>
                                    )}

                                    {removal.errors.password ? (
                                        <p className="setup-error" role="alert">
                                            {removal.errors.password}
                                        </p>
                                    ) : null}

                                    {removal.errors.secret ? (
                                        <p className="setup-error" role="alert">
                                            {removal.errors.secret}
                                        </p>
                                    ) : null}

                                    <div className="setup-form-actions">
                                        <button
                                            type="button"
                                            className="org-action org-action-danger"
                                            onClick={() => confirmRemoval(name)}
                                            disabled={removal.processing}
                                        >
                                            {removal.processing
                                                ? 'Removing…'
                                                : `Remove saved ${labelFor(name).toLowerCase()}`}
                                        </button>

                                        <button
                                            type="button"
                                            className="org-action org-action-quiet"
                                            onClick={() => {
                                                setRemoving(null)
                                                removal.reset()
                                            }}
                                        >
                                            Keep it
                                        </button>
                                    </div>
                                </div>
                            ) : (
                                <button
                                    type="button"
                                    className="org-action org-action-quiet"
                                    onClick={() => setRemoving(name)}
                                >
                                    Remove saved credential
                                </button>
                            )}
                        </div>
                    ))}
                </div>
            ) : null}
        </section>
    )
}

/**
 * What to say when the server has no sentence of its own.
 *
 * THE BADGE AND THE SENTENCE MUST NOT CONTRADICT EACH OTHER. This was a single
 * hard-coded "This has not been checked yet.", which read correctly while
 * "Not checked" was the only unconfigured state there was. Gate C correction 4A
 * split that into two, and the fallback stayed behind — so an integration
 * nobody had set up showed a "Not configured" badge beside a sentence telling
 * the reader to go and test it.
 *
 * Found by looking at the rendered screen, not by a test: both halves were
 * individually correct and only the combination was wrong.
 */
function describe(status) {
    if (status === 'not_configured') {
        return 'This has not been set up yet.'
    }

    if (status === 'not_applicable') {
        return 'This does not apply to this deployment.'
    }

    return 'This has not been checked yet.'
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
