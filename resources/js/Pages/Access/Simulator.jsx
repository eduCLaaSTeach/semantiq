import { useForm, usePage } from '@inertiajs/react'
import AccessPage from '../../Components/AccessPage'

/**
 * The Access Simulator.
 *
 * IT ASKS THE REAL ENGINE. This screen holds no authorization logic — it builds
 * a question, posts it, and renders what came back. A simulator with its own
 * logic gives confident answers that are wrong exactly when the two
 * implementations have drifted, which is exactly when somebody most needs the
 * truth.
 *
 * IT SHOWS EVERY AUTHORISING PATH, not just the first. An administrator about
 * to revoke a grant is asking "would this person still have access
 * afterwards?", and a first-match answer would say Finance access comes from
 * the Manager path while an Executive path also grants it — leading them into a
 * change they did not intend.
 *
 * IT SHOWS NO BUSINESS VALUES and writes nothing.
 *
 * Every sentence here comes from the engine's own reason code, translated once
 * on the server. No reason code reaches this screen.
 */
export default function Simulator({ people, domains, teams, businessUnits, sensitivities, result, submitted, scopeEquivalenceNote }) {
    const { productAreas, errors } = usePage().props

    const form = useForm({
        user_id: submitted?.user_id ?? '',
        business_domain_id: submitted?.business_domain_id ?? '',
        sensitivity: submitted?.sensitivity ?? 'standard',
        record_owner: submitted?.record_owner ?? '',
        team_id: submitted?.team_id ?? '',
        business_unit_id: submitted?.business_unit_id ?? '',
    })

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/access/simulator/run', { preserveScroll: true })
    }

    return (
        <AccessPage
            productAreas={productAreas}
            errors={errors}
            back={{ href: '/console/access', label: 'role assignments' }}
            title="Access Simulator"
            description="Ask what somebody would be able to see, and why. Nothing here changes anybody's access, and no business information is shown."
        >
            <form className="org-form org-form-profile" onSubmit={submit}>
                <label>
                    Person
                    <select value={form.data.user_id} onChange={(e) => form.setData('user_id', e.target.value)} required>
                        <option value="">Choose somebody</option>
                        {people.map((person) => (
                            <option key={person.id} value={person.id}>
                                {person.name}
                                {person.active ? '' : ' (inactive)'}
                            </option>
                        ))}
                    </select>
                </label>

                <label>
                    Business domain
                    <select
                        value={form.data.business_domain_id}
                        onChange={(e) => form.setData('business_domain_id', e.target.value)}
                        required
                    >
                        <option value="">Choose a domain</option>
                        {domains.map((domain) => (
                            <option key={domain.id} value={domain.id}>
                                {domain.name}
                                {domain.enabled ? '' : ' (disabled)'}
                            </option>
                        ))}
                    </select>
                </label>

                <label>
                    How sensitive is the information?
                    <select
                        value={form.data.sensitivity}
                        onChange={(e) => form.setData('sensitivity', e.target.value)}
                        required
                    >
                        {sensitivities.map((level) => (
                            <option key={level.value} value={level.value}>
                                {level.label}
                            </option>
                        ))}
                    </select>
                </label>

                <label>
                    Whose record is it?
                    <select value={form.data.record_owner} onChange={(e) => form.setData('record_owner', e.target.value)}>
                        <option value="">Somebody else&apos;s</option>
                        <option value="self">Their own</option>
                    </select>
                </label>

                <label>
                    Which team does the record belong to? <span className="org-hint">Optional</span>
                    <select value={form.data.team_id} onChange={(e) => form.setData('team_id', e.target.value)}>
                        <option value="">Any or none</option>
                        {teams.map((team) => (
                            <option key={team.id} value={team.id}>
                                {team.name}
                            </option>
                        ))}
                    </select>
                </label>

                <label>
                    Which business unit? <span className="org-hint">Optional</span>
                    <select
                        value={form.data.business_unit_id}
                        onChange={(e) => form.setData('business_unit_id', e.target.value)}
                    >
                        <option value="">Any or none</option>
                        {businessUnits.map((unit) => (
                            <option key={unit.id} value={unit.id}>
                                {unit.name}
                            </option>
                        ))}
                    </select>
                </label>

                <p className="org-hint org-hint-plain">{scopeEquivalenceNote}</p>

                <button type="submit" className="org-action" disabled={form.processing}>
                    {form.processing ? 'Checking…' : 'Check access'}
                </button>
            </form>

            {result ? (
                <section className="org-record-section">
                    <h3>
                        {result.allowed ? 'Allowed' : 'Not allowed'}{' '}
                        <span className={`org-pill org-pill-${result.allowed ? 'enabled' : 'disabled'}`}>
                            {result.allowed ? 'Yes' : 'No'}
                        </span>
                    </h3>

                    <p className="org-meta">
                        {result.subjectName} — {result.domainName} — {result.sensitivityLabel} information.
                    </p>

                    {/*
                      * The engine's own reason, in business language. The
                      * machine code stays internal, for evidence and tests.
                      *
                      * Suppressed on an allow, where it reads "Allowed." under
                      * a heading that already says Allowed. The paths below say
                      * the useful thing.
                      */}
                    {result.allowed ? null : <p className="org-description">{result.explanation}</p>}

                    {result.allowed ? (
                        <>
                            <h4>How they have it</h4>
                            <p className="org-description">
                                Every grant that allows this. Revoking one only removes access if it is the
                                last one listed.
                            </p>

                            {result.paths.map((path) => (
                                <div className="org-path" key={path.assignmentId}>
                                    <strong>{path.sentence}</strong>
                                    <p className="org-path-note">
                                        {path.accessSurvivesWithoutThis
                                            ? 'Revoking this grant would not remove their access — another grant also allows it.'
                                            : 'This is the only grant allowing it. Revoking it would remove their access.'}
                                    </p>
                                    {path.coversWholeDomain ? (
                                        <p className="org-path-note">{scopeEquivalenceNote}</p>
                                    ) : null}
                                </div>
                            ))}
                        </>
                    ) : null}

                    {/*
                      * ONLY WHEN DENIED.
                      *
                      * The first version showed these whenever the engine had
                      * any failed candidate, including on an ALLOWED result -
                      * so an administrator saw "Allowed" and then "What is in
                      * the way: the assigned scope does not include this
                      * record", and would reasonably conclude something was
                      * broken. Those failures come from OTHER grants that did
                      * not authorise; on an allow they are noise at best and
                      * misleading at worst. Found in the browser.
                      */}
                    {! result.allowed && result.blockers.length > 0 ? (
                        <>
                            <h4>What is in the way</h4>
                            {result.blockers.map((blocker, index) => (
                                <div className="org-path" key={index}>
                                    <strong>{blocker.sentence}</strong>
                                    <p className="org-path-note">{blocker.detail}</p>
                                </div>
                            ))}
                        </>
                    ) : null}

                    <p className="org-hint org-hint-plain">
                        This check shows what would be visible. It never shows the information itself,
                        and running it changes nothing.
                    </p>
                </section>
            ) : null}
        </AccessPage>
    )
}
