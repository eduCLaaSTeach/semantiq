import Icon from '../Components/Icon'
import SignInLayout from '../Layouts/SignInLayout'

/**
 * The Login page.
 *
 * Sign in with Microsoft is the only Release 1 authentication method, and the
 * only one offered: no Google, no email and password, no social tabs, no
 * placeholder alternatives. Nothing about the authentication flow itself
 * changed here - this page restyles the entrance, and the Entra redirect,
 * callback, session and logout behind it are exactly as P1-00 delivered them.
 *
 * The provider is offered only when it is configured, per blueprint 0.2: a
 * button that cannot work is worse than no button.
 *
 * Copy is the Product Owner's approved messaging (D-17) and is not paraphrased.
 */
export default function Entry({ microsoftEnabled }) {
    return (
        <SignInLayout>
            <h2 className="signin-welcome">Welcome to SemantIQ</h2>

            <p className="signin-lead">
                Sign in securely to continue to your decision intelligence workspace.
            </p>

            {microsoftEnabled ? (
                <a className="signin-action" href="/auth/microsoft/redirect">
                    <Icon name="i-microsoft" />
                    Continue with Microsoft
                </a>
            ) : (
                <p className="signin-unavailable">Sign-in is not yet available on this deployment.</p>
            )}

            <p className="signin-support">
                Access is managed by your organisation&rsquo;s administrator.
                <span>Contact your administrator if you cannot access SemantIQ.</span>
            </p>
        </SignInLayout>
    )
}
