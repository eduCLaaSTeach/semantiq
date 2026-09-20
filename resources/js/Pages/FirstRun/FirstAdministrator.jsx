import { Link, useForm } from '@inertiajs/react'
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
    const form = useForm({ email: '', password: '' })

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
                    {/*
                     * THE ADDRESS IS THE ONLY PART THAT MAY BREAK MID-WORD.
                     *
                     * A nominated address is one unbreakable 33-character
                     * token, and in a heading at 390px it pushed the whole
                     * setup column to 426px. The panel headings used to carry
                     * `overflow-wrap: anywhere` for it, which fixed this and
                     * broke every OTHER heading instead - "Microso / ft
                     * Fabric" beside a status badge.
                     *
                     * So the permission is attached to the address itself
                     * rather than to headings in general.
                     */}
                    <h2>
                        Send this link to <span className="setup-breakable">{nominated}</span>
                    </h2>

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

                            {/*
                             * D-159. THE HANDOVER RECONFIRMS THE SETUP PASSWORD.
                             *
                             * This is the single most consequential action on
                             * the setup surface: it creates the link that
                             * makes somebody a permanent System Administrator
                             * and closes the local account that is asking.
                             * An unattended browser left open on this screen
                             * should not be enough to hand the deployment to
                             * an address of the finder's choosing.
                             */}
                            <div className="setup-field">
                                <label htmlFor="nomination-password">
                                    Confirm with your setup password
                                </label>
                                <input
                                    id="nomination-password"
                                    type="password"
                                    autoComplete="current-password"
                                    value={form.data.password}
                                    onChange={(event) =>
                                        form.setData('password', event.target.value)
                                    }
                                    required
                                />
                                <p className="setup-hint">
                                    Handing SemantIQ over needs the password you signed in to setup
                                    with.
                                </p>
                                {form.errors.password ? (
                                    <p className="setup-error" role="alert">
                                        {form.errors.password}
                                    </p>
                                ) : null}
                            </div>

                            <div className="setup-form-actions">
                                <button
                                    type="submit"
                                    className="org-action"
                                    disabled={form.processing}
                                >
                                    {form.processing ? 'Preparing…' : 'Create the handover link'}
                                </button>
                            </div>
                        </form>
                    </>
                ) : (
                    <>
                        <p className="setup-description">
                            Microsoft sign-in has not been entered and tested yet. Until it is, a
                            nominated administrator would have no way to sign in.
                        </p>

                        {/*
                         * A BLOCKED STATE THAT NAMES THE BLOCKER MUST ALSO OFFER THE WAY OUT.
                         *
                         * Without this the screen says what is wrong and leaves the
                         * administrator to find the fix themselves - which on a setup
                         * surface, at the last step, is where somebody gives up. The rail
                         * is there too, but a reader who has just been told what is
                         * missing should not have to go looking for it.
                         */}
                        <Link href="/first-run/integration/identity" className="org-action">
                            Go to Microsoft sign-in
                        </Link>
                    </>
                )}
            </section>
        </SetupShell>
    )
}
