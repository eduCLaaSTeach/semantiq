import { router } from '@inertiajs/react'
import AuthCard from '../../Components/AuthCard'

/**
 * The signed-in confirmation state.
 *
 * Deliberately minimal. It proves that an authenticated session reaches a
 * protected route, and nothing more: no sidebar, no Administration Home, no
 * menu, no placeholder for a later unit, no business data.
 *
 * It must not grow into the application shell. P1-01 owns what comes next.
 *
 * The message about assigned access is the honest state of the system, not a
 * placeholder - authentication grants no business-domain access, and that stays
 * true however many units are added later.
 */
export default function Home({ displayName, email, isSystemAdministrator }) {
    return (
        <AuthCard title="Signed in">
            <p>
                Signed in as <strong>{displayName}</strong>
                <br />
                {email}
            </p>

            <p className="entry-note">
                {isSystemAdministrator
                    ? 'You are a System Administrator. No business-domain access is assigned; platform administration does not grant it.'
                    : 'No application access has been assigned to your account yet.'}
            </p>

            <button
                type="button"
                className="auth-action"
                onClick={() => router.post('/auth/logout')}
            >
                Sign out
            </button>
        </AuthCard>
    )
}
