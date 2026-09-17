import SecurityPage from '../../Components/SecurityPage'
import PostureRows from '../../Components/PostureRows'

/**
 * Secure Baseline.
 *
 * Nine controls plus the three that apply and cannot be observed, plus the
 * carried sign-in re-check. Every one is enforced somewhere else and was
 * already accepted; this screen owns none of them, and every row's only
 * affordance is a link to the screen that does.
 *
 * NO ROW OFFERS A TOGGLE, AN OVERRIDE, AN ACKNOWLEDGEMENT OR A "MARK AS
 * REVIEWED". There is no route under this prefix that could carry one.
 */
export default function Baseline({ productAreas, summary, rows }) {
    return (
        <SecurityPage
            productAreas={productAreas}
            summary={summary}
            title="Secure Baseline"
            description="The protections every SemantIQ deployment is expected to have, and what this one currently reports."
        >
            <PostureRows rows={rows} />

            <p className="sec-note">
                Nothing on this page changes a setting. Each item is managed on the screen named
                beneath it, and that screen checks your authority again when you arrive.
            </p>
        </SecurityPage>
    )
}
