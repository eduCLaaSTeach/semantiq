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
export default function BrandMark({ short = false, className = '' }) {
    const light = short ? logoShortLight : logoFullLight
    const dark = short ? logoShortDark : logoFullDark

    return (
        <span className={`brand-mark ${short ? 'brand-mark-short' : 'brand-mark-full'} ${className}`.trim()}>
            <img className="brand-mark-light" src={light} alt="CLaaS2SaaS" />
            <img className="brand-mark-dark" src={dark} alt="" aria-hidden="true" />
        </span>
    )
}
