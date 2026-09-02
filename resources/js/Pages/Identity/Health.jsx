import { useForm, usePage } from '@inertiajs/react'
import IdentityPage from '../../Components/IdentityPage'
import { IdentityState } from '../../Components/IdentityRows'

/**
 * SSO Health.
 *
 * The overall state is worded carefully, and the caution is deliberate. This
 * unit performs no authentication transaction and cannot see a client secret's
 * expiry, so "Healthy" says what was checked rather than promising an outcome it
 * has not observed. The first time a screen claims sign-in works over a broken
 * sign-in, nobody believes it again - so the limits are printed under the state,
 * where the claim is, rather than left in a document.
 *
 * TWO ROWS THAT LOOK SIMILAR AND ARE NOT: "Identity trust available" asks
 * whether sign-in has what it needs, from cache or provider, and a cached answer
 * is a good answer. "Microsoft reachable (live check)" asks whether an explicit
 * check reached Microsoft just now. Merging them is how a screen whose purpose
 * is to make outages visible reports Healthy through one for up to a day.
 *
 * Re-check is an async action: disabled while in flight, stable width, guarded
 * against double submission.
 */
const WORDS = {
    healthy: 'Healthy',
    degraded: 'Needs attention',
    failed: 'Sign-in unavailable',
    not_checked: 'Not checked',
}

export default function Health({ health }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({})

    const recheck = (event) => {
        event.preventDefault()
        form.post('/console/identity/health/re-check', { preserveScroll: true })
    }

    return (
        <IdentityPage
            productAreas={productAreas}
            errors={errors}
            title="SSO Health"
            description="Whether sign-in is set up correctly, and whether Microsoft can be reached."
            actions={
                <form onSubmit={recheck}>
                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Checking…' : 'Re-check now'}
                    </button>
                </form>
            }
        >
            <div className={`idn-overall idn-overall-${health.state}`}>
                <p className="idn-overall-state">
                    <IdentityState state={health.state}>{health.stateInWords}</IdentityState>
                </p>
                <p className="idn-overall-summary">{health.summary}</p>
                <p className="org-hint-plain">
                    These checks read the settings on this deployment and contact Microsoft. They do
                    not sign anyone in, and they cannot tell whether the client secret is close to
                    expiring.
                </p>
            </div>

            {health.establishedAt ? null : (
                <div className="org-empty">
                    <p>Health has not been checked on this deployment yet.</p>
                    <p className="org-hint-plain">
                        A deployment clears the last result, so this is expected straight after a
                        release. Use Re-check now to establish it.
                    </p>
                </div>
            )}

            <div className="org-table-scroll">
                <table className="org-table idn-checks">
                    <colgroup>
                        <col className="idn-col-check" />
                        <col className="idn-col-result" />
                        <col className="idn-col-meaning" />
                    </colgroup>
                    <thead>
                        <tr>
                            <th scope="col">Check</th>
                            <th scope="col">Result</th>
                            <th scope="col">What this means</th>
                        </tr>
                    </thead>
                    <tbody>
                        {health.checks.map((check) => (
                            <tr key={check.key}>
                                <td>{check.label}</td>
                                <td>
                                    <IdentityState state={check.state}>{WORDS[check.state]}</IdentityState>
                                </td>
                                <td>
                                    {check.finding}
                                    {check.action && check.state !== 'healthy' ? (
                                        <p className="idn-action">{check.action}</p>
                                    ) : null}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </IdentityPage>
    )
}
