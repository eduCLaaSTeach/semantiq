/**
 * D-24 §6: the three removal-shaped actions must be obviously different.
 *
 * They are one click apart in the same row, two of them are reversible and one
 * destroys the record, so the difference cannot live only in the button labels.
 * Stated once per list, in the words the Product Owner used.
 */
export default function LifecycleLegend({ noun }) {
    return (
        <p className="org-note">
            <strong>Edit</strong> corrects a name, code or other detail that was entered wrongly.{' '}
            <strong>Deactivate</strong> retires a real {noun} that is no longer in use and{' '}
            <strong>keeps the record and its history</strong>.{' '}
            <strong>Delete permanently</strong> is only for a {noun} entered by mistake: it removes the
            record for good, and it is refused if anything at all still uses it.
        </p>
    )
}
