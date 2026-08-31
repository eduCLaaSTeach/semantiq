import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'

export default function Teams({ teams, departments }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ department_id: '', name: '', code: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/teams', { onSuccess: () => form.reset() })
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Teams"
            description="Teams belong to a department. Deactivating one with active members is refused."
        >
            <table className="org-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {teams.length === 0 ? (
                        <tr>
                            <td colSpan={5} className="org-empty">No teams yet.</td>
                        </tr>
                    ) : (
                        teams.map((team) => (
                            <tr key={team.id}>
                                <td>
                                    <a href={`/console/organisation/teams/${team.id}`}>{team.name}</a>
                                </td>
                                <td>{team.department ?? '—'}</td>
                                <td>{team.members}</td>
                                <td><StatusPill status={team.status} /></td>
                                <td>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.patch(
                                                `/console/organisation/teams/${team.id}/${
                                                    team.status === 'active' ? 'deactivate' : 'reactivate'
                                                }`
                                            )
                                        }
                                    >
                                        {team.status === 'active' ? 'Deactivate' : 'Reactivate'}
                                    </button>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>

            <form className="org-form org-form-inline" onSubmit={submit}>
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
                <button type="submit" className="org-action" disabled={form.processing}>
                    Add team
                </button>
            </form>
        </OrganisationPage>
    )
}
