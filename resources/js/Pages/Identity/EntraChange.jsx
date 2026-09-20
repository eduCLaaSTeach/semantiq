import { Link, useForm, usePage } from '@inertiajs/react'
import IdentityPage from '../../Components/IdentityPage'

/**
 * Change Microsoft Entra ID — the post-install SSO setup screen.
 *
 * THIS SCREEN CHANGES THE ONLY WAY ANYBODY SIGNS IN, and says so before the
 * fields rather than after them. Everything about it is shaped by that:
 *
 * NOTHING IS SAVED WHEN YOU PRESS THE BUTTON. The details are held, you are
 * sent to Microsoft to prove it is still you, and SemantIQ then checks the new
 * details against Microsoft before they take effect. If any step fails the
 * current settings are still in force — which is the sentence the page leads
 * with, because it is the one that makes this safe to try.
 *
 * THE CLIENT SECRET IS NEVER PRE-FILLED, on a screen that pre-fills everything
 * else. A secret in the page source would undo the masking the read-only screen
 * exists to provide, and there is no route that would return one.
 */
export default function EntraChange({ fields, isConfigured, secretIsSet }) {
    const { productAreas, errors } = usePage().props

    const form = useForm({
        tenant_id: fields.tenant_id ?? '',
        client_id: fields.client_id ?? '',
        redirect_uri: fields.redirect_uri ?? '',
        client_secret: '',
    })

    const submit = (event) => {
        event.preventDefault()
        form.put('/console/identity/entra')
    }

    return (
        <IdentityPage
            productAreas={productAreas}
            errors={errors}
            title="Change Microsoft Entra ID"
            description="Update the directory, application and secret SemantIQ uses to sign people in."
        >
            <div className="idn-notice">
                <p>
                    <strong>Nothing changes until this is confirmed and checked.</strong>
                </p>
                <ol className="idn-steps">
                    <li>You enter the new details here.</li>
                    <li>SemantIQ asks you to sign in with Microsoft again.</li>
                    <li>SemantIQ checks the new details against Microsoft.</li>
                    <li>Only then do they take effect.</li>
                </ol>
                <p className="org-hint-plain">
                    If you cancel, or the new details do not check out, your current sign-in
                    settings stay exactly as they are.
                </p>
            </div>

            <form onSubmit={submit} className="setup-form">
                <div className="setup-field">
                    <label htmlFor="entra-tenant">Directory (tenant) ID</label>
                    <input
                        id="entra-tenant"
                        type="text"
                        autoComplete="off"
                        value={form.data.tenant_id}
                        onChange={(event) => form.setData('tenant_id', event.target.value)}
                    />
                    <p className="setup-hint">
                        The directory in Microsoft Entra that your people sign in from.
                    </p>
                    {form.errors.tenant_id ? (
                        <p className="setup-error" role="alert">
                            {form.errors.tenant_id}
                        </p>
                    ) : null}
                </div>

                <div className="setup-field">
                    <label htmlFor="entra-client">Application (client) ID</label>
                    <input
                        id="entra-client"
                        type="text"
                        autoComplete="off"
                        value={form.data.client_id}
                        onChange={(event) => form.setData('client_id', event.target.value)}
                    />
                    <p className="setup-hint">
                        The application registered in Microsoft Entra for SemantIQ.
                    </p>
                    {form.errors.client_id ? (
                        <p className="setup-error" role="alert">
                            {form.errors.client_id}
                        </p>
                    ) : null}
                </div>

                <div className="setup-field">
                    <label htmlFor="entra-redirect">Sign-in return address</label>
                    <input
                        id="entra-redirect"
                        type="text"
                        autoComplete="off"
                        value={form.data.redirect_uri}
                        onChange={(event) => form.setData('redirect_uri', event.target.value)}
                    />
                    <p className="setup-hint">
                        Must match the redirect address registered on the application in Microsoft
                        Entra, exactly.
                    </p>
                    {form.errors.redirect_uri ? (
                        <p className="setup-error" role="alert">
                            {form.errors.redirect_uri}
                        </p>
                    ) : null}
                </div>

                <div className="setup-field">
                    <label htmlFor="entra-secret">Client secret</label>
                    <input
                        id="entra-secret"
                        type="password"
                        autoComplete="new-password"
                        placeholder={secretIsSet ? 'Saved — leave blank to keep it' : ''}
                        value={form.data.client_secret}
                        onChange={(event) => form.setData('client_secret', event.target.value)}
                    />
                    <p className="setup-hint">
                        {secretIsSet
                            ? 'A secret is saved. SemantIQ never shows it again. Leave this blank to keep it.'
                            : 'No secret is saved yet.'}
                    </p>
                    {form.errors.client_secret ? (
                        <p className="setup-error" role="alert">
                            {form.errors.client_secret}
                        </p>
                    ) : null}
                </div>

                {isConfigured ? (
                    <p className="setup-hint">
                        Microsoft sign-in is in use. You can replace these details, but you cannot
                        leave one of them empty — that would leave nobody able to sign in.
                    </p>
                ) : null}

                <div className="setup-form-actions">
                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Preparing…' : 'Continue to Microsoft'}
                    </button>

                    <Link className="org-action org-action-quiet" href="/console/identity">
                        Cancel
                    </Link>
                </div>
            </form>
        </IdentityPage>
    )
}
