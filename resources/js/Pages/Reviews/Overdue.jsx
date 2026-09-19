import ReviewPage, { ReviewList } from '../../Components/ReviewPage'

/**
 * A PROJECTION over the same items, not a separate list. Nothing here has a
 * state the other two tabs do not, so they cannot disagree - and being overdue
 * changes nothing about the access. Nothing is removed automatically, ever.
 */
export default function Overdue({ productAreas, counts, items, canStartCycle }) {
    return (
        <ReviewPage
            productAreas={productAreas}
            counts={counts}
            canStartCycle={canStartCycle}
            title="Overdue Reviews"
            description="Reviews that are past their date and still waiting for somebody. Being overdue does not change anybody's access — nothing is removed automatically."
        >
            <ReviewList items={items} emptyMessage="Nothing is overdue." />
        </ReviewPage>
    )
}
