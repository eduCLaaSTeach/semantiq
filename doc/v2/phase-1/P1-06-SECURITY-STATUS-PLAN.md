# P1-06 — Security Status: PLAN

**Show an administrator where this deployment actually stands, in their words,
without asking them to become a security engineer — and without ever saying
"Healthy" about something nobody measured.**

| | |
| --- | --- |
| Unit | **P1-06 — Security Status** |
| Menu | `System Administration → Security Status` (already a locked node in `ApprovedMenu`) |
| Subscreens | Secure Baseline · Privileged Access Health · Exceptions · Security Events |
| Baseline | P1-05 **PRODUCT OWNER ACCEPTED**, merge `dde0b610bd694856ed86ab3f6657c3912140eaee` |
| Status | **PLAN — awaiting Product Owner review.** No code, schema, migration, route or production change |

---

## 1. What this unit is, and the one sentence that governs it

P1-06 **reports**. It does not enforce, configure, or remediate.

Every control it names is already enforced somewhere else and was already
accepted: authentication in P1-00/P1-02, backend authorization and the access
model in P1-05, domain state in P1-04, people state in P1-03. Security Status is
the screen that finally says, in one place, *what all of that currently adds up
to.*

> **The governing sentence: absence of evidence is never evidence of health.**

Everything in this plan follows from it — the state model, the aggregation, the
Exceptions definition, and the Security Events boundary in particular.

### 1.1 What P1-06 must not become

| Not this | Because |
| --- | --- |
| A second security model | The answer must come from the P1-00–P1-05 sources or not be shown |
| A second audit system | **P1-08 owns durable audit.** §7 is written mainly to prevent this |
| A configuration screen | Mandatory controls are platform controls. Nothing here may switch one off |
| A score | "83/100" compresses away the one thing an administrator needs: *which* thing is wrong |
| A review workflow | **P1-07 owns** recertification. P1-06 shows posture; it does not run a process |
| A risk engine | A role's *name* is not a risk. Only an observed state is |

---

## 2. The posture model

### 2.1 Five states, and what each one means

| State | Business label | Means | Colour role |
| --- | --- | --- | --- |
| `critical` | **Act now** | An observed condition that leaves the deployment exposed or unadministrable | Danger |
| `attention` | **Needs attention** | An observed condition that is not yet harmful but should not persist | Warning |
| `unverified` | **Not verified** | The control exists, but **this deployment has produced no evidence either way** | Neutral, never green |
| `not_applicable` | **Not part of Release 1** | Genuinely deferred; stated so nobody assumes it is covered | Muted |
| `healthy` | **Healthy** | Evidence exists **and** says the control is in effect | Success |

**There is no sixth state, and `not_configured` is deliberately absent.** A
*mandatory* baseline control that is not configured is not a neutral fact — it
is `critical`. Giving it a gentle state of its own is how a deployment sits
permanently amber on something that should stop the room. Optional things are
`not_applicable` and say why.

### 2.2 Precedence — the worst contributing state wins

```
critical  >  attention  >  unverified  >  not_applicable  >  healthy
```

**The aggregate is `healthy` only when every contributing control is `healthy`.**
Stated positively rather than as "worst wins", because the two differ precisely
where it matters: a set containing only `unverified` rows must aggregate to
`unverified`, not to `healthy`.

> **D-75 candidate — we deliberately do NOT inherit P1-02's rule.**
> `IdentityHealthReport::state()` returns *"any Failed; else any Degraded; else
> Healthy"*, and its `NotChecked` **contributes nothing**. That is defensible on
> a single-purpose identity screen where a not-yet-run live probe is genuinely
> not a fault. It is **not** defensible as a posture aggregate: a report whose
> every row is `NotChecked` returns **Healthy**, which is exactly "green because
> nothing is being measured".
>
> P1-06 therefore defines its **own** aggregation in which `unverified` is a
> first-class contributor. **This plan does not propose changing P1-02** — that
> unit is accepted and its rule is correct for its own screen. P1-06 *maps*
> P1-02's four row states into the five above (§3.2) rather than re-using its
> aggregate.

### 2.3 Fail-closed for missing, conflicting and unavailable inputs

| Input condition | Resulting state | Never |
| --- | --- | --- |
| Source throws, times out, or is unreachable | `unverified` + the reason in business words | Not `healthy`; not a blank row |
| Source returns nothing where rows were expected | `unverified` | Not `healthy` |
| Two sources disagree | **`attention`**, naming both, and the disagreement is itself the finding | Not silently preferring one |
| A control is enumerated but has no evaluator | **Build failure** (§10, architecture guard) | Not a missing row |
| An enum/state value the code does not recognise | `unverified`, and one `access.state.unrecognised`-style event | Not a crash, not a guess |

**A row is never omitted to avoid an awkward state.** Omission is the failure
mode that makes a posture screen lie, because the reader counts what they see.

### 2.4 No score

No number is proposed. The blueprint asks for posture an administrator can
*act on*; a composite score is the opposite of actionable and invites the
"get it to 90" behaviour that ends in controls being marked not-applicable to
move a number.

---

## 3. Secure Baseline

### 3.1 The controls P1-06 reports

Nine, each with a real evidence source that exists today. **P1-06 owns none of
them** — the "Owned by" column is where they are configured and enforced, and
every row links there rather than offering a control of its own.

| # | Control (as an administrator reads it) | Evidence source (exists today) | Owned by |
| --- | --- | --- | --- |
| **B-1** | Everybody signs in with Microsoft | `IdentityHealthCheck` rows `provider_configured`, `configuration_valid`, `identity_trust` | P1-02 |
| **B-2** | Only approved sign-in providers are registered | `ProviderInventory` · `ApprovedProviders` | P1-02 |
| **B-3** | Sign-in is reachable right now | `IdentityHealthCheck` row `microsoft_reachable` (**live probe, `NotChecked` until run**) | P1-02 |
| **B-4** | Sessions expire and are re-checked on every request | `SessionPolicy::matchesApprovedPolicy()`, `idleIsShorterThanAbsolute()`, `revalidatesEveryRequest()` | P1-02 |
| **B-5** | Inactive accounts cannot use the product | `EnsureSessionIsCurrent` present in the middleware priority list; `UserStatus` enforced in the engine | P1-00/P1-03 |
| **B-6** | Every administration screen is checked by the server | **Route-table inspection**: every `console/*` route declares an `ActionClass` via `RequireActionClass` | P1-05 |
| **B-7** | Access is refused unless a complete grant exists | `AccessEngine` global gates present; `ActionClass::requiresGrantPath()` | P1-05 |
| **B-8** | The last System Administrator cannot be removed | `AdministratorSetGuard` present and reachable from both reducing operations | P1-05 |
| **B-9** | Privileged changes need a fresh Microsoft sign-in | Step-up routes registered; `StepUpAction` catalogue; **second redirect URI** | P1-05 |

### 3.2 Mapping P1-02's row states into P1-06's

| P1-02 row state | P1-06 state | Why |
| --- | --- | --- |
| `FAILED` | `critical` | Sign-in unavailable stops the product |
| `DEGRADED` | `attention` | Real finding, not yet fatal |
| `HEALTHY` | `healthy` | Evidence exists and is good |
| `NOT_CHECKED` | **`unverified`** | **The divergence.** Nothing was measured, so nothing may be claimed |

### 3.3 Status display versus configuration ownership

Every Secure Baseline row carries **exactly one** action affordance: a link to
the screen that owns it (Identity & SSO, Roles & Access, Business Domains,
Users & Groups). That link re-authorises on arrival — visibility here is never
permission there, which is the P1-05 lesson applied.

**No row offers a toggle, an override, an acknowledgement, or a "mark as
reviewed".** §10 carries a test that no mutating route exists under Security
Status at all.

### 3.4 What has no runtime evidence, and is therefore not claimed

| Often expected | Why it is not a green row |
| --- | --- |
| Encryption at rest / in transit | Enforced by hosting and the TLS terminator. The application cannot observe it from inside. **`not_applicable` with that sentence**, never green |
| Backups | No accepted v2 source. Not shown at all rather than shown as unknown-forever |
| Web-exposure hardening | `deploy.yml` runs real negative tests — but **at deploy time, not at runtime**. Reporting a past deploy as current posture is exactly the false-green this unit must not produce. **`unverified`**, with the honest sentence that it is checked at deployment |

> This table is the plan's main defence against the failure the Product Owner
> named: *"never show green simply because nothing is being measured."*

---

## 4. Privileged Access Health

Every indicator is **derived from stored P1-05 state**. None invents risk from a
role name; each is an *observed condition* with a stated threshold.

| # | Indicator | Derived from | State rule |
| --- | --- | --- | --- |
| **PR-1** | Active System Administrators | `AdministratorSetGuard::effectiveCount()` | `0` → `critical` (D-49a floor breached) · `1` → `attention` (sole-administrator lockout risk) · `≥2` → `healthy` |
| **PR-2** | Organisation Administrators | current assignments, that role | Count shown. `attention` only above a Product-Owner threshold (**D-78**), else `healthy` |
| **PR-3** | Privileged people holding business-domain entitlements | assignments in `RoleCatalogue::requiringStepUp()` **and** a current `DomainEntitlement` | Any → `attention`. *Administration authority must not quietly become business-data authority* |
| **PR-4** | Restricted sensitivity grants | current `EntitlementCeiling` at `Restricted` | Any → `attention`, listed with domain and role. Never `critical`: a Restricted grant is legitimate, it is *unreviewed* Restricted grants P1-07 will chase |
| **PR-5** | Inactive people still holding current assignments | `users.status = inactive` × current assignments | Any → `attention`. **Correct by design** (P1-03 preserves relationships) but must be *visible*, not silent |
| **PR-6** | Incomplete grant paths | current entitlement with **no** current scope, or **no** current ceiling | Any → `attention`. Grants nothing today; it is *latent* access somebody believes exists |
| **PR-7** | Accountability without entitlement, and the reverse | P1-04 `business_domain_owners` versus P1-05 entitlements | **Information, never a fault.** D-51: the two are independent by design and neither implies the other |
| **PR-8** | Broad scopes | current scopes of type `Domain`/`Organisation` (`coversWholeDomain()`) | Count shown. `attention` above a threshold (**D-78**) |
| **PR-9** | Step-up availability | step-up routes + `StepUpAction` catalogue + redirect-URI evidence | Unavailable → `critical` (privileged changes would be unconfirmable) |

**PR-7 is deliberately not a finding.** Presenting "owns a domain but has no
entitlement" as a problem would re-import exactly the conflation D-51 exists to
forbid. It is shown because an administrator asks the question, and answered
with *"these are independent — owning a domain grants nothing"*.

**No indicator reads `access_expectation`.** D-61 makes it context only, and the
engine never reads it; a posture screen that did would be a second opinion about
access.

---

## 5. Domain-aware posture

P1-04 prepared domains precisely so posture can be *per domain*. Each domain row
reports only what current information genuinely supports:

| Facet | Source | Honest statement |
| --- | --- | --- |
| Enabled / disabled | `BusinessDomain::status` | Disabled → **nobody reaches its information**, which is a *fail-closed success*, not a fault |
| Accountable owner | `business_domain_owners` current row | Enabled **without** an owner → `attention` (P1-04 refuses it through the UI; a stored state that escaped is worth seeing) |
| Current explicit entitlements | `DomainEntitlement` | Count |
| Privileged grants into it | entitlements whose role requires step-up | Any → `attention` |
| Scope breadth | `EntitlementScope` types | Whole-domain scope count |
| Sensitivity ceilings | `EntitlementCeiling` | Restricted count |
| Incomplete grants | entitlement without current scope/ceiling | Count |

**Three hard rules**

1. **Domain ownership is never access** — stated on the screen, not just in this plan.
2. **No domain's posture may be computed from another domain's rows.** §10 carries a cross-contamination test.
3. **Nothing implies data classification or Fabric security exists.** Sensitivity is a *ceiling on a grant*, not a label on data; there is no data in Phase 1.

A **disabled** domain's posture is reported as `healthy` **for the gate** and
plainly labelled *"Disabled — nobody reaches its information"*. Calling a
fail-closed state a fault teaches people to ignore the screen.

---

## 6. Exceptions

### 6.1 Definition

> **An Exception is any control or indicator whose current state is not
> `healthy`.** It is a *view* of §3–§5, not a separate mechanism and not a
> separate store.

Every exception carries a **kind**, because the four are answered by completely
different people:

| Kind | Means | Example | Who resolves |
| --- | --- | --- | --- |
| **Unresolved security condition** | Observed, actionable now | Zero administrators; step-up unavailable | Administrator, today |
| **Accepted limitation** | Known, deliberate, recorded | Encryption not observable from the application | Nobody — it is documented |
| **Verification incomplete** | Control exists; this deployment has no evidence | Live probe never run; **P1-02 SSO Re-check** (§8) | Administrator can often produce it |
| **Not yet configured** | Setup step outstanding | Second Entra redirect URI absent | Administrator, once |

### 6.2 No exception table, and why

**Recommendation: derive everything. Add no persistence in Release 1.**

An acknowledgement store is where a posture screen goes to die: the moment an
exception can be dismissed, the screen stops reporting the deployment and starts
reporting *what somebody clicked*. It also needs an owner, an expiry, a reason
(free text — a leak channel), and a review lifecycle, which is **P1-07's job**.

The "accepted limitation" kind therefore lives in **code, as a reviewed static
register**, not in a table an administrator can edit. Changing what this
deployment accepts is a **code change with a Product Owner decision**, which is
the correct weight for it.

**This is surfaced as D-77 in case the Product Owner wants acknowledgement in
Release 1.** If so, it must be justified against P1-07 first, and this plan
would recommend deferring rather than building it here.

---

## 7. Security Events — the P1-08 boundary

**This is the section with the highest risk of accidentally building P1-08, and
the finding below decides it.**

### 7.1 What exists today, exactly

`SecurityEventLogger` declares **71 event types** (counted, not estimated) across P1-00 to P1-05 with a
**closed context vocabulary of 15 keys** and **no free-text channel** — a leak is
structurally unrepresentable, which is a genuinely strong property.

But `record()` ends:

```php
Log::info($event, $context + ['at' => now()->toIso8601String()]);
```

Its own docblock is unambiguous:

> *"No audit table — P1-08 owns durable storage and adopts these events later."*

**The consequences, stated plainly:**

| Property | Reality |
| --- | --- |
| Durable | **No.** Laravel log files, `daily` driver, rotated and finite |
| Queryable | **No.** Text lines, no index, no schema |
| Tamper-resistant | **No.** Ordinary files on the application server |
| Complete | **No.** Retention is a file-rotation setting, not a policy |
| Attributable across deployments | **No.** A deploy or a host move can end the history |

### 7.2 Therefore

> **P1-06 must not read the log files.** Parsing them to populate a screen would
> create a second audit system with worse properties than the one P1-08 will
> build, present a partial history as if it were complete, and put unbounded
> file I/O behind an administration screen.

**Recommended Release-1 Security Events screen — the smallest honest thing:**

It shows **what this deployment records and how it is protected**, derived from
the code that already exists — not a history:

1. **The recorded-events catalogue** — all 71 declared event types, grouped in
   business language (*Sign-in · Administration changes · Access changes ·
   Privileged confirmations · Permanent deletions*), read from
   `SecurityEventLogger::events()` so it can never drift from reality.
2. **The redaction contract, stated as a promise with its enforcement** — the 15
   permitted keys, and the sentence that a token, code, nonce or grant *cannot*
   be recorded because there is nowhere for it to go.
3. **An explicit limitation panel**: *"Searchable security history arrives with
   Audit. What you see here is what is being recorded, not a record of what
   happened."* — kind **Verification incomplete** in Exceptions.

This is honest, useful on day one (an administrator can see coverage and the
redaction guarantee), needs **no schema**, and leaves P1-08 free to design
durable storage without inheriting a shape chosen here.

### 7.3 The boundary, in one table

| Belongs to P1-06 | Belongs to P1-08 |
| --- | --- |
| *What* is recorded, and the redaction contract | The durable store, and its schema |
| Coverage gaps as posture | Searchable history, filters, retention |
| A link to Audit when it exists | Tamper resistance, export, viewer-appropriate redaction |

**P1-06 creates no table, writes no event, and adds no key to `ALLOWED_KEYS`.**
If a control genuinely needs a new event, that is a change to the P1-08-bound
vocabulary and a Product Owner decision, not a P1-06 convenience.

---

## 8. The carried P1-02 SSO Re-check

The provider-wide SSO Re-check is open because no genuine second **permanent**
System Administrator exists, and one must not be manufactured.

**P1-06 represents it as an Exception of kind *Verification incomplete*, state
`unverified`**, worded for a business reader:

> **Provider-wide sign-in re-check — not verified.** This check needs a second
> permanent System Administrator to observe safely. One has not been created,
> deliberately. Nothing is known to be wrong; nothing has been confirmed either.

It is **never `healthy`** (nothing confirmed it) and **never `critical` or
`attention`** (nothing indicates a fault). This is the clearest case in the unit
of why `unverified` has to exist as a first-class state rather than being folded
into "fine" or "broken".

---

## 9. Authorization

**Derived from the accepted P1-05 catalogue. No role is broadened.**

`ActionClass::EvidenceRead` **already exists** and is already held by
System Administrator, Organisation Administrator and Auditor. It is exactly the
right class: *read the evidence, change nothing.*

| Who | Security Status | Reasoning |
| --- | --- | --- |
| **System Administrator** | Full — every row | Holds `PlatformAdmin` too, so platform rows are legitimately theirs |
| **Organisation Administrator** | Organisation-scoped rows; platform rows **named but not valued** | Holds `EvidenceRead` but **not** `PlatformAdmin`. Identity configuration authority stays System-Administrator-only (P1-05 §10) |
| **Auditor** | Same as Organisation Administrator, read-only | `EvidenceRead` is the entirety of the Auditor's catalogue entry |
| **Everyone else** | Refused, with **no security metadata in the refusal** | `BusinessData` roles hold no administration class |

**Actions: none.** Every Security Status route is a `GET`. Remediation is a link
to the owning screen, which re-authorises independently.

**"Named but not valued"** means an Organisation Administrator sees *"Microsoft
sign-in trust — managed by the platform administrator"* rather than either a
value or a suspicious blank. A hidden row would make the count wrong; a valued
row would widen the role. **This is D-76.**

---

## 10. Tests

Non-vacuous throughout: each guard below is broken deliberately and observed to
fail, and the mutation is recorded beside the case.

### 10.1 The posture model

| # | Case | Mutation it must catch |
| --- | --- | --- |
| N-SS1 | Each of the five states renders with its business label | Rename a label to a code |
| N-SS2 | **An all-`unverified` set aggregates to `unverified`, never `healthy`** | Copy P1-02's "NotChecked contributes nothing" |
| N-SS3 | Aggregate is `healthy` **only** when every row is `healthy` | Return `healthy` when no `critical` row exists |
| N-SS4 | Precedence holds for every adjacent pair | Swap two states |
| N-SS5 | A throwing source yields `unverified` **and the row still renders** | Catch and skip the row |
| N-SS6 | Conflicting sources yield `attention` naming both | Prefer one silently |
| N-SS7 | **Every enumerated control has an evaluator** (architecture guard) | Add a control with no evaluator |
| N-SS8 | An unrecognised stored value yields `unverified`, not a crash or a guess | `match` without a default |

### 10.2 Privileged Access Health

| # | Case |
| --- | --- |
| N-SS9 | Sole administrator → `attention`; **two genuine administrators → `healthy`** (the non-vacuous half) |
| N-SS10 | Zero administrators → `critical` |
| N-SS11 | Privileged user **with** a business entitlement → `attention`; **without** → `healthy` |
| N-SS12 | Restricted ceiling → `attention`; Standard → `healthy` |
| N-SS13 | Incomplete entitlement (no scope) → `attention`; complete → `healthy` |
| N-SS14 | Inactive user holding a current assignment → `attention` |
| N-SS15 | **Owner-without-entitlement is information, never a fault** (D-51) |

### 10.3 Domains

| # | Case |
| --- | --- |
| N-SS16 | Disabled domain → *"nobody reaches its information"*, **not** a fault |
| N-SS17 | Enabled domain with no current owner → `attention` |
| N-SS18 | **A domain's posture never includes another domain's rows** |
| N-SS19 | No domain row implies data classification or Fabric security |

### 10.4 The hard security guarantees

| # | Case | Why it exists |
| --- | --- | --- |
| N-SS20 | **No secret, token, code, nonce, grant or client secret in rendered props, JSON or logs** — swept like P1-02's screen sweep | The unit's headline requirement |
| N-SS21 | **Every Security Status route is a `GET`** and no mutating route exists under it | "Cannot disable a mandatory control through ordinary admin UI", enforced structurally rather than asserted |
| N-SS22 | An unauthorised request returns **no security metadata** — same refusal whether the deployment is healthy or critical | A posture screen is an attacker's map |
| N-SS23 | Organisation Administrator sees platform rows **named but not valued**; System Administrator sees values | D-76, both halves |
| N-SS24 | Auditor may read; Auditor may not reach any other administration screen | No silent broadening |
| N-SS25 | **P1-06 reads no log file** (architecture guard on file I/O) | The P1-08 boundary, structurally |
| N-SS26 | **P1-06 creates no table and records no security event** | Ditto |
| N-SS27 | The events catalogue is read from `SecurityEventLogger::events()` and cannot drift | A hand-copied list is a list that goes stale |
| N-SS28 | **P1-02 carried gate renders as `unverified`**, never healthy, never failed | §8 |
| N-SS29 | **There is exactly one posture evaluator** (architecture guard) | The "accidental second security model" the Product Owner named |
| N-SS30 | No numeric score anywhere in props or copy | §2.4 |

### 10.5 Empty and day-one states

Every one is a case, not a screenshot: nothing configured · no exceptions · no
privileged business access · sole administrator · evidence unavailable · healthy
populated · warning · critical.

**`no exceptions` is the dangerous one** — it is the screen most likely to be
mistaken for "all clear". It must read *"No unresolved conditions. N controls
reported, M not verified"*, and **N-SS2 is what stops it lying.**

---

## 11. Schema impact

> **Recommendation: no new tables and no new columns.**

Every value in §3–§5 is derivable from accepted sources:

| Question | Answered by |
| --- | --- |
| Identity posture | `IdentityHealthCheck` / `SessionPolicy` / `ProviderInventory` (P1-02) |
| Administrator counts | `AdministratorSetGuard` (P1-05) |
| Roles, entitlements, scopes, ceilings | P1-05 tables |
| Domain state and accountability | P1-04 tables |
| People state | P1-03 `users` |
| Route authorization coverage | The route table itself |
| Recorded events | `SecurityEventLogger::events()` |

The only candidate for persistence is exception acknowledgement — **rejected in
§6.2, surfaced as D-77.** If the Product Owner wants it, this plan recommends it
be designed in **P1-07**, which already owns the review lifecycle, an owner and
an expiry. Adding it here would mean a table with no lifecycle, no reviewer and
no expiry, which is how a permanent green tick gets bought for one click.

**Cost of the recommendation, stated honestly:** posture is computed per request
from several sources. Expected volumes in Phase 1 are small (tens of rows), and
**D-69 forbids a permission cache**, so this plan proposes **no caching of
posture** either — a cached posture is a posture that can be wrong at the moment
it matters. If measurement later shows a real cost, that is a DESIGN concern
with evidence, not a PLAN assumption.

---

## 12. Delivery order

| Step | Content | Gate |
| --- | --- | --- |
| 1 | Posture model: states, precedence, aggregation, fail-closed, plus **N-SS1–N-SS8** | Model correct before anything renders |
| 2 | Secure Baseline evaluators B-1…B-9 over existing sources | No new source invented |
| 3 | Privileged Access Health PR-1…PR-9 | Derived only |
| 4 | Domain posture | Cross-contamination guard first |
| 5 | Exceptions as a derived view | No persistence |
| 6 | Security Events — catalogue + redaction contract + limitation panel | P1-08 boundary guards |
| 7 | Screens, empty states, polish gate | Browser verification at both widths, both themes |
| 8 | Product Owner Test Script + verification record | CLAUDE.md §3 |

---

## 13. Consolidated Product Owner decisions

Only decisions that materially affect **security meaning, privilege, durable
data, architecture or future compatibility**. Routine UI and coding choices are
not here; this plan makes them.

| # | Decision | Options | **Recommendation** | Why |
| --- | --- | --- | --- | --- |
| **D-75** | Does `unverified` contribute to the aggregate, diverging from P1-02's `NotChecked`? | (a) **Contributes — aggregate can never be healthy while anything is unverified** · (b) Contributes nothing, as P1-02 | **(a)** | Option (b) returns **Healthy** for a report where *nothing was measured*. That is the exact failure the unit was told to prevent. P1-02 itself is not changed |
| **D-76** | What does an Organisation Administrator / Auditor see of **platform** rows? | (a) **Named but not valued** · (b) Hidden entirely · (c) Full values | **(a)** | (b) makes the count wrong and hides that a control exists; (c) silently widens a role past P1-05 §10. (a) is honest without granting anything |
| **D-77** | Does Release 1 persist exception **acknowledgement**? | (a) **No — derive only** · (b) Yes, with owner/expiry/reason | **(a)** | (b) is a review lifecycle, which is **P1-07's**. Built here it would have no reviewer and no expiry — a permanent green tick for one click. Accepted limitations live in a reviewed static register instead |
| **D-78** | Thresholds for "too many" — Organisation Administrators (PR-2), whole-domain scopes (PR-8) | (a) **No threshold in Release 1 — report the count, no state** · (b) Product Owner sets numbers now | **(a)** | An invented threshold is invented risk. Counts are visible immediately; a threshold can be added later against real data. If you prefer (b), give the numbers and they become the rule |
| **D-79** | Release-1 Security Events content | (a) **Catalogue + redaction contract + limitation panel; no log reading** · (b) Parse log files into a history · (c) Omit the subscreen | **(a)** | (b) builds a second audit system with worse properties than P1-08's and shows partial history as complete. (c) leaves an approved subscreen blank and tells an administrator nothing about coverage |
| **D-80** | Is *hosting/web-exposure* posture shown from `deploy.yml` evidence? | (a) **`unverified`, stating it is checked at deployment** · (b) `healthy` from the last deploy · (c) Omit | **(a)** | (b) reports a **past** result as **current** posture — false green by construction. (a) is true and still tells the reader where the check lives |
| **D-81** | Does P1-06 evaluate posture **live per request**, with no cache? | (a) **Live, no cache** · (b) Cache with a TTL | **(a)** | Consistent with **D-69** (no permission cache). A cached posture is a posture that is wrong exactly when something has just changed. Revisit with measurement, not assumption |

**Not asked, because this plan decides them:** tab order, copy wording, icon
choice, row grouping, how many rows per panel, and the shape of the empty
states. Those follow the frozen CLaaS2SaaS foundation and the accepted P1-02–
P1-05 screen patterns.

---

## 14. Exit criteria

1. An administrator with no security background can read the four screens and say
   what is wrong and what to do next.
2. **Nothing reports `healthy` without evidence** — N-SS2 and N-SS3 hold.
3. No mandatory baseline control can be switched off from these screens —
   N-SS21 holds structurally.
4. No secret appears anywhere — N-SS20 holds.
5. The P1-08 boundary is intact: **no table, no event, no log reading** —
   N-SS25, N-SS26.
6. The P1-02 carried gate reads honestly as **not verified** — N-SS28.
7. Product Owner Test Script complete, with everything not observable in
   production named rather than inferred.
