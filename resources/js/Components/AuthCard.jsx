import logoDark from '../../brand/logo-full-dark.png'
import logoLight from '../../brand/logo-full-light.png'

/**
 * The Auth archetype (shared standard 5.7): a standalone centred card with no
 * shell, shared by the Login page, every refusal state and the bootstrap entry.
 *
 * One component rather than six near-copies, because these screens must stay
 * indistinguishable in shape. If "access not assigned" and "account inactive"
 * drifted into different layouts, the difference itself would tell an anonymous
 * caller which of the two they had hit - which is the directory enumeration the
 * design forbids.
 */
export default function AuthCard({ title, children, footer }) {
    return (
        <main className="entry">
            <section className="entry-card">
                <picture>
                    <source srcSet={logoDark} media="(prefers-color-scheme: dark)" />
                    <img className="entry-mark" src={logoLight} alt="CLaaS2SaaS" />
                </picture>
                <h1>{title}</h1>
                {children}
                {footer ? <div className="entry-note">{footer}</div> : null}
            </section>
        </main>
    )
}
