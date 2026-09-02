import { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import PeoplePage from '../../Components/PeoplePage'
import StatusPill from '../../Components/StatusPill'
import Pagination from '../../Components/Pagination'

/**
 * Groups: organisational labels and membership containers.
 *
 * A group grants nothing, and the screen says so once rather than implying it by
 * omission. Add Group is an action here, not a tab - D-38.
 */
export default function Groups({ groups, filters }) {
    const { productAreas, errors } = usePage().props
    const [adding, setAdding] = useState(false)

    const form = useForm({ name: '', code: '', description: '' })

    const narrow = (changes) => {
        router.get('/console/people/groups', { ...filters, ...changes, page: 1 }, { preserveState: true, replace: true })
    }

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/people/groups', {
            onSuccess: () => {
                form.reset()
                setAdding(false)
            },
        })
    }

    return (
        <PeoplePage
            productAreas={productAreas}
            errors={errors}
            title="Groups"
            description="Ways of grouping people. A group does not give anybody access to anything."
            actions={
                <button type="button" className="org-action" onClick={() => setAdding(!adding)}>
                    {adding ? 'Cancel' : 'Add Group'}
                </button>
            }
        >
            {adding ? (
                <form className="org-form org-form-profile" onSubmit={submit}>
                    <h3 className="org-form-title">Add a group</h3>

                    <label>
                        Name
                        <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </label>
                    {form.errors.name ? <p className="org-field-error">{form.errors.name}</p> : null}

                    <label>
                        Code <span className="org-hint">Optional. A short identifier.</span>
                        <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} />
                    </label>

                    <label>
                        Description <span className="org-hint">Optional.</span>
                        <input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </label>

                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Adding…' : 'Add group'}
                    </button>
                </form>
            ) : null}

            <div className="org-filters">
                <label>
                    Search
                    <input
                        value={filters.search}
                        onChange={(e) => narrow({ search: e.target.value })}
                        placeholder="Name, code or description"
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
            </div>

            {groups.data.length === 0 ? (
                <div className="org-empty">
                    <p>No groups match what you are looking for.</p>
                    <button type="button" className="org-action" onClick={() => narrow({ search: '', status: '' })}>
                        Clear filters
                    </button>
                </div>
            ) : (
                <div className="org-table-scroll">
                    <table className="org-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Code</th>
                                <th scope="col">Description</th>
                                <th scope="col">Members</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {groups.data.map((group) => (
                                <tr key={group.id}>
                                    <td><a href={`/console/people/groups/${group.id}`}>{group.name}</a></td>
                                    <td>{group.code ?? '—'}</td>
                                    <td>{group.description ?? '—'}</td>
                                    <td>{group.members}</td>
                                    <td><StatusPill status={group.status} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {groups.data.length > 0 ? (
                <Pagination
                    path="/console/people/groups"
                    query={filters}
                    page={groups.currentPage}
                    lastPage={groups.lastPage}
                    total={groups.total}
                    noun={{ one: 'group', many: 'groups' }}
                />
            ) : null}
        </PeoplePage>
    )
}
