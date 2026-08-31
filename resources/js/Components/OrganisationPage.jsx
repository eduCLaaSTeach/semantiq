import AppShell from '../Layouts/AppShell'

/**
 * The list/detail chrome shared by every Organisation screen.
 *
 * Refusals are rendered from the stable reason and message the service produced,
 * never from a raw exception - the design's negative test 17 exists because
 * rendering an exception message is how a stack trace or the name of a record
 * the viewer may not see reaches a browser.
 */
export default function OrganisationPage({ productAreas, title, description, errors = {}, children }) {
    const refusal = errors.structure

    return (
        <AppShell productAreas={productAreas} title="Organisation">
            <div className="org-page">
                <header className="org-header">
                    <h1>{title}</h1>
                    {description ? <p className="org-description">{description}</p> : null}
                </header>

                {refusal ? (
                    <div className="org-refusal" role="alert">
                        <strong>Refused.</strong> {refusal}
                        {errors.blockedBy ? <div className="org-refusal-detail">Blocked by: {errors.blockedBy}</div> : null}
                    </div>
                ) : null}

                {children}
            </div>
        </AppShell>
    )
}
