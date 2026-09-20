import { Link } from '@inertiajs/react'
import HealthStatusBadge from './HealthStatusBadge'

/**
 * An integration this screen does not own — Gate C correction 3, D-148.
 *
 * MICROSOFT SIGN-IN BELONGS TO IDENTITY & SSO, and this screen was editing it
 * anyway: a second writer for a configuration that already had an owner, with
 * its own test button and its own idea of what "saved" means. Two screens that
 * both write the same tenant is how a deployment ends up signed in to one
 * directory and configured for another.
 *
 * SO THIS CARD SAYS HOW IT IS AND SENDS YOU SOMEWHERE ELSE. There is no form
 * element here at all — not a disabled one, which reads as "you may not do
 * this" when the truth is "this is done over there".
 *
 * IT CANNOT RENDER A FIELD IT WAS NOT GIVEN. The server sends a summary with no
 * `fields` and no `secrets` key, so the directory and application identifiers
 * are not in this page's source to be revealed by a future edit.
 */
export default function IntegrationSummaryCard({
    integration,
    manageUrl,
    manageLabel,
    showHeading = true,
}) {
    const showTestedAt = integration.lastTestedAt && integration.status !== 'not_checked'

    return (
        <section className="setup-panel">
            {/*
             * GATE D. ON THE CONSOLE THE HEADING IS THE PAGE'S, NOT THE CARD'S.
             *
             * Every Pattern B feature in this product puts the section name in
             * an `org-section-head` between the tab strip and the content -
             * Company Profile, Current state, and six others. This card carried
             * its own because it used to be one of four stacked on a page that
             * had no section level at all, and two headings saying the same
             * thing is worse than either.
             *
             * FIRST-RUN STILL NEEDS IT. There is no tab strip there and no
             * section head: the card IS the section, and dropping its heading
             * would leave a setup step with no name. So this defaults to
             * showing it and only the console turns it off.
             */}
            {showHeading ? (
                <div className="setup-panel-head">
                    <div>
                        <h2>{integration.name}</h2>
                        <p className="setup-description">
                            {integration.explanation ??
                                (integration.status === 'not_configured'
                                    ? 'Microsoft sign-in has not been set up yet.'
                                    : 'Microsoft sign-in is set up and checked on the Identity & SSO screen.')}
                        </p>
                    </div>

                    <HealthStatusBadge status={integration.status} />
                </div>
            ) : null}

            {showTestedAt ? (
                <p className="setup-meta">
                    Last checked {new Date(integration.lastTestedAt).toLocaleString()}
                </p>
            ) : null}

            <div className="setup-form-actions">
                <Link className="org-action org-action-quiet" href={manageUrl}>
                    {manageLabel}
                </Link>
            </div>

            <p className="setup-hint">
                Sign-in settings are managed on the Identity &amp; SSO screen, which is also where
                they are checked. They are not edited here.
            </p>
        </section>
    )
}
