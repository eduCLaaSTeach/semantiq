import AuthCard from '../Components/AuthCard'

/**
 * The Login page.
 *
 * Sign in with Microsoft is the primary Release 1 action. Nothing here
 * describes what exists behind authentication - no menu, no product areas, no
 * counts, no version, no tenant name. An unauthenticated browser learns only
 * that this is SemantIQ.
 *
 * The provider is offered only when it is configured, per blueprint 0.2: a
 * button that cannot work is worse than no button.
 */
export default function Entry({ microsoftEnabled }) {
    return (
        <AuthCard
            title="SemantIQ"
            footer={
                <>
                    Access is assigned by your organisation&rsquo;s administrator.
                    <br />
                    Contact them if you cannot sign in.
                </>
            }
        >
            <p>Secure business decision intelligence.</p>

            {microsoftEnabled ? (
                <a className="auth-action" href="/auth/microsoft/redirect">
                    Sign in with Microsoft
                </a>
            ) : (
                <p className="entry-note">Sign-in is not yet available on this deployment.</p>
            )}
        </AuthCard>
    )
}
