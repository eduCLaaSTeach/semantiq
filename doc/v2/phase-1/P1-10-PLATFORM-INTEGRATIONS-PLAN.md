# P1-10 — Platform Integrations & Setup: PLAN

**PLAN ONLY.** No design, no implementation, no schema, no deployment, no
production `.env` change, no bootstrap account, no credentials of any kind.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** (delivery order 12) |
| Authority | `PRODUCT-OWNER-AMENDMENT-PLATFORM-SETUP-AND-BOOTSTRAP.md`, merged `09855b0` |
| Followed by | **P1-11 — Administration Home** (successor), which must not start until this unit is accepted. *(Corrected 20 Sep 2026: this row read "Follows". P1-10 does not follow P1-11.)* |
| Schema | **NEW SCHEMA IS EXPECTED HERE** — and that is a change from the last four units. See §7 |
| Decisions | **D-148 – D-178 ANSWERED** — Product Owner rulings, 20 September 2026 |
| Status | **PLAN APPROVED.** Next: DESIGN only |

---

## 0. The two sentences this unit is most likely to violate

> **1. A credential must live in exactly one place.**
> **2. A connection is not a capability.**

The first is why "no second Entra configuration model" is stated as a rule
rather than a preference: P1-09 showed what happens when one fact has two
sources, and there the cost was a disagreeing status. **Here the cost is a
client secret in two places, one of which somebody forgets to rotate.**

The second is why the amendment fences AI and Fabric so hard. This unit makes
SemantIQ *able* to reach an AI provider and a Fabric workspace. **It must not
become able to do anything with them.** The temptation is real and it arrives
disguised as "we already have the connection, so a small test query would…".

---

## 1. INVENTORY — what is actually in this repository

**Read, not assumed.** Every row below was checked in the working tree.

### 1.1 Bootstrap and first-run — MORE EXISTS THAN EXPECTED

| Thing | State | Where |
| --- | --- | --- |
| `bootstrap_grants` table | **EXISTS** — `token_hash` (SHA-256, 64), `expected_subject`, `expected_tenant`, `issued_by`, `expires_at`, `consumed_at`, `consumed_by_user_id`, unique on the hash | `2026_08_31_000002_create_bootstrap_grants_table.php` |
| Single-use enforcement | **EXISTS, and it is in the `WHERE` clause of the consuming `UPDATE`** — so two concurrent redemptions cannot both succeed regardless of timing | same migration's docblock |
| `BootstrapGrant` model | **EXISTS** | `app/Modules/Platform/Models/BootstrapGrant.php` |
| `BootstrapState` | **EXISTS** — `isConfigured()` / `isUnconfigured()`, **computed, never stored** | `app/Modules/Platform/Bootstrap/BootstrapState.php` |
| Operator issuance command | **EXISTS** — `semantiq:bootstrap-grant {--subject=}` | `IssueBootstrapGrantCommand.php` |
| `bootstrap.grant.issued` event | **EXISTS** in the catalogue | `SecurityEventLogger` |

**This is the single most important inventory finding.** The amendment's
instruction to *"inspect and reuse the existing P1-00 bootstrap grant mechanism
rather than creating an unrelated second bootstrap system"* is not merely
possible — **most of the mechanism is already built, tested and accepted**,
including the concurrency property that is hardest to get right.

**What does NOT exist:** any local password credential, any local
authentication path, any first-run setup screen, and any notion of an
installation principal. `bootstrap_grants` binds a grant to an **Entra
subject and tenant**, which is exactly the circularity the amendment removes.
**D-160 asks whether that table is extended or joined by a second one.**

### 1.2 Identity — owned by P1-02, and how its secret lives today

| Thing | State |
| --- | --- |
| `config/identity.php` | `tenant_id`, `client_id`, **`client_secret`**, `redirect_uri` — all `env()` |
| Storage | **`.env` on the server only.** Nothing in the database, nothing encrypted at rest by the application |
| Deploy | `.env` is **excluded from rsync**, deliberately. This is why a stale value survives indefinitely — as the P1-09 session-driver finding proved |
| Reveal endpoint | **EXISTS** — `EntraController::reveal()`, and it reveals **only** `tenant_id` and `client_id`. **The client secret has no reveal path at all**, and its `default => null` arm names no field |
| Presence checks | `ConfigurationValidator` / `ConfigurationRequirements` — **presence only**, never value |

**The existing reveal pattern is the precedent D-155 should follow**: a closed
match with a `default` that reveals nothing and names nothing.

### 1.3 Email — configured, unconfigured, and never used

| Thing | State |
| --- | --- |
| `config/mail.php` | **EXISTS** — Laravel default, `MAIL_MAILER` defaulting to **`log`** |
| `.env.example` | **NO `MAIL_*` KEYS AT ALL** |
| Production | **Unknown to this repository.** No verification has ever asked |
| Any mail send in the app | **NONE.** No `Mail::`, no Mailable, no notification |

**SemantIQ has never sent an email.** The `log` default means a send today would
silently write to a log file and report success — which is exactly the shape of
failure P1-09's cache check was built to catch, and D-151 exists because of it.

### 1.4 AI, Fabric and notifications — nothing at all

| Thing | State |
| --- | --- |
| AI provider config, client, key | **NONE** |
| Fabric config, client, API code | **NONE** |
| Notification / task / digest model | **NONE** |
| `config/services.php` | **Laravel stock only** — Postmark, Resend, SES, Slack placeholders, none used, none configured |

**Nothing is assumed to exist, and nothing does.** Every AI and Fabric decision
in §6 is a genuine open question rather than a choice between existing things.

### 1.5 Security foundations available to reuse

| Thing | State |
| --- | --- |
| `APP_KEY` / Laravel encrypter | **Available.** `openssl` and `sodium` both present on the host; Laravel 13.29, PHP 8.5 |
| Application-level encryption in use today | **NONE.** No `Crypt::`, no `encrypted` cast anywhere in `app/` |
| Hashing | Laravel's `Hash` facade available; the grant uses **SHA-256 for a lookup token**, which is correct for a high-entropy token and **wrong for a password** — see D-161 |
| Step-up re-authentication | **EXISTS** — `StepUpService`, `PendingStepUp`, `StepUpAction` (9 cases), `StepUpCompletionRegistry`, `pending_step_ups` table, and P1-07 already registered a second completion against it |
| `SecurityEventLogger` | **77 events**, and a **closed 15-key `ALLOWED_KEYS` list**: `provider, subject, tenant, user_id, result, reason, expires_at, organisation_id, entity_type, entity_id, related_id, role, domain_id, scope, sensitivity` |
| `AuditWriter` | Atomicity guard: a state-change event **must** be inside a transaction, enforced at runtime |
| Rate limiting | `RateLimiter` used by P1-02's re-check, per administrator |

**`ALLOWED_KEYS` has no key for a credential, an endpoint, a host or a URL, and
that is the property to preserve rather than extend.** D-158.

### 1.6 Deployment constraints

Shared cPanel · MySQL 8.4 · `QUEUE_CONNECTION=sync`, **no worker, no
scheduler** · `CACHE_STORE=file` · `SESSION_DRIVER=file` in production against a
`database` target (**carried finding — not this unit's to resolve**) ·
`storage/` and `.env` excluded from rsync · outbound HTTPS works (Entra is
reached today).

**No background job can run.** Every connection test is synchronous, inside a
request, on shared hosting — which makes timeout and rate-limit decisions
(D-156, D-157) load-bearing rather than tidy.

---

## 1.7 The `.env`-only circularity is P1-10's to solve — Product Owner ruling

**Correction 2, 20 September 2026.** The PLAN as first written was wrong in a
way its own inventory should have caught.

It said P1-10 *"may surface Identity/SSO setup and readiness and navigate to the
owning screens"*, and treated that as sufficient. **It is not, and §1.2 of this
document proves why:** P1-02's Microsoft configuration is sourced from `.env`,
`.env` is excluded from rsync, and **nothing in the application can write it**.
A Bootstrap Administrator linked to P1-02's existing screen still cannot
configure Entra — so the circularity this unit exists to remove would have
survived the unit built to remove it.

### The ruling

> **P1-02 remains the authoritative owner of Identity/SSO. P1-10 is authorised
> to introduce the secure persisted configuration seam that makes P1-02
> customer-configurable.**

**This is NOT a second Entra model.** It is a migration of P1-02's *one*
authoritative configuration:

```text
server .env only   →   typed + encrypted application-managed configuration, owned by P1-02
```

| Requirement | |
| --- | --- |
| Fresh installations | Configure SSO **through First-Run Setup** |
| P1-02 at runtime | **Reads the new authoritative configuration** |
| P1-10's First-Run UI | **Invokes P1-02's owning service.** It does not write identity configuration itself |
| Credential model | **One.** No duplicate Entra credential store |
| Secrets | Never returned to React |
| Existing production | A **controlled one-time migration/cutover** |
| After cutover | **No indefinite `.env` fallback.** Two active credential authorities is the failure state |
| During PLAN and DESIGN | **Do not remove or change production `.env`** |
| DESIGN owes | The exact migration, cutover **and rollback** procedure |

### The sentence future sessions must not undo

> **After cutover, `.env` is NOT the permanent source of truth for Identity/SSO
> configuration.** A future change that restores it as authority — or leaves a
> silent fallback to it — reopens both the circularity and the
> two-credential-sources failure. **This is recorded here, in the Product Owner
> amendment and in P1-02's own PLAN**, because a single mention is how an
> architectural decision gets quietly reversed.

---

## 2. What this unit must NOT build

- **No second Entra configuration model.** P1-02 owns identity.
- **No notification, task, digest or alert system**, merely because an email
  connection exists.
- **No Phase 3 AI functionality** — no conversational AI, RAG, agents, prompts,
  business-data retrieval or AI decision logic.
- **No Phase 2 Fabric functionality** — no Data Sources, Discovery, Ingestion,
  Lakehouse/Warehouse creation, Semantic Model, pipelines, Power BI publication
  or business-data ingestion.
- **No generic editable key/value settings table.** Typed allowlist or nothing.
- **No `bootstrap_administrator` in `RoleCatalogue`.**
- **No standing local super-admin**, and no re-enable switch on a settings
  screen.
- **No vendor default password**, no fixed installation password, nothing in the
  repository, nothing in plaintext, nothing in deployment logs.
- **No second bootstrap system** where the existing grant mechanism serves.
- **No change** to `/up`, `semantiq:health`, D-19, P1-05's access model, or any
  carried item.

---

## 3. The security boundary, stated as failures to prevent

Each of these is a negative test the unit owes, not an aspiration:

| Must be impossible | Why it is the one to name |
| --- | --- |
| The Bootstrap Administrator reaching business-domain data | It is an installation principal. Business access is the whole thing it must never have |
| The Bootstrap Administrator assigning itself a role | The shortest path from "installation principal" to "permanent backdoor" |
| Bootstrap remaining enabled after transition | A shutdown that is a preference is not a shutdown |
| Replaying an old bootstrap credential | The grant already fails closed this way; the local credential must too |
| A recovery grant being reusable or non-expiring | *"Time-limited"* has to be enforced, not documented |
| A secret round-tripping to the browser | **Masked state, never the value.** The P1-02 reveal endpoint is the precedent |
| A failed connection test naming a token | Provider error bodies routinely echo credentials |
| A successful test implicitly enabling business access | A connection is not a capability |
| AI or Fabric touching business data in Phase 1 | The scope fence, as a test |
| An unknown local username being distinguishable from a wrong password | Generic refusal, as P1-00 already does for Entra identities |
| Integration configuration weakening P1-05 | Nothing here may alter effective access |

---

## 4. Product Owner rulings — D-148 to D-178

**All thirty-one answered, 20 September 2026.** Where a ruling differs from the
recommendation, **the ruling is what DESIGN implements**; the recommendation is
kept so the reasoning that was overruled stays visible.

### Platform Integrations — surface

| # | Ruling |
| --- | --- |
| **D-148** | **One landing page, four cards** — Identity/SSO, Email & Notifications, AI Provider, Microsoft Fabric. Email, AI and Fabric may have their own detail/setup screens. **Identity/SSO uses the P1-02-owned configuration, service and screens; P1-10 must not clone it.** First-Run / Platform Setup is a **separate restricted** pre-normal-administration experience |
| **D-149** | **Reuse `HealthStatus` verbatim** — Available, Degraded, Unavailable, Not configured, **Not applicable**, Not checked. No second enum carrying the same semantics. **`Not applicable` is reserved for an integration deliberately not required on that deployment — never for a failed setup** |
| **D-150** | **`PlatformAdmin`.** Normal Platform Integrations administration is System Administrator only. **The Bootstrap Administrator does NOT receive `PlatformAdmin`** — it uses its own narrowly scoped First-Run authority |

**D-149 adds `Not applicable`, which the PLAN's recommendation omitted**, and
draws the line the PLAN did not: it means *deliberately not required here*, not
*we tried and it failed*. That distinction is exactly the one P1-09 had to
learn for its Jobs rows.

### Email

| # | Ruling |
| --- | --- |
| **D-151** | **SMTP for Release 1.** Provider-neutral; **works before Microsoft SSO exists**; **does not require expanding D-04's Graph scopes**; works with Microsoft 365 SMTP and other approved services. Keep a provider adapter boundary for a later explicit decision. **Do not expand Microsoft Graph scopes in P1-10 for email** |
| **D-152** | Typed configuration: host, port, transport/security mode, username where applicable, **encrypted** password, From address, From display name. **Never expose the password after save. Do not claim sender ownership is verified merely because the fields saved.** A test must establish whether the configured provider accepts the configuration |
| **D-153** | **Test email goes only to the authenticated principal's own address** — a permanent administrator's stored identity email, or the operator-established Bootstrap Administrator email. **No arbitrary recipient textbox in Release 1**, so Platform Integrations cannot become a relay-testing tool |
| **D-154** | A provider interface with **SMTP as the only Release 1 implementation**. **Do not pre-build Graph / SES / Resend adapters** |

**This resolves the PLAN's one genuine blocker.** D-151 chooses the option that
does not touch D-04, so no Graph scope decision is required and nothing is
blocked.

### Secrets and connection tests

| # | Ruling |
| --- | --- |
| **D-155** | **Secrets are NEVER revealed after save.** The UI shows configured/not configured, masked presence, and safe last-changed/last-tested metadata. **No reveal endpoint for passwords, client secrets, API keys or tokens** |
| **D-156** | **Maximum 10-second synchronous budget per provider call.** A timeout or network uncertainty reports **Degraded**, not a definitive Unavailable. **Never display a raw provider response or exception** |
| **D-157** | **One explicit test per administrator, per integration, per 60 seconds** — P1-02's shape. **No automatic polling or background tests** |
| **D-158** | Privileged configuration changes and bootstrap/recovery state changes **require Audit evidence**. **Preserve the closed context-key allowlist — no credential, endpoint, hostname or URL keys.** New event *names* may be introduced where genuinely required; **context must use existing safe keys and never contain secrets.** State-changing writes remain under P1-08's durable/atomic rules. Connection tests may record safe outcome evidence **without provider error bodies**. DESIGN proposes the **minimal** catalogue |
| **D-159** | **Step-up required** for a normal System Administrator before replacing/removing an integration credential, changing identity tenant/client configuration, or changing AI/Fabric identity credentials. **A simple Test connection does not require step-up.** During First-Run, **SSO step-up does not yet exist**, so the Bootstrap Administrator **confirms its current local credential** before equivalent privileged secret changes and before final nomination/transition. **Do not create an impossible dependency on Microsoft step-up before Microsoft exists** |

**D-159's second half is the one that would have been got wrong.** The obvious
implementation — "privileged change requires step-up" — is unsatisfiable during
First-Run, and the failure mode is a setup flow that cannot complete.

### Bootstrap Administrator

| # | Ruling |
| --- | --- |
| **D-160** | **A second, narrow local-principal table.** **Do not mutate `bootstrap_grants`' established Entra-bound semantics** to serve a different principal type; they remain authoritative for their accepted purpose. The new storage is tightly typed and supports **exactly one** local Bootstrap Administrator. DESIGN minimises tables and justifies every field. **No generic settings table** |
| **D-161** | **`Hash::make` / configured adaptive hashing.** **Never SHA-256 a human password.** The existing SHA-256 grant-token hashing is **unchanged** — those tokens are high entropy |
| **D-162** | **Operator-controlled Artisan command.** Interactive/hidden password entry; **plaintext password not accepted as a normal command-line argument**; never printed back; operator supplies the login identity; **one local principal only**; reset/recovery stays operator-controlled; **never a settings toggle** |
| **D-163** | **TOTP is NOT in Release 1.** Installation/recovery-only, operator-created, rate limited, short session, auto-disabled, not a standing alternate login. **If the local account ever becomes permanently enabled this ruling lapses and MFA must be reconsidered** |
| **D-164** | Generic refusals, and **max 5 failed attempts / 15 minutes per IP + normalised login identifier**, plus a **bounded global bootstrap limiter** so rotating identifiers cannot bypass it. DESIGN may set the smallest reasonable global threshold but **may not weaken 5/15**. A successful login resets the relevant counter |
| **D-165** | **30-minute idle, 4-hour absolute.** Regenerate the session identifier at authentication. Privileged secret changes require local credential confirmation. **Every request re-checks that bootstrap access is still permitted.** It must not inherit the longer normal session merely because both use Laravel sessions |
| **D-166** | **A dedicated Bootstrap authentication guard/session context.** It may share Laravel's session infrastructure, but **it is not `User`**, never enters `RoleCatalogue`, never obtains an `ActionClass`, never obtains a domain entitlement, and **cannot become a normal principal by changing a flag**. Every Bootstrap route re-evaluates installation/bootstrap state and **fails closed after shutdown** |

> **D-166 names the file-session consequence explicitly, and it is the sharpest
> operational point in this whole ruling set.** Production currently runs
> `SESSION_DRIVER=file` — the carried alignment finding. **A stale Bootstrap
> session must become unusable because the server-side state and guard reject
> it, not because the session was deleted.** A shutdown that depends on
> removing session files would be defeated by a file that happens to remain.

### First-Run Setup

| # | Ruling |
| --- | --- |
| **D-167** | Nine steps, in order: **1** Bootstrap Login · **2** Platform Setup Overview · **3** Identity/SSO · **4** Email *(optional)* · **5** AI Provider *(optional)* · **6** Microsoft Fabric *(optional)* · **7** First Permanent System Administrator · **8** SSO verification / administrator sign-in · **9** Setup Complete. **The UI must show which steps are required and which optional** |
| **D-168** | **NO Organisation fields are required in First-Run Setup.** P1-10 configures the platform; it does not silently grant the P1-01 Organisation surface. Organisation profile and hierarchy remain normal P1-01 administration after the first permanent administrator exists. **If DESIGN finds a genuinely unavoidable field, STOP and raise it** rather than adding Organisation access |
| **D-169** | Bootstrap closes when **both**: approved SSO configuration exists **and has been verified**, **and** the nominated first permanent System Administrator **completes the normal SSO path and is established**. At that moment: installation becomes CONFIGURED **by authoritative computed state**; bootstrap login refuses; **existing bootstrap sessions fail on their next request**; the local credential becomes unusable; **no manual "disable bootstrap" button is required**. **Do not close bootstrap merely because a connection test passed** |
| **D-170** | The in-between state is **`SSO ready — waiting for administrator sign-in`**. Bootstrap remains available while no permanent administrator has completed SSO, and may correct SSO configuration, verify the connection, nominate/correct the first-admin identity, and finish optional integrations. **It still has zero normal administration or business access** |
| **D-171** | **Email, AI and Fabric are optional.** Only **verified SSO** and **successful first permanent administrator establishment** are mandatory to close bootstrap. Optional integrations may remain **Not configured** and be completed later. **First-Run is resumable until shutdown** |
| **D-172** | Recovery requires the **trusted operator / SSH channel**: a newly issued high-entropy token, **hashed at rest**, **30-minute TTL**, **single-use atomic consumption**, recovering **the same one** local principal rather than creating additional ones. It temporarily enables **only** the limited First-Run/Recovery surface, and closes again once normal System Administrator SSO access is restored **and successfully used**. **Never a permanently enabled break-glass password** |
| **D-173** | **No dedicated Bootstrap Audit/evidence viewer in Release 1.** Bootstrap and recovery actions still **create** appropriate security/Audit evidence; the limited principal simply does not get the normal Audit occurrence screens |

**D-169 is stricter than the PLAN proposed, and correctly so.** The PLAN's
recommendation was to reuse the computed predicate; the ruling adds that a
passing *connection test* must not close bootstrap. Those are easy to conflate,
and conflating them would strand an installation with no administrator and no
way back in.

### AI

| # | Ruling |
| --- | --- |
| **D-174** | **Two connection types: Azure AI Foundry / Azure OpenAI model endpoint, and an OpenAI-compatible endpoint.** The OpenAI-compatible adapter **is** the extensibility seam for approved compatible and self-hosted providers. **Do not add other provider adapters as placeholders.** This decision is about **connection setup only**, not AI functionality |
| **D-175** | **No completion, prompt, RAG or business-data call.** Use the provider's **cheapest safe authentication/metadata capability** that verifies endpoint and credential. **If a provider cannot genuinely validate credentials without performing inference: do not secretly generate a prompt — report `Not checked` for credential-level verification** and defer inference proof to the later AI phase. **A TCP/TLS response alone must not be called "AI Available"** |

**D-175 is the ruling most likely to be quietly violated**, because "just a tiny
test prompt" is always available and always looks harmless. The ruling names
the honest alternative — **`Not checked`** — so there is a correct answer to
reach for instead.

### Microsoft Fabric

| # | Ruling |
| --- | --- |
| **D-176** | **Entra service principal / client credentials for Release 1** — tenant identifier, client/application identifier, **encrypted client secret**, and the configured Fabric workspace identifier where required. **Do not use delegated employee credentials. Do not design around managed identity** — the fixed runtime is shared cPanel, not an Azure-hosted workload identity. **Certificate credentials are a later enhancement; do not implement them now** |
| **D-177** | The test proves **authentication and access to the explicitly configured workspace only**. It may obtain an application token and request safe workspace metadata/authorisation **for that workspace**. It must **NOT** enumerate arbitrary business data, enumerate Lakehouse/Warehouse tables, query semantic models, run DAX or SQL, ingest data, or create/update/delete Fabric resources. **Success means SemantIQ can authenticate and reach the configured workspace — it does not mean Phase 2 is complete** |

### What P1-11 projects

| # | Ruling |
| --- | --- |
| **D-178** | **P1-11 Administration Home gains Platform Integration Readiness** under its readiness/operational summary, projecting exactly: **Identity/SSO status from P1-02's authoritative source**, and **Email, AI Provider and Fabric status from P1-10**. **No secret or provider credential appears.** P1-11 may create **authorised action links** for Not configured / Degraded / Unavailable integrations, and **must not re-run connection tests**. This is a new P1-11 source responsibility created by the amendment; **D-130 – D-147 are preserved**, and the deferred P1-11 PLAN is updated later so this source is explicit |

**D-178 answers P1-11's D-130 in part without closing it.** Integration
readiness is now a real, sourced component of "readiness" — which is exactly
what that unit's PLAN said it lacked. Whether readiness means anything *beyond*
the integration states is still open, and still P1-11's to ask.

---

## 5. Carried items P1-10 must NOT resolve

| Item | Status |
| --- | --- |
| **P1-02** provider-wide SSO re-check | **OPEN / CARRIED / UNVERIFIED.** Do not create a second permanent System Administrator |
| **Production session-driver alignment** | **OPEN / CARRIED.** Target `database`, production `file`. **Not this unit's**, and specifically not to be absorbed into "platform setup" |
| **Privilege-change / session-revocation verification** | Outstanding at phase level |
| **P1-07 / P1-08 / P1-09** carried items | Carried, unchanged |
| **D-19** | Unchanged |

---

## 6. Test expectations

Every negative case in §3 is mandatory. Beyond them:

- **every guard proven non-vacuous** by a recorded mutation — CLAUDE.md §2;
- **architecture guards**, following P1-09's: no second Entra model, no untyped
  settings table, no new `ALLOWED_KEYS` entry, no `bootstrap_administrator` in
  `RoleCatalogue`, no business model named in the setup module;
- **the MySQL suite step**, as P1-09 now has — this unit writes, so it must be
  proven on the engine production runs;
- **a Product Owner test script** that asks nobody to create a real credential
  for a service they do not use.

---

## 7. SCHEMA — EXPECTED, AND THAT IS A CHANGE

**P1-06, P1-07 and P1-09 added none. This unit almost certainly must**, because
a UI-configured integration has to persist somewhere and `.env` is
hand-edited, excluded from deployment, and unreachable from the application.

Two candidates, both to be decided at DESIGN and neither assumed:

1. **A typed integration-configuration table** — **not** a generic key/value
   store. The amendment forbids that outright, and the reason is the same
   closed-list discipline that makes `ALLOWED_KEYS` and `HealthRow` work: a
   place any future value can be written is a place a credential eventually is.
2. **A local bootstrap principal table** — see D-160.

**No migration is written at PLAN.** If DESIGN finds it needs a third table, it
is raised, not added.

---

## 8. Genuine blockers

**One, and it is a decision rather than an obstacle: D-151.** Choosing Microsoft
365 / Graph for email would require OIDC scopes beyond `openid profile email`,
which **D-04 explicitly fenced** — *"Anything beyond requires a later explicit
Product Owner decision."* That decision has not been taken. SMTP avoids it
entirely.

**Nothing else is blocked.** The bootstrap mechanism, the step-up registry, the
encrypter, the event logger and the rate limiter all exist and are reusable.

---

## 9. Status

**PLAN ONLY — AWAITING PRODUCT OWNER REVIEW.**
**NO DESIGN / NO IMPLEMENTATION / NO SCHEMA / NO DEPLOYMENT.**
No production `.env` change. No bootstrap account created. No AI, Fabric or
email credentials created or requested.

**D-148 – D-178 are open and none is assumed.**
**P1-02 remains OPEN / CARRIED / UNVERIFIED. Production session-driver alignment
remains OPEN / CARRIED. P1-07, P1-08 and P1-09 carried items remain carried.
D-19 unchanged. P1-11 Administration Home is deferred and must not start before
this unit is accepted.**
