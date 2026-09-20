import { useForm } from '@inertiajs/react'
import AuthCard from '../../Components/AuthCard'

/**
 * Step 1. The local setup sign-in.
 *
 * THE AUTH ARCHETYPE, the same card as every other pre-authentication screen,
 * because these must stay indistinguishable in shape. A setup sign-in that
 * looked different from the ordinary one would tell an anonymous caller which
 * of the two they had reached — and therefore whether this deployment has an
 * administrator yet.
 *
 * ONE MESSAGE FOR EVERY REFUSAL. An unknown address, a wrong password and a
 * bootstrap that has already closed all produce the same sentence, because
 * distinguishing them turns this screen into a way to ask a question nobody
 * should be able to ask.
 */
export default function SignIn() {
    const form = useForm({ email: '', password: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/first-run/sign-in', { onFinish: () => form.reset('password') })
    }

    return (
        <AuthCard
            title="Set up SemantIQ"
            tagline="Sign in with the setup details your administrator created on the server."
            footer="This sign-in exists only until the first administrator is established. It closes itself afterwards."
        >
            <form onSubmit={submit} className="auth-form">
                <label htmlFor="setup-email">Email address</label>
                <input
                    id="setup-email"
                    type="email"
                    autoComplete="username"
                    value={form.data.email}
                    onChange={(event) => form.setData('email', event.target.value)}
                    required
                />

                <label htmlFor="setup-password">Password</label>
                <input
                    id="setup-password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    required
                />

                {form.errors.email ? (
                    <p className="auth-error" role="alert">
                        {form.errors.email}
                    </p>
                ) : null}

                <button type="submit" className="auth-action" disabled={form.processing}>
                    {form.processing ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
        </AuthCard>
    )
}
