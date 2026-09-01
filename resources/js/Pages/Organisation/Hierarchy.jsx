import { router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import OrganisationPage from '../../Components/OrganisationPage'

/**
 * The management hierarchy.
 *
 * It records who reports to whom and resolves nothing. P1-05 will walk this
 * chain to work out manager scope; a cycle would be an infinite loop in that
 * engine, which is why one is refused here before the engine exists.
 *
 * Set and Change are the same operation to the server - one current manager per
 * user - so the screen uses one control and only changes its label. Clear ends
 * the current link; it does not erase it, and the ended row stays answerable.
 *
 * A user is never offered as their own manager. That is a convenience, not the
 * guard: the server refuses self-management and cycles regardless of what the
 * browser sends.
 */
export default function Hierarchy({ people }) {
    const { productAreas, errors } = usePage().props
    const [editing, setEditing] = useState(null)
    const [choice, setChoice] = useState('')
    const [saving, setSaving] = useState(false)

    // One user cannot report to anybody. Say so, rather than showing a control
    // that can only fail.
    const assignable = people.length > 1

    const start = (person) => {
        setEditing(person.id)
        setChoice(person.managerId ? String(person.managerId) : '')
    }

    const cancel = () => {
        setEditing(null)
        setChoice('')
    }

    const save = (person) => {
        if (!choice) {
            return
        }

        setSaving(true)

        router.post(
            '/console/organisation/hierarchy',
            { user_id: person.id, manager_id: choice },
            { preserveScroll: true, onSuccess: cancel, onFinish: () => setSaving(false) }
        )
    }

    const clear = (person) =>
        router.patch(`/console/organisation/hierarchy/${person.id}/clear`, {}, { preserveScroll: true })

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Management Hierarchy"
            description="Each user has one current manager. Changing it ends the previous link and keeps its history. A cycle is refused. This grants no access."
        >
            <table className="org-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Manager</th>
                        <th><span className="sr-only">Actions</span></th>
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
                        people.map((person) =>
                            editing === person.id ? (
                                <tr key={person.id}>
                                    <td>{person.name}</td>
                                    <td>{person.email}</td>
                                    <td>
                                        <select
                                            aria-label={`Manager for ${person.name}`}
                                            value={choice}
                                            onChange={(e) => setChoice(e.target.value)}
                                            required
                                        >
                                            <option value="">Choose…</option>
                                            {people
                                                .filter((candidate) => candidate.id !== person.id)
                                                .map((candidate) => (
                                                    <option key={candidate.id} value={candidate.id}>
                                                        {candidate.name}
                                                    </option>
                                                ))}
                                        </select>
                                    </td>
                                    <td>
                                        <div className="org-row-actions">
                                            <button
                                                type="button"
                                                className="org-action"
                                                onClick={() => save(person)}
                                                disabled={saving || !choice}
                                            >
                                                Save
                                            </button>
                                            <button type="button" onClick={cancel}>
                                                Cancel
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                <tr key={person.id}>
                                    <td>{person.name}</td>
                                    <td>{person.email}</td>
                                    <td>{person.manager ?? 'No manager recorded'}</td>
                                    <td>
                                        <div className="org-row-actions">
                                            {assignable ? (
                                                <button type="button" onClick={() => start(person)}>
                                                    {person.managerId ? 'Change manager' : 'Set manager'}
                                                </button>
                                            ) : null}
                                            {person.managerId ? (
                                                <button type="button" onClick={() => clear(person)}>
                                                    Clear
                                                </button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            )
                        )
                    )}
                </tbody>
            </table>

            {people.length === 1 ? (
                <p className="org-note">
                    A manager can be assigned when at least two organisation users are available. Only one user is
                    associated with this organisation, and nobody can report to themselves. Adding users is not part of
                    Organisation — it arrives with User Management.
                </p>
            ) : null}
        </OrganisationPage>
    )
}
