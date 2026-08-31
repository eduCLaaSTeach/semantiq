import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'

/**
 * Business unit detail, including the D-14 associations.
 *
 * The shape is the point: a business unit may carry several legal entities, and
 * a legal entity may appear on several business units. A single-parent model was
 * rejected because the two axes do not align.
 *
 * The association grants nothing. It is recorded here and read by nobody in
 * P1-01 to decide access.
 */
export default function BusinessUnit({ businessUnit, associated, available, departments }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({ legal_entity_id: '' })

    const submit = (event) => {
        event.preventDefault()
        form.post(`/console/organisation/business-units/${businessUnit.id}/legal-entities`, {
            onSuccess: () => form.reset(),
        })
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title={businessUnit.name}
            description="Business unit detail. Legal-entity associations are recorded here and grant no access."
        >
            <p className="org-meta">
                Code: {businessUnit.code ?? '—'} · <StatusPill status={businessUnit.status} />
            </p>

            <section className="org-section">
                <h2>Legal entities</h2>
                {associated.length === 0 ? (
                    <p className="org-empty">Not associated with any legal entity.</p>
                ) : (
                    <ul className="org-list">
                        {associated.map((entity) => (
                            <li key={entity.id}>
                                {entity.name}
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.patch(
                                            `/console/organisation/business-units/${businessUnit.id}/legal-entities/${entity.id}/dissociate`
                                        )
                                    }
                                >
                                    Remove
                                </button>
                            </li>
                        ))}
                    </ul>
                )}

                {available.length > 0 ? (
                    <form className="org-form org-form-inline" onSubmit={submit}>
                        <label>
                            Associate a legal entity
                            <select
                                value={form.data.legal_entity_id}
                                onChange={(e) => form.setData('legal_entity_id', e.target.value)}
                                required
                            >
                                <option value="">Choose…</option>
                                {available.map((entity) => (
                                    <option key={entity.id} value={entity.id}>
                                        {entity.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <button type="submit" className="org-action" disabled={form.processing}>
                            Associate
                        </button>
                    </form>
                ) : null}
            </section>

            <section className="org-section">
                <h2>Departments</h2>
                {departments.length === 0 ? (
                    <p className="org-empty">No departments.</p>
                ) : (
                    <ul className="org-list">
                        {departments.map((department) => (
                            <li key={department.id}>
                                {department.name} <StatusPill status={department.status} />
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </OrganisationPage>
    )
}
