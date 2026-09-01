import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'

/**
 * Team detail and membership.
 *
 * Past members are shown rather than hidden. A removal sets left_at and retains
 * the row precisely so "who was in this team in March" stays answerable, and a
 * screen that never displays that would make the retention pointless.
 *
 * Only users associated with this organisation can be offered as candidates
 * (D-16). That list is built from organisation_id, never from Entra tenant_id.
 */
export default function Team({ team, members, candidates }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ user_id: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post(`/console/organisation/teams/${team.id}/members`, { onSuccess: () => form.reset() })
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            back={{ label: 'Teams', href: '/console/organisation/teams' }}
            title={team.name}
            description={`Team in ${team.department ?? 'no department'}. Membership records structure and grants no access.`}
        >
            <p className="org-meta">
                <StatusPill status={team.status} />
            </p>

            <section className="org-section">
                <h2>Members</h2>
                <table className="org-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Joined</th>
                            <th>Left</th>
                            <th><span className="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        {members.length === 0 ? (
                            <tr>
                                <td colSpan={5} className="org-empty">No members.</td>
                            </tr>
                        ) : (
                            members.map((member) => (
                                <tr key={member.id} className={member.current ? '' : 'org-row-past'}>
                                    <td>{member.name}</td>
                                    <td>{member.email}</td>
                                    <td>{member.joined_at}</td>
                                    <td>{member.left_at ?? '—'}</td>
                                    <td>
                                        {member.current ? (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.patch(
                                                        `/console/organisation/teams/${team.id}/members/${member.id}/remove`
                                                    )
                                                }
                                            >
                                                Remove
                                            </button>
                                        ) : null}
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </section>

            {candidates.length > 0 ? (
                <form className="org-form org-form-inline" onSubmit={submit}>
                        <h2 className="org-form-title">Add a member</h2>
                    <label>
                        Add a member
                        <select
                            value={form.data.user_id}
                            onChange={(e) => form.setData('user_id', e.target.value)}
                            required
                        >
                            <option value="">Choose…</option>
                            {candidates.map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                    {candidate.display_name} ({candidate.email})
                                </option>
                            ))}
                        </select>
                    </label>
                    <button type="submit" className="org-action" disabled={form.processing}>
                        Add member
                    </button>
                </form>
            ) : (
                <p className="org-empty">
                    No further users are associated with this organisation. P1-03 provisions users.
                </p>
            )}
        </OrganisationPage>
    )
}
