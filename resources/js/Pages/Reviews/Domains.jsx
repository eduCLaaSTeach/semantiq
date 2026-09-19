import ReviewPage, { ReviewList } from '../../Components/ReviewPage'

export default function Domains({ productAreas, counts, items, canStartCycle }) {
    return (
        <ReviewPage
            productAreas={productAreas}
            counts={counts}
            canStartCycle={canStartCycle}
            title="Domain Reviews"
            description="Access to business domains that is sensitive — either because it reaches confidential or restricted information, or because it covers a whole domain rather than one team."
        >
            <ReviewList items={items} emptyMessage="No sensitive domain access is awaiting your review." />
        </ReviewPage>
    )
}
