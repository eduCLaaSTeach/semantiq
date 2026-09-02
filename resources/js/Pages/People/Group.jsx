import { router, useForm, usePage } from '@inertiajs/react'
import PeoplePage from '../../Components/PeoplePage'
import StatusPill from '../../Components/StatusPill'
import Pagination from '../../Components/Pagination'

/**
 * One group, its details, and its members past and present.
 *
 * Ended memberships are QUIETENED, not hidden. Retaining them is the only reason
 * to keep a membership table rather than a list of current members, so hiding
 * them would throw away the thing being kept.
 */
export default function Group({ group, members, candidates, filters, everHadMembers }) {
    const { productAreas, errors } = usePage().props

    const narrow = (changes) => {
        router.get(
            `/console/people/groups/${group.id}`,
            { ...filters, ...changes, page: 1 },
            { preserveState: true, replace: true }
        )
    }

    const details = useForm({
        name: group.name,
        code: group.code ?? '',
        description: group.description ?? '',
    })

    const member = useForm({ user_id: '' })
    const status = useForm({})
    const purge = useForm({})

    return (
        <PeoplePage
            productAreas={productAreas}
            errors={errors}
            back={{ href: '/console/people/groups', label: 'Groups' }}
            title={group.name}
            description="A group does not give anybody access to anything."
            actions={
                group.status === 'active' ? (
                    <button
                        type="button"
                        className="org-action"
                        disabled={status.processing}
                        onClick={() => status.patch(`/console/people/groups/${group.id}/deactivate`)}
                    >
                        Deactivate
                    </button>
                ) : (
                    <button
                        type="button"
                        className="org-action"
                        disabled={status.processing}
                        onClick={() => status.patch(`/console/people/groups/${group.id}/reactivate`)}
                    >
                        Reactivate
                    </button>
                )
            }
        >
            <p className="org-meta">Status: <StatusPill status={group.status} /></p>

            <form
                className="org-form org-form-profile"
                onSubmit={(e) => {
                    e.preventDefault()
                    details.put(`/console/people/groups/${group.id}`)
                }}
            >
                <p className="org-form-title">Group details</p>

                <label>
                    Name
                    <input value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} required />
                </label>
                {details.errors.name ? <p className="org-field-error">{details.errors.name}</p> : null}

                <label>
                    Code
                    <input value={details.data.code} onChange={(e) => details.setData('code', e.target.value)} />
                </label>

                <label>
                    Description
                    <input value={details.data.description} onChange={(e) => details.setData('description', e.target.value)} />
                </label>

                <button type="submit" className="org-action" disabled={details.processing}>
                    {details.processing ? 'Saving…' : 'Save group'}
                </button>
            </form>

            <h3 className="idn-subhead">Members</h3>

            {candidates.length > 0 && group.status === 'active' ? (
                <form
                    className="org-form-inline"
                    onSubmit={(e) => {
                        e.preventDefault()
                        member.post(`/console/people/groups/${group.id}/members`, {
                            onSuccess: () => member.reset(),
                        })
                    }}
                >
                    <label>
                        Add a member
                        <select value={member.data.user_id} onChange={(e) => member.setData('user_id', e.target.value)} required>
                            <option value="">Choose a person</option>
                            {candidates.map((person) => (
                                <option key={person.id} value={person.id}>{person.display_name}</option>
                            ))}
                        </select>
                    </label>
                    <button type="submit" className="org-action" disabled={member.processing}>Add member</button>
                </form>
            ) : null}

            {everHadMembers ? (
                <div className="org-form-inline">
                    <label>
                        Search members
                        <input
                            value={filters.search}
                            onChange={(e) => narrow({ search: e.target.value })}
                            placeholder="Name or email"
                        />
                    </label>

                    <label>
                        Membership
                        <select value={filters.period} onChange={(e) => narrow({ period: e.target.value })}>
                            <option value="">All</option>
                            <option value="current">Current</option>
                            <option value="past">Past</option>
                        </select>
                    </label>
                </div>
            ) : null}

            {members.data.length === 0 ? (
                <div className="org-empty">
                    {/*
                      * Two different facts, and conflating them would state a
                      * falsehood: an empty group has never had anybody, whereas
                      * an empty RESULT means the filter matched nobody.
                      */}
                    {everHadMembers ? (
                        <>
                            <p>No members match what you are looking for.</p>
                            <button type="button" className="org-action" onClick={() => narrow({ search: '', period: '' })}>
                                Clear filters
                            </button>
                        </>
                    ) : (
                        <p>Nobody has ever been in this group.</p>
                    )}
                </div>
            ) : (
                <div className="org-table-scroll">
                    <table className="org-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Joined</th>
                                <th scope="col">Left</th>
                                <th scope="col"><span className="org-hint-plain">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            {members.data.map((entry) => (
                                <tr key={entry.id} className={entry.current ? undefined : 'org-row-past'}>
                                    <td><a href={`/console/people/users/${entry.userId}`}>{entry.name}</a></td>
                                    <td>{entry.email}</td>
                                    <td>{entry.joinedAt}</td>
                                    <td>{entry.leftAt ?? 'Current'}</td>
                                    <td>
                                        <div className="org-row-actions">
                                            {entry.current ? (
                                                <button
                                                    type="button"
                                                    className="org-action"
                                                    onClick={() => member.patch(`/console/people/groups/${group.id}/members/${entry.id}/remove`)}
                                                >
                                                    End membership
                                                </button>
                                            ) : null}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {members.data.length > 0 ? (
                <Pagination
                    path={`/console/people/groups/${group.id}`}
                    query={filters}
                    page={members.currentPage}
                    lastPage={members.lastPage}
                    total={members.total}
                    noun={{ one: 'membership', many: 'memberships' }}
                />
            ) : null}

            {group.purgeable ? (
                <div className="org-section">
                    <h3 className="idn-subhead">Remove permanently</h3>
                    <p className="org-hint-plain">
                        Nobody has ever been in this group, so it can be removed. This cannot be
                        undone.
                    </p>
                    <button
                        type="button"
                        className="org-action org-action-danger"
                        disabled={purge.processing}
                        onClick={() => purge.delete(`/console/people/groups/${group.id}`)}
                    >
                        Remove permanently
                    </button>
                </div>
            ) : (
                <p className="org-hint-plain">
                    This group has membership history, so it is kept. Deactivate it instead of
                    removing it.
                </p>
            )}
        </PeoplePage>
    )
}
