import { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import PeoplePage from '../../Components/PeoplePage'
import StatusPill from '../../Components/StatusPill'
import Pagination from '../../Components/Pagination'

/**
 * The people in SemantIQ.
 *
 * "Not signed in yet" is the words, not an empty cell. A blank there reads as a
 * missing value; this reads as a person who has been added and has not arrived,
 * which is a real and expected state under D-33.
 *
 * Add User is an action on this screen, not a tab - D-38.
 */
export default function Users({ users, filters, groups }) {
    const { productAreas, errors } = usePage().props
    const [adding, setAdding] = useState(false)

    const form = useForm({ object_id: '', email: '', display_name: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/people/users', {
            onSuccess: () => {
                form.reset()
                setAdding(false)
            },
        })
    }

    // Narrowing always returns to page one. Staying on page 3 of a list that
    // now has one page shows an empty table and no explanation.
    const narrow = (changes) => {
        router.get('/console/people/users', { ...filters, ...changes, page: 1 }, { preserveState: true, replace: true })
    }

    return (
        <PeoplePage
            productAreas={productAreas}
            errors={errors}
            title="Users"
            description="Everybody who can sign in to SemantIQ, and everybody who has been added and has not yet."
            actions={
                <button type="button" className="org-action" onClick={() => setAdding(!adding)}>
                    {adding ? 'Cancel' : 'Add User'}
                </button>
            }
        >
            {adding ? (
                <form className="org-form org-form-profile" onSubmit={submit}>
                    <h3 className="org-form-title">Add a user</h3>

                    <label>
                        Microsoft Entra Object ID
                        <input
                            value={form.data.object_id}
                            onChange={(e) => form.setData('object_id', e.target.value)}
                            placeholder="00000000-0000-0000-0000-000000000000"
                            required
                        />
                    </label>
                    {form.errors.object_id ? <p className="org-field-error">{form.errors.object_id}</p> : null}

                    {/*
                      * The honest statement. SemantIQ has no Microsoft Graph
                      * permission by decision, so it can check the format and
                      * whether the ID is already used, and nothing else. A tick
                      * meaning "the format is right" that reads as "we found
                      * them" would be worse than saying so.
                      */}
                    <p className="org-hint-plain">
                        SemantIQ cannot check that this ID exists in Microsoft Entra. Copy it from the
                        person&rsquo;s profile in the Microsoft Entra admin centre. If it is wrong,
                        they will never be able to sign in and you will need to remove the record and
                        add them again.
                    </p>

                    <label>
                        Work email
                        <input
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            required
                        />
                    </label>
                    {form.errors.email ? <p className="org-field-error">{form.errors.email}</p> : null}

                    <label>
                        Display name <span className="org-hint">Optional. The email is used if you leave this blank.</span>
                        <input
                            value={form.data.display_name}
                            onChange={(e) => form.setData('display_name', e.target.value)}
                        />
                    </label>

                    <p className="org-hint-plain">
                        The email and display name are what you type until this person first signs
                        in. Microsoft replaces them with their real details then.
                    </p>

                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Adding…' : 'Add user'}
                    </button>
                </form>
            ) : null}

            <div className="org-filters">
                <label>
                    Search
                    <input
                        value={filters.search}
                        onChange={(e) => narrow({ search: e.target.value })}
                        placeholder="Name or email"
                    />
                </label>

                <label>
                    Status
                    <select value={filters.status} onChange={(e) => narrow({ status: e.target.value })}>
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </label>

                <label>
                    Organisation
                    <select value={filters.organisation} onChange={(e) => narrow({ organisation: e.target.value })}>
                        <option value="">All</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Not assigned</option>
                    </select>
                </label>

                <label>
                    Group
                    <select value={filters.group} onChange={(e) => narrow({ group: e.target.value })}>
                        <option value="">All</option>
                        {groups.map((group) => (
                            <option key={group.id} value={group.id}>{group.name}</option>
                        ))}
                    </select>
                </label>
            </div>

            {users.data.length === 0 ? (
                <div className="org-empty">
                    <p>No users match what you are looking for.</p>
                    <button type="button" className="org-action" onClick={() => narrow({ search: '', status: '', group: '', organisation: '' })}>
                        Clear filters
                    </button>
                </div>
            ) : (
                <div className="org-table-scroll">
                    <table className="org-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Status</th>
                                <th scope="col">Organisation</th>
                                <th scope="col">Last signed in</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id}>
                                    <td><a href={`/console/people/users/${user.id}`}>{user.name}</a></td>
                                    <td>{user.email}</td>
                                    <td><StatusPill status={user.status} /></td>
                                    <td>{user.organisation ?? <span className="org-hint-plain">Not assigned</span>}</td>
                                    <td>{user.lastSignedIn ?? <span className="org-hint-plain">Not signed in yet</span>}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {users.data.length > 0 ? (
                <Pagination
                    path="/console/people/users"
                    query={filters}
                    page={users.currentPage}
                    lastPage={users.lastPage}
                    total={users.total}
                    noun={{ one: 'person', many: 'people' }}
                />
            ) : null}
        </PeoplePage>
    )
}
