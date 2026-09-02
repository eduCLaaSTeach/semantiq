/**
 * A labelled read-only row, and the list of them.
 *
 * These screens are §5.3 Detail / show - labelled read-only rows - and NOT §5.5
 * Settings / Config. That distinction is worth being explicit about, because a
 * reviewer will look for §5.5's mandatory test-before-save footer and should
 * find the reason it is absent: that contract governs screens which CONFIGURE an
 * outbound connection, and P1-02 has no save, on any screen, at all. There is
 * nothing to test before, and rendering that footer would advertise a capability
 * the unit deliberately does not have.
 */
export function IdentityRows({ children }) {
    return <dl className="idn-rows">{children}</dl>
}

export function IdentityRow({ label, children, note = null }) {
    return (
        <div className="idn-row">
            <dt>{label}</dt>
            <dd>
                {children}
                {note ? <p className="idn-note">{note}</p> : null}
            </dd>
        </div>
    )
}

/**
 * A state, never signalled by colour alone.
 *
 * Every one carries its word. The P1-01 lesson behind that rule was expensive: a
 * status pill hardcoded a dark-green hex with no dark-theme value and measured
 * 1.15:1 on the dark card, and the refusal banner bordered in a raw Violet-Red
 * at 1.33:1 - so the one element whose job was to signal a refusal lost its only
 * signal in one theme.
 */
export function IdentityState({ state, children }) {
    return <span className={`idn-state idn-state-${state}`}>{children}</span>
}
