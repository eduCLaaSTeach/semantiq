import { useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import PeoplePage from '../../Components/PeoplePage'
import StatusPill from '../../Components/StatusPill'
import { IdentityRow, IdentityRows } from '../../Components/IdentityRows'

/**
 * One person, and every lifecycle action that acts on them.
 *
 * The Platform role row is SHOWN and read-only, saying that roles come later.
 * Hiding it would leave an administrator hunting for a control that does not
 * exist; naming the absence is the honest version.
 *
 * Reveal is a server round-trip - the P1-02 D-27 pattern, reused. The full
 * Object ID and tenant never reach the page payload, so the mask is real rather
 * than decoration.
 */
export default function User({ person, dependencies, teams, manages, groups, organisations }) {
    const { productAreas, errors } = usePage().props
    const [revealed, setRevealed] = useState({})
    const [failed, setFailed] = useState(null)
    const [confirming, setConfirming] = useState(false)

    const status = useForm({})
    const purge = useForm({})
    const organisation = useForm({ organisation_id: person.organisationId ?? '' })

    const xsrfToken = () => {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)

        return match ? decodeURIComponent(match[1]) : ''
    }

    const reveal = async (field) => {
        if (revealed[field]) {
            setRevealed({ ...revealed, [field]: null })
            return
        }

        setFailed(null)

        try {
            const response = await fetch(`/console/people/users/${person.id}/reveal`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ field }),
            })

            if (!response.ok) {
                setFailed('That value could not be shown. Try again.')
                return
            }

            setRevealed({ ...revealed, [field]: (await response.json()).value })
        } catch {
            setFailed('That value could not be shown. Try again.')
        }
    }

    const identifier = (field, masked) => (
        <>
            <span className="idn-value">{revealed[field] ?? masked}</span>
            <span className="idn-value-actions">
                <button type="button" className="org-action" onClick={() => reveal(field)}>
                    {revealed[field] ? 'Hide' : 'Reveal'}
                </button>
            </span>
        </>
    )

    const summary = dependencies.length === 0
        ? 'This user has no current teams, groups or reports.'
        : `This user currently ${dependencies.join(', ')}. Deactivation stops their SemantIQ access but does not remove these relationships.`

    return (
        <PeoplePage
            productAreas={productAreas}
            errors={errors}
            back={{ href: '/console/people/users', label: 'Users' }}
            title={person.name}
            description={person.email}
            actions={
                person.status === 'active' ? (
                    <button type="button" className="org-action" onClick={() => setConfirming(true)}>
                        Deactivate
                    </button>
                ) : (
                    <button
                        type="button"
                        className="org-action"
                        disabled={status.processing}
                        onClick={() => status.patch(`/console/people/users/${person.id}/reactivate`)}
                    >
                        Reactivate
                    </button>
                )
            }
        >
            {failed ? <div className="org-refusal" role="alert">{failed}</div> : null}

            {confirming ? (
                <div className="org-confirm" role="alertdialog" aria-label="Deactivate this user">
                    <p className="org-confirm-name">Deactivate {person.name}?</p>
                    <p className="org-confirm-guidance">{summary}</p>
                    <p className="org-confirm-guidance">
                        Their access stops at their next request. Nothing is deleted, and you can
                        reactivate them at any time.
                    </p>
                    <div className="org-confirm-actions">
                        <button
                            type="button"
                            className="org-action org-action-danger"
                            disabled={status.processing}
                            onClick={() => status.patch(`/console/people/users/${person.id}/deactivate`, {
                                onFinish: () => setConfirming(false),
                            })}
                        >
                            Deactivate
                        </button>
                        <button type="button" className="org-action" onClick={() => setConfirming(false)}>
                            Cancel
                        </button>
                    </div>
                </div>
            ) : null}

            <IdentityRows>
                <IdentityRow label="Status"><StatusPill status={person.status} /></IdentityRow>

                <IdentityRow label="Signed in">
                    {person.lastSignedIn ?? 'Not signed in yet'}
                </IdentityRow>

                <IdentityRow label="Provider">{person.provider}</IdentityRow>

                <IdentityRow
                    label="Object ID"
                    note="From Microsoft Entra. It identifies this person and cannot be changed here."
                >
                    {identifier('object_id', person.objectIdMasked)}
                </IdentityRow>

                <IdentityRow label="Directory (tenant)" note="From Microsoft Entra.">
                    {identifier('tenant', person.tenantMasked)}
                </IdentityRow>

                <IdentityRow
                    label="Name and email"
                    note={
                        person.lastSignedIn
                            ? 'From Microsoft Entra, refreshed each time this person signs in.'
                            : 'Provisional. These were entered by an administrator and have not been confirmed by Microsoft. They will be replaced when this person first signs in.'
                    }
                >
                    {person.name} &middot; {person.email}
                </IdentityRow>

                <IdentityRow
                    label="Organisation"
                    note={
                        /*
                          * No note when the change is blocked: the refusal
                          * below already explains why, and a second sentence
                          * saying the same thing differently reads as two
                          * different reasons.
                          */
                        person.organisationId !== null && dependencies.length > 0
                            ? null
                            : person.organisationId === null
                                ? 'Somebody must belong to an organisation before they can join a group.'
                                : 'Their teams, groups and reporting lines all belong to this organisation.'
                    }
                >
                    {/*
                      * Assigning is easy; CHANGING is guarded, and the guard is
                      * the service's. A control that cannot act is not rendered
                      * - the reason is stated instead, as P1-01 settled.
                      */}
                    {person.organisationId !== null && dependencies.length > 0 ? (
                        <>
                            {/* An organisation's name is a name, not an
                              * identifier - .idn-value is the monospace face
                              * the Object ID and tenant are set in. */}
                            <span>{person.organisationName}</span>
                            <p className="org-hint-plain">
                                This cannot be changed while this person {dependencies.join(', ')}. End
                                those first, because they all belong to this organisation.
                            </p>
                        </>
                    ) : (
                        <form
                            className="org-filters"
                            onSubmit={(e) => {
                                e.preventDefault()
                                organisation.put(`/console/people/users/${person.id}`)
                            }}
                        >
                            <label>
                                {/*
                                  * The row already says "Organisation". A visible
                                  * second copy read as a heading above the control
                                  * and made the row say its own name twice, so the
                                  * name stays for assistive technology only.
                                  */}
                                <span className="sr-only">Organisation</span>
                                <select
                                    aria-label="Organisation"
                                    value={organisation.data.organisation_id}
                                    onChange={(e) => organisation.setData('organisation_id', e.target.value)}
                                >
                                    <option value="">Not assigned</option>
                                    {organisations.map((option) => (
                                        <option key={option.id} value={option.id}>{option.name}</option>
                                    ))}
                                </select>
                            </label>
                            <button type="submit" className="org-action" disabled={organisation.processing}>
                                {organisation.processing ? 'Saving…' : 'Save organisation'}
                            </button>
                        </form>
                    )}
                </IdentityRow>

                <IdentityRow label="Platform role" note="Roles are assigned in a later release.">
                    None
                </IdentityRow>
            </IdentityRows>

            <h3 className="idn-subhead">What this person is part of</h3>

            <p className="org-hint-plain">{summary}</p>

            <div className="org-table-scroll">
                <table className="org-table idn-ownership">
                    <thead>
                        <tr>
                            <th scope="col">Group</th>
                            <th scope="col">Joined</th>
                            <th scope="col">Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.length === 0 ? (
                            <tr><td colSpan="3">Not in any group.</td></tr>
                        ) : groups.map((membership) => (
                            <tr key={membership.id} className={membership.current ? undefined : 'org-row-past'}>
                                <td><a href={`/console/people/groups/${membership.groupId}`}>{membership.name}</a></td>
                                <td>{membership.joinedAt}</td>
                                <td>{membership.leftAt ?? 'Current'}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Sentences, not counters. "People reporting to them: 0" is a zero
              * clause of exactly the kind the dependency summary omits. */}
            <p className="org-meta">
                {teams.length === 0
                    ? 'In no team.'
                    : `Teams: ${teams.map((team) => team.name).join(', ')}.`}
                {' '}
                {manages === 0
                    ? 'Nobody reports to them.'
                    : manages === 1
                        ? '1 person reports to them.'
                        : `${manages} people report to them.`}
            </p>

            {/*
              * A control that cannot act is not rendered. When a purge is not
              * available the reason is stated instead - P1-01 settled that a
              * disabled destructive button with a tooltip is worse than an
              * explanation.
              */}
            {person.purgeable ? (
                <div className="org-section">
                    <h3 className="idn-subhead">Remove permanently</h3>
                    <p className="org-hint-plain">
                        This person has never signed in and has no history, so their record can be
                        removed. This cannot be undone.
                    </p>
                    <button
                        type="button"
                        className="org-action org-action-danger"
                        disabled={purge.processing}
                        onClick={() => purge.delete(`/console/people/users/${person.id}`)}
                    >
                        Remove permanently
                    </button>
                </div>
            ) : (
                <p className="org-hint-plain">
                    This person&rsquo;s record is kept as part of the organisation&rsquo;s history.
                    Deactivate them instead of removing them.
                </p>
            )}
        </PeoplePage>
    )
}
