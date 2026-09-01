import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'
import ConfirmPurge from '../../Components/ConfirmPurge'
import LifecycleLegend from '../../Components/LifecycleLegend'
import useRowEditor from '../../Components/useRowEditor'
import usePurge from '../../Components/usePurge'

/**
 * Teams.
 *
 * Same separation as Departments, for the same reason. Edit changes the team's
 * own name and code; Move re-parents it to another department and is recorded as
 * scope-affecting. Correcting a spelling must not emit a move, or the audit
 * catalogue would record a restructure that never happened.
 *
 * department_id is therefore NOT a field the row editor touches.
 */
export default function Teams({ teams, departments }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ department_id: '', name: '', code: '' })

    // Name and code only. department_id is deliberately absent.
    const row = useRowEditor('/console/organisation/teams', ['name', 'code'])

    // D-24. Two steps by construction: ask() opens the confirmation, and only
    // confirm() sends the request.
    const purge = usePurge('/console/organisation/teams')

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/teams', { onSuccess: () => form.reset() })
    }

    const move = (team, departmentId) => {
        if (!departmentId || Number(departmentId) === team.departmentId) {
            return
        }

        router.patch(
            `/console/organisation/teams/${team.id}/move`,
            { department_id: departmentId },
            { preserveScroll: true }
        )
    }

    const lifecycle = (team) =>
        router.patch(
            `/console/organisation/teams/${team.id}/${team.status === 'active' ? 'deactivate' : 'reactivate'}`,
            {},
            { preserveScroll: true }
        )

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Teams"
            description="Teams belong to a department. Editing the name or code is not a move; changing the department is. Deactivating a team with active members is refused."
        >
            <div className="org-table-scroll">
                <table className="org-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Department</th>
                            <th>Members</th>
                            <th>Status</th>
                            <th><span className="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {teams.length === 0 ? (
                            <tr>
                                <td colSpan={6} className="org-empty">
                                    No teams yet. Add the ones that genuinely exist under a department.
                                </td>
                            </tr>
                        ) : (
                            teams.map((team) =>
                                row.isEditing(team.id) ? (
                                    <tr key={team.id}>
                                        <td>
                                            <input
                                                aria-label={`Name of ${team.name}`}
                                                value={row.draft.name}
                                                onChange={(e) => row.set('name', e.target.value)}
                                                required
                                            />
                                        </td>
                                        <td>
                                            <input
                                                aria-label={`Code for ${team.name}`}
                                                value={row.draft.code}
                                                onChange={(e) => row.set('code', e.target.value)}
                                                maxLength={32}
                                            />
                                        </td>
                                        <td className="org-meta">
                                            {team.department ?? '—'}
                                            <span className="org-hint">Use Move to change this</span>
                                        </td>
                                        <td>{team.members}</td>
                                        <td><StatusPill status={team.status} /></td>
                                        <td>
                                            <div className="org-row-actions">
                                                <button
                                                    type="button"
                                                    className="org-action"
                                                    onClick={() => row.save(team.id)}
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
                                    <tr key={team.id}>
                                        <td>
                                            <a href={`/console/organisation/teams/${team.id}`}>{team.name}</a>
                                        </td>
                                        <td>{team.code || '—'}</td>
                                        <td>
                                            <label className="org-move">
                                                <span className="org-hint" aria-hidden="true">Move to</span>
                                                <span className="sr-only">Move {team.name} to another department</span>
                                                <select
                                                    value={team.departmentId ?? ''}
                                                    onChange={(e) => move(team, e.target.value)}
                                                >
                                                    {departments.map((department) => (
                                                        <option key={department.id} value={department.id}>
                                                            {department.name}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>
                                        </td>
                                        <td>{team.members}</td>
                                        <td><StatusPill status={team.status} /></td>
                                        <td>
                                            <div className="org-row-actions">
                                                <button type="button" onClick={() => row.start(team)}>
                                                    Edit
                                                </button>
                                                <button type="button" onClick={() => lifecycle(team)}>
                                                    {team.status === 'active' ? 'Deactivate' : 'Reactivate'}
                                                </button>
                                                <button
                                                    type="button"
                                                    className="org-action-danger"
                                                    onClick={() => purge.ask(team)}
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

            <LifecycleLegend noun="team" />

            <form className="org-form org-form-inline" onSubmit={submit}>
                <h2 className="org-form-title">Add a team</h2>
                <label>
                    Department
                    <select
                        value={form.data.department_id}
                        onChange={(e) => form.setData('department_id', e.target.value)}
                        required
                    >
                        <option value="">Choose…</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.name}
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
                    <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} maxLength={32} />
                </label>
                <button type="submit" className="org-action" disabled={form.processing}>
                    Add team
                </button>
            </form>
            <ConfirmPurge
                target={purge.target}
                noun="team"
                busy={purge.busy}
                onCancel={purge.cancel}
                onConfirm={purge.confirm}
            />
        </OrganisationPage>
    )
}
