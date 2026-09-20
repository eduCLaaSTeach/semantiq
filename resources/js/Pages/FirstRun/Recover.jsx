import { useForm } from '@inertiajs/react'
import AuthCard from '../../Components/AuthCard'

/**
 * Recovery. The only way back into local sign-in once setup has closed.
 *
 * REACHED WITH A TOKEN AN OPERATOR ISSUED OVER SSH, and nothing on this page
 * can produce one. That is the point: after the first System Administrator is
 * established, the local password is made unusable and losing every
 * administrator does NOT bring it back. A deliberate operator action does.
 *
 * ONE MESSAGE FOR EVERY REFUSAL. Unknown, expired and already-used are
 * indistinguishable, so this page cannot be used to learn whether a token
 * exists.
 */
export default function Recover() {
    const form = useForm({ token: '', password: '', password_confirmation: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/first-run/recover', {
            onFinish: () => form.reset('password', 'password_confirmation'),
        })
    }

    return (
        <AuthCard
            title="Recover setup access"
            tagline="Use the recovery token issued on the server."
            footer="Recovery closes again as soon as an administrator signs in with Microsoft."
        >
            <form onSubmit={submit} className="auth-form">
                <label htmlFor="recovery-token">Recovery token</label>
                <input
                    id="recovery-token"
                    type="text"
                    autoComplete="off"
                    value={form.data.token}
                    onChange={(event) => form.setData('token', event.target.value)}
                    required
                />

                <label htmlFor="recovery-password">Choose a new setup password</label>
                <input
                    id="recovery-password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(event) => form.setData('password', event.target.value)}
                    required
                />

                <label htmlFor="recovery-password-confirm">Type it again</label>
                <input
                    id="recovery-password-confirm"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                    required
                />

                {form.errors.token ? (
                    <p className="auth-error" role="alert">
                        {form.errors.token}
                    </p>
                ) : null}

                {form.errors.password ? (
                    <p className="auth-error" role="alert">
                        {form.errors.password}
                    </p>
                ) : null}

                <button type="submit" className="auth-action" disabled={form.processing}>
                    {form.processing ? 'Checking…' : 'Recover access'}
                </button>
            </form>
        </AuthCard>
    )
}
