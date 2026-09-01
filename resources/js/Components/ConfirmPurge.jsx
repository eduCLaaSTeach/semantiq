import { useEffect, useRef } from 'react'

/**
 * The D-24 confirmation, before a record is destroyed.
 *
 * A native <dialog> opened with showModal(), rather than a div dressed as one.
 * That is not a shortcut: it brings the focus trap, the Escape key, inert
 * background content and the accessible modal role with it, and every one of
 * those is something a hand-rolled overlay gets wrong on the screen where
 * getting it wrong destroys a record.
 *
 * Cancel takes the initial focus, never the destructive button. A dialog that
 * opens with Delete focused turns a stray Enter into a permanent deletion.
 *
 * The record's name is shown because "are you sure?" is not a question anybody
 * can answer. The point of the dialog is that you can see WHICH record.
 *
 * None of this is the guard. The server re-checks the dependencies inside the
 * write transaction and refuses there; this only makes the action deliberate.
 */
export default function ConfirmPurge({ target, noun, busy, onCancel, onConfirm }) {
    const dialog = useRef(null)
    const cancelButton = useRef(null)

    useEffect(() => {
        const element = dialog.current

        if (!element) {
            return
        }

        if (target && !element.open) {
            element.showModal()
            cancelButton.current?.focus()
        }

        if (!target && element.open) {
            element.close()
        }
    }, [target])

    return (
        <dialog
            ref={dialog}
            className="org-confirm"
            aria-labelledby="org-confirm-title"
            onCancel={(event) => {
                // Escape. Let it close, but route it through the same handler
                // so the page's state does not fall out of step with the dialog.
                event.preventDefault()
                onCancel()
            }}
        >
            <h2 id="org-confirm-title">Delete this {noun} permanently?</h2>

            <p className="org-confirm-name">{target?.name}</p>

            <p>
                This permanently removes the {noun} and <strong>cannot be undone</strong>. It is not the
                same as deactivating: nothing is kept, and there is no way to bring it back.
            </p>

            <p className="org-confirm-guidance">
                Delete permanently only if this record was entered by mistake. If it is a real {noun} that
                is simply no longer in use, close this and choose <strong>Deactivate</strong> instead — that
                keeps the record and its history.
            </p>

            <div className="org-confirm-actions">
                <button type="button" ref={cancelButton} onClick={onCancel} disabled={busy}>
                    Cancel
                </button>
                <button type="button" className="org-action-danger" onClick={onConfirm} disabled={busy}>
                    Delete permanently
                </button>
            </div>
        </dialog>
    )
}
