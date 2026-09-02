import { usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'
import IdentityTabs from './IdentityTabs'

/**
 * The chrome shared by every Identity & SSO screen.
 *
 * Same hierarchy as Organisation, stated once so every screen reads the same
 * way:
 *
 *   FEATURE   Identity & SSO, with what the feature is for
 *   TAB       the section you are in, route-backed
 *   CONTENT   the section's own title and body
 *
 * ON THE CLASS NAMES. These are the shared console page-chrome classes, which
 * carry an `org-` prefix only because Organisation was the first feature to need
 * them. Duplicating the whole stylesheet under an `idn-` prefix would give the
 * product two tab strips, two refusal banners and two confirmation banners that
 * drift apart - the same two-sources-of-truth failure this project keeps
 * finding, applied to the things a person actually looks at. Only genuinely new
 * Identity elements get their own names.
 *
 * The refusal and the confirmation sit in the same places, with the same roles,
 * as they do on every Organisation screen: role="alert" for a refusal, because
 * it interrupts, and role="status" for a confirmation, because a success is news
 * rather than an interruption.
 */
export default function IdentityPage({ productAreas, title, description, errors = {}, actions = null, children }) {
    const refusal = errors.identity
    const page = usePage()
    const { url } = page
    const { confirmation } = page.props

    return (
        <AppShell productAreas={productAreas} title="Identity & SSO">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Identity &amp; SSO</h1>
                    <p>
                        See how sign-in is configured and whether it is healthy. Everything here is
                        read-only: identity settings are held on the server and are not changed from
                        this screen.
                    </p>
                </header>

                <IdentityTabs path={url} />

                <div className="org-section-head">
                    <div>
                        <h2>{title}</h2>
                        {description ? <p className="org-description">{description}</p> : null}
                    </div>

                    {actions ? <div className="org-section-actions">{actions}</div> : null}
                </div>

                {refusal ? (
                    <div className="org-refusal" role="alert">
                        {refusal}
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
