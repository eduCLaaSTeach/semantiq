import SecurityPage from '../../Components/SecurityPage'
import PostureRows from '../../Components/PostureRows'

/**
 * Exceptions - everything applicable that is not currently healthy.
 *
 * A VIEW, NOT A MECHANISM. The list arrives already derived from the same
 * projection the badge and the tab count come from, so the three cannot
 * disagree.
 *
 * THERE IS NO ACKNOWLEDGE, DISMISS OR SNOOZE, and no route that could carry
 * one. The moment an exception can be dismissed, the screen stops reporting the
 * deployment and starts reporting what somebody clicked.
 *
 * THE EMPTY STATE IS THE DANGEROUS ONE. It must never read "All clear": it
 * carries the counts, including how many controls nobody has verified, because
 * this is the screen most likely to be mistaken for an all-clear.
 */
export default function Exceptions({ productAreas, summary, exceptions, notApplicable, metricCount, withheldCount }) {
    return (
        <SecurityPage
            productAreas={productAreas}
            summary={summary}
            title="Exceptions"
            description="Everything that applies to this deployment and is not currently healthy, and who resolves it."
        >
            {exceptions.length === 0 ? (
                <p className="sec-empty">{summary.caption}</p>
            ) : (
                <>
                    <ul className="sec-exceptions">
                        {exceptions.map((row) => (
                            <li key={row.control} className="sec-exception">
                                <span className="sec-exception-kind">{row.kind}</span>
                                <PostureRows rows={[row]} qualify />
                            </li>
                        ))}
                    </ul>
                </>
            )}

            <section className="org-record-section" aria-labelledby="sec-excluded-heading">
                <h3 id="sec-excluded-heading">Not counted here</h3>
                <p className="org-description">
                    {metricCount === 0
                        ? 'Counts of legitimate access are shown on Privileged Access Health rather than listed as unresolved conditions.'
                        : `${metricCount} counts of legitimate, deliberately granted access are shown on Privileged Access Health rather than listed here. A permitted grant is not an exception to anything.`}
                    {withheldCount > 0
                        ? ` ${withheldCount} platform checks are managed by the platform administrator and are not included in the figures above.`
                        : ''}
                </p>

                <h3>Not part of Release 1</h3>
                {notApplicable.length === 0 ? (
                    <p className="sec-empty">
                        Data classification and Fabric security do not exist yet, so nothing on
                        these screens reports on them. They are not listed as controls, because a
                        control nobody has built is not a control.
                    </p>
                ) : (
                    <PostureRows rows={notApplicable} qualify />
                )}
            </section>
        </SecurityPage>
    )
}
