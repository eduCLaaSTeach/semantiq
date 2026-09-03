import { usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'

/**
 * The chrome shared by both Business Domains screens.
 *
 * Same hierarchy, same classes and same two banners as Organisation, Identity
 * and People — MINUS THE TAB STRIP, which is the only deliberate difference.
 * Those units deliver two or more kinds of thing; this one delivers one, and a
 * tab would ask the reader to know which tab a domain lives in before they
 * could find it.
 *
 * role="alert" for a refusal, because it interrupts. role="status" for a
 * confirmation, because a success is news rather than an interruption.
 *
 * The header sentence is not decoration. A domain is the most access-shaped
 * object in Phase 1, and "Finance, owner Salil, enabled" reads like a grant to
 * almost every reader. It is said once, plainly, at the top of every screen.
 */
export default function DomainsPage({ productAreas, title, description, back = null, errors = {}, actions = null, children }) {
    const refusal = errors.domains
    const page = usePage()
    const { confirmation } = page.props

    return (
        <AppShell productAreas={productAreas} title="Business Domains">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Business Domains</h1>
                    <p>
                        Name the intelligence domains this organisation has and who is accountable
                        for each one. Nothing here grants access to any of it.
                    </p>
                </header>

                {back ? (
                    <a className="org-back" href={back.href}>
                        <span aria-hidden="true">&larr;</span> Back to {back.label}
                    </a>
                ) : null}

                <div className="org-section-head">
                    <div>
                        <h2>{title}</h2>
                        {description ? <p className="org-description">{description}</p> : null}
                    </div>

                    {actions ? <div className="org-section-actions">{actions}</div> : null}
                </div>

                {refusal ? (
                    <div className="org-refusal" role="alert">
                        <strong>Refused.</strong> {refusal}
                        {errors.blockedBy ? <div className="org-refusal-detail">Blocked by: {errors.blockedBy}</div> : null}
                    </div>
                ) : null}

                {confirmation && !refusal ? (
                    <div className="org-confirmation" role="status">
                        <span className="org-confirmation-mark" aria-hidden="true">&#10003;</span>
                        {confirmation}
                    </div>
                ) : null}

                {children}
            </div>
        </AppShell>
    )
}
