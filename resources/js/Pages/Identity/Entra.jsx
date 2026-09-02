import { useState } from 'react'
import { usePage } from '@inertiajs/react'
import IdentityPage from '../../Components/IdentityPage'
import { IdentityRow, IdentityRows, IdentityState } from '../../Components/IdentityRows'

/**
 * Microsoft Entra ID - read only.
 *
 * REVEAL IS A SERVER ROUND-TRIP, not a client toggle, and that is the decision
 * that makes the mask real rather than cosmetic. If the full identifier shipped
 * in the page props and the mask were CSS, the value would already be in the
 * page source, the browser cache and every screenshot-adjacent artefact - the
 * mask would look like protection while providing none.
 *
 * So the payload carries only the masked form. Revealing asks the server, which
 * re-authorises; the answer lives in component state for this view only and is
 * never persisted, never put in the URL, never written to browser storage.
 *
 * The client secret has no Reveal, no Copy and no mask. It is Present or
 * Missing, and there is no field name the endpoint would accept for it.
 */
export default function Entra({ configuration, healthSummary }) {
    const { productAreas, errors } = usePage().props
    const [revealed, setRevealed] = useState({})
    const [copied, setCopied] = useState(null)
    const [failed, setFailed] = useState(null)

    /*
     * The CSRF token comes from the XSRF-TOKEN cookie Laravel already sets, not
     * from a meta tag added to every page. The reveal is a POST precisely so it
     * cannot be triggered by a third-party page the administrator happens to be
     * visiting, and this is the token that enforces it.
     */
    const xsrfToken = () => {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)

        return match ? decodeURIComponent(match[1]) : ''
    }

    const reveal = async (field) => {
        if (revealed[field]) {
            setRevealed({ ...revealed, [field]: null })
            return
        }

        setFailed(null)

        try {
            const response = await fetch('/console/identity/entra/reveal', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({ field }),
            })

            if (!response.ok) {
                setFailed('That value could not be shown. Try again.')
                return
            }

            const body = await response.json()
            setRevealed({ ...revealed, [field]: body.value })
        } catch {
            setFailed('That value could not be shown. Try again.')
        }
    }

    const copy = async (field) => {
        try {
            await navigator.clipboard.writeText(revealed[field])
            setCopied(field)
        } catch {
            setFailed('That value could not be copied.')
        }
    }

    /*
     * Reveal is offered only when there is something to reveal.
     *
     * Observed in the browser on an unconfigured deployment: every row read
     * "Not set" with a Reveal button beside it, which would have returned an
     * empty string and told the reader nothing. An action that cannot do
     * anything should not be offered.
     */
    const identifier = (field, masked) => {
        const isSet = masked !== 'Not set'

        return (
            <>
                <span className="idn-value">{revealed[field] ?? masked}</span>
                {isSet ? (
                    <span className="idn-value-actions">
                        <button type="button" className="org-action" onClick={() => reveal(field)}>
                            {revealed[field] ? 'Hide' : 'Reveal'}
                        </button>
                        {revealed[field] ? (
                            <button type="button" className="org-action" onClick={() => copy(field)}>
                                {copied === field ? 'Copied' : 'Copy'}
                            </button>
                        ) : null}
                    </span>
                ) : null}
            </>
        )
    }

    return (
        <IdentityPage
            productAreas={productAreas}
            errors={errors}
            title="Microsoft Entra ID"
            description="How this deployment is set up to sign people in with Microsoft."
        >
            {failed ? (
                <div className="org-refusal" role="alert">
                    {failed}
                </div>
            ) : null}

            {configuration.missingKeys.length > 0 ? (
                <div className="org-empty">
                    <p>
                        Microsoft sign-in is not available, because this deployment is missing
                        settings it needs.
                    </p>
                    <ul className="idn-missing">
                        {configuration.missingKeys.map((key) => (
                            <li key={key}>{key}</li>
                        ))}
                    </ul>
                    <p className="org-hint-plain">
                        These are set on the server. They cannot be changed from this screen.
                    </p>
                </div>
            ) : null}

            <IdentityRows>
                <IdentityRow label="Provider">{configuration.providerName}</IdentityRow>

                <IdentityRow label="Status">
                    <IdentityState state={configuration.configured ? 'healthy' : 'failed'}>
                        {configuration.configured ? 'Configured' : 'Not configured'}
                    </IdentityState>
                </IdentityRow>

                <IdentityRow
                    label="Directory (tenant)"
                    note={
                        configuration.directoryMasked === 'Not set'
                            ? 'Set on the server. It cannot be changed from this screen.'
                            : 'Shown in part. Reveal it to check it against the directory in Microsoft Entra.'
                    }
                >
                    {identifier('directory', configuration.directoryMasked)}
                </IdentityRow>

                <IdentityRow
                    label="Application (client) ID"
                    note={
                        configuration.applicationMasked === 'Not set'
                            ? 'Set on the server. It cannot be changed from this screen.'
                            : 'Shown in part. Reveal it to check it against the application in Microsoft Entra.'
                    }
                >
                    {identifier('application', configuration.applicationMasked)}
                </IdentityRow>

                <IdentityRow
                    label="Client secret"
                    note="A secret is never shown here, in whole or in part. Only whether one is set."
                >
                    <IdentityState state={configuration.secret === 'Present' ? 'healthy' : 'failed'}>
                        {configuration.secret}
                    </IdentityState>
                </IdentityRow>

                <IdentityRow
                    label="Sign-in return address"
                    note="Shown in full: it is public, and it appears in the address bar during every sign-in."
                >
                    <span className="idn-value">{configuration.redirectUri || 'Not set'}</span>
                </IdentityRow>

                <IdentityRow label="Return address matches this deployment">
                    <IdentityState state={configuration.redirectUriMatchesDeployment ? 'healthy' : 'degraded'}>
                        {configuration.redirectUriMatchesDeployment
                            ? 'Matches this deployment'
                            : 'Does not match'}
                    </IdentityState>
                </IdentityRow>

                <IdentityRow
                    label="Microsoft sign-in offered on the sign-in page"
                    note="A consequence of the settings above, not a switch."
                >
                    {configuration.configured ? 'Yes' : 'No'}
                </IdentityRow>

                <IdentityRow label="Configuration health">
                    <IdentityState state={healthSummary.state}>{healthSummary.stateInWords}</IdentityState>
                    <span className="idn-value-actions">
                        <a className="org-action" href="/console/identity/health">
                            Open SSO Health
                        </a>
                    </span>
                </IdentityRow>
            </IdentityRows>
        </IdentityPage>
    )
}
