import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import logoDark from '../../../images/brand/logo-full-dark.png';
import logoLight from '../../../images/brand/logo-full-light.png';

/**
 * A message that belongs to the sign-in attempt as a whole rather than to any
 * field. Rendered as one persistent inline alert, never as a toast and never as
 * a summary card duplicating field errors.
 */
export interface SignInStatus {
    level: 'danger' | 'warning' | 'info';
    title: string;
    body: string;
}

interface SignInProps {
    status: SignInStatus | null;
    supportEmail: string | null;
}

/** Warning glyph, approved icon style: 24px viewBox, 2px stroke, round caps, outline. */
function AlertIcon({ level }: { level: SignInStatus['level'] }) {
    if (level === 'info') {
        return (
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 11v6M12 8h.01" />
                <circle cx="12" cy="12" r="9" />
            </svg>
        );
    }

    return (
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 9v4M12 17h.01" />
            <circle cx="12" cy="12" r="9" />
        </svg>
    );
}

/**
 * The Microsoft four-square mark.
 *
 * This is a third-party identity-provider brand mark, not an entry in the
 * approved icon registry, and it introduces four off-palette colours. It is a
 * documented, signed-off deviation - see doc/04-UI-Specification.md section 7,
 * conflict 3. It is scoped to this one control, marked aria-hidden so the
 * accessible name is the label alone, and must not be reused as a general icon.
 */
function MicrosoftMark() {
    return (
        <svg className="ms-mark" viewBox="0 0 23 23" aria-hidden="true" focusable="false">
            <rect x="1" y="1" width="10" height="10" fill="#F25022" />
            <rect x="12" y="1" width="10" height="10" fill="#7FBA00" />
            <rect x="1" y="12" width="10" height="10" fill="#00A4EF" />
            <rect x="12" y="12" width="10" height="10" fill="#FFB900" />
        </svg>
    );
}

/**
 * Sign in - the Auth archetype.
 *
 * Standalone centered card with no application shell, because the shell's
 * navigation and profile menu are meaningless before authentication. There is
 * no username or password field: identity is delegated entirely to Microsoft
 * Entra ID, so the application holds no local credential to collect.
 */
export default function SignIn({ status, supportEmail }: SignInProps) {
    const { post, processing } = useForm({});

    /**
     * Begin the federated sign-in. Posted rather than linked so the request
     * carries the CSRF token, which is what prevents a third party from
     * initiating a sign-in on the user's behalf.
     */
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/auth/microsoft/redirect', { preserveScroll: true });
    }

    return (
        <div className="auth-page">
            <Head title="Sign in" />

            <main className="auth-shell">
                <div className="auth-card">
                    <div className="auth-brand">
                        <img className="brand-light" src={logoLight} alt="CLaaS2SaaS" />
                        <img className="brand-dark" src={logoDark} alt="CLaaS2SaaS" />
                    </div>

                    <div className="auth-head">
                        <h1>Sign in to SemantIQ</h1>
                        <p>Use your organisation&rsquo;s Microsoft account.</p>
                    </div>

                    {/* Assertive: a failed sign-in is the reason the user is still on this page. */}
                    <div className="auth-message" role="alert" aria-live="assertive">
                        {status && (
                            <div className={`alert alert-${status.level}`}>
                                <AlertIcon level={status.level} />
                                <div>
                                    <strong>{status.title}</strong>
                                    <p>{status.body}</p>
                                </div>
                            </div>
                        )}
                    </div>

                    <form onSubmit={submit}>
                        <button
                            type="submit"
                            className={`btn btn-primary btn-lg btn-block${processing ? ' is-loading' : ''}`}
                            disabled={processing}
                            aria-busy={processing}
                        >
                            <MicrosoftMark />
                            <span>{processing ? 'Redirecting to Microsoft…' : 'Sign in with Microsoft'}</span>
                        </button>
                    </form>

                    <div className="auth-divider">Single sign-on only</div>

                    <p className="auth-help">
                        SemantIQ has no separate password. Access is granted by your administrator in
                        Microsoft Entra ID.
                        {supportEmail && (
                            <>
                                <br />
                                Can&rsquo;t get in? <a href={`mailto:${supportEmail}`}>Contact your administrator</a>.
                            </>
                        )}
                    </p>
                </div>

                <div className="auth-trust">
                    <div className="row">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <rect x="4" y="10" width="16" height="10" rx="2" />
                            <path d="M8 10V7a4 4 0 018 0v3" />
                        </svg>
                        <span>Authentication is handled by Microsoft. SemantIQ never sees your password.</span>
                    </div>
                    <div className="row">
                        <span>CLaaS2SaaS SemantIQ</span>
                    </div>
                </div>
            </main>
        </div>
    );
}
