import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'

/**
 * The Company Profile.
 *
 * This screen creates the organisation - there is no seed row, because a row
 * created by migration would be invented business content. Under D-16 it is also
 * the single place users.organisation_id is written: the administrator who
 * creates the profile is associated with it in the same transaction.
 */
export default function Profile({ organisation, associated }) {
    const { productAreas, errors } = usePage().props
    const existing = Boolean(organisation)

    const form = useForm({
        name: organisation?.name ?? '',
        legal_name: organisation?.legal_name ?? '',
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

            <form className="org-form" onSubmit={submit}>
                <label>
                    Name
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                </label>
                {form.errors.name ? <p className="org-field-error">{form.errors.name}</p> : null}

                <label>
                    Legal name
                    <input value={form.data.legal_name} onChange={(e) => form.setData('legal_name', e.target.value)} />
                </label>

                <label className="org-field-sm">
                    Country
                    <input
                        value={form.data.country}
                        onChange={(e) => form.setData('country', e.target.value.toUpperCase())}
                        maxLength={2}
                        placeholder="SG"
                    />
                </label>

                <label className="org-field-md">
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

            {existing ? (
                <nav aria-label="Organisation sections">
                    <h2 className="org-next-title">Organisation sections</h2>

                    <div className="org-next">
                        <button type="button" onClick={() => router.get('/console/organisation/legal-entities')}>
                            Legal Entities
                        </button>
                        <button type="button" onClick={() => router.get('/console/organisation/business-units')}>
                            Business Units
                        </button>
                        <button type="button" onClick={() => router.get('/console/organisation/departments')}>
                            Departments
                        </button>
                        <button type="button" onClick={() => router.get('/console/organisation/teams')}>
                            Teams
                        </button>
                        <button type="button" onClick={() => router.get('/console/organisation/hierarchy')}>
                            Management Hierarchy
                        </button>
                    </div>
                </nav>
            ) : null}
        </OrganisationPage>
    )
}
