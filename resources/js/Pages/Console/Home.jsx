import { router, usePage } from '@inertiajs/react'
import AppShell from '../../Layouts/AppShell'

/**
 * The signed-in landing page.
 *
 * P1-00 shipped this as a standalone card with no shell, because nothing was
 * implemented to navigate to. P1-01 delivers the first navigable capability, so
 * this now renders inside the authenticated shell - otherwise Organisation is
 * built, routed and authorised but unreachable from the page a System
 * Administrator actually lands on.
 *
 * The canvas stays deliberately minimal. This is NOT Administration Home -
 * P1-10 owns that - and it is not a placeholder dashboard. It states who is
 * signed in, what access that does and does not confer, and offers sign-out.
 *
 * The sidebar is presentation only. Every route inside /console re-authorises
 * on its own; if the navigation filter were wrong the request would still be
 * refused.
 */
export default function Home({ displayName, email, isSystemAdministrator }) {
    const { productAreas } = usePage().props

    return (
        <AppShell productAreas={productAreas} title="SemantIQ">
            <div className="console-landing">
                <h1>Signed in</h1>

                <p>
                    <strong>{displayName}</strong>
                    <br />
                    {email}
                </p>

                {/* Unchanged from P1-00, and still true: administering the
                    platform grants no business-domain access. SYS-004. */}
                <p className="console-note">
                    {isSystemAdministrator
                        ? 'You are a System Administrator. No business-domain access is assigned; platform administration does not grant it.'
                        : 'No application access has been assigned to your account yet.'}
                </p>

                <button
                    type="button"
                    className="org-action"
                    onClick={() => router.post('/auth/logout')}
                >
                    Sign out
                </button>
            </div>
        </AppShell>
    )
}
