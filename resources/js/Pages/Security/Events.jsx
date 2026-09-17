import SecurityPage from '../../Components/SecurityPage'

/**
 * Security Events - WHAT IS RECORDED, AND HOW IT IS PROTECTED. Not a history.
 *
 * SemantIQ records security events through the application log. That log is not
 * durable, not searchable, not tamper-resistant and not complete, so this
 * screen does not pretend it is one: it shows the COVERAGE and the REDACTION
 * CONTRACT, both read from the code that already exists, and states plainly
 * what is missing until Audit arrives.
 *
 * EVERY EVENT IS SHOWN AS A READABLE LABEL. The declared keys are source truth
 * on the server and never reach this screen - an administrator should never be
 * shown something like a dotted internal identifier, and the build fails if an
 * event has no label.
 */
export default function Events({
    productAreas,
    summary,
    groups,
    total,
    permittedFields,
    redactionPromise,
    limitation,
}) {
    return (
        <SecurityPage
            productAreas={productAreas}
            summary={summary}
            title="Security Events"
            description="What SemantIQ records when something security-related happens, and what it is not allowed to record."
        >
            <div className="sec-limitation" role="status">
                <strong>This is not a history.</strong> {limitation}
            </div>

            <section className="org-record-section" aria-labelledby="sec-recorded-heading">
                <h3 id="sec-recorded-heading">What is recorded</h3>
                <p className="org-description">
                    {total} kinds of event are recorded across the product. This list is read from
                    the product itself, so it cannot fall out of step with what actually happens.
                </p>

                <div className="sec-event-groups">
                    {groups.map((group) => (
                        <div key={group.category} className="sec-event-group">
                            <h4>
                                {group.category}
                                <span className="sec-event-count">{group.events.length}</span>
                            </h4>
                            <ul>
                                {group.events.map((event) => (
                                    <li key={event}>{event}</li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            </section>

            <section className="org-record-section" aria-labelledby="sec-redaction-heading">
                <h3 id="sec-redaction-heading">What can never be recorded</h3>
                <p className="org-description">{redactionPromise}</p>

                <ul className="sec-fields">
                    {permittedFields.map((field) => (
                        <li key={field}>{field}</li>
                    ))}
                </ul>
            </section>
        </SecurityPage>
    )
}
