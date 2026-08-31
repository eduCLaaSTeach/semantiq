import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'

export default function BusinessUnits({ businessUnits }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ name: '', code: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/business-units', { onSuccess: () => form.reset() })
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Business Units"
            description="The top of the structural tree. Deactivating one with active departments is refused, not cascaded."
        >
            <table className="org-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Code</th>
                        <th>Departments</th>
                        <th>Status</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {businessUnits.length === 0 ? (
                        <tr>
                            <td colSpan={5} className="org-empty">No business units yet.</td>
                        </tr>
                    ) : (
                        businessUnits.map((unit) => (
                            <tr key={unit.id}>
                                <td>
                                    <a href={`/console/organisation/business-units/${unit.id}`}>{unit.name}</a>
                                </td>
                                <td>{unit.code ?? '—'}</td>
                                <td>{unit.departments}</td>
                                <td><StatusPill status={unit.status} /></td>
                                <td>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.patch(
                                                `/console/organisation/business-units/${unit.id}/${
                                                    unit.status === 'active' ? 'deactivate' : 'reactivate'
                                                }`
                                            )
                                        }
                                    >
                                        {unit.status === 'active' ? 'Deactivate' : 'Reactivate'}
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
                    Code
                    <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} maxLength={32} />
                </label>
                <button type="submit" className="org-action" disabled={form.processing}>
                    Add business unit
                </button>
            </form>
        </OrganisationPage>
    )
}
