import { useForm } from '@inertiajs/react'
import SetupShell from '../../Layouts/SetupShell'

/**
 * Step 7. Nominate the first permanent System Administrator.
 *
 * THE LINK IS SHOWN ONCE AND CANNOT BE SHOWN AGAIN, and the screen says so
 * before the administrator navigates away rather than after. Only its SHA-256
 * is stored, so there is nothing to re-display — which is a property of the
 * design, not a gap in this page.
 *
 * IT IS NOT EMAILED. Email is optional in SemantIQ's setup (D-171), and a
 * required step that depended on an optional one would let an installation
 * reach this screen and be unable to finish. The operator conveys the link
 * however they already convey things.
 *
 * THE NOMINATED PERSON MUST BE IN THE CONFIGURED MICROSOFT DIRECTORY. Said
 * plainly here, because the alternative is a link that fails at redemption for
 * a reason the person holding it cannot see.
 */
export default function FirstAdministrator({ steps, identityIsReady, handoffLink, nominated }) {
    const form = useForm({ email: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/first-run/first-administrator')
    }

    return (
        <SetupShell
            title="The first administrator"
            lead="Hand SemantIQ over to the person who will run it."
            steps={steps}
        >
            {handoffLink ? (
                <section className="setup-panel setup-panel-emphasis">
                    <h2>Send this link to {nominated}</h2>

                    <p className="setup-description">
                        They open it, sign in with Microsoft, and SemantIQ creates their System
                        Administrator account.
                    </p>

                    <p className="setup-handoff" data-testid="handoff-link">
                        {handoffLink}
                    </p>

                    <p className="setup-warning">
                        This link is shown once and cannot be shown again. If you leave this page
                        without copying it, nominate the administrator again to get a new one. It
                        can be used once, and stops working after 30 minutes.
                    </p>
                </section>
            ) : null}

            <section className="setup-panel">
                <h2>Nominate an administrator</h2>

                {identityIsReady ? (
                    <>
                        <p className="setup-description">
                            Enter the work email address or username of the person who will be the
                            first System Administrator. They must already be in the Microsoft
                            directory entered in the sign-in step.
                        </p>

                        <form onSubmit={submit} className="setup-form">
                            <div className="setup-field">
                                <label htmlFor="nominated-email">Email address or username</label>
                                <input
                                    id="nominated-email"
                                    type="email"
                                    autoComplete="off"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    required
                                />
                                {form.errors.email ? (
                                    <p className="setup-error" role="alert">
                                        {form.errors.email}
                                    </p>
                                ) : null}
                            </div>

                            <div className="setup-form-actions">
                                <button type="submit" className="org-action" disabled={form.processing}>
                                    {form.processing ? 'Preparing…' : 'Create the handover link'}
                                </button>
                            </div>
                        </form>
                    </>
                ) : (
                    <p className="setup-description">
                        Microsoft sign-in has not been entered and tested yet. Until it is, a
                        nominated administrator would have no way to sign in.
                    </p>
                )}
            </section>
        </SetupShell>
    )
}
