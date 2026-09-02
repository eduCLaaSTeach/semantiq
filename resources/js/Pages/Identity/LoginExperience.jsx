import { usePage } from '@inertiajs/react'
import IdentityPage from '../../Components/IdentityPage'
import { IdentityRow, IdentityRows, IdentityState } from '../../Components/IdentityRows'

/**
 * Login Experience - a read-only ownership map, and it says so.
 *
 * It carries no form, no field and no save button. A read-only screen with a
 * disabled Save is worse than one with none: it implies a capability that does
 * not exist.
 *
 * The value is that the boundary is currently invisible. Somebody asking "why
 * does the sign-in page say that" has had nowhere to look, and now does.
 */
const OWNERSHIP = [
    ['Brand, layout, colours and typography', 'The approved design system', 'Not changed here'],
    ['Headline, supporting text and cards', 'Approved wording', 'Not changed here'],
    ['The Continue with Microsoft button', 'The sign-in process', 'Not changed here'],
    ['Whether Microsoft sign-in appears at all', 'The Microsoft settings on this deployment', 'Reported, not set'],
    ['Wording when sign-in is refused', 'The sign-in process', 'Not changed here'],
    ['Support contact shown when sign-in is refused', 'The sign-in process', 'Not changed here'],
]

export default function LoginExperience({ signInOffered, providerName }) {
    const { productAreas, errors } = usePage().props

    return (
        <IdentityPage
            productAreas={productAreas}
            errors={errors}
            title="Login Experience"
            description="What the sign-in page shows, and where each part of it is decided."
        >
            <IdentityRows>
                <IdentityRow label="Microsoft sign-in">
                    <IdentityState state={signInOffered ? 'healthy' : 'degraded'}>
                        {signInOffered ? 'Offered on the sign-in page' : 'Not offered'}
                    </IdentityState>
                </IdentityRow>

                <IdentityRow label="Provider in use">{providerName}</IdentityRow>
            </IdentityRows>

            <h3 className="idn-subhead">Who decides what</h3>

            <div className="org-table-scroll">
                <table className="org-table idn-ownership">
                    <thead>
                        <tr>
                            <th scope="col">Part of the sign-in page</th>
                            <th scope="col">Decided by</th>
                            <th scope="col">On this screen</th>
                        </tr>
                    </thead>
                    <tbody>
                        {OWNERSHIP.map(([element, owner, here]) => (
                            <tr key={element}>
                                <td>{element}</td>
                                <td>{owner}</td>
                                <td>{here}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            <p className="org-hint-plain">
                Nothing on the sign-in page is edited here. This screen reports what is in force and
                where each part of it is decided.
            </p>
        </IdentityPage>
    )
}
