# Product Owner amendment — Platform Integrations & Setup, and the local Bootstrap Administrator

**Approved by the Product Owner, 20 September 2026.**
**This amendment is authority.** It supersedes conflicting lower-level PLAN and
DESIGN decisions, including approved ones. Every document it touches carries a
*superseded* marker rather than a rewrite: **a decision that was taken is not
erased because it was later changed.**

| | |
| --- | --- |
| Scope | Two changes — a new Phase 1 unit, and an amendment to the first-administrator rule |
| Effect on sequence | **P1-10 = Platform Integrations & Setup.** Administration Home becomes **P1-11** |
| Phase 1 now | **P1-00 through P1-11**, each individually accepted |
| Status | **AUTHORITY — in force** |

---

## 1. Why this amendment exists

Two gaps, both found by asking what a *customer* would have to do:

1. **SemantIQ has no customer-manageable place to establish the external
   services it depends on.** Identity is administered (P1-02) but its settings
   are server-side; email, AI and Microsoft Fabric have no configuration surface
   at all. A product whose integrations can only be configured by editing `.env`
   over SSH is not a product a customer administers.

2. **The first administrator cannot be established before SSO exists.** P1-00's
   approved D-03 requires the nominated first administrator to complete
   Microsoft Entra SSO. **That is circular on a fresh installation**: SSO cannot
   be configured until somebody privileged can sign in, and nobody privileged
   can sign in until SSO is configured. The existing single-use grant closes the
   loop only when Entra is *already* configured on the server.

---

## 2. CHANGE 1 — Platform Integrations & Setup becomes P1-10

### 2.1 The new unit

**System Administration → Platform Integrations.** A place where authorised
platform administrators establish and verify the external services SemantIQ
depends on. **Platform configuration, not business-domain data configuration.**

Four integration families:

| Family | Ownership |
| --- | --- |
| **Identity / SSO** | **P1-02 remains the authoritative owner.** P1-10 may surface setup/readiness and **navigate** to the owning screens |
| **Email & Notifications** | **P1-10 owns** the new platform email/notification connection boundary |
| **AI Provider** | **P1-10 owns** the reusable platform connection only |
| **Microsoft Fabric** | **P1-10 owns** the platform connection/trust boundary only |

### 2.2 The ownership rule, stated as a rule

> **Platform Integrations must not duplicate another module's authoritative
> configuration.**

**There must be no second Entra configuration model.** P1-02 owns Microsoft
Entra ID, additional approved providers, login experience, SSO health and
session policy. P1-10 projects and links; it does not restate.

> **EXTENDED 20 September 2026 — projecting and linking is NOT sufficient, and
> this is authority.**
>
> P1-02's Microsoft configuration is sourced from `.env`; `.env` is excluded
> from rsync; **nothing in the application can write it.** A Bootstrap
> Administrator linked to P1-02's existing screens therefore still cannot
> configure Entra — so the circularity this amendment exists to remove would
> survive the unit built to remove it.
>
> **P1-10 is authorised to introduce the secure persisted configuration seam
> that makes P1-02 customer-configurable**, as a migration of P1-02's *one*
> authoritative configuration:
>
> ```text
> server .env only  →  typed + encrypted application-managed configuration, owned by P1-02
> ```
>
> P1-02 reads the new authoritative configuration; P1-10's First-Run UI invokes
> P1-02's owning service; there is **one** credential model; no secret returns
> to React; existing production requires a **controlled one-time cutover** with
> a rollback path, designed in P1-10's DESIGN and **not performed during PLAN
> or DESIGN**.
>
> **AFTER CUTOVER, `.env` IS NOT THE PERMANENT SOURCE OF TRUTH FOR
> IDENTITY/SSO CONFIGURATION, AND NO INDEFINITE FALLBACK TO IT REMAINS.** This
> sentence is repeated in the P1-10 PLAN and in P1-02's PLAN deliberately: a
> single mention is how an architectural decision gets quietly reversed by a
> later session that finds `.env` simpler.

**This is the P1-09 lesson applied one level up.** System Health projects nine
existing checks and reimplements none, and a guard fails the build if it tries.
Platform Integrations is the same shape with a larger blast radius, because
here a duplicate would not merely disagree — it would be a **second place a
credential lives**.

### 2.3 Hard scope limits

**AI.** P1-10 establishes a reusable connection and nothing else. **No Phase 3
AI business functionality is brought forward**: no conversational AI, no RAG,
no agents, no prompts, no business-data retrieval, no AI decision logic.

**Fabric.** P1-10 establishes the connection/trust boundary and validates that
SemantIQ can authenticate. **Phase 2 is not pulled forward**: no Data Sources,
Discovery, Ingestion, Lakehouse/Warehouse creation, Semantic Model, pipelines,
Power BI publication or business-data ingestion.

**Email.** A connection is not a notification system. **Do not build
notifications, digests, tasks or alerts because an email connection exists.**

**Providers are not assumed.** The architecture must support *approved*
providers rather than one provider forever, and **no provider is selected
without PLAN analysis and a Product Owner ruling.**

### 2.4 The common integration experience

Every integration ultimately supports: provider/type · configuration state ·
**safe** connection test · status · last checked/tested time · business-readable
error state · edit/reconfigure · authorised administrator only.

**A small fixed status vocabulary**, preferably aligned with the health
semantics P1-09 already established and the Product Owner already accepted:

`Connected / Available` · `Degraded` · `Failed / Unavailable` ·
`Not configured` · `Not checked`

**DESIGN decides the exact wording. Per-integration statuses are not invented.**

### 2.5 Secret management — the requirement that makes this unit hard

Configuring through a UI means secrets can no longer live only in a
hand-edited `.env`. PLAN and DESIGN must define a safe server-side credential
model:

- secrets **encrypted at rest**, using the existing application key / security
  foundation where appropriate;
- **never returned to React after saving** — masked state, never the value;
- no secret in a URL or query string;
- **no secret in Audit context** — the closed `ALLOWED_KEYS` list stands;
- no secret in logs, browser error messages, or GitHub workflow output;
- **connection tests must not leak provider response credentials or tokens.**

**Changing or replacing privileged integration credentials should require the
existing approved step-up re-authentication pattern where appropriate.**

**Configuration changes must produce Audit evidence without persisting the
secret itself.**

> **Do not invent a generic editable key/value configuration table without an
> explicit typed allowlist/schema.** An untyped settings table is a place any
> future value can be written, which is the opposite of the closed-list
> discipline every leak boundary in this codebase depends on.

---

## 3. CHANGE 2 — one local Bootstrap Administrator

### 3.1 What is amended

**P1-00's D-03 required the first administrator to complete Microsoft Entra SSO
before any privileged setup could occur. That requirement is amended.**

It is **not** withdrawn, and the reasoning behind it stands: the first
*permanent* System Administrator still authenticates through the approved
normal identity path. What changes is that a **narrowly scoped local principal
may make that path configurable in the first place.**

### 3.2 What the Bootstrap Administrator is, and is not

**An installation principal, not a SemantIQ user.**

| It is NOT | |
| --- | --- |
| a new business role | **`bootstrap_administrator` is NOT added to `RoleCatalogue`** |
| `System Administrator` | nor an Organisation Administrator, nor a Business User |
| a substitute for P1-05 | the effective-access model is untouched |
| entitled to business data | **zero business-domain access** |
| permanent | it is disabled automatically on transition |

### 3.3 The approved first-run flow

```text
Fresh SemantIQ deployment
        ↓
Installation state = UNCONFIGURED
        ↓
Local Bootstrap Administrator login
        ↓
First-Run / Platform Setup experience
        ↓
Configure essential Platform Integrations
        ↓
Configure Microsoft Entra / approved SSO
        ↓
Verify SSO connection
        ↓
Nominate the first permanent System Administrator identity
        ↓
That person signs in through verified SSO
        ↓
First permanent System Administrator established
        ↓
Bootstrap Administrator disabled
        ↓
Installation state = CONFIGURED
```

### 3.4 Credentials

**There is no vendor default password.** No `admin/admin`, no fixed
installation password, nothing committed to the repository, nothing stored in
plaintext, and nothing emitted into normal deployment logs — the same
discipline that already governs `APP_KEY` and the bootstrap grant.

Credentials are established through an **operator-controlled installation
process or command**.

> **PLAN and DESIGN must first inspect and reuse the existing P1-00 bootstrap
> grant mechanism where possible, rather than creating an unrelated second
> bootstrap system.** A second bootstrap path is a second thing to get wrong.

Password material uses **Laravel-approved secure hashing and credential
handling**. The principal must have: rate-limited authentication · generic
refusal messages · session fixation protection · idle and absolute timeout ·
CSRF protection · Audit/security evidence · brute-force protection consistent
with the existing authentication architecture.

**TOTP MFA is an open question, not an assumption.** DESIGN must evaluate
whether it is appropriate for a local bootstrap principal and **raise the
decision** if it introduces unjustified dependency or operational complexity —
rather than silently implementing or silently omitting it.

### 3.5 Access surface

**The Bootstrap Administrator does not get the normal System Administration
shell.** It gets a dedicated **First-Run / Platform Setup** experience whose
authority is limited to establishing the platform: installation status ·
Identity/SSO configuration · email connection · AI connection · Fabric
connection · connection tests · nomination of the first permanent System
Administrator.

**It must not browse** customer records, learner records, finance or sales
information, Fabric business data, normal Workplace information, or Audit
occurrence contents — except any narrowly required bootstrap/security evidence
view **explicitly approved in DESIGN**.

**If organisation/company profile fields are required to establish tenant
trust, PLAN must justify the minimum set field by field.** The entire
Organisation administration surface is not granted silently.

### 3.6 Shutdown, and why it is not a preference

After **both**: SSO is successfully configured and verified, **and** at least
one permanent System Administrator has successfully authenticated —

**the Bootstrap Administrator login is disabled. This is automatic product
behaviour.** A normal administrator cannot casually re-enable it from a
settings screen.

### 3.7 Recovery — time-limited, never standing

**No permanent always-enabled local super-admin account exists.** If every
permanent System Administrator is lost, or SSO configuration becomes unusable,
an infrastructure/deployment operator may initiate a **time-limited** recovery
through the existing privileged operator channel:

```text
trusted operator / SSH deployment authority
       ↓
issue single-use / short-lived recovery grant
       ↓
temporary Bootstrap Administrator recovery access
       ↓
repair identity/admin access
       ↓
recovery grant consumed/expires
       ↓
local bootstrap access disabled again
```

**Reuse the existing P1-00 grant/recovery mechanisms wherever they remain
suitable. Do not create a permanently active hidden backdoor.**

### 3.8 Installation state — retained, meaning amended

| State | Amended meaning |
| --- | --- |
| **UNCONFIGURED** | The platform has not yet established its first permanent SSO System Administrator. The local Bootstrap Administrator setup path is available under its controlled credential/grant |
| **CONFIGURED** | At least one permanent System Administrator has successfully authenticated through the approved normal identity path. Normal bootstrap login is closed. Recovery is possible only through the privileged operator procedure |

---

## 4. Sequence change

| Order | Was | Now |
| --- | --- | --- |
| 12 | P1-10 Administration Home | **P1-10 — Platform Integrations & Setup** |
| 13 | — | **P1-11 — Administration Home** |

**Administration Home remains last**, and the reason is unchanged and now
stronger: it must **project** the integration and readiness facts P1-10
delivers rather than inventing them. Its own PLAN already identified that it
owns no fact; after this amendment it has more real sources to project and
fewer excuses to invent.

**P1-11 must not be implemented before P1-10 is accepted.**

**Phase 1 closes when P1-00 through P1-11 are each individually accepted**, and
the existing blocking items still stand:

- **P1-02 provider-wide SSO re-check** — OPEN / CARRIED / UNVERIFIED;
- **production session-driver alignment** — OPEN / CARRIED;
- **privilege-change / session-revocation verification**;
- carried **P1-07 / P1-08 / P1-09** items, per their recorded disposition.

---

## 5. What was superseded, and where it is recorded

**Nothing is erased.** Each document below keeps its original decision text and
gains an amendment note beside it.

| Document | What is superseded |
| --- | --- |
| `PHASE-1-PLAN.md` §3 | Delivery sequence — twelve units becomes thirteen |
| `PHASE-1-PLAN.md` §4, D-03 | *"Deferred to P1-00"* — the deferral stands; the ruling it produced is amended |
| `PHASE-1-PLAN.md` §7 | Phase acceptance wording — *"P1-BASE and P1-00 through P1-10"* |
| `P1-00-LOGIN-BOOTSTRAP-PLAN.md` §20, D-03 | The approved rule that privileged setup requires Entra SSO first |
| `P1-00-LOGIN-BOOTSTRAP-PLAN.md` §4 | The bootstrap flow diagram |
| `P1-00-LOGIN-BOOTSTRAP-DESIGN.md` | Bootstrap entry and installation-state wording |
| `P1-00-LOGIN-BOOTSTRAP-VERIFICATION.md` | What was verified remains true of what was built; the *rule* it verified is amended |
| `P1-02-IDENTITY-SSO-PLAN.md` / `-DESIGN.md` | Setup ownership — P1-02 keeps identity; P1-10 surfaces and links |
| Blueprint §2.6.1, §3.3 | Master menu and screen-to-phase traceability gain Platform Integrations |
| `SemantIQ_v2_PHASE_1_System_Administration.md` | Unit list and sequence |
| `P1-10-ADMINISTRATION-HOME-PLAN.md` | **Renumbered to P1-11 and deferred.** D-130 – D-147 are carried forward intact |

**Historical dated logs in `doc/dailyupdates/` are not edited.** They record
what was believed on the day. They are history, not current authority, and
correcting them would falsify a record rather than a claim.

---

## 6. The worked example of superseded wording

The form every amendment note below takes:

> **D-03 — superseded in part by Product Owner amendment, 20 September 2026.**
> The original decision required the first administrator to pass Entra SSO
> before privileged application access. **Superseded:** a narrowly scoped local
> Bootstrap Administrator may perform First-Run Platform Setup before SSO is
> configured. **Unchanged:** the first *permanent* System Administrator still
> authenticates through the approved normal identity path, and the single-use
> grant mechanism, its hashing, its atomic consumption and its operator-only
> issuance all stand.

---

## 7. Status

**AUTHORITY — IN FORCE.** The next active unit is **P1-10 — Platform
Integrations & Setup, PLAN only.**

**No application code, schema, deployment, production `.env` change, local
bootstrap account, or AI/Fabric/email credential is created by this amendment.**
