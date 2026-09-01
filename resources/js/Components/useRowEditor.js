import { router } from '@inertiajs/react'
import { useState } from 'react'

/**
 * Inline row editing, shared by every Organisation list.
 *
 * One row at a time turns into inputs with Save and Cancel. This keeps editing
 * inside the accepted design system - the same table, the same controls, no new
 * screen and no modal - which is what the scope correction asks for: expose the
 * missing operations, do not redesign the shell.
 *
 * Cancel restores the row from the record, never from a remembered draft, so an
 * abandoned edit leaves nothing behind.
 */
export default function useRowEditor(endpoint, fields) {
    const [editing, setEditing] = useState(null)
    const [draft, setDraft] = useState({})
    const [saving, setSaving] = useState(false)

    const start = (record) => {
        setEditing(record.id)
        setDraft(Object.fromEntries(fields.map((f) => [f, record[f] ?? ''])))
    }

    const cancel = () => {
        setEditing(null)
        setDraft({})
    }

    const set = (field, value) => setDraft((current) => ({ ...current, [field]: value }))

    const save = (id) => {
        setSaving(true)

        router.put(`${endpoint}/${id}`, draft, {
            preserveScroll: true,
            onSuccess: cancel,
            onFinish: () => setSaving(false),
        })
    }

    return { editing, draft, saving, start, cancel, set, save, isEditing: (id) => editing === id }
}
