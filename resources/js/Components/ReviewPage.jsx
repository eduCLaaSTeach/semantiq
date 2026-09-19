import { useForm, usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'
import ReviewTabs from './ReviewTabs'

/**
 * FEATURE -> TAB -> CONTENT, the same three levels as every other feature.
 *
 * The confirmation and refusal banners are rendered here so all three screens
 * say the same thing the same way, and so a refusal is never a raw exception.
 */
export default function ReviewPage({ productAreas, counts, canStartCycle, title, description, children }) {
    const { props } = usePage()
    const { post, processing } = useForm({ due_in_days: 30 })

    return (
        <AppShell productAreas={productAreas} title="Access Reviews">
            <div className="org-page">
                <header className="org-feature">
                    <h1>Access Reviews</h1>
                    <p>
                        Periodic confirmation of privileged and sensitive access. Confirming access
                        changes nothing about it; removing it takes effect immediately.
                    </p>
                </header>

                {props.confirmation ? <p className="org-confirmation">{props.confirmation}</p> : null}
                {props.refusal ? <p className="org-refusal">{props.refusal}</p> : null}

                <ReviewTabs counts={counts} />

                <div className="org-section-head">
                    <div>
                        <h2>{title}</h2>
                        {description ? <p className="org-description">{description}</p> : null}
                    </div>
                    {canStartCycle ? (
                        <div className="org-section-actions">
                            <button
                                type="button"
                                className="org-action"
                                disabled={processing}
                                onClick={() => post('/console/access-reviews/cycles', { preserveScroll: true })}
                            >
                                Start a review cycle
                            </button>
                        </div>
                    ) : null}
                </div>

                {children}
            </div>
        </AppShell>
    )
}

export function ReviewList({ items, emptyMessage }) {
    if (!items.data.length) {
        return <p className="rev-empty">{emptyMessage}</p>
    }

    return (
        <>
            <ul className="rev-list">
                {items.data.map((item) => (
                    <ReviewRow key={item.id} item={item} />
                ))}
            </ul>
            {items.lastPage > 1 ? (
                <p className="rev-pagination">
                    Page {items.currentPage} of {items.lastPage} — {items.total} in total
                </p>
            ) : null}
        </>
    )
}

function ReviewRow({ item }) {
    const { post, processing } = useForm({ decision: 'retain' })

    const decide = (decision) => {
        post(`/console/access-reviews/items/${item.id}/decide`, {
            data: { decision },
            preserveScroll: true,
        })
    }

    return (
        <li className={item.overdue ? 'rev-row rev-row-overdue' : 'rev-row'}>
            <div className="rev-row-head">
                <h3>{item.person}</h3>
                <span className={`rev-state rev-state-${item.state}`}>{item.stateLabel}</span>
            </div>

            <dl className="rev-facts">
                <div><dt>Role</dt><dd>{item.role}</dd></div>
                {item.domain ? <div><dt>Business domain</dt><dd>{item.domain}</dd></div> : null}
                {item.scopes?.length ? (
                    <div><dt>Records they can reach</dt><dd>{item.scopes.join(', ')}</dd></div>
                ) : null}
                {item.ceiling ? <div><dt>Most sensitive information</dt><dd>{item.ceiling}</dd></div> : null}
                <div><dt>Due</dt><dd>{item.dueAt}{item.overdue ? ' — overdue' : ''}</dd></div>
                {item.basis ? <div><dt>You can review this as</dt><dd>{item.basis}</dd></div> : null}
            </dl>

            {!item.personActive ? (
                <p className="rev-note">
                    This person is deactivated, so they cannot sign in and this access already grants
                    nothing. Confirming it means it should survive if they return.
                </p>
            ) : null}

            {item.supersededReason ? <p className="rev-note">{item.supersededReason}</p> : null}
            {item.selfReview ? <p className="rev-note">Reviewed by the person who holds this access.</p> : null}

            {item.decidable ? (
                <div className="rev-actions">
                    <button type="button" className="org-action" disabled={processing} onClick={() => decide('retain')}>
                        Confirm this access
                    </button>
                    <button type="button" className="org-action org-action-danger" disabled={processing} onClick={() => decide('revoke')}>
                        Remove this access
                    </button>
                </div>
            ) : (
                <p className="rev-note">{item.stateDescription}</p>
            )}
        </li>
    )
}
