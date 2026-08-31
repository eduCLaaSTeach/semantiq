import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'

/**
 * The legal axis. Never a level in Business Unit > Department > Team: D-14
 * records that the two axes do not align.
 */
export default function LegalEntities({ legalEntities }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ name: '', registration_number: '', jurisdiction: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/legal-entities', { onSuccess: () => form.reset() })
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Legal Entities"
            description="A separate organisational axis. A legal entity may be associated with any number of business units."
        >
            <table className="org-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Registration</th>
                        <th>Jurisdiction</th>
                        <th>Status</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {legalEntities.length === 0 ? (
                        <tr>
                            <td colSpan={5} className="org-empty">No legal entities yet.</td>
                        </tr>
                    ) : (
                        legalEntities.map((entity) => (
                            <tr key={entity.id}>
                                <td>{entity.name}</td>
                                <td>{entity.registration_number ?? '—'}</td>
                                <td>{entity.jurisdiction ?? '—'}</td>
                                <td><StatusPill status={entity.status} /></td>
                                <td>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.patch(
                                                `/console/organisation/legal-entities/${entity.id}/${
                                                    entity.status === 'active' ? 'deactivate' : 'reactivate'
                                                }`
                                            )
                                        }
                                    >
                                        {entity.status === 'active' ? 'Deactivate' : 'Reactivate'}
                                    </button>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>

            <form className="org-form org-form-inline" onSubmit={submit}>
                <label>
                    Name
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                </label>
                <label>
                    Registration number
                    <input
                        value={form.data.registration_number}
                        onChange={(e) => form.setData('registration_number', e.target.value)}
                    />
                </label>
                <label>
                    Jurisdiction
                    <input value={form.data.jurisdiction} onChange={(e) => form.setData('jurisdiction', e.target.value)} />
                </label>
                <button type="submit" className="org-action" disabled={form.processing}>
                    Add legal entity
                </button>
            </form>
        </OrganisationPage>
    )
}
