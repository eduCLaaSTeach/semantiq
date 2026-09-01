import AuthCard from '../Components/AuthCard'

/**
 * The Login page.
 *
 * Sign in with Microsoft is the primary Release 1 action. Nothing here
 * describes what exists behind authentication - no menu, no product areas, no
 * counts, no version, no tenant name. An unauthenticated browser learns what
 * SemantIQ is for, and nothing about who uses this deployment.
 *
 * The provider is offered only when it is configured, per blueprint 0.2: a
 * button that cannot work is worse than no button.
 *
 * Copy is the Product Owner's approved messaging (D-17) and is not paraphrased.
 */
export default function Entry({ microsoftEnabled }) {
    return (
        <AuthCard
            title="SemantIQ"
            tagline="Turn business data into confident decisions."
            wide
            footer={
                <>
                    Access is assigned by your organisation&rsquo;s administrator.
                    <br />
                    Contact them if you cannot sign in.
                </>
            }
        >
            <p className="entry-supporting">
                See what changed. Understand why. Decide what&rsquo;s next.
            </p>

            <p className="entry-description">
                SemantIQ brings governed data, business context and intelligent insights together in
                one secure decision-intelligence experience.
            </p>

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
