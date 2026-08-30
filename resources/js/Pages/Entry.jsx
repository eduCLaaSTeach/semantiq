import logoDark from '../../brand/logo-full-dark.png'
import logoLight from '../../brand/logo-full-light.png'

/**
 * Pre-authentication entry page.
 *
 * The Auth archetype from the shared standard (5.7): a standalone centred card
 * with no shell. It deliberately says nothing about what exists behind
 * authentication - no menu, no product areas, no counts, no version. An
 * unauthenticated browser learns only that this is SemantIQ.
 *
 * The brand mark has light and dark variants and both ship, because the two
 * themes' surfaces are different colours by design and a single asset is legible
 * on only one of them.
 *
 * P1-00 replaces this with the Login page and its Sign in with Microsoft action.
 */
export default function Entry() {
    return (
        <main className="entry">
            <section className="entry-card">
                <picture>
                    <source srcSet={logoDark} media="(prefers-color-scheme: dark)" />
                    <img className="entry-mark" src={logoLight} alt="CLaaS2SaaS" />
                </picture>
                <h1>SemantIQ</h1>
                <p>Secure business decision intelligence.</p>
                <p className="entry-note">Sign-in is not yet available on this deployment.</p>
            </section>
        </main>
    )
}
