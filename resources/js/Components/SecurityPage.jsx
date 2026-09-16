import { usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'
import SecurityTabs from './SecurityTabs'
import PostureBadge from './PostureBadge'

/**
 * The chrome shared by every Security Status screen.
 *
 *   FEATURE   Security Status, and what it is for
 *   SUMMARY   the deployment aggregate, from the one projection
 *   TAB       the section you are in, route-backed
 *   CONTENT   the section's own title and body
 *
 * ON THE CLASS NAMES. These are the shared console page-chrome classes, which
 * carry an `org-` prefix only because Organisation was the first feature to
 * need them. Duplicating the stylesheet under a `sec-` prefix would give the
 * product three tab strips and three refusal banners that drift apart - the
 * same two-sources-of-truth failure this project keeps finding, applied to the
 * things a person actually looks at. Only genuinely new Security elements get
 * their own names.
 *
 * NOTHING HERE COMPUTES POSTURE. The aggregate, its label and the caption
 * arrive already decided by the server. React chooses a CSS class from a state
 * it was GIVEN; it never works out which row is worst. A summary component that
 * derived the aggregate from `rows` would be the accidental second evaluator,
 * and would drift from Aggregation within a unit or two.
 */
export default function SecurityPage({ productAreas, summary, title, description, children }) {
    const { url } = usePage()

    return (
        <AppShell productAreas={productAreas} title="Security Status">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Security Status</h1>
                    <p>
                        Where this deployment stands. Everything here is read-only: each control is
                        enforced on the screen that owns it, and this page reports what those
                        settings currently add up to.
                    </p>
                </header>

                <section className="sec-summary" aria-label="Overall security status">
                    <PostureBadge state={summary.aggregate} label={summary.aggregateLabel} size="large" />
                    <p className="sec-summary-caption">{summary.caption}</p>
                </section>

                <SecurityTabs path={url} exceptionCount={summary.exceptionCount} />

                <div className="org-section-head">
                    <div>
                        <h2>{title}</h2>
                        {description ? <p className="org-description">{description}</p> : null}
                    </div>
                </div>

                {children}
            </div>
        </AppShell>
    )
}
