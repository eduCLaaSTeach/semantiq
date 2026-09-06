import { useForm, usePage } from '@inertiajs/react'
import AccessPage from '../../Components/AccessPage'

/**
 * The step-up confirmation card.
 *
 * IT ASKS FOR NO CREDENTIAL. There is no password field here and nothing that
 * imitates one — the button leaves for Microsoft, and Microsoft performs the
 * re-authentication. A dialog that merely said "are you sure?" would be a
 * confirmation prompt wearing the word "step-up", which is exactly what this
 * unit was told not to build.
 *
 * It says what is about to happen before anybody leaves, because the whole
 * point of the round trip is that the person confirms THIS action rather than
 * confirms in general.
 *
 * It claims nothing about which credential Microsoft will ask for. SemantIQ can
 * verify that Microsoft reports a fresh sign-in; it cannot prove which factor
 * was used unless the organisation's Entra policy guarantees it.
 */
export default function StepUp({ reference, description, expiresInMinutes }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({})

    const submit = (event) => {
        event.preventDefault()
        form.post(`/console/access/step-up/${reference}`)
    }

    return (
        <AccessPage
            productAreas={productAreas}
            errors={errors}
            back={{ href: '/console/access', label: 'role assignments' }}
            title="Confirm your identity"
            description="This is a privileged change, so it needs you to sign in with Microsoft again before it is applied."
        >
            <section className="org-record-section">
                <p className="org-description">
                    You are about to <strong>{description}</strong>.
                </p>

                <p className="org-description">
                    Selecting Continue takes you to Microsoft to sign in again. Nothing changes until
                    you come back, and this confirmation covers only this one change.
                </p>

                <p className="org-hint org-hint-plain">
                    This confirmation expires in {expiresInMinutes} minutes. If you cancel at
                    Microsoft, or it expires, nothing is applied and you can start again.
                </p>

                <form onSubmit={submit}>
                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Taking you to Microsoft…' : 'Continue to Microsoft'}
                    </button>
                </form>

                <p className="org-hint org-hint-plain">
                    <a href="/console/access">Cancel and go back</a> — nothing will be changed.
                </p>
            </section>
        </AccessPage>
    )
}
