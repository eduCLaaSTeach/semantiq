import BrandMark from '../Components/BrandMark'
import Icon from '../Components/Icon'

/**
 * The Login archetype: a branded hero beside a clean authentication panel.
 *
 * This is the one screen an unauthenticated visitor sees, so it carries the
 * product's value proposition as well as its sign-in control. It is deliberately
 * the ONLY split screen in the product - every refusal state stays on the plain
 * AuthCard, because those must remain indistinguishable from one another (see
 * AuthCard: telling "access not assigned" and "account inactive" apart is how a
 * caller would enumerate the directory).
 *
 * NOTHING here describes what exists behind authentication: no menu, no product
 * areas, no counts, no version, no tenant or customer name. The hero says what
 * SemantIQ is for, not who uses this deployment.
 *
 * The hero is Midnight Blue in both themes - it is a branded surface, not a
 * themed one - so the company mark is pinned to its dark-chrome variant rather
 * than following the theme.
 */

const JOURNEY = ['Connect', 'Govern', 'Understand', 'Ask', 'Decide']

const BENEFITS = [
    {
        icon: 'i-layers',
        title: 'Unified Intelligence',
        body: 'Bring trusted business information together in one governed intelligence experience.',
    },
    {
        icon: 'i-message',
        title: 'Ask SemantIQ',
        body: 'Explore performance, change, risk and opportunity using natural business questions.',
    },
    {
        icon: 'i-check-circle',
        title: 'Decision Intelligence',
        body: 'Turn insights into clearer priorities, recommendations and informed next actions.',
    },
]

const TRUST = [
    { icon: 'i-shield', label: 'Secure sign-in' },
    { icon: 'i-key', label: 'Role-aware access' },
    { icon: 'i-shield-check', label: 'Governed intelligence' },
]

export default function SignInLayout({ children }) {
    return (
        <main className="signin">
            <section className="signin-hero">
                <div className="signin-hero-inner">
                    {/*
                      D-17 hierarchy: the company mark, then the product name.
                      One is a logo and the other is set in type, so they read as
                      company and product rather than as two competing logos.
                    */}
                    <div className="signin-brand">
                        <BrandMark on="dark" />
                        <span className="signin-product">SemantIQ</span>
                    </div>

                    <p className="signin-badge">Business Decision Intelligence</p>

                    {/*
                      Three deliberate lines, as the direction sets them out.
                      Left to wrap on its own the last line broke as "in /
                      moments.", which reads as an accident rather than a
                      cadence.
                    */}
                    <h1 className="signin-headline">
                        <span>From business data to</span>
                        <span className="signin-highlight">confident decisions</span>
                        <span>in moments.</span>
                    </h1>

                    <p className="signin-supporting">
                        Bring governed data, business context and intelligent analysis together to understand
                        what changed, why it matters and what to do next.
                    </p>

                    {/*
                      The product journey, stated. Informational only: these are
                      not links and are not in the tab order, because there is
                      nothing behind them to reach.
                    */}
                    <ol className="signin-journey">
                        {JOURNEY.map((step, index) => (
                            <li className="signin-chip" key={step}>
                                <span className="signin-chip-number">{index + 1}</span>
                                {step}
                            </li>
                        ))}
                    </ol>

                    <ul className="signin-benefits">
                        {BENEFITS.map((benefit) => (
                            <li className="signin-benefit" key={benefit.title}>
                                <span className="signin-benefit-icon">
                                    <Icon name={benefit.icon} />
                                </span>
                                <span>
                                    <span className="signin-benefit-title">{benefit.title}</span>
                                    <span className="signin-benefit-body">{benefit.body}</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </section>

            <section className="signin-panel">
                <div className="signin-panel-inner">
                    {children}

                    <ul className="signin-trust">
                        {TRUST.map((item) => (
                            <li key={item.label}>
                                <Icon name={item.icon} />
                                {item.label}
                            </li>
                        ))}
                    </ul>
                </div>
            </section>
        </main>
    )
}
