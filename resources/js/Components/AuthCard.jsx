import BrandMark from './BrandMark'

/**
 * The Auth archetype (shared standard 5.7): a standalone centred card with no
 * shell, shared by the Login page, every refusal state and the bootstrap entry.
 *
 * One component rather than six near-copies, because these screens must stay
 * indistinguishable in shape. If "access not assigned" and "account inactive"
 * drifted into different layouts, the difference itself would tell an anonymous
 * caller which of the two they had hit - which is the directory enumeration the
 * design forbids.
 *
 * D-17 brand hierarchy: the CLaaS2SaaS company mark sits above the SemantIQ
 * product name. Company brand, then product brand.
 */
export default function AuthCard({ title, tagline, children, footer, wide = false }) {
    return (
        <main className="entry">
            <section className={`entry-card${wide ? ' entry-card-wide' : ''}`}>
                <BrandMark className="entry-mark" />

                <h1>{title}</h1>

                {tagline ? <p className="entry-tagline">{tagline}</p> : null}

                {children}

                {footer ? <div className="entry-note">{footer}</div> : null}
            </section>
        </main>
    )
}
