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
export default function IdentityPage({
    productAreas,
    title,
    description,
    errors = {},
    actions = null,
    children,
}) {
    const page = usePage()
    const { url } = page
    const { confirmation, refusal: flashedRefusal } = page.props

    /*
     * TWO SOURCES, BOTH RENDERED, AND THE SECOND WAS MISSING.
     *
     * `errors.identity` is a refused form field. `refusal` is a flashed
     * sentence - which is what a rejected Microsoft Entra candidate produces:
     * the administrator confirmed at Microsoft, came back, and the details did
     * not check out. Nothing about that is a field error.
     *
     * Until Gate C round 3 this component read only the first, so a flashed
     * refusal would have gone to a blank page. HandleInertiaRequests carries
     * the same note about Access Reviews, where exactly that happened and no
     * automated test caught it: assertSessionHas('refusal') passes on a message
     * that reaches the session and never reaches the screen.
     */
    const refusal = errors.identity ?? flashedRefusal

    return (
        <AppShell productAreas={productAreas} title="Identity & SSO">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Identity &amp; SSO</h1>
                    {/*
                     * THIS USED TO SAY "Everything here is read-only: identity
                     * settings are held on the server and are not changed from
                     * this screen."
                     *
                     * Gate C round 3 made that false, and it was shown at the
                     * top of EVERY Identity screen - including the change
                     * screen itself, where the page contradicted its own form
                     * two inches further down.
                     */}
                    <p>
                        See how sign-in is configured and whether it is healthy, and change the
                        Microsoft Entra details SemantIQ signs people in with.
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
                        <span className="org-confirmation-mark" aria-hidden="true">
                            &#10003;
                        </span>
                        {confirmation}
                    </div>
                ) : null}

                {children}
            </div>
        </AppShell>
    )
}
