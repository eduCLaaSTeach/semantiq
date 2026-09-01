import { router } from '@inertiajs/react'
import { useState } from 'react'

/**
 * D-24 guarded permanent delete, shared by every Organisation list.
 *
 * Two steps by construction: ask() only opens the confirmation, and confirm()
 * is the only thing that sends the request. There is no code path from a single
 * click to a DELETE.
 *
 * The refusal is not handled here. When the server declines, it redirects back
 * with its reason and the page renders it exactly like any other refusal - the
 * screen has no opinion about which records are safe to remove, because the
 * screen is not the control.
 */
export default function usePurge(endpoint) {
    const [target, setTarget] = useState(null)
    const [busy, setBusy] = useState(false)

    const ask = (record) => setTarget(record)

    const cancel = () => setTarget(null)

    const confirm = () => {
        if (!target) {
            return
        }

        setBusy(true)

        router.delete(`${endpoint}/${target.id}`, {
            preserveScroll: true,
            onFinish: () => {
                setBusy(false)
                setTarget(null)
            },
        })
    }

    return { target, busy, ask, cancel, confirm }
}
