import { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import DomainsPage from '../../Components/DomainsPage'
import Pagination from '../../Components/Pagination'

/**
 * The list of business domains.
 *
 * Baseline and custom together, distinguished by a Kind column and a filter
 * rather than by a tab. Every domain shows who is accountable for it and
 * whether that person is still active — and none of that gives anybody access.
 */
export default function Index({ domains, filters, anyDomains }) {
    const { productAreas, errors } = usePage().props
    const [adding, setAdding] = useState(false)

    const form = useForm({ name: '', code: '', description: '' })

    const narrow = (changes) => {
        router.get('/console/domains', { ...filters, ...changes, page: 1 }, { preserveState: true, replace: true })
    }

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/domains', {
            onSuccess: () => {
                form.reset()
                setAdding(false)
            },
        })
    }

    return (
        <DomainsPage
            productAreas={productAreas}
            errors={errors}
            title="Domains"
            description="The seven standard domains ship with SemantIQ. Add your own alongside them. A domain does not give anybody access to anything."
            actions={
                <button type="button" className="org-action" onClick={() => setAdding(!adding)}>
                    {adding ? 'Cancel' : 'Add Domain'}
                </button>
            }
        >
            {adding ? (
                <form className="org-form org-form-profile" onSubmit={submit}>
                    <h3 className="org-form-title">Add a domain</h3>

                    <label>
                        Name
                        <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </label>
                    {form.errors.name ? <p className="org-field-error">{form.errors.name}</p> : null}

                    <label>
                        Identity code <span className="org-hint">Letters, numbers and hyphens. This never changes.</span>
                        <input value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} required />
                    </label>
                    {form.errors.code ? <p className="org-field-error">{form.errors.code}</p> : null}

                    <label>
                        Description <span className="org-hint">Optional. What this domain covers.</span>
                        <input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    </label>
                    {form.errors.description ? <p className="org-field-error">{form.errors.description}</p> : null}

                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Adding…' : 'Add domain'}
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
                    Kind
                    <select value={filters.kind} onChange={(e) => narrow({ kind: e.target.value })}>
                        <option value="">All</option>
                        <option value="baseline">Baseline</option>
                        <option value="custom">Custom</option>
                    </select>
                </label>

                <label>
                    Status
                    <select value={filters.status} onChange={(e) => narrow({ status: e.target.value })}>
                        <option value="">All</option>
                        <option value="enabled">Enabled</option>
                        <option value="disabled">Disabled</option>
                    </select>
                </label>

                <label>
                    Owner
                    <select value={filters.owner} onChange={(e) => narrow({ owner: e.target.value })}>
                        <option value="">All</option>
                        <option value="assigned">Assigned</option>
                        <option value="unassigned">Not assigned</option>
                        <option value="attention">Needs attention</option>
                    </select>
                </label>
            </div>

            {domains.data.length === 0 ? (
                <div className="org-empty">
                    {/*
                      * Two different facts, and they must not share a sentence.
                      * P1-03 shipped a group screen that said "nobody has ever
                      * been in this group" whenever a filter matched nothing,
                      * which for a group with history was simply untrue.
                      */}
                    {anyDomains ? (
                        <>
                            <p>No domains match these filters.</p>
                            <button
                                type="button"
                                className="org-action"
                                onClick={() => narrow({ search: '', kind: '', status: '', owner: '' })}
                            >
                                Clear filters
                            </button>
                        </>
                    ) : (
                        <p>No business domains yet.</p>
                    )}
                </div>
            ) : (
                <div className="org-table-scroll">
                    <table className="org-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Identity code</th>
                                <th scope="col">Kind</th>
                                <th scope="col">Status</th>
                                <th scope="col">Owner</th>
                                <th scope="col">Access expectation</th>
                            </tr>
                        </thead>
                        <tbody>
                            {domains.data.map((domain) => (
                                <tr key={domain.id}>
                                    <td><a href={`/console/domains/${domain.id}`}>{domain.name}</a></td>
                                    <td className="org-identifier">{domain.code}</td>
                                    <td>{domain.kindLabel}</td>
                                    <td>
                                        <span className={`org-pill org-pill-${domain.status}`}>{domain.statusLabel}</span>
                                    </td>
                                    <td>
                                        {domain.owner ? domain.owner.name : <span className="org-muted">Not assigned</span>}
                                        {domain.needsAttention ? (
                                            <span className="org-pill org-pill-attention">Owner inactive</span>
                                        ) : null}
                                    </td>
                                    <td>{domain.expectationLabel}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {domains.data.length > 0 ? (
                <Pagination
                    path="/console/domains"
                    query={filters}
                    page={domains.currentPage}
                    lastPage={domains.lastPage}
                    total={domains.total}
                    noun={{ one: 'domain', many: 'domains' }}
                />
            ) : null}
        </DomainsPage>
    )
}
