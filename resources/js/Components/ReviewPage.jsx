import { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AppShell from '../Layouts/AppShell'
import ReviewTabs from './ReviewTabs'

/**
 * FEATURE -> TAB -> CONTENT, the same three levels as every other feature.
 *
 * The confirmation and refusal banners are rendered here so all three screens
 * say the same thing the same way, and so a refusal is never a raw exception.
 */
/**
 * A REVIEW CYCLE IS ONE GLOBAL CYCLE covering privileged AND sensitive domain
 * access - not one cycle per tab. So the control that starts one belongs to a
 * single screen, and `canStartCycle` is only ever true on Privileged Reviews.
 *
 * It was shown on all three, and starting it from Domain Reviews or Overdue
 * Reviews returned the person to Privileged Reviews - which looked like a
 * navigation bug and was really the screen offering an action it did not own.
 */
export default function ReviewPage({
    productAreas,
    counts,
    canStartCycle,
    cycleInProgress,
    title,
    description,
    children,
}) {
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
                        <div className="org-section-actions rev-start">
                            <button
                                type="button"
                                className="org-action"
                                disabled={processing || cycleInProgress}
                                onClick={() => post('/console/access-reviews/cycles', { preserveScroll: true })}
                            >
                                Start a review cycle
                            </button>
                            <p className="rev-start-note">
                                {cycleInProgress
                                    ? 'A review cycle is already in progress. Complete the outstanding reviews before starting another cycle.'
                                    : 'Starts one review cycle covering privileged and sensitive domain access.'}
                            </p>
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
    const [processing, setProcessing] = useState(false)

    /*
     * THE DECISION IS THE REQUEST BODY, not a form default that a click hopes
     * to override.
     *
     * This used to be useForm({ decision: 'retain' }) submitted with
     * post(url, { data: { decision } }). Inertia types the form's submit
     * options as Omit<VisitOptions, 'data'> - the `data` key is EXCLUDED - so
     * it was silently dropped and EVERY click sent `retain`. "Remove this
     * access" quietly confirmed the access instead, which is why it looked
     * like the button did nothing and why the screen filled with rows marked
     * "Access confirmed".
     *
     * router.post sends exactly what it is given, so the two buttons cannot
     * send the same thing.
     */
    const decide = (decision) => {
        setProcessing(true)
        router.post(
            `/console/access-reviews/items/${item.id}/decide`,
            { decision },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        )
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
