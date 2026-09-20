import AuthCard from '../../Components/AuthCard'

/**
 * Step 9. Setup is over.
 *
 * SETUP CLOSED ITSELF, AND THE SCREEN SAYS SO. There is no button here that
 * ends setup: the local sign-in was closed in the same transaction that created
 * the first System Administrator. Stating it plainly is the difference between
 * an administrator who knows the door is shut and one who wonders whether they
 * were supposed to shut it.
 */
export default function Complete({ isConfigured }) {
    return (
        <AuthCard
            title="SemantIQ is set up"
            tagline={
                isConfigured
                    ? 'The first System Administrator has been created.'
                    : 'Setup is finished, but no administrator has signed in yet.'
            }
            footer="The setup sign-in has closed itself. Everyone signs in with Microsoft from now on."
        >
            <p>
                {isConfigured
                    ? 'Nothing more is needed here. Sign in with Microsoft to start using SemantIQ.'
                    : 'The person you nominated has not used their link yet. It stops working 30 minutes after it was created.'}
            </p>

            <a className="auth-action" href="/auth/microsoft/redirect">
                Sign in with Microsoft
            </a>
        </AuthCard>
    )
}
