import { router, useForm, usePage } from '@inertiajs/react'
import OrganisationPage from '../../Components/OrganisationPage'
import StatusPill from '../../Components/StatusPill'
import useRowEditor from '../../Components/useRowEditor'

/**
 * The legal axis. Never a level in Business Unit > Department > Team: D-14
 * records that the two axes do not align.
 *
 * Jurisdiction is chosen from the packaged ISO 3166-1 list, never typed. The
 * select is a convenience; the server validates the same list, because a select
 * constrains nothing once the request leaves the browser.
 */
export default function LegalEntities({ legalEntities, jurisdictions }) {
    const { productAreas, errors } = usePage().props
    const form = useForm({
        name: '',
        registration_number: '',
        jurisdiction: '',
        registered_address: '',
    })

    const row = useRowEditor('/console/organisation/legal-entities', [
        'name',
        'registration_number',
        'jurisdiction',
        'registered_address',
    ])

    const submit = (event) => {
        event.preventDefault()
        form.post('/console/organisation/legal-entities', { onSuccess: () => form.reset() })
    }

    const lifecycle = (entity) =>
        router.patch(
            `/console/organisation/legal-entities/${entity.id}/${
                entity.status === 'active' ? 'deactivate' : 'reactivate'
            }`,
            {},
            { preserveScroll: true }
        )

    return (
        <OrganisationPage
            productAreas={productAreas}
            errors={errors}
            title="Legal Entities"
            description="A separate organisational axis. A legal entity may be associated with any number of business units."
        >
            <table className="org-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Registration</th>
                        <th>Jurisdiction</th>
                        <th>Registered address</th>
                        <th>Status</th>
                        <th><span className="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    {legalEntities.length === 0 ? (
                        <tr>
                            <td colSpan={6} className="org-empty">
                                No legal entities yet. Add the ones your business is registered as.
                            </td>
                        </tr>
                    ) : (
                        legalEntities.map((entity) =>
                            row.isEditing(entity.id) ? (
                                <tr key={entity.id}>
                                    <td>
                                        <input
                                            aria-label={`Name of ${entity.name}`}
                                            value={row.draft.name}
                                            onChange={(e) => row.set('name', e.target.value)}
                                            required
                                        />
                                    </td>
                                    <td>
                                        <input
                                            aria-label={`Registration number for ${entity.name}`}
                                            value={row.draft.registration_number}
                                            onChange={(e) => row.set('registration_number', e.target.value)}
                                        />
                                    </td>
                                    <td>
                                        <select
                                            aria-label={`Jurisdiction of ${entity.name}`}
                                            value={row.draft.jurisdiction}
                                            onChange={(e) => row.set('jurisdiction', e.target.value)}
                                        >
                                            <option value="">Not recorded</option>
                                            {jurisdictions.map((name) => (
                                                <option key={name} value={name}>
                                                    {name}
                                                </option>
                                            ))}
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            aria-label={`Registered address of ${entity.name}`}
                                            value={row.draft.registered_address}
                                            onChange={(e) => row.set('registered_address', e.target.value)}
                                        />
                                    </td>
                                    <td><StatusPill status={entity.status} /></td>
                                    <td>
                                        <div className="org-row-actions">
                                            <button
                                                type="button"
                                                className="org-action"
                                                onClick={() => row.save(entity.id)}
                                                disabled={row.saving}
                                            >
                                                Save
                                            </button>
                                            <button type="button" onClick={row.cancel}>
                                                Cancel
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                <tr key={entity.id}>
                                    <td>{entity.name}</td>
                                    <td>{entity.registration_number || '—'}</td>
                                    <td>{entity.jurisdiction || '—'}</td>
                                    <td>{entity.registered_address || '—'}</td>
                                    <td><StatusPill status={entity.status} /></td>
                                    <td>
                                        <div className="org-row-actions">
                                            <button type="button" onClick={() => row.start(entity)}>
                                                Edit
                                            </button>
                                            <button type="button" onClick={() => lifecycle(entity)}>
                                                {entity.status === 'active' ? 'Deactivate' : 'Reactivate'}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            )
                        )
                    )}
                </tbody>
            </table>

            <form className="org-form org-form-inline" onSubmit={submit}>
                <h2 className="org-form-title">Add a legal entity</h2>
                <label>
                    Name
                    <input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                </label>
                <label>
                    Registration number
                    <input
                        value={form.data.registration_number}
                        onChange={(e) => form.setData('registration_number', e.target.value)}
                    />
                </label>
                <label>
                    Jurisdiction
                    <select
                        value={form.data.jurisdiction}
                        onChange={(e) => form.setData('jurisdiction', e.target.value)}
                    >
                        <option value="">Not recorded</option>
                        {jurisdictions.map((name) => (
                            <option key={name} value={name}>
                                {name}
                            </option>
                        ))}
                    </select>
                </label>
                <label>
                    Registered address
                    <input
                        value={form.data.registered_address}
                        onChange={(e) => form.setData('registered_address', e.target.value)}
                    />
                </label>
                <button type="submit" className="org-action" disabled={form.processing}>
                    Add legal entity
                </button>
            </form>
        </OrganisationPage>
    )
}
