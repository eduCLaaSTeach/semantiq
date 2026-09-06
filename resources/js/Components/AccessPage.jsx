import { usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'

/**
 * The chrome shared by every Roles & Access screen.
 *
 * Same hierarchy, same classes and the same two banners as Organisation,
 * Identity, People and Business Domains. Like Business Domains it has NO TAB
 * STRIP: Roles & Access delivers one thing — a person's access — and
 * entitlements, scopes and ceilings all hang beneath a role assignment rather
 * than sitting beside it. A tab would ask the reader to know which tab a scope
 * lives in before they could find one.
 *
 * role="alert" for a refusal, because it interrupts. role="status" for a
 * confirmation, because a success is news rather than an interruption.
 *
 * THE HEADER SENTENCE IS NOT DECORATION. This is the screen where access is
 * actually granted, and the one thing a reader must not assume is that a role
 * on its own does anything. It says what a complete grant needs, once, at the
 * top of every screen.
 */
export default function AccessPage({ productAreas, title, description, back = null, errors = {}, actions = null, warning = null, children }) {
    const refusal = errors.access
    const { confirmation } = usePage().props

    return (
        <AppShell productAreas={productAreas} title="Roles & Access">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Roles &amp; Access</h1>
                    <p>
                        Give people access to business information. A role on its own grants
                        nothing — access needs a role, an entitlement to a domain, a scope saying
                        which records, and a sensitivity level.
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

                {/*
                  * D-49a. Informational only — it never blocks anything, and it
                  * is deliberately not styled as a refusal.
                  */}
                {warning ? (
                    <div className="org-notice" role="status">
                        <strong>Worth knowing.</strong> {warning}
                    </div>
                ) : null}

                {refusal ? (
                    <div className="org-refusal" role="alert">
                        <strong>Refused.</strong> {refusal}
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
