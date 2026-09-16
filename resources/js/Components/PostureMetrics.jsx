/**
 * Informational metrics: a COUNT AND CONTEXT, and no state.
 *
 * Rendered in their own panel, deliberately apart from the posture rows, so the
 * difference between "an observed condition" and "a legitimate state worth
 * seeing" is visible rather than only structural.
 *
 * NO BADGE, NO COLOUR, NO STATE CLASS. A metric arrives with no state field at
 * all, so there is nothing here that could paint one amber. P1-05 delivers
 * Restricted grants, several Organisation Administrators, whole-domain scope
 * and assignments preserved across deactivation deliberately, each protected by
 * its own control and each approved; painting them amber would mean this screen
 * declaring approved behaviour to be a fault.
 */
export default function PostureMetrics({ metrics }) {
    if (!metrics.length) {
        return null
    }

    return (
        <ul className="sec-metrics">
            {metrics.map((metric) => (
                <li key={metric.control} className="sec-metric">
                    <span className="sec-metric-count">{metric.count}</span>
                    <div>
                        <span className="sec-metric-label">{metric.label}</span>
                        <p className="sec-metric-context">{metric.context}</p>
                    </div>
                </li>
            ))}
        </ul>
    )
}
