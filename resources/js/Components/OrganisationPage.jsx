import { usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'
import OrganisationTabs from './OrganisationTabs'

/**
 * The chrome shared by every Organisation screen.
 *
 * The hierarchy is stated once, here, so every screen reads the same way:
 *
 *   FEATURE   Organisation, with what the feature is for
 *   TAB       the section you are in, route-backed
 *   CONTENT   the section's own title and body
 *
 * Before this, each screen led with its section title as an <h1> under a top
 * bar also saying "Organisation", with nothing to connect them, and the way to
 * another section was a row of buttons at the bottom of Company Profile only.
 * Sections are not actions and they are not reached from one screen's footer.
 *
 * `back` is a local contextual return link for a detail screen. The standard
 * would normally return through the breadcrumb, but D-21 defers breadcrumbs, so
 * without this a detail page has no visible way back at all. It is deliberately
 * local to the screen that asks for one and is NOT a global breadcrumb system.
 *
 * Refusals are rendered from the stable reason and message the service produced,
 * never from a raw exception - the design's negative test 17 exists because
 * rendering an exception message is how a stack trace or the name of a record
 * the viewer may not see reaches a browser.
 */
export default function OrganisationPage({
    productAreas,
    title,
    description,
    back = null,
    errors = {},
    actions = null,
    children,
}) {
    const refusal = errors.structure
    const { url } = usePage()

    return (
        <AppShell productAreas={productAreas} title="Organisation">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Organisation</h1>
                    <p>
                        Manage company structure, legal entities, business units, departments, teams
                        and reporting relationships.
                    </p>
                </header>

                <OrganisationTabs path={url} />

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

                {children}
            </div>
        </AppShell>
    )
}
