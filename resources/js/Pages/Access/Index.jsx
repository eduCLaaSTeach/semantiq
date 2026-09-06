import { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AccessPage from '../../Components/AccessPage'
import Pagination from '../../Components/Pagination'

/**
 * Who holds which role.
 *
 * The list is role ASSIGNMENTS rather than people, because one person may hold
 * several roles and a row per person would have to hide that or invent a way to
 * summarise it. Every row links to the record page, where the entitlements
 * beneath that role live.
 *
 * The role picker shows what each role actually does, next to its name. A
 * reader choosing between "Executive" and "Domain Owner / Director" has no way
 * to know which is wider from the names alone, and getting that wrong is a
 * security decision made by guesswork.
 */
export default function Index({ assignments, filters, roles, candidates, anyAssignments, soleAdministratorWarning }) {
    const { productAreas, errors } = usePage().props
    const [granting, setGranting] = useState(false)

    const form = useForm({ user_id: '', role_code: '' })

    const narrow = (changes) => {
        router.get('/console/access', { ...filters, ...changes, page: 1 }, { preserveState: true, replace: true })
    }

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/access/assignments', {
            onSuccess: () => {
                form.reset()
                setGranting(false)
            },
        })
    }

    const chosenRole = roles.find((role) => role.value === form.data.role_code)

    return (
        <AccessPage
            productAreas={productAreas}
            errors={errors}
            warning={soleAdministratorWarning}
            title="Role assignments"
            description="Every role somebody currently holds. Open one to give it access to a business domain."
            actions={
                <button type="button" className="org-action" onClick={() => setGranting(!granting)}>
                    {granting ? 'Cancel' : 'Grant a role'}
                </button>
            }
        >
            {granting ? (
                <form className="org-form org-form-profile" onSubmit={submit}>
                    <h3 className="org-form-title">Grant a role</h3>

                    <label>
                        Person
                        <select
                            value={form.data.user_id}
                            onChange={(e) => form.setData('user_id', e.target.value)}
                            required
                        >
                            <option value="">Choose somebody</option>
                            {candidates.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name} — {person.email}
                                </option>
                            ))}
                        </select>
                    </label>
                    {form.errors.user_id ? <p className="org-field-error">{form.errors.user_id}</p> : null}

                    <label>
                        Role
                        <select
                            value={form.data.role_code}
                            onChange={(e) => form.setData('role_code', e.target.value)}
                            required
                        >
                            <option value="">Choose a role</option>
                            {roles.map((role) => (
                                <option key={role.value} value={role.value}>
                                    {role.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    {/*
                      * What the chosen role actually does, before it is granted
                      * rather than after. The names alone do not say which is
                      * wider, and guessing is a security decision.
                      */}
                    {chosenRole ? <p className="org-hint org-hint-plain">{chosenRole.summary}</p> : null}
                    {form.errors.role_code ? <p className="org-field-error">{form.errors.role_code}</p> : null}

                    <p className="org-hint org-hint-plain">
                        Granting the System Administrator or Organisation Administrator role, or
                        granting anything to yourself, asks you to confirm your identity with
                        Microsoft first.
                    </p>

                    <button type="submit" className="org-action" disabled={form.processing}>
                        {form.processing ? 'Granting…' : 'Grant role'}
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
                    Role
                    <select value={filters.role} onChange={(e) => narrow({ role: e.target.value })}>
                        <option value="">All roles</option>
                        {roles.map((role) => (
                            <option key={role.value} value={role.value}>
                                {role.label}
                            </option>
                        ))}
                    </select>
                </label>

                <label>
                    Shows
                    <select value={filters.state} onChange={(e) => narrow({ state: e.target.value })}>
                        <option value="">Current only</option>
                        <option value="ended">Revoked</option>
                        <option value="all">Everything</option>
                    </select>
                </label>
            </div>

            {assignments.data.length === 0 ? (
                <div className="org-empty">
                    {/*
                      * Two different facts, and they must not share a sentence.
                      * P1-03 shipped a screen that said "nobody has ever been
                      * in this group" whenever a filter matched nothing.
                      */}
                    {anyAssignments ? (
                        <>
                            <p>No role assignments match these filters.</p>
                            <button
                                type="button"
                                className="org-action"
                                onClick={() => narrow({ search: '', role: '', state: '' })}
                            >
                                Clear filters
                            </button>
                        </>
                    ) : (
                        <p>No roles have been granted yet.</p>
                    )}
                </div>
            ) : (
                <div className="org-table-scroll">
                    <table className="org-table">
                        <thead>
                            <tr>
                                <th scope="col">Person</th>
                                <th scope="col">Role</th>
                                <th scope="col">Account</th>
                                <th scope="col">Domains entitled</th>
                                <th scope="col">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            {assignments.data.map((assignment) => (
                                <tr key={assignment.id}>
                                    <td>
                                        <a href={`/console/access/assignments/${assignment.id}`}>{assignment.name}</a>
                                        <div className="org-muted">{assignment.email}</div>
                                    </td>
                                    <td>{assignment.roleLabel}</td>
                                    <td>
                                        {assignment.userActive ? (
                                            <span className="org-pill org-pill-active">Active</span>
                                        ) : (
                                            /*
                                              * Shown because an inactive person
                                              * keeps their grants and gets them
                                              * back exactly on reactivation.
                                              * Hiding it would make a denial
                                              * look like a defect.
                                              */
                                            <span className="org-pill org-pill-inactive">Inactive</span>
                                        )}
                                    </td>
                                    <td>
                                        {assignment.entitlementCount === 0 ? (
                                            <span className="org-muted">None — grants no access</span>
                                        ) : (
                                            assignment.entitlementCount
                                        )}
                                    </td>
                                    <td>
                                        {assignment.current ? (
                                            <span className="org-pill org-pill-enabled">Current</span>
                                        ) : (
                                            <span className="org-pill org-pill-disabled">Revoked</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {assignments.data.length > 0 ? (
                <Pagination
                    path="/console/access"
                    query={filters}
                    page={assignments.currentPage}
                    lastPage={assignments.lastPage}
                    total={assignments.total}
                    noun={{ one: 'role assignment', many: 'role assignments' }}
                />
            ) : null}
        </AccessPage>
    )
}
