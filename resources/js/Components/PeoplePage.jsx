import { usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'
import PeopleTabs from './PeopleTabs'

/**
 * The chrome shared by every Users & Groups screen.
 *
 * Same hierarchy, same classes and same two banners as Organisation and
 * Identity. These are the shared console page-chrome classes, which carry an
 * `org-` prefix only because Organisation needed them first; a third copy under
 * a third name would give the product three tab strips that drift apart.
 *
 * role="alert" for a refusal, because it interrupts. role="status" for a
 * confirmation, because a success is news rather than an interruption.
 */
export default function PeoplePage({ productAreas, title, description, back = null, errors = {}, actions = null, children }) {
    const refusal = errors.people
    const page = usePage()
    const { url } = page
    const { confirmation } = page.props

    return (
        <AppShell productAreas={productAreas} title="Users & Groups">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Users &amp; Groups</h1>
                    <p>
                        Bring people into SemantIQ, group them, and end their access. Nothing here
                        grants access to business data.
                    </p>
                </header>

                <PeopleTabs path={url} />

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
