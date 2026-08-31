import AuthCard from '../../Components/AuthCard'

/**
 * Every pre-authentication refusal and outcome state.
 *
 * The wording is deliberately uninformative. "Access not assigned" and "account
 * inactive" say the same thing in the same shape on purpose: telling them apart
 * is precisely how an anonymous caller would work out which accounts exist.
 *
 * Nothing here carries a token, a tenant name, an internal role mapping, a
 * stack trace or a diagnostic detail.
 */
const STATES = {
    'access-not-assigned': {
        title: 'Access not assigned',
        body: 'Your account does not have access to SemantIQ.',
    },
    'account-inactive': {
        title: 'Access not available',
        body: 'Your account does not have access to SemantIQ.',
    },
    'access-denied': {
        title: 'Access denied',
        body: 'You do not have access to the requested resource.',
    },
    'session-expired': {
        title: 'Session ended',
        body: 'Your session has ended. Sign in again to continue.',
    },
    'signed-out': {
        title: 'Signed out',
        body: 'You have been signed out of SemantIQ.',
    },
    'sign-in-unavailable': {
        title: 'Sign-in unavailable',
        body: 'Sign-in is temporarily unavailable. Please try again shortly.',
    },
    'bootstrap-closed': {
        title: 'Setup complete',
        body: 'Initial setup for this deployment is already complete.',
    },
}

export default function State({ state }) {
    const { title, body } = STATES[state] ?? STATES['sign-in-unavailable']

    return (
        <AuthCard
            title={title}
            footer={
                <>
                    Access is assigned by your organisation&rsquo;s administrator.
                    <br />
                    Contact them if you need access.
                </>
            }
        >
            <p>{body}</p>
            <a className="auth-action" href="/">
                Return to sign in
            </a>
        </AuthCard>
    )
}
