import { useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'

/**
 * The Company Profile.
 *
 * This screen creates the organisation - there is no seed row, because a row
 * created by migration would be invented business content. Under D-16 it is also
 * the single place users.organisation_id is written: the administrator who
 * creates the profile is associated with it in the same transaction.
 *
 * D-25 adds the primary legal entity: the organisation's corporate identity, and
 * NOT the parent of its business units. It is offered only once the organisation
 * exists, because there can be no legal entity to choose before then, and the
 * empty option is Clear rather than a missing value - an organisation without a
 * primary legal entity is a real state, and production is in it today.
 */
export default function Profile({ organisation, associated, legalEntities }) {
    const { productAreas, errors } = usePage().props
    const existing = Boolean(organisation)

    const form = useForm({
        name: organisation?.name ?? '',
        legal_name: organisation?.legal_name ?? '',
        primary_legal_entity_id: organisation?.primary_legal_entity_id ?? '',
        country: organisation?.country ?? '',
        timezone: organisation?.timezone ?? '',
    })

    const submit = (event) => {
        event.preventDefault()
        existing ? form.put('/console/organisation') : form.post('/console/organisation')
    }

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Company Profile"
            description={
                existing
                    ? 'The organisation every structural record belongs to.'
                    : 'Create the organisation before adding any structure. You will be associated with it.'
            }
        >
            {existing && !associated ? (
                <div className="org-refusal" role="alert">
                    Your account is not associated with this organisation, so you cannot be added to a
                    team or a management chain.
                </div>
            ) : null}

            <form className="org-form org-form-profile" onSubmit={submit}>
                <label>
                    Name
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                </label>
                {form.errors.name ? <p className="org-field-error">{form.errors.name}</p> : null}

                <label>
                    Legal name
                    <input value={form.data.legal_name} onChange={(e) => form.setData('legal_name', e.target.value)} />
                </label>

                {existing ? (
                    <label className="org-field-wide">
                        Primary legal entity
                        {legalEntities.length === 0 ? (
                            <span className="org-hint org-hint-plain">
                                Add a legal entity before selecting a primary legal entity.
                            </span>
                        ) : (
                            <select
                                value={form.data.primary_legal_entity_id ?? ''}
                                onChange={(e) => form.setData('primary_legal_entity_id', e.target.value)}
                            >
                                <option value="">Not selected</option>
                                {legalEntities.map((entity) => (
                                    <option key={entity.id} value={entity.id}>
                                        {entity.name}
                                    </option>
                                ))}
                            </select>
                        )}
                    </label>
                ) : null}
                {form.errors.primary_legal_entity_id ? (
                    <p className="org-field-error">{form.errors.primary_legal_entity_id}</p>
                ) : null}

                <label>
                    Country
                    <input
                        value={form.data.country}
                        onChange={(e) => form.setData('country', e.target.value.toUpperCase())}
                        maxLength={2}
                        placeholder="SG"
                    />
                </label>

                <label>
                    Timezone
                    <input
                        value={form.data.timezone}
                        onChange={(e) => form.setData('timezone', e.target.value)}
                        placeholder="Asia/Singapore"
                    />
                </label>

                <button type="submit" className="org-action" disabled={form.processing}>
                    {existing ? 'Save changes' : 'Create organisation'}
                </button>
            </form>

        </OrganisationPage>
    )
}
