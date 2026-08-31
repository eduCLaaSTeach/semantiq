import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'

export default function Departments({ departments, businessUnits }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ business_unit_id: '', name: '', code: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/departments', { onSuccess: () => form.reset() })
    }

    /* A move is recorded as scope-affecting: it is the change most likely to
       alter someone's future scope. */
    const move = (department, businessUnitId) => {
        if (!businessUnitId || Number(businessUnitId) === department.businessUnitId) {
            return
        }

        router.patch(`/console/organisation/departments/${department.id}/move`, {
            business_unit_id: businessUnitId,
        })
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Departments"
            description="Departments belong to a business unit. Moving one is recorded as scope-affecting."
        >
            <table className="org-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Business unit</th>
                        <th>Status</th>
                        <th><span className="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    {departments.length === 0 ? (
                        <tr>
                            <td colSpan={4} className="org-empty">No departments yet.</td>
                        </tr>
                    ) : (
                        departments.map((department) => (
                            <tr key={department.id}>
                                <td>{department.name}</td>
                                <td>
                                    <select
                                        value={department.businessUnitId}
                                        onChange={(e) => move(department, e.target.value)}
                                        aria-label={`Business unit for ${department.name}`}
                                    >
                                        {businessUnits.map((unit) => (
                                            <option key={unit.id} value={unit.id}>
                                                {unit.name}
                                            </option>
                                        ))}
                                    </select>
                                </td>
                                <td><StatusPill status={department.status} /></td>
                                <td>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.patch(
                                                `/console/organisation/departments/${department.id}/${
                                                    department.status === 'active' ? 'deactivate' : 'reactivate'
                                                }`
                                            )
                                        }
                                    >
                                        {department.status === 'active' ? 'Deactivate' : 'Reactivate'}
                                    </button>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>

            <form className="org-form org-form-inline" onSubmit={submit}>
                        <h2 className="org-form-title">Add a department</h2>
                <label>
                    Business unit
                    <select
                        value={form.data.business_unit_id}
                        onChange={(e) => form.setData('business_unit_id', e.target.value)}
                        required
                    >
                        <option value="">Choose…</option>
                        {businessUnits.map((unit) => (
                            <option key={unit.id} value={unit.id}>
                                {unit.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Name
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                </label>
                <button type="submit" className="org-action" disabled={form.processing}>
                    Add department
                </button>
            </form>
        </OrganisationPage>
    )
}
