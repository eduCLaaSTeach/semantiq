import AuthCard from '../../Components/AuthCard'

/**
 * First-run bootstrap entry.
 *
 * Reaching this page grants nothing. The nominated administrator must still
 * sign in with Microsoft, and the verified identity must match the one the
 * grant was issued for - otherwise the grant is refused and, importantly, not
 * consumed.
 *
 * The grant itself and the expected subject are never rendered.
 */
export default function FirstRun() {
    return (
        <AuthCard
            title="Set up SemantIQ"
            footer="This link is single-use and expires shortly after it was issued."
        >
            <p>
                This deployment has no System Administrator yet. Sign in with Microsoft to
                establish the first one.
            </p>
            <a className="auth-action" href="/auth/microsoft/redirect">
                Sign in with Microsoft
            </a>
        </AuthCard>
    )
}
