import { useForm, usePage } from '@inertiajs/react'
import AppShell from '../../Layouts/AppShell'
import HealthStatusBadge from '../../Components/HealthStatusBadge'

/**
 * System Health. Five areas, eleven rows, and no control that changes anything.
 *
 * THE ROW IS THE WHOLE PAYLOAD: a name, a status, one sentence and - for the
 * one row that is not live - an age. The server's HealthRow has no field for a
 * hostname, a path, a driver, a version, a connection string or an exception,
 * so there is nothing here to render one from.
 *
 * OPENING THIS PAGE CONTACTS NOBODY. The local checks run on render; Microsoft
 * Entra ID is the answer P1-02 last stored, shown with its age so a reader
 * cannot mistake it for a measurement taken now.
 *
 * THE ONE ACTION IS P1-02'S. "Check sign-in now" posts to the Identity & SSO
 * re-check endpoint - already rate limited, already recording its own events -
 * and returns here. This unit adds no write route of its own, which is why
 * there is no button on this page that restarts, clears or re-runs anything.
 */
export default function Index({ productAreas, areas }) {
    const page = usePage()
    const { errors, confirmation } = page.props
    const refusal = errors?.identity
    const form = useForm({})

    const recheck = (event) => {
        event.preventDefault()
        form.post('/console/identity/health/re-check', { preserveScroll: true })
    }

    return (
        <AppShell productAreas={productAreas} title="System Health">
            <div className="org-page">
                <header className="org-feature">
                    <h1>System Health</h1>
                    <p>
                        Whether SemantIQ and the services it depends on are working. Everything here
                        is read-only — nothing on this page starts, stops, clears or changes
                        anything.
                    </p>
                </header>

                <div className="org-section-head">
                    <div>
                        <h2>Current state</h2>
                        <p className="org-description">
                            Checked when this page was opened. Sign-in is the one exception: it
                            shows the result of the last check, with its age, because opening this
                            page does not contact Microsoft.
                        </p>
                    </div>

                    <div className="org-section-actions">
                        <form onSubmit={recheck}>
                            <button type="submit" className="org-action" disabled={form.processing}>
                                {form.processing ? 'Checking…' : 'Check sign-in now'}
                            </button>
                        </form>
                    </div>
                </div>

                {refusal ? (
                    <div className="org-refusal" role="alert">
                        {refusal}
                    </div>
                ) : null}

                {confirmation && !refusal ? (
                    <div className="org-confirmation" role="status">
                        <span className="org-confirmation-mark" aria-hidden="true">
                            &#10003;
                        </span>
                        {confirmation}
                    </div>
                ) : null}

                <div className="sys-areas">
                    {areas.map((area) => (
                        <section className="sys-area" key={area.name} aria-labelledby={`sys-${area.name}`}>
                            <div className="sys-area-head">
                                <h3 id={`sys-${area.name}`}>{area.name}</h3>
                                <p className="sys-area-description">{area.description}</p>
                            </div>

                            {/*
                              * NO AREA-LEVEL STATUS, deliberately. An area verdict
                              * would be a twelfth status nothing measured, rolled
                              * up from rows whose meanings do not combine: "one
                              * Not applicable and one Unavailable" has no summary
                              * that is not a lie.
                              */}
                            <ul className="sys-rows">
                                {area.rows.map((row) => (
                                    <li className="sys-row" key={row.name}>
                                        <div className="sys-row-head">
                                            <span className="sys-row-name">{row.name}</span>
                                            <HealthStatusBadge status={row.status} />
                                        </div>

                                        <p className="sys-row-explanation">{row.explanation}</p>

                                        {/*
                                          * An age appears only on a stored result,
                                          * and always on one. A live row carrying
                                          * an age would suggest its value might be
                                          * stale; a stored row without one would
                                          * let last week's answer read as now.
                                          */}
                                        {row.checkedAt ? (
                                            <p className="sys-row-age">Last checked {row.checkedAt}</p>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </div>

                <p className="sys-note">
                    Nothing on this page changes a setting or restarts a service. Sign-in is
                    managed in Identity &amp; SSO, and that screen checks your authority again when
                    you arrive.
                </p>
            </div>
        </AppShell>
    )
}
