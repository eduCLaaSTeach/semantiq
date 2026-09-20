import { Link } from '@inertiajs/react'
import SetupShell from '../../Layouts/SetupShell'

/**
 * Step 2. What is done, what is left, and which of it matters.
 *
 * ONLY MICROSOFT SIGN-IN IS REQUIRED. Saying so here and on every step is the
 * whole of D-167 and D-171: an administrator who cannot tell which steps are
 * required either stops at the first one they lack, or sets up all four to be
 * safe — creating credentials nobody asked for.
 *
 * THE FINAL STEP IS NOT AVAILABLE UNTIL SIGN-IN IS READY, and the reason is
 * stated rather than left to be discovered. Nominating an administrator before
 * Microsoft sign-in works produces a link that cannot be used, and the person
 * who finds out is the nominated administrator, at the moment they are trying
 * to sign in.
 */
export default function Overview({ steps, canNominate, isConfigured }) {
    return (
        <SetupShell
            title="Set up SemantIQ"
            lead="Enter the details SemantIQ needs, then hand over to the first permanent administrator."
            steps={steps}
        >
            {isConfigured ? (
                <p className="setup-note">
                    This deployment already has a System Administrator. Setup is finished.
                </p>
            ) : null}

            <section className="setup-panel">
                <h2>What SemantIQ needs</h2>

                <p className="setup-description">
                    Microsoft sign-in is the only required step. Email, AI and Fabric can be left
                    until later — setup finishes without them.
                </p>

                <ul className="setup-list">
                    {steps.map((step) => (
                        <li key={step.family}>
                            <Link href={`/first-run/integration/${step.family}`} className="setup-list-link">
                                <span className="setup-list-name">{step.name}</span>
                                <span className="setup-list-meta">
                                    {step.required ? 'Required' : 'Optional'} ·{' '}
                                    {step.configured ? 'Entered' : 'Not entered yet'} · {step.statusInWords}
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            </section>

            <section className="setup-panel">
                <h2>Hand over to a permanent administrator</h2>

                {canNominate ? (
                    <>
                        <p className="setup-description">
                            Name the person who will be the first System Administrator. They sign in
                            with Microsoft, and SemantIQ creates their account.
                        </p>

                        <Link href="/first-run/first-administrator" className="org-action">
                            Nominate the first administrator
                        </Link>
                    </>
                ) : (
                    <p className="setup-description">
                        Enter and test Microsoft sign-in first. Until then a nominated administrator
                        would have no way to sign in.
                    </p>
                )}
            </section>
        </SetupShell>
    )
}
