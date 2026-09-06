import { useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import AccessPage from '../../Components/AccessPage'

/**
 * One role assignment, and everything that hangs beneath it.
 *
 * Entitlements are the sections; a scope and a sensitivity level live inside
 * each one. They are nested rather than listed side by side because that is
 * what they are — a scope belongs to an entitlement and means nothing without
 * it, and a flat list would ask the reader to reconstruct the relationship.
 *
 * AN ENTITLEMENT WITH NO ACTIVE SCOPE IS NOT SHOWN AS THOUGH IT WORKS. It says
 * "No access — scope required" and explains why, because the alternative is a
 * row that looks like every other row on the screen whose whole job is to say
 * who can see what.
 */
export default function Record({ assignment, entitlements, domains, scopeTypes, sensitivities, teams, businessUnits }) {
    const { productAreas, errors } = usePage().props
    const [grantingTo, setGrantingTo] = useState(false)
    const [scopingOn, setScopingOn] = useState(null)

    const entitlementForm = useForm({ business_domain_id: '' })
    const scopeForm = useForm({ scope_type: '', target_id: '' })

    const grantEntitlement = (event) => {
        event.preventDefault()
        entitlementForm.post(`/console/access/assignments/${assignment.id}/entitlements`, {
            onSuccess: () => {
                entitlementForm.reset()
                setGrantingTo(false)
            },
        })
    }

    const assignScope = (event, entitlementId) => {
        event.preventDefault()
        scopeForm.post(`/console/access/entitlements/${entitlementId}/scopes`, {
            onSuccess: () => {
                scopeForm.reset()
                setScopingOn(null)
            },
        })
    }

    const chosenScope = scopeTypes.find((scope) => scope.value === scopeForm.data.scope_type)

    return (
        <AccessPage
            productAreas={productAreas}
            errors={errors}
            back={{ href: '/console/access', label: 'role assignments' }}
            title={`${assignment.name} — ${assignment.roleLabel}`}
            description={assignment.summary}
        >
            <section className="org-record-section">
                <p className="org-meta">
                    {assignment.name} — {assignment.email}
                </p>
                <p className="org-meta">
                    Granted {assignment.assignedAt}.{' '}
                    {assignment.current ? (
                        <span className="org-pill org-pill-enabled">Current</span>
                    ) : (
                        <span className="org-pill org-pill-disabled">Revoked {assignment.endedAt}</span>
                    )}{' '}
                    {assignment.userActive ? null : (
                        <span className="org-pill org-pill-attention">Account inactive</span>
                    )}
                </p>

                {assignment.platformScoped ? (
                    <p className="org-description">
                        This role applies across the whole platform rather than within one
                        organisation. It gives no access to business information.
                    </p>
                ) : null}

                {assignment.current ? (
                    <div>
                        <p className="org-hint org-hint-plain">
                            Revoking this role also revokes every entitlement, scope and sensitivity
                            level beneath it. Re-granting the role later does not bring them back.
                            {/*
                              * The SERVER decides whether this needs step-up.
                              * The first version compared assignment.role to a
                              * raw role code here - only to pick wording, but
                              * it is the shape that must not exist: a screen
                              * that reads a role code today branches on one
                              * tomorrow, and the code itself does not belong on
                              * a user surface either.
                              */}
                            {assignment.requiresStepUpToRevoke
                                ? ' You will be asked to confirm your identity with Microsoft first.'
                                : ''}
                        </p>
                        <RevokeButton
                            href={`/console/access/assignments/${assignment.id}/revoke`}
                            label="Revoke this role"
                            danger
                        />
                    </div>
                ) : null}
            </section>

            <div className="org-section-head">
                <div>
                    <h2>Domain entitlements</h2>
                    <p className="org-description">
                        Which business domains this role may take part in. An entitlement needs a
                        scope before it gives anybody access to anything.
                    </p>
                </div>

                {assignment.current ? (
                    <div className="org-section-actions">
                        <button type="button" className="org-action" onClick={() => setGrantingTo(!grantingTo)}>
                            {grantingTo ? 'Cancel' : 'Add entitlement'}
                        </button>
                    </div>
                ) : null}
            </div>

            {grantingTo ? (
                <form className="org-form org-form-profile" onSubmit={grantEntitlement}>
                    <label>
                        Business domain
                        <select
                            value={entitlementForm.data.business_domain_id}
                            onChange={(e) => entitlementForm.setData('business_domain_id', e.target.value)}
                            required
                        >
                            <option value="">Choose a domain</option>
                            {domains.map((domain) => (
                                <option key={domain.id} value={domain.id}>
                                    {domain.name}
                                    {domain.enabled ? '' : ' (disabled)'}
                                </option>
                            ))}
                        </select>
                    </label>
                    <p className="org-hint org-hint-plain">
                        A disabled domain can still be entitled. Nobody reaches its information
                        until it is enabled again, and the entitlement is kept meanwhile.
                    </p>
                    {/*
                      * Again the SERVER decides. This screen never compares the
                      * person on the record with the person reading it.
                      */}
                    {assignment.requiresStepUpToGrantEntitlement ? (
                        <p className="org-hint org-hint-plain">
                            This role belongs to you. Adding a domain to your own access asks you to
                            confirm your identity with Microsoft first, and nothing is granted until
                            you do.
                        </p>
                    ) : null}

                    <button type="submit" className="org-action" disabled={entitlementForm.processing}>
                        {entitlementForm.processing ? 'Adding…' : 'Add entitlement'}
                    </button>
                </form>
            ) : null}

            {entitlements.length === 0 ? (
                <div className="org-empty">
                    <p>This role has no domain entitlements, so it gives no access to business information.</p>
                </div>
            ) : (
                entitlements.map((entitlement) => (
                    <section
                        key={entitlement.id}
                        className={`org-section ${entitlement.incomplete ? 'org-incomplete' : ''}`}
                    >
                        <div className="org-section-head">
                            <div>
                                <h3>{entitlement.domainName}</h3>

                                {/*
                                  * The incomplete grant path, said plainly. The
                                  * entitlement is still current — revoking a
                                  * scope does not revoke its parent — but it
                                  * authorises nothing until a new scope is
                                  * assigned.
                                  */}
                                {entitlement.incomplete ? (
                                    <p className="org-description">
                                        <span className="org-incomplete-label">No access — scope required.</span>{' '}
                                        This domain entitlement has no active scope and currently grants no
                                        business-data access.
                                    </p>
                                ) : null}

                                {!entitlement.domainEnabled && entitlement.current ? (
                                    <p className="org-description">
                                        This business domain is currently disabled, so nobody reaches its
                                        information. This entitlement is kept and works again when the domain
                                        is enabled.
                                    </p>
                                ) : null}
                            </div>

                            <div className="org-section-actions">
                                {entitlement.current ? (
                                    <span className="org-pill org-pill-enabled">Current</span>
                                ) : (
                                    <span className="org-pill org-pill-disabled">Revoked {entitlement.endedAt}</span>
                                )}
                            </div>
                        </div>

                        <h4>Sensitivity level</h4>
                        <p className="org-description">
                            The most sensitive information this entitlement may reach. Requests above it
                            are refused rather than trimmed.
                        </p>

                        {entitlement.current ? (
                            <CeilingControl entitlement={entitlement} sensitivities={sensitivities} />
                        ) : (
                            <p>{entitlement.ceilingLabel ?? 'None'}</p>
                        )}

                        <div className="org-section-head">
                            <div>
                                <h4>Scopes</h4>
                                <p className="org-description">
                                    Which records inside this domain the person may reach. Scopes add
                                    together — someone with three team scopes reaches all three teams.
                                </p>
                            </div>

                            {entitlement.current ? (
                                <div className="org-section-actions">
                                    <button
                                        type="button"
                                        className="org-action"
                                        onClick={() => setScopingOn(scopingOn === entitlement.id ? null : entitlement.id)}
                                    >
                                        {scopingOn === entitlement.id ? 'Cancel' : 'Add scope'}
                                    </button>
                                </div>
                            ) : null}
                        </div>

                        {scopingOn === entitlement.id ? (
                            <form className="org-form org-form-profile" onSubmit={(e) => assignScope(e, entitlement.id)}>
                                <label>
                                    Scope
                                    <select
                                        value={scopeForm.data.scope_type}
                                        onChange={(e) => scopeForm.setData({ scope_type: e.target.value, target_id: '' })}
                                        required
                                    >
                                        <option value="">Choose a scope</option>
                                        {scopeTypes.map((scope) => (
                                            <option key={scope.value} value={scope.value}>
                                                {scope.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                {chosenScope ? <p className="org-hint org-hint-plain">{chosenScope.description}</p> : null}

                                {/*
                                  * D-74. Domain and Organisation grant the same
                                  * records today, and saying so is a delivery
                                  * requirement rather than a note: two identical
                                  * choices presented without explanation is a
                                  * trap, and the next person to grant access
                                  * would assume one of them must be narrower.
                                  */}
                                {chosenScope && chosenScope.coversWholeDomain ? (
                                    <p className="org-hint org-hint-plain">
                                        Domain and Organisation scope grant the same records today. Domain is
                                        reserved for a future split of a domain across parts of the business.
                                    </p>
                                ) : null}

                                {chosenScope && chosenScope.requiresTarget ? (
                                    <label>
                                        {chosenScope.targetKind === 'team' ? 'Team' : 'Business unit'}
                                        <select
                                            value={scopeForm.data.target_id}
                                            onChange={(e) => scopeForm.setData('target_id', e.target.value)}
                                            required
                                        >
                                            <option value="">Choose one</option>
                                            {(chosenScope.targetKind === 'team' ? teams : businessUnits).map((target) => (
                                                <option key={target.id} value={target.id}>
                                                    {target.name}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                ) : null}

                                <button type="submit" className="org-action" disabled={scopeForm.processing}>
                                    {scopeForm.processing ? 'Adding…' : 'Add scope'}
                                </button>
                            </form>
                        ) : null}

                        {entitlement.scopes.length === 0 ? (
                            <p className="org-muted">No scopes have ever been assigned to this entitlement.</p>
                        ) : (
                            <div className="org-table-scroll">
                                <table className="org-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Scope</th>
                                            <th scope="col">Applies to</th>
                                            <th scope="col">Assigned</th>
                                            <th scope="col">State</th>
                                            <th scope="col"><span className="sr-only">Actions</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {entitlement.scopes.map((scope) => (
                                            <tr key={scope.id}>
                                                <td>{scope.label}</td>
                                                <td>
                                                    {scope.targetName ?? (
                                                        <span className="org-muted">
                                                            {scope.coversWholeDomain ? 'The whole domain' : 'Their own records'}
                                                        </span>
                                                    )}
                                                </td>
                                                <td>{scope.assignedAt}</td>
                                                <td>
                                                    {scope.current ? (
                                                        <span className="org-pill org-pill-enabled">Current</span>
                                                    ) : (
                                                        <span className="org-pill org-pill-disabled">
                                                            Revoked {scope.endedAt}
                                                        </span>
                                                    )}
                                                </td>
                                                <td>
                                                    {scope.current && entitlement.current ? (
                                                        <RevokeButton
                                                            href={`/console/access/scopes/${scope.id}/revoke`}
                                                            label="Revoke"
                                                        />
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {entitlement.current ? (
                            <div>
                                <p className="org-hint org-hint-plain">
                                    Revoking this entitlement also revokes its scopes and sensitivity level.
                                    The record of who had access is kept.
                                </p>
                                <RevokeButton
                                    href={`/console/access/entitlements/${entitlement.id}/revoke`}
                                    label="Revoke this entitlement"
                                    danger
                                />
                            </div>
                        ) : null}
                    </section>
                ))
            )}
        </AccessPage>
    )
}

/**
 * The sensitivity control.
 *
 * "Clear" is not offered as a separate action, because there is no such thing:
 * setting Standard IS the reset, and it writes a level rather than removing
 * one. An entitlement with no level at all is a fault, not a state somebody can
 * choose.
 */
function CeilingControl({ entitlement, sensitivities }) {
    const form = useForm({ sensitivity: entitlement.ceiling ?? '' })

    const submit = (event) => {
        event.preventDefault()
        form.patch(`/console/access/entitlements/${entitlement.id}/ceiling`)
    }

    const chosen = sensitivities.find((level) => level.value === form.data.sensitivity)

    return (
        <form className="org-form org-form-inline" onSubmit={submit}>
            <label>
                Level
                <select
                    value={form.data.sensitivity}
                    onChange={(e) => form.setData('sensitivity', e.target.value)}
                    required
                >
                    <option value="">Choose a level</option>
                    {sensitivities.map((level) => (
                        <option key={level.value} value={level.value}>
                            {level.label}
                        </option>
                    ))}
                </select>
            </label>

            {chosen ? <p className="org-hint org-hint-plain">{chosen.description}</p> : null}

            <button type="submit" className="org-action" disabled={form.processing}>
                {form.processing ? 'Saving…' : 'Set level'}
            </button>
        </form>
    )
}

/**
 * A revocation. A PATCH rather than a DELETE, because it ends a period and
 * destroys nothing — the verb is the honest one, and DELETE in this codebase
 * means a record is permanently gone.
 */
function RevokeButton({ href, label, danger = false }) {
    const form = useForm({})

    const submit = (event) => {
        event.preventDefault()
        form.patch(href)
    }

    return (
        <form onSubmit={submit}>
            <button
                type="submit"
                className={`org-action ${danger ? 'org-action-danger' : 'org-action-quiet'}`}
                disabled={form.processing}
            >
                {form.processing ? 'Revoking…' : label}
            </button>
        </form>
    )
}
