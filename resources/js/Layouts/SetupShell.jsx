import { Link, router, usePage } from '@inertiajs/react'
import BrandMark from '../Components/BrandMark'

/**
 * The First-Run chrome. DELIBERATELY NOT AppShell.
 *
 * No sidebar, no product areas, no organisation switcher. A setup surface that
 * looks like the console invites the assumption that the console is reachable
 * from it — and then invites somebody to make that true. The bootstrap
 * principal is not a User and can reach no console route, so the chrome should
 * not suggest otherwise.
 *
 * REQUIRED AND OPTIONAL ARE SHOWN ON EVERY STEP (D-167, D-171). An
 * administrator who cannot tell which steps are required either stops at the
 * first one they lack, or configures all four to be safe. Both waste a setup
 * session, and the second creates credentials nobody needed.
 */
export default function SetupShell({ title, lead, steps = [], current, children, actions }) {
    const { confirmation } = usePage().props

    const signOut = (event) => {
        event.preventDefault()
        router.post('/first-run/sign-out')
    }

    return (
        <div className="setup">
            <header className="setup-head">
                <BrandMark className="setup-mark" />

                <div className="setup-head-text">
                    <p className="setup-eyebrow">Setting up SemantIQ</p>
                    <h1>{title}</h1>
                    {lead ? <p className="setup-lead">{lead}</p> : null}
                </div>

                <form onSubmit={signOut} className="setup-head-actions">
                    <button type="submit" className="org-action org-action-quiet">
                        Sign out
                    </button>
                </form>
            </header>

            <div className="setup-body">
                {steps.length > 0 ? (
                    <nav className="setup-steps" aria-label="Setup steps">
                        <p className="setup-steps-title">Steps</p>

                        <ol>
                            {steps.map((step) => (
                                <li
                                    key={step.family}
                                    className={
                                        step.family === current ? 'setup-step setup-step-current' : 'setup-step'
                                    }
                                >
                                    <Link href={`/first-run/integration/${step.family}`}>
                                        <span className="setup-step-name">{step.name}</span>
                                        <span className="setup-step-meta">
                                            {step.required ? 'Required' : 'Optional'}
                                            {' · '}
                                            {step.configured ? 'Entered' : 'Not entered yet'}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ol>
                    </nav>
                ) : null}

                <main className="setup-main">
                    {confirmation ? (
                        <p className="setup-confirmation" role="status">
                            {confirmation}
                        </p>
                    ) : null}

                    {children}

                    {actions ? <div className="setup-actions">{actions}</div> : null}
                </main>
            </div>
        </div>
    )
}
