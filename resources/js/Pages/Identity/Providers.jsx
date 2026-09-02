import { usePage } from '@inertiajs/react'
import IdentityPage from '../../Components/IdentityPage'
import { IdentityState } from '../../Components/IdentityRows'

/**
 * Other Identity Providers.
 *
 * The screen exists to answer one question honestly: what else could sign
 * people in? The answer today is nothing, and saying so plainly is the whole
 * value.
 *
 * The list is the APPROVED CATALOGUE, not whatever the application happens to
 * have wired up. An earlier design enumerated the container, which made
 * "approved" mean "present" - anything bound would have promoted itself onto
 * this screen as an approved way into the product, which is the opposite of the
 * rule it was meant to express.
 *
 * Google, Okta, Auth0 and every other provider are ABSENT - not greyed out, not
 * listed as available, not a disabled toggle. A greyed-out Google button is a
 * product claim Release 1 does not make.
 */
export default function Providers({ approved }) {
    const { productAreas, errors } = usePage().props

    return (
        <IdentityPage
            productAreas={productAreas}
            errors={errors}
            title="Other Identity Providers"
            description="Which identity providers are approved to sign people in to SemantIQ."
        >
            <div className="org-table-scroll">
                <table className="org-table">
                    <thead>
                        <tr>
                            <th scope="col">Provider</th>
                            <th scope="col">Approval</th>
                            <th scope="col">Configured</th>
                        </tr>
                    </thead>
                    <tbody>
                        {approved.map((provider) => (
                            <tr key={provider.key}>
                                <td>{provider.name}</td>
                                <td>
                                    <IdentityState state="healthy">
                                        {provider.inUse ? 'Approved and in use' : 'Approved'}
                                    </IdentityState>
                                </td>
                                <td>
                                    <IdentityState state={provider.configured ? 'healthy' : 'degraded'}>
                                        {provider.configured ? 'Configured' : 'Not configured'}
                                    </IdentityState>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <div className="org-empty">
                <p>No other identity provider is approved.</p>
                <p className="org-hint-plain">
                    Adding another identity provider requires Product Owner approval. A provider
                    that is present in the software is not, on its own, an approved way to sign in.
                </p>
            </div>
        </IdentityPage>
    )
}
