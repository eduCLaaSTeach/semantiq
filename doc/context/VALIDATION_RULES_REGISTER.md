# Validation Rules Register

Validation rules are canonical server-side rules. Client-side validation may improve the experience but is never the only enforcement for a security, tenancy, permission or sovereignty rule.

| Validation ID | Entity / action | Rule | Severity | Server enforcement | Client UX | Message | Source | Tests | Status |
|---|---|---|---|---|---|---|---|---|---|
| VAL-AUTH-PAIR-001 | Sign-in credentials | A wrong pair is a form-level error, never a field error, and the message is identical for an unknown address and a wrong password | Block | Required | Alert beside the submit | Those credentials do not match our records. | ROLE_MODEL.md 9 | `SignInTest` | Implemented |
| VAL-AUTH-THROTTLE-001 | Sign-in attempts | At most 5 per address-and-network per minute | Block | Required | Countdown in the form alert | Too many sign-in attempts. Try again in N seconds. | NFR-SEC | `SignInTest` | Implemented |
| VAL-AUTH-STATE-001 | OIDC callback | `state` must match the session value and is single use | Block | Required | Generic sign-in failure | That sign-in link is no longer valid. | FR-AUTH-001 | `MicrosoftSignInTest` | Implemented |
| VAL-AUTH-NONCE-001 | OIDC ID token | The token's `nonce` must match the one this sign-in started with | Block | Required | Generic sign-in failure | Sign-in could not be completed. | FR-AUTH-001 | `MicrosoftSignInTest` | Implemented |
| VAL-NAV-POLICY-001 | Every gated node and route | A node names a policy; an unknown policy denies. The route is gated by the same policy as its sidebar entry | Block | Required, middleware | Node absent from the markup | 403 You do not have access to this area. | ROLE_MODEL.md 5 | `AccessModelTest` | Implemented |
| VAL-NAV-DOMAIN-001 | Business domain nodes | Access requires BOTH the minimum tier AND an entitlement to that domain. No tier implies a domain | Block | Required | Domain absent from the rail and from My Intelligence | Not shown | ROLE_MODEL.md 1 | `AccessModelTest` | Implemented |
| VAL-NAV-AUDITOR-001 | Compliance cluster | The Auditor capability satisfies the Compliance policy without conferring any operational tier | Allow | Required | Compliance cluster appears | Not shown | ROLE_MODEL.md 2 | `AccessModelTest` | Implemented |
| VAL-SOV-GEO-001 | Capacity / workspace / AI region | Storage and processing geography must be inside the approved list or carry an active exception | Block | Required | Preflight block | Selected region is outside the approved data boundary | REF-DPS-001 | TBD | Planned, Phase 02 |
