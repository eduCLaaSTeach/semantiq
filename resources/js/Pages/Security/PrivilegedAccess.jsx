import SecurityPage from '../../Components/SecurityPage'
import PostureRows from '../../Components/PostureRows'
import PostureMetrics from '../../Components/PostureMetrics'
import PostureBadge from '../../Components/PostureBadge'

/**
 * Privileged Access Health, and - per D-82 - business domains as a CLEARLY
 * SEPARATED SECTION rather than a fifth tab.
 *
 * TWO KINDS OF THING, IN TWO PANELS. What is observed and could be wrong sits
 * above; what is legitimate and worth seeing sits below, as a count with the
 * sentence that explains why it is not a finding.
 */
export default function PrivilegedAccess({ productAreas, summary, rows, metrics, domains }) {
    return (
        <SecurityPage
            productAreas={productAreas}
            summary={summary}
            title="Privileged Access Health"
            description="Who can administer this deployment, and what administration authority currently reaches."
        >
            <PostureRows rows={rows} />

            <section className="org-record-section" aria-labelledby="sec-metrics-heading">
                <h3 id="sec-metrics-heading">Worth knowing</h3>
                <p className="org-description">
                    These are counts, not findings. Each describes access that was granted
                    deliberately and is working as intended.
                </p>

                <PostureMetrics metrics={metrics} />
            </section>

            <section className="org-record-section" aria-labelledby="sec-domains-heading">
                <h3 id="sec-domains-heading">By business domain</h3>
                <p className="org-description">
                    Each domain is reported on its own. Owning a domain grants nothing — being
                    accountable for information and being able to see it are separate.
                </p>

                {domains.length === 0 ? (
                    <p className="sec-empty">
                        No business domains have been set up yet. Nothing can be granted until one
                        exists.
                    </p>
                ) : (
                    <ul className="sec-domains">
                        {domains.map((domain) => (
                            <li key={domain.id} className="sec-domain">
                                <div className="sec-domain-head">
                                    <span className="sec-domain-name">{domain.name}</span>
                                    <PostureBadge state={domain.state} label={domain.stateLabel} />
                                </div>

                                <PostureRows rows={domain.rows} />
                                <PostureMetrics metrics={domain.metrics} />
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </SecurityPage>
    )
}
