import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'

/**
 * The management hierarchy.
 *
 * It records who reports to whom and resolves nothing. P1-05 will walk this
 * chain to work out manager scope; a cycle would be an infinite loop in that
 * engine, which is why one is refused here before the engine exists.
 */
export default function Hierarchy({ people }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ user_id: '', manager_id: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/hierarchy', { onSuccess: () => form.reset() })
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Management Hierarchy"
            description="Each user has one current manager. A cycle is refused. This grants no access."
        >
            <table className="org-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Manager</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    {people.length === 0 ? (
                        <tr>
                            <td colSpan={4} className="org-empty">
                                No users are associated with this organisation yet.
                            </td>
                        </tr>
                    ) : (
                        people.map((person) => (
                            <tr key={person.id}>
                                <td>{person.name}</td>
                                <td>{person.email}</td>
                                <td>{person.manager ?? '—'}</td>
                                <td>
                                    {person.managerId ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.patch(`/console/organisation/hierarchy/${person.id}/clear`)
                                            }
                                        >
                                            Clear
                                        </button>
                                    ) : null}
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>

            {people.length > 1 ? (
                <form className="org-form org-form-inline" onSubmit={submit}>
                    <label>
                        User
                        <select
                            value={form.data.user_id}
                            onChange={(e) => form.setData('user_id', e.target.value)}
                            required
                        >
                            <option value="">Choose…</option>
                            {people.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label>
                        Reports to
                        <select
                            value={form.data.manager_id}
                            onChange={(e) => form.setData('manager_id', e.target.value)}
                            required
                        >
                            <option value="">Choose…</option>
                            {people.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <button type="submit" className="org-action" disabled={form.processing}>
                        Set manager
                    </button>
                </form>
            ) : null}
        </OrganisationPage>
    )
}
