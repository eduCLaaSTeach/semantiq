import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'
import ConfirmPurge from '../../Components/ConfirmPurge'
import LifecycleLegend from '../../Components/LifecycleLegend'
import useRowEditor from '../../Components/useRowEditor'
import usePurge from '../../Components/usePurge'

/**
 * Departments.
 *
 * EDIT AND MOVE ARE DIFFERENT ACTIONS, and the screen has to say so. Edit
 * changes the department's own name and code; Move re-parents it and is
 * recorded as scope-affecting, because it is the change most likely to alter
 * someone's future scope. Correcting a spelling must never emit a move: the
 * audit catalogue would then record a restructure that never happened.
 *
 * That is why the business unit is NOT one of the fields the row editor
 * touches, and why the move control is a separate, labelled action.
 */
export default function Departments({ departments, businessUnits }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ business_unit_id: '', name: '', code: '' })

    // Name and code only. business_unit_id is deliberately absent.
    const row = useRowEditor('/console/organisation/departments', ['name', 'code'])

    // D-24. Two steps by construction: ask() opens the confirmation, and only
    // confirm() sends the request.
    const purge = usePurge('/console/organisation/departments')

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/departments', { onSuccess: () => form.reset() })
    }

    const move = (department, businessUnitId) => {
        if (!businessUnitId || Number(businessUnitId) === department.businessUnitId) {
            return
        }

        router.patch(
            `/console/organisation/departments/${department.id}/move`,
            { business_unit_id: businessUnitId },
            { preserveScroll: true }
        )
    }

    const lifecycle = (department) =>
        router.patch(
            `/console/organisation/departments/${department.id}/${
                department.status === 'active' ? 'deactivate' : 'reactivate'
            }`,
            {},
            { preserveScroll: true }
        )

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Departments"
            description="Departments belong to a business unit. Editing the name or code is not a move; changing the business unit is, and is recorded as scope-affecting."
        >
            <div className="org-table-scroll">
                <table className="org-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Business unit</th>
                            <th>Status</th>
                            <th><span className="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {departments.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="org-empty">
                                    No departments yet. Add the ones that genuinely exist under a business unit.
                                </td>
                            </tr>
                        ) : (
                            departments.map((department) =>
                                row.isEditing(department.id) ? (
                                    <tr key={department.id}>
                                        <td>
                                            <input
                                                aria-label={`Name of ${department.name}`}
                                                value={row.draft.name}
                                                onChange={(e) => row.set('name', e.target.value)}
                                                required
                                            />
                                        </td>
                                        <td>
                                            <input
                                                aria-label={`Code for ${department.name}`}
                                                value={row.draft.code}
                                                onChange={(e) => row.set('code', e.target.value)}
                                            />
                                        </td>
                                        <td className="org-meta">
                                            {department.businessUnit ?? '—'}
                                            <span className="org-hint">Use Move to change this</span>
                                        </td>
                                        <td><StatusPill status={department.status} /></td>
                                        <td>
                                            <div className="org-row-actions">
                                                <button
                                                    type="button"
                                                    className="org-action"
                                                    onClick={() => row.save(department.id)}
                                                    disabled={row.saving}
                                                >
                                                    Save
                                                </button>
                                                <button type="button" onClick={row.cancel}>
                                                    Cancel
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    <tr key={department.id}>
                                        <td>{department.name}</td>
                                        <td>{department.code || '—'}</td>
                                        <td>
                                            <label className="org-move">
                                                <span className="org-hint" aria-hidden="true">Move to</span>
                                                <span className="sr-only">Move {department.name} to another business unit</span>
                                                <select
                                                    value={department.businessUnitId}
                                                    onChange={(e) => move(department, e.target.value)}
                                                >
                                                    {businessUnits.map((unit) => (
                                                        <option key={unit.id} value={unit.id}>
                                                            {unit.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                        </td>
                                        <td><StatusPill status={department.status} /></td>
                                        <td>
                                            <div className="org-row-actions">
                                                <button type="button" onClick={() => row.start(department)}>
                                                    Edit
                                                </button>
                                                <button type="button" onClick={() => lifecycle(department)}>
                                                    {department.status === 'active' ? 'Deactivate' : 'Reactivate'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="org-action-danger"
                                                    onClick={() => purge.ask(department)}
                                                >
                                                    Delete permanently
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                )
                            )
                        )}
                    </tbody>
                </table>
            </div>

            <LifecycleLegend noun="department" />

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
                <label>
                    Code
                    <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                </label>
                <button type="submit" className="org-action" disabled={form.processing}>
                    Add department
                </button>
            </form>
            <ConfirmPurge
                target={purge.target}
                noun="department"
                busy={purge.busy}
                onCancel={purge.cancel}
                onConfirm={purge.confirm}
            />
        </OrganisationPage>
    )
}
