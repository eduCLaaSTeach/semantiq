import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'
import ConfirmPurge from '../../Components/ConfirmPurge'
import LifecycleLegend from '../../Components/LifecycleLegend'
import useRowEditor from '../../Components/useRowEditor'
import usePurge from '../../Components/usePurge'

/**
 * Business units.
 *
 * A business unit has no parent to move it to - it is the top of the structural
 * tree - so Edit here is the whole of the change operation, and there is no move
 * control to keep separate from it.
 */
export default function BusinessUnits({ businessUnits }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ name: '', code: '' })

    const row = useRowEditor('/console/organisation/business-units', ['name', 'code'])

    // D-24. Two steps by construction: ask() opens the confirmation, and only
    // confirm() sends the request.
    const purge = usePurge('/console/organisation/business-units')

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/business-units', { onSuccess: () => form.reset() })
    }

    const lifecycle = (unit) =>
        router.patch(
            `/console/organisation/business-units/${unit.id}/${
                unit.status === 'active' ? 'deactivate' : 'reactivate'
            }`,
            {},
            { preserveScroll: true }
        )

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Business Units"
            description="The top of the structural tree. Deactivating one with active departments is refused, not cascaded."
        >
            <div className="org-table-scroll">
                <table className="org-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Departments</th>
                            <th>Status</th>
                            <th><span className="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {businessUnits.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="org-empty">
                                    No business units yet. Add the ones that genuinely exist.
                                </td>
                            </tr>
                        ) : (
                            businessUnits.map((unit) =>
                                row.isEditing(unit.id) ? (
                                    <tr key={unit.id}>
                                        <td>
                                            <input
                                                aria-label={`Name of ${unit.name}`}
                                                value={row.draft.name}
                                                onChange={(e) => row.set('name', e.target.value)}
                                                required
                                            />
                                        </td>
                                        <td>
                                            <input
                                                aria-label={`Code for ${unit.name}`}
                                                value={row.draft.code}
                                                onChange={(e) => row.set('code', e.target.value)}
                                                maxLength={32}
                                            />
                                        </td>
                                        <td>{unit.departments}</td>
                                        <td><StatusPill status={unit.status} /></td>
                                        <td>
                                            <div className="org-row-actions">
                                                <button
                                                    type="button"
                                                    className="org-action"
                                                    onClick={() => row.save(unit.id)}
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
                                    <tr key={unit.id}>
                                        <td>
                                            <a href={`/console/organisation/business-units/${unit.id}`}>{unit.name}</a>
                                        </td>
                                        <td>{unit.code || '—'}</td>
                                        <td>{unit.departments}</td>
                                        <td><StatusPill status={unit.status} /></td>
                                        <td>
                                            <div className="org-row-actions">
                                                <button type="button" onClick={() => row.start(unit)}>
                                                    Edit
                                                </button>
                                                <button type="button" onClick={() => lifecycle(unit)}>
                                                    {unit.status === 'active' ? 'Deactivate' : 'Reactivate'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="org-action-danger"
                                                    onClick={() => purge.ask(unit)}
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

            <LifecycleLegend noun="business unit" />

            <form className="org-form org-form-inline" onSubmit={submit}>
                        <h2 className="org-form-title">Add a business unit</h2>
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
            <ConfirmPurge
                target={purge.target}
                noun="business unit"
                busy={purge.busy}
                onCancel={purge.cancel}
                onConfirm={purge.confirm}
            />
        </OrganisationPage>
    )
}
