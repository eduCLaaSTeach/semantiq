import { useState } from 'react'
import { router } from '@inertiajs/react'
import AppShell from '../../Layouts/AppShell'
import AuditTabs from '../../Components/AuditTabs'

const WITHHELD = 'Managed by the platform administrator.'

/**
 * Audit. Four tabs over durable evidence, read-only.
 *
 * THE EVIDENCE START DATE IS ON EVERY TAB, above the list, in the reader's
 * words. D-109's whole purpose is that nobody mistakes this for complete
 * earlier history, so it is not a footnote and not a tooltip.
 *
 * EVERY ROW READS AS A SENTENCE. The dotted event key never reaches the screen;
 * the catalogue already holds a business label for all 77.
 */
export default function Log({
    productAreas,
    counts,
    title,
    description,
    events,
    filters,
    actions,
    evidenceStart,
    chain,
}) {
    return (
        <AppShell productAreas={productAreas} title="Audit">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Audit</h1>
                    <p>
                        A permanent record of who did what, and what the platform refused. Records
                        here are never edited and never deleted.
                    </p>
                </header>

                {chain && !chain.intact ? (
                    <div className="org-refusal" role="alert">
                        <strong>This record may have been tampered with.</strong>{' '}
                        {chainSentence(chain)} Raise this with whoever administers the database.
                    </div>
                ) : null}

                <AuditTabs counts={counts} />

                <div className="org-section-head">
                    <div>
                        <h2>{title}</h2>
                        {description ? <p className="org-description">{description}</p> : null}
                    </div>
                </div>

                {evidenceStart ? (
                    <p className="aud-start">
                        Evidence begins {evidenceStart}. Activity before that time was not recorded
                        and is not available here.
                    </p>
                ) : null}

                <Filters filters={filters} actions={actions} />

                {events.data.length === 0 ? (
                    <p className="rev-empty">Nothing here matches what you are looking for.</p>
                ) : (
                    <>
                        <ul className="aud-list">
                            {events.data.map((entry) => (
                                <Row key={entry.id} entry={entry} />
                            ))}
                        </ul>
                        {events.lastPage > 1 ? (
                            <p className="rev-pagination">
                                Page {events.currentPage} of {events.lastPage} — {events.total} in
                                total
                            </p>
                        ) : null}
                    </>
                )}
            </div>
        </AppShell>
    )
}

function chainSentence(chain) {
    if (chain.finding === 'altered') {
        return `Record ${chain.sequence} is not what it was when it was written.`
    }

    if (chain.finding === 'removed' || chain.finding === 'missing') {
        return `A record is missing at position ${chain.sequence}.`
    }

    return 'The record could not be checked.'
}

function Filters({ filters, actions }) {
    const [form, setForm] = useState({
        event: filters.event ?? '',
        outcome: filters.outcome ?? '',
        person: filters.person ?? '',
        from: filters.from ?? '',
        to: filters.to ?? '',
    })

    const set = (key) => (e) => setForm({ ...form, [key]: e.target.value })

    const apply = (e) => {
        e.preventDefault()
        router.get(window.location.pathname, form, { preserveState: true, preserveScroll: true })
    }

    const clear = () => {
        setForm({ event: '', outcome: '', person: '', from: '', to: '' })
        router.get(window.location.pathname, {}, { preserveScroll: true })
    }

    return (
        <form className="aud-filters" onSubmit={apply}>
            <label>
                Action
                <select value={form.event} onChange={set('event')}>
                    <option value="">Any action</option>
                    {actions.map((a) => (
                        <option key={a.value} value={a.value}>
                            {a.label}
                        </option>
                    ))}
                </select>
            </label>

            <label>
                Person
                <input type="text" value={form.person} onChange={set('person')} placeholder="Name" />
            </label>

            <label>
                Outcome
                <input type="text" value={form.outcome} onChange={set('outcome')} placeholder="Any" />
            </label>

            <label>
                From
                <input type="date" value={form.from} onChange={set('from')} />
            </label>

            <label>
                To
                <input type="date" value={form.to} onChange={set('to')} />
            </label>

            <div className="aud-filter-actions">
                <button type="submit" className="org-action">
                    Apply
                </button>
                <button type="button" className="org-action org-action-quiet" onClick={clear}>
                    Clear
                </button>
            </div>
        </form>
    )
}

function Row({ entry }) {
    /*
     * A WITHHELD FIELD IS SHOWN AS WITHHELD, never hidden. A hidden field makes
     * the reader believe there was nothing there; P1-06's WithheldRow made the
     * same choice for the same reason.
     */
    const directory =
        entry.directorySubject === WITHHELD
            ? WITHHELD
            : [entry.directorySubject, entry.directoryTenant].filter(Boolean).join(' · ')

    return (
        <li className="aud-row">
            <div className="aud-row-head">
                <h3>{entry.action}</h3>
                <span className="aud-at">{entry.at}</span>
            </div>

            <dl className="rev-facts">
                <div>
                    <dt>Who</dt>
                    <dd>{entry.actor}</dd>
                </div>
                {entry.subject && !entry.subjectIsActor ? (
                    <div>
                        <dt>About</dt>
                        <dd>{entry.subject}</dd>
                    </div>
                ) : null}
                {entry.target ? (
                    <div>
                        <dt>What</dt>
                        <dd>{entry.target}</dd>
                    </div>
                ) : null}
                {entry.outcome ? (
                    <div>
                        <dt>Outcome</dt>
                        <dd>{readable(entry.outcome)}</dd>
                    </div>
                ) : null}
                {entry.reason ? (
                    <div>
                        <dt>Why</dt>
                        <dd>{readable(entry.reason)}</dd>
                    </div>
                ) : null}
                {directory ? (
                    <div>
                        <dt>Directory account</dt>
                        <dd>{directory}</dd>
                    </div>
                ) : null}
            </dl>
        </li>
    )
}

/**
 * A stored code is a stored code; a SCREEN never shows one. These are fixed
 * vocabularies, so this is presentation of a known value rather than a guess at
 * arbitrary text.
 */
function readable(value) {
    return value.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())
}
