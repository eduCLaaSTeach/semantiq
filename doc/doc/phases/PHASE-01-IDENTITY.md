# Phase 01 - Tenant Onboarding, SSO and Fabric Automation Identity

**Reference ID:** P01-IDN
**SRS sections:** 5, 15, 16.1, Appendix A
**Completion phrase:** `CONFIRM PHASE 01 COMPLETE`

> **Execution gate:** Do not start a later phase until this phase has passed verification and the user has explicitly confirmed completion.

## Objective

Allow a customer to sign in securely, establish tenant identity, obtain required tenant-admin consent, register/connect a customer-owned automation identity and verify a backend Fabric token without exposing credentials to the browser.

## Preconditions

- Phase 00 is `CONFIRMED` in `IMPLEMENTATION_STATUS.md`.
- Current branch/tests are in a known passing state.
- User approves the phase implementation plan before material code changes.

## Implementation activities

1. Implement Microsoft Entra authorization-code flow with PKCE for Semantiq user sign-in.
2. Resolve and persist verified tenant ID and block privileged actions when the token tenant does not match the onboarded organisation.
3. Implement SSO & Consent screen with admin-consent status, callback/redirect verification, Test SSO and context help.
4. Implement Fabric Automation Identity screen for Tenant ID, Client ID, credential type, secret/certificate reference, expiry and validation state.
5. Implement secure server-side client-credentials token acquisition for Fabric using scope https://api.fabric.microsoft.com/.default.
6. Store secrets/certificates only in the approved vault/KMS and retain only references/metadata in the application database.
7. Implement credential-expiry monitoring and rotation workflow.
8. Implement detailed guided help for App Registration, credential creation, Fabric service-principal tenant settings and workspace access.
9. Implement offboarding controls that remove stored credentials/tokens and record revocation checklist.

## Required outputs

- Working SSO
- Tenant verification
- Admin consent workflow
- Automation identity configuration
- Secure token test
- Credential rotation/expiry monitoring
- Identity help topics
- Identity audit evidence

## Application screens

| ID | Screen | Primary role | Key functions | Integration | Help |
| --- | --- | --- | --- | --- | --- |
| SC-001 | Sign In | All users | SSO login, tenant detection, session creation | Microsoft Entra OIDC/OAuth | HLP-SSO-001 |
| SC-002 | Organisation Setup | Semantiq Admin | Organisation name, tenant ID, domain, region, owner | Semantiq metadata | HLP-ORG-001 |
| SC-003 | SSO & Consent | Tenant Admin | Consent status, callback, roles, test sign-in | Microsoft identity platform | HLP-SSO-001 |
| SC-004 | Fabric Automation Identity | Tenant/Fabric Admin | Client ID, credential, token test, expiry | Entra token + Fabric API | HLP-AUTH-002 |


## Functional requirements

| ID | Requirement | Priority | Acceptance / Notes |
| --- | --- | --- | --- |
| FR-AUTH-001 | Support Microsoft Entra SSO for Semantiq users using authorization code flow with PKCE. | Must | User signs in without a browser-held client secret. |
| FR-AUTH-002 | Resolve and persist the customer tenant ID from verified token claims and onboarding configuration. | Must | Tenant mismatch blocks privileged operations. |
| FR-AUTH-003 | Support tenant-wide admin consent workflow for the Semantiq enterprise application where required. | Must | UI shows granted status after re-check. |
| FR-AUTH-004 | Support a customer-owned Fabric automation service principal for unattended operations. | Must | Tenant ID, client ID and credential can be tested server-side. |
| FR-AUTH-005 | Support certificate credentials and client-secret credentials; prefer certificate for production. | Must | Credential type and expiry are visible; secret value is never redisplayed. |
| FR-AUTH-006 | Acquire Fabric tokens using scope https://api.fabric.microsoft.com/.default. | Must | Token test succeeds and token content is not logged. |
| FR-AUTH-007 | Store automation credentials only in an encrypted secret-management service. | Must | Database contains reference/secret ID, not plaintext secret. |
| FR-AUTH-008 | Provide test actions for token acquisition, Fabric connectivity and permission diagnosis. | Must | Result distinguishes authentication, tenant-setting, workspace-role and API-support failures. |
| FR-AUTH-009 | Monitor secret/certificate expiry and create proactive alerts. | Must | Alerts at configurable 30/14/7-day thresholds. |
| FR-AUTH-010 | Allow credential rotation without deleting customer configuration. | Must | New credential validated before activation. |
| FR-AUTH-011 | Map Semantiq application roles to customer users/groups separately from Fabric roles. | Must | Semantiq role does not imply Fabric privilege. |
| FR-AUTH-012 | Support customer offboarding that disables tokens, removes stored credentials and optionally removes Semantiq service-principal access from Fabric. | Must | Offboarding checklist records completion. |


## In-app help topics

| Topic ID | Help topic |
| --- | --- |
| HLP-SSO-001 | Set up Semantiq SSO and grant tenant admin consent |
| HLP-AUTH-002 | Create the Fabric Automation App Registration |
| HLP-AUTH-003 | Create a certificate or client secret and connect it to Semantiq |


## Acceptance evidence

| ID | Scenario | Pass criterion |
| --- | --- | --- |
| AT-001 | SSO onboarding | New customer signs in, admin consent is granted, tenant is verified and Semantiq role assigned. |
| AT-002 | Fabric identity | Customer enters automation app credentials; token and Fabric read test succeed. |
| AT-016 | Help flow | User follows SSO/Fabric help topic, completes portal action, selects Re-check and receives verified completion. |


## Non-functional requirements

| ID | Category | Requirement |
| --- | --- | --- |
| NFR-SEC-01 | Security | Secrets encrypted at rest; no plaintext secrets in logs/database; OWASP-aligned web controls. |
| NFR-SEC-02 | Tenant isolation | All Semantiq records are partitioned and authorised by organisation/tenant context. |
| NFR-OBS-01 | Observability | Structured logs, metrics, distributed correlation IDs, alerting and searchable audit. |
| NFR-UX-01 | Usability | Non-specialist admin can follow guided setup with contextual help and clear prerequisite status. |
| NFR-COMP-01 | Privacy | Store minimum customer business data in control plane; prefer metadata and resource IDs. |
| NFR-SUP-01 | Supportability | Support export contains configuration and request IDs but redacts secrets and sensitive business data. |


## Identity integration constants

| Purpose | Endpoint / value |
| --- | --- |
| Authorization endpoint | `https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/authorize` |
| Token endpoint | `https://login.microsoftonline.com/{tenantId}/oauth2/v2.0/token` |
| Fabric API base | `https://api.fabric.microsoft.com/v1` |
| Fabric client-credentials scope | `https://api.fabric.microsoft.com/.default` |


### Help flow: App registration -> credential -> Fabric access

1. In the Semantiq **Fabric Automation Identity** screen, show Tenant ID, Client ID, credential type, expiry, token status, Fabric API status and context help.
2. Guide the customer administrator to create/select a customer-owned Microsoft Entra App Registration and copy the Directory (tenant) ID and Application (client) ID.
3. Prefer a certificate for production; allow a client secret only according to customer policy/MVP. Store the credential in the approved vault and never redisplay its value.
4. Guide the Fabric administrator to enable the tenant developer setting allowing the service principal to call Fabric public APIs, preferably scoped to a dedicated security group. Enable the separate create-workspaces/connections/deployment-pipelines setting only when required.
5. Grant the service principal the minimum required role in an existing target workspace when applicable.
6. Return to Semantiq and run **Test Token** then **Test Fabric API**. Report authentication, tenant-setting and workspace-role failures separately.

## Verification checklist

- [ ] New customer can sign in and tenant ID is validated.
- [ ] Admin consent can be re-checked and reflected in the UI.
- [ ] Automation credential is never returned to browser after save.
- [ ] Fabric token test succeeds with the configured service principal.
- [ ] Invalid client/expired credential/consent errors map to specific help guidance.
- [ ] Rotation validates the new credential before activation.

## Evidence before user confirmation

- [ ] Automated tests and test-report paths
- [ ] Manual workflow verification
- [ ] Redacted screenshots / request IDs / correlation IDs where applicable
- [ ] Migration/configuration and rollback notes
- [ ] Known issues and risks
- [ ] Confirmation that later-phase scope was not implemented

## User completion gate

Claude Code must ask the user to confirm the phase. Only after the user explicitly sends **`CONFIRM PHASE 01 COMPLETE`** may the phase be marked `CONFIRMED` and the next phase unlocked.

## Claude Code execution rules

1. Read `CLAUDE.md`, the master plan, status file and this phase document.
2. Inspect the existing codebase and do not assume a stack or architecture not present/approved.
3. Create/update `doc/execution/PHASE-01-PLAN.md` and obtain user approval.
4. Implement only current-phase scope.
5. Create/update `doc/execution/PHASE-01-VERIFICATION.md` with evidence.
6. Ask the user for explicit completion confirmation.
7. After confirmation, update `IMPLEMENTATION_STATUS.md` and unlock the next phase.
8. If a Microsoft API/UI/permission differs from the reference, verify current Microsoft documentation, log the decision and obtain approval before changing approach.

## Mandatory v1.3 controls - Identity, Privacy & Sovereignty

- During Organisation Setup capture customer-approved storage/processing geographies and policy owner before privileged Fabric actions are enabled.
- Tenant/admin identity evidence is metadata; do not store ID-token/access-token payloads beyond the minimum needed for session/security diagnostics.
- Ensure user/session/audit records carry organisation/tenant context and retention classification.
- Service-principal credentials remain vault-only; support certificate preference and auditable rotation/revocation.
- Update code/data/validation/configuration context registers for SSO, consent, token acquisition and offboarding flows.
