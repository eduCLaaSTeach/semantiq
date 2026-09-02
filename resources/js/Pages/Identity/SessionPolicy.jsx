import { usePage } from '@inertiajs/react'
import IdentityPage from '../../Components/IdentityPage'
import { IdentityRow, IdentityRows } from '../../Components/IdentityRows'

/**
 * Session Policy - read only, D-26.
 *
 * EVERY NUMBER ON THIS SCREEN COMES FROM THE SERVER, and none is written here.
 * That is not a style preference: the defect this screen was built alongside was
 * a constant declaring a 60-minute idle timeout that nothing read, sitting
 * beside a configuration enforcing 120 that nothing checked. Typing 60 into this
 * component would recreate exactly that, one layer further out, and the screen
 * would then be able to display a policy the system is not applying.
 *
 * A drift test sets the enforced value to something unusual and asserts this
 * screen shows THAT, and not 60.
 */
export default function SessionPolicy({ policy }) {
    const { productAreas, errors } = usePage().props

    return (
        <IdentityPage
            productAreas={productAreas}
            errors={errors}
            title="Session Policy"
            description="How long a signed-in session lasts, and what ends it."
        >
            <IdentityRows>
                <IdentityRow
                    label="Idle timeout"
                    note="If you leave SemantIQ alone for longer than this, you will be asked to sign in again."
                >
                    {policy.idleMinutes} minutes
                </IdentityRow>

                <IdentityRow
                    label="Maximum session length"
                    note="A session ends after this long no matter how active you have been, and you sign in again."
                >
                    {policy.absoluteHours} hours
                </IdentityRow>

                <IdentityRow
                    label="Account re-check"
                    note="If an account is deactivated, access stops at the next thing that account does."
                >
                    {policy.revalidatesEveryRequest ? 'Every request. Always on.' : 'Not enforced'}
                </IdentityRow>

                <IdentityRow label="Where sessions are stored">{policy.storage}</IdentityRow>
            </IdentityRows>

            <p className="org-hint-plain">
                These values cannot be changed from inside SemantIQ. They are a security control, and
                changing one needs the durable record of who changed it and when, which a later
                release provides.
            </p>
        </IdentityPage>
    )
}
