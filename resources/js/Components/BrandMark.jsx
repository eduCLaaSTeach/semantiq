import logoFullDark from '../../brand/logo-full-dark.png'
import logoFullLight from '../../brand/logo-full-light.png'
import logoShortDark from '../../brand/logo-short-dark.png'
import logoShortLight from '../../brand/logo-short-light.png'

/**
 * The CLaaS2SaaS company mark (D-17).
 *
 * CLaaS2SaaS is the company brand; SemantIQ is the product brand. The mark is
 * used exactly as supplied - never recoloured, boxed, padded, plated, stretched
 * or regenerated - and the light and dark variants are the approved pair from
 * the design-system asset pack.
 *
 * Both variants are always in the DOM and CSS chooses between them, so the mark
 * switches with the theme without a re-render or a flash.
 */
export default function BrandMark({ short = false, on = null, className = '' }) {
    const light = short ? logoShortLight : logoFullLight
    const dark = short ? logoShortDark : logoFullDark
    const shape = short ? 'brand-mark-short' : 'brand-mark-full'

    /*
     * `on` pins the variant to a surface whose colour does not follow the theme
     * - the sign-in hero is Midnight Blue in light mode and dark mode alike, so
     * the mark there is always the dark-chrome variant. The pinned image
     * deliberately carries NEITHER variant class, so none of the theme
     * display rules touch it and exactly one mark is ever in the DOM.
     */
    if (on === 'dark' || on === 'light') {
        return (
            <span className={`brand-mark ${shape} ${className}`.trim()}>
                <img className="brand-mark-fixed" src={on === 'dark' ? dark : light} alt="CLaaS2SaaS" />
            </span>
        )
    }

    return (
        <span className={`brand-mark ${shape} ${className}`.trim()}>
            <img className="brand-mark-light" src={light} alt="CLaaS2SaaS" />
            <img className="brand-mark-dark" src={dark} alt="" aria-hidden="true" />
        </span>
    )
}
