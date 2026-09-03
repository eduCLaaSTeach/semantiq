import { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import DomainsPage from '../../Components/DomainsPage'
import ConfirmPurge from '../../Components/ConfirmPurge'

/**
 * One business domain: what it is, who is accountable for it, whether the
 * organisation is using it, and what it expects about access.
 *
 * THE SENTENCE THAT MATTERS is in the Accountability section. Naming somebody
 * accountable for Finance reads like giving them Finance to almost every
 * reader, and it does not. It is said plainly rather than left to be inferred.
 */
export default function Record({ domain, history, expectations, candidates }) {
    const { productAreas, errors } = usePage().props
    const [confirming, setConfirming] = useState(false)

    const details = useForm({
        name: domain.name,
        description: domain.description ?? '',
        access_expectation: domain.expectation,
    })

    const owner = useForm({ user_id: domain.owner ? String(domain.owner.id) : '' })

    const save = (event) => {
        event.preventDefault()
        details.put(`/console/domains/${domain.id}`)
    }

    const assign = (event) => {
        event.preventDefault()
        owner.patch(`/console/domains/${domain.id}/owner`)
    }

    return (
        <DomainsPage
            productAreas={productAreas}
            errors={errors}
            back={{ href: '/console/domains', label: 'Business Domains' }}
            title={domain.name}
            description={`${domain.kindLabel} domain`}
        >
            <section className="org-record-section">
                <h3>Details</h3>

                <form className="org-form org-form-profile" onSubmit={save}>
                    <label>
                        Name
                        <input value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} required />
                    </label>
                    {details.errors.name ? <p className="org-field-error">{details.errors.name}</p> : null}

                    <label>
                        Identity code
                        <input value={domain.code} readOnly disabled />
                        <span className="org-hint">This never changes, even if the name does.</span>
                    </label>

                    <label>
                        Description
                        <input
                            value={details.data.description}
                            onChange={(e) => details.setData('description', e.target.value)}
                            placeholder="What this domain covers"
                        />
                    </label>
                    {details.errors.description ? <p className="org-field-error">{details.errors.description}</p> : null}

                    <label>
                        Access expectation
                        <select
                            value={details.data.access_expectation}
                            onChange={(e) => details.setData('access_expectation', e.target.value)}
                        >
                            {expectations.map((option) => (
                                <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                        </select>
                        {/*
                          * This field is ADVISORY. No lock icon, no shield, and
                          * not the word "policy" — every one of those implies
                          * enforcement that does not exist. P1-02 was corrected
                          * for the same class of overclaim.
                          */}
                        <span className="org-hint">
                            This is a statement of intent. It does not grant or restrict anything today.
                            Access is assigned in Roles &amp; Access.
                        </span>
                    </label>

                    <button type="submit" className="org-action" disabled={details.processing}>
                        {details.processing ? 'Saving…' : 'Save changes'}
                    </button>
                </form>
            </section>

            <section className="org-record-section">
                <h3>Accountability</h3>

                <p className="org-description">
                    The owner is accountable for this domain. They do not get access to it.
                    Access is assigned in Roles &amp; Access.
                </p>

                {domain.needsAttention ? (
                    <div className="org-attention" role="status">
                        <strong>Needs attention — owner inactive.</strong> The domain remains enabled.
                        Assign an active owner when you can. This ownership status does not change
                        anyone&rsquo;s access.
                    </div>
                ) : null}

                <form className="org-form org-form-inline" onSubmit={assign}>
                    <label>
                        Owner
                        <select value={owner.data.user_id} onChange={(e) => owner.setData('user_id', e.target.value)} required>
                            <option value="">Choose a person</option>
                            {candidates.map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                    {candidate.name} — {candidate.email}
                                </option>
                            ))}
                        </select>
                    </label>

                    <button type="submit" className="org-action" disabled={owner.processing}>
                        {domain.owner ? 'Change owner' : 'Set owner'}
                    </button>

                    {domain.owner ? (
                        <button
                            type="button"
                            className="org-action org-action-quiet"
                            onClick={() => router.patch(`/console/domains/${domain.id}/owner/clear`)}
                        >
                            Clear owner
                        </button>
                    ) : null}
                </form>

                {history.length === 0 ? (
                    <p className="org-empty-inline">Nobody has been accountable for this domain yet.</p>
                ) : (
                    <div className="org-table-scroll">
                        <table className="org-table">
                            <thead>
                                <tr>
                                    <th scope="col">Person</th>
                                    <th scope="col">From</th>
                                    <th scope="col">Until</th>
                                    <th scope="col">Period</th>
                                </tr>
                            </thead>
                            <tbody>
                                {history.map((period) => (
                                    <tr key={period.id} className={period.current ? undefined : 'org-row-past'}>
                                        <td>
                                            {period.name}
                                            {period.current && period.userStatus !== 'active' ? (
                                                <span className="org-pill org-pill-attention">Inactive</span>
                                            ) : null}
                                        </td>
                                        <td>{period.assignedAt}</td>
                                        <td>{period.endedAt ?? '—'}</td>
                                        <td>{period.current ? 'Current' : 'Ended'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <section className="org-record-section">
                <h3>Availability</h3>

                <p className="org-description">
                    Enabled means this organisation is using this domain. It is not a permission,
                    and disabling a domain takes nothing away from anybody.
                </p>

                <p>
                    Status: <span className={`org-pill org-pill-${domain.status}`}>{domain.statusLabel}</span>
                </p>

                {domain.status === 'enabled' ? (
                    <button
                        type="button"
                        className="org-action"
                        onClick={() => router.patch(`/console/domains/${domain.id}/disable`)}
                    >
                        Disable
                    </button>
                ) : (
                    <button
                        type="button"
                        className="org-action"
                        onClick={() => router.patch(`/console/domains/${domain.id}/enable`)}
                    >
                        Enable
                    </button>
                )}
            </section>

            {/*
              * Permanent removal exists ONLY for a custom domain nobody has ever
              * been accountable for — the domain created by mistake five minutes
              * ago. A baseline domain has no control here at all, and the server
              * refuses one regardless of what this renders.
              */}
            {domain.kind === 'custom' ? (
                <section className="org-record-section">
                    <h3>Permanent removal</h3>

                    {domain.purgeable ? (
                        <>
                            <p className="org-description">
                                This domain has never had an owner and nothing refers to it, so it can
                                still be removed permanently. Once somebody has been accountable for it,
                                disable it instead.
                            </p>
                            <button type="button" className="org-action org-action-danger" onClick={() => setConfirming(true)}>
                                Remove permanently
                            </button>
                        </>
                    ) : (
                        <p className="org-description">
                            This domain has ownership history, so it is kept. Disable it instead — that
                            keeps the record of who was accountable for it.
                        </p>
                    )}
                </section>
            ) : null}

            {confirming ? (
                <ConfirmPurge
                    target={domain.name}
                    noun="domain"
                    busy={false}
                    onCancel={() => setConfirming(false)}
                    onConfirm={() => {
                        setConfirming(false)
                        router.delete(`/console/domains/${domain.id}`)
                    }}
                />
            ) : null}
        </DomainsPage>
    )
}
