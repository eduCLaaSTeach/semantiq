import ReviewPage, { ReviewList } from '../../Components/ReviewPage'

export default function Privileged({ productAreas, counts, items, canStartCycle, cycleInProgress }) {
    return (
        <ReviewPage
            productAreas={productAreas}
            counts={counts}
            canStartCycle={canStartCycle}
            cycleInProgress={cycleInProgress}
            title="Privileged Reviews"
            description="People who hold authority over the platform itself — administration and evidence access. Holding one of these roles grants no business information."
        >
            <ReviewList items={items} emptyMessage="No privileged access is awaiting your review." />
        </ReviewPage>
    )
}
