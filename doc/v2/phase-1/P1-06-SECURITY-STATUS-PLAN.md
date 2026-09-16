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
| `unverified` | **Not verified** | The control **applies**, but this deployment has produced no evidence either way | Neutral, never green |
| `not_applicable` | **Not part of Release 1** | Genuinely **outside** Release 1's scope. **Display only — it does not contribute** | Muted |
| `healthy` | **Healthy** | Evidence exists **and** says the control is in effect | Success |

**There is no sixth state, and `not_configured` is deliberately absent.** A
*mandatory* baseline control that is not configured is not a neutral fact — it
is `critical`. Giving it a gentle state of its own is how a deployment sits
permanently amber on something that should stop the room.

> **`not_applicable` is reserved for something genuinely outside Release 1 — it
> is NOT a resting place for an applicable control whose evidence is
> unavailable.** That case is `unverified`, every time. Encryption in transit is
> the worked example: it plainly applies to this product, SemantIQ simply has no
> accepted runtime evidence source for it. Calling that "not applicable" would
> quietly write the control out of scope; calling it "not verified" states the
> truth (§3.4).

### 2.2 Precedence, and the two-tier contract

**Only four states contribute to the aggregate:**

```
critical  >  attention  >  unverified  >  healthy
```

**`not_applicable` is a display state and contributes nothing.**

> **The aggregate is `healthy` when every APPLICABLE contributing control is
> `healthy`.** If a deployment has no applicable controls at all — every one is
> `not_applicable` — the aggregate is `not_applicable`, not `healthy` and not
> `unverified`.

Stated as "every applicable control", not as "worst wins", because the two
differ exactly where it matters: a set containing only `unverified` rows must
aggregate to `unverified`, never to `healthy`.

**Why `not_applicable` had to be pulled out of the chain.** An earlier draft of
this plan ordered it *between* `unverified` and `healthy` while also requiring
every contributor to be `healthy`. Those two statements are incompatible: a
deployment in which every applicable control is genuinely healthy could never
reach **Healthy**, because one permanently-out-of-scope row would hold the
aggregate down forever. A posture screen that cannot ever say "Healthy" is a
posture screen people stop reading — the same failure as false green, arrived at
from the other side.

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
| An enum/state value the code does not recognise | **`unverified`**, and nothing else | Not a crash, not a guess — **and no security event** |

**A row is never omitted to avoid an awkward state.** Omission is the failure
mode that makes a posture screen lie, because the reader counts what they see.

> **P1-06 emits no security event, including on the unrecognised-value path.**
> An earlier draft of this plan proposed an `access.state.unrecognised`-style
> event here, which directly contradicted §7 and N-SS26. The contradiction is
> resolved in favour of the **P1-08 boundary**: P1-06 fails closed to
> `unverified` and records nothing. Adding a durable event is a change to the
> P1-08-bound vocabulary and belongs to that unit's decision, not to a reporting
> screen's convenience.

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
| **B-9a** | Privileged changes need a fresh Microsoft sign-in — **SemantIQ's side** | Step-up routes registered; `StepUpAction` catalogue; local redirect configuration present | P1-05 |
| **B-9b** | …and **Microsoft's side accepts the return** | **No accepted runtime evidence source** | P1-02 / Entra |

#### B-9 is split deliberately, and B-9b can never be green today

Step-up has two halves and SemantIQ can only see one of them.

| Half | Rule |
| --- | --- |
| **B-9a — SemantIQ's side** | Route, catalogue or local configuration **missing → `critical`**. Privileged changes would be unconfirmable |
| **B-9b — the external half** | SemantIQ-side prerequisites present, but the **Entra redirect registration and current provider acceptance cannot be observed → `unverified`** |

> **A configured local redirect URI does not prove Microsoft has registered it.**
> Inferring the external half from the local half is precisely how a screen ends
> up green over a `redirect_uri_mismatch` — and P1-05 already proved that failure
> mode is real: the first live use of step-up is where it would surface.
>
> **B-9b becomes `healthy` only when a future accepted live evidence source
> exists**, in the way P1-02's live probe is an accepted source for reachability.
> Until then `unverified` is the whole truth.

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

| Often expected | State | Why |
| --- | --- | --- |
| Encryption in transit / at rest | **`unverified`** | It plainly **applies** to this product — it is enforced by the hosting platform and the TLS terminator, which the application cannot observe from inside. **Not `not_applicable`:** calling an applicable control "not applicable" quietly writes it out of scope. The row says SemantIQ has no accepted runtime evidence source for it |
| Backups | **`unverified`** *if shown* | Same reasoning. There is no accepted v2 source, and an applicable control with no evidence is unverified — never green, never written out of scope |
| Web-exposure hardening | **`unverified`** | `deploy.yml` runs real negative tests — but **at deploy time, not at runtime**. Reporting a past deploy as current posture is false-green by construction. The row says where the check actually lives |

**Nothing in this table is `not_applicable`.** All three controls apply; what is
missing is evidence. `not_applicable` is reserved for capabilities genuinely
outside Release 1 — data classification and Fabric security are the real
examples, and they are stated as absent rather than as controls at all.

> This table is the plan's main defence against the failure the Product Owner
> named: *"never show green simply because nothing is being measured."*

---

## 4. Privileged Access Health

### 4.1 Two kinds of row, and the line between them

This section reports two things that must never be confused:

| Kind | Contributes to the aggregate? | What it is |
| --- | :---: | --- |
| **Posture control** | **Yes** — carries `critical` / `attention` / `unverified` / `healthy` | An **observed condition** that is wrong, or that nobody has verified |
| **Informational metric** | **No** — carries a **count and context only** | A **legitimate, approved state** worth seeing, which is not by itself a finding |

> **An informational metric has no state and can never enter aggregation.** It
> also can never be silently converted into `healthy` — a count is not evidence
> of health, it is a number. §10 guards both directions.

**Why the line exists.** P1-05 deliberately delivers capabilities — Restricted
sensitivity grants, several Organisation Administrators, whole-domain scope,
assignments preserved across deactivation — each protected by its own control
and each approved. Painting them amber would mean P1-06 declaring approved
P1-05 behaviour to be a fault, training administrators that amber means nothing,
and inventing risk from a legitimate state. **P1-07 owns whether a particular
grant is overdue or unreviewed.** P1-06 shows it exists.

### 4.2 Posture controls — state-bearing

| # | Indicator | Derived from | State rule |
| --- | --- | --- | --- |
| **PR-1** | Active System Administrators | `AdministratorSetGuard::effectiveCount()` | `0` → `critical` (D-49a floor breached) · `1` → `attention` (sole-administrator lockout risk) · `≥2` → `healthy` |
| **PR-3** | Privileged people holding business-domain entitlements | assignments in `RoleCatalogue::requiringStepUp()` **and** a current `DomainEntitlement` | Any → **`attention`**, worded as *"an explicit permitted grant worth reviewing"* — **never** as a policy violation. Administration authority becoming business-data authority is the high-impact combination this unit exists to surface |
| **PR-6** | Incomplete grant paths | current entitlement with **no** current scope, or **no** current ceiling | Any → **`attention`**. It authorises nothing today, which is exactly why it persists unnoticed: it is incomplete **configuration** somebody believes is working |
| **PR-9a** | Step-up — SemantIQ's side | routes + `StepUpAction` catalogue + local configuration | Missing → `critical` |
| **PR-9b** | Step-up — the external half | — | **`unverified`** (see B-9b) |
| **PR-10** | **The inactive-account gate itself** | `AccessEngine` global gate present and reachable | Gate absent or bypassable → **`critical`**. This is the control that makes PR-5 below merely informational; if it fails, preserved assignments stop being harmless |

### 4.3 Informational metrics — count and context only, no state

| # | Metric | Derived from | What it says |
| --- | --- | --- | --- |
| **PR-2** | Organisation Administrators | current assignments, that role | **Count only.** No threshold is invented (**D-78**). A number is shown; no state is attached |
| **PR-4** | Restricted sensitivity grants | current `EntitlementCeiling` at `Restricted` | **Count, domain and role — elevated-access information.** Restricted is an **approved P1-05 capability protected by step-up**; a legitimate grant does not make a deployment amber. P1-07 decides whether a particular one is overdue |
| **PR-5** | Inactive people holding current assignments | `users.status = inactive` × current assignments | **Count, with the explanation:** *"Assignments preserved; the inactive account has no effective access."* This is **P1-05 behaving as designed** — P1-03 preserves relationships and the inactive-user global gate removes effective access. The real risk is the **gate** failing, which is **PR-10** and is a posture control |
| **PR-7** | Accountability versus entitlement | P1-04 `business_domain_owners` versus P1-05 entitlements | **Information, never a fault.** D-51: the two are independent and neither implies the other. Answered with *"owning a domain grants nothing"* |
| **PR-8** | Broad scopes | current scopes of type `Domain` / `Organisation` (`coversWholeDomain()`) | **Count only** until an approved threshold or a P1-07 review rule exists (**D-78**). Whole-domain scope is a legitimate, deliberate grant |

**No indicator reads `access_expectation`.** D-61 makes it context only and the
engine never reads it; a posture screen that did would be a second opinion about
access.

**PR-5 and PR-10 together are the shape of this whole section**: the legitimate
state is *shown*, and the *control that makes it safe* is what carries the
state. Marking the legitimate state amber would be inventing risk; leaving the
control unwatched would be the real gap.

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

> **An Exception is any APPLICABLE POSTURE CONTROL whose current state is not
> `healthy`.** It is a *view* of §3–§5, not a separate mechanism and not a
> separate store.

**Two things are therefore NOT unresolved Exceptions:**

| Not an exception | Where it goes instead |
| --- | --- |
| A **`not_applicable`** row | A clearly separated **"Not part of Release 1"** informational area on the same screen — visible, labelled, and never counted in the unresolved total |
| An **informational metric** (§4.3) | Its own panel, as a count with context. A legitimate Restricted grant is not an exception to anything |

Listing either among unresolved conditions would produce a list that can never
reach zero, which is how an exceptions screen becomes wallpaper.

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

1. **The recorded-events catalogue, in business language.** All 71 declared
   event types, read from `SecurityEventLogger::events()` **as source truth** so
   the coverage can never drift from reality — but **presented as categories and
   readable labels, not as raw dotted identifiers**.

   > **The catalogue is source truth; it is not display text.** A screen that
   > printed `access.step_up.refused` at an administrator would be exactly the
   > CLAUDE.md §4 failure — an internal key on a user-facing surface. DESIGN owns
   > the mapping from every declared key to a category and a readable label, and
   > §10 guards that **every** key has one, so a new event cannot appear raw.

   Proposed categories: *Sign-in · Administration changes · Access changes ·
   Privileged confirmations · Permanent deletions*.
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

### 10.1 The posture model, and the aggregation contract

| # | Case | Mutation it must catch |
| --- | --- | --- |
| N-SS1 | Each state renders with its business label | Rename a label to a code |
| N-SS2 | **All-`unverified` aggregates to `unverified`, never `healthy`** | Copy P1-02's "NotChecked contributes nothing" |
| N-SS3 | Aggregate is `healthy` **only** when every **applicable** control is `healthy` | Return `healthy` when merely no `critical` row exists |
| **N-SS3a** | **`healthy` + `not_applicable` → `healthy`** | Put `not_applicable` back into the precedence chain — a fully healthy deployment could then never be Healthy |
| **N-SS3b** | **`unverified` + `healthy` → `unverified`** | Let a healthy row outvote an unverified one |
| **N-SS3c** | **All-`not_applicable` → `not_applicable`** (not `healthy`, not `unverified`) | Return `healthy` for an empty applicable set |
| N-SS4 | Precedence holds for every adjacent **applicable** pair | Swap two states |
| N-SS5 | A throwing source yields `unverified` **and the row still renders** | Catch and skip the row |
| N-SS6 | Conflicting sources yield `attention` naming both | Prefer one silently |
| N-SS7 | **Every enumerated control has an evaluator** (architecture guard) | Add a control with no evaluator |
| N-SS8 | An unrecognised stored value yields `unverified` | `match` without a default |
| **N-SS8a** | **An applicable-but-unobservable control is `unverified`, never `not_applicable`** — encryption is the fixture | Reclassify it to write the control out of scope |

### 10.2 Posture controls versus informational metrics

| # | Case | Mutation it must catch |
| --- | --- | --- |
| N-SS9 | Sole administrator → `attention`; **two genuine administrators → `healthy`** | Report the count without a state |
| N-SS10 | Zero administrators → `critical` | Treat it as attention |
| N-SS11 | Privileged user **with** a business entitlement → `attention`; **without** → `healthy` | Drop the combination |
| N-SS12 | Incomplete entitlement (no scope, or no ceiling) → `attention`; complete → `healthy` | Ignore a missing ceiling |
| **N-SS12a** | **Step-up: local configuration present but external unobserved → `unverified`, never `healthy`** | Infer Entra registration from the local redirect URI |
| **N-SS12b** | **Step-up: route or catalogue missing → `critical`** | Collapse both halves into one row |
| **N-SS13** | **The inactive-account gate present → `healthy`; absent or bypassable → `critical`** (PR-10) | Remove the gate and rely on PR-5 to notice |
| **N-SS14** | **A legitimate Restricted grant does NOT turn posture amber** — aggregate stays `healthy` with a Restricted count present | Give PR-4 a state |
| **N-SS15** | **An inactive person holding preserved assignments does NOT turn posture amber while the gate holds** | Give PR-5 a state |
| **N-SS16** | **Count-only metrics cannot enter aggregation** — adding any number of PR-2/4/5/7/8 rows never changes the aggregate | Let an informational row contribute |
| **N-SS17** | **A count is never converted into `healthy`** — an informational row carries no state at all | Default a metric to healthy |
| N-SS18 | **Owner-without-entitlement is information, never a fault** (D-51) | Make it a finding |

### 10.3 Domains

| # | Case |
| --- | --- |
| N-SS19 | Disabled domain → *"nobody reaches its information"*, **not** a fault |
| N-SS20 | Enabled domain with no current owner → `attention` |
| N-SS21 | **A domain's posture never includes another domain's rows** |
| N-SS22 | No domain row implies data classification or Fabric security |

### 10.4 The hard security guarantees

| # | Case | Why it exists |
| --- | --- | --- |
| N-SS23 | **No secret, token, code, nonce, grant or client secret in rendered props, JSON or logs** | The unit's headline requirement |
| N-SS24 | **Every Security Status route is a `GET`**; no mutating route exists under it | "Cannot disable a mandatory control", enforced structurally |
| N-SS25 | An unauthorised request returns **no security metadata** — identical refusal whether the deployment is healthy or critical | A posture screen is an attacker's map |
| N-SS26 | Organisation Administrator sees platform rows **named but not valued**; System Administrator sees values | D-76, both halves |
| N-SS27 | Auditor may read; Auditor reaches no other administration screen | No silent broadening |
| N-SS28 | **P1-06 reads no log file** (architecture guard on file I/O) | The P1-08 boundary, structurally |
| N-SS29 | **P1-06 creates no table** | Ditto |
| **N-SS30** | **P1-06 emits NO security event — on every path, including the unrecognised-value path** | The §2.3 contradiction, now guarded rather than merely resolved in prose |
| **N-SS31** | **`SecurityEventLogger::events()` and `ALLOWED_KEYS` are unchanged by this unit** | No vocabulary is added by a reporting screen |
| N-SS32 | The events catalogue is read from `SecurityEventLogger::events()` and cannot drift | A hand-copied list goes stale |
| **N-SS33** | **Every declared event key maps to a category and a readable label** — and **no raw dotted identifier reaches a rendered surface** | D-79 / CLAUDE.md §4: internal keys are not display text |
| N-SS34 | **Exactly one posture evaluator** (architecture guard) | The accidental second security model |
| N-SS35 | No numeric score anywhere in props or copy | §2.4 |
| **N-SS36** | **`not_applicable` rows and informational metrics are excluded from the unresolved-exception count** | An exceptions list that can never reach zero |
| **N-SS37** | **Rendering Security Status triggers no outbound network call** | D-81: "live" must not mean probing Microsoft on every page view |


### 10.5 Empty and day-one states

Every one is a case, not a screenshot: nothing configured · no exceptions · no
privileged business access · sole administrator · evidence unavailable · healthy
populated · warning · critical.

Plus two the corrections added: **all controls `not_applicable`**, and **a
deployment whose only non-healthy rows are informational metrics**.

**`no exceptions` is the dangerous one** — it is the screen most likely to be
mistaken for "all clear". It must read *"No unresolved conditions. N applicable
controls reported, M not verified"*, and **N-SS2 is what stops it lying**, with
**N-SS36** stopping the opposite failure: a `not_applicable` row or an
informational count padding the unresolved total so it never reaches zero.

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

### 11.1 What "live" means — and what it does not

**D-81 proposes no P1-06 posture cache.** That needs one sentence of precision,
because the obvious misreading would be expensive and wrong:

> **"Live" means P1-06 recomputes posture from the CURRENT AUTHORITATIVE SOURCE
> STATE on every request. It does NOT mean Security Status launches Microsoft or
> network probes every time somebody opens the page.**

| P1-06 does | P1-06 does not |
| --- | --- |
| Read current stored state — assignments, entitlements, domains, users, routes | Trigger a live Entra probe to render a page |
| Ask P1-02 for its **current** health result and map `NotChecked` → `unverified` | Re-run P1-02's live check, or cache its answer |
| Hold no posture cache of its own | Override or duplicate a source unit's own caching |

**Source-specific behaviour and caching remain owned by the source unit.** If
P1-02 decides its live probe runs on demand and its result is held until re-run,
that is P1-02's contract and P1-06 consumes whatever it currently says. An
unrun probe is `unverified` — which is the honest answer and costs nothing.

**Cost, stated honestly:** expected Phase 1 volumes are small (tens of rows), and
**D-69 forbids a permission cache**, so a posture cache would be inconsistent as
well as risky — a cached posture is wrong at exactly the moment something has
just changed. If measurement later shows a real cost, that is a DESIGN concern
with evidence, not a PLAN assumption.

---

## 12. Delivery order

| Step | Content | Gate |
| --- | --- | --- |
| 1 | Posture model: five states, the **two-tier** contract, fail-closed, plus **N-SS1–N-SS8a** | Model correct before anything renders. **N-SS3a/3b/3c are the aggregation contract** |
| 2 | Secure Baseline evaluators **B-1…B-8, B-9a, B-9b** over existing sources | No new source invented. B-9b stays `unverified` |
| 3 | Privileged Access Health — **posture controls PR-1, PR-3, PR-6, PR-9a/b, PR-10 first**, then informational metrics PR-2, PR-4, PR-5, PR-7, PR-8 | State-bearing rows before count-only ones, so the split is built in rather than retrofitted |
| 4 | Domain posture | Cross-contamination guard first |
| 5 | Exceptions as a derived view | No persistence |
| 6 | Security Events — catalogue + redaction contract + limitation panel | P1-08 boundary guards |
| 7 | Screens, empty states, polish gate | Browser verification at both widths, both themes |
| 8 | Product Owner Test Script + verification record | CLAUDE.md §3 |

---

## 13. Product Owner decisions — DECIDED

**All seven were reviewed and accepted on 16 September 2026**, subject to the
seven corrections now folded into this document. They are recorded here as
settled, not as open questions.

| # | Decision | **Decided** | Notes from review |
| --- | --- | --- | --- |
| **D-75** | `unverified` contributes to the aggregate | **YES** | …**and `not_applicable` does not.** Applicable states are `critical > attention > unverified > healthy`; `not_applicable` is display only. All-N/A aggregates to N/A |
| **D-76** | Organisation Administrator / Auditor see platform rows **named but not valued** | **YES** | Grounded in the accepted P1-05 catalogue: System Administrator and Organisation Administrator hold `EvidenceRead`; Auditor holds **only** `EvidenceRead`. No role expansion |
| **D-77** | No persisted exception acknowledgement in P1-06 | **YES** | Derive only. Review lifecycle stays with P1-07 |
| **D-78** | No invented numeric thresholds | **YES** | Counts without thresholds are **informational and non-contributing** — PR-2 and PR-8 carry no state at all |
| **D-79** | Security Events = catalogue + redaction contract + explicit P1-08 limitation | **YES** | Never parse log files. **The catalogue is source truth, not display text** — business categories and readable labels, never raw dotted identifiers |
| **D-80** | Hosting / web exposure stays `unverified` | **YES** | Never false-green from an old deploy. **And `unverified`, not `not_applicable`** — the control applies; only the evidence is missing |
| **D-81** | Recompute posture per request, no P1-06 cache | **YES** | **"Live" = recompute from current authoritative source state.** It must **never** trigger an external Microsoft or network probe to render a page; source-specific behaviour and caching stay with the owning unit |

### 13.1 The seven corrections, and what each changed

| # | Correction | What it fixed |
| --- | --- | --- |
| **1** | `not_applicable` must not poison the aggregate | A real contradiction in the first draft: it sat **inside** the precedence chain while the contract also demanded every contributor be healthy, so a fully healthy deployment could never reach **Healthy**. Now display-only and non-contributing; N/A rows are also excluded from unresolved Exceptions |
| **2** | Applicable-but-unobservable is `unverified` | Encryption was wrongly `not_applicable`. It plainly applies — only the evidence is missing. `not_applicable` is now reserved for genuinely out-of-scope capabilities |
| **3** | Do not invent risk from legitimate P1-05 states | §4 is split into **posture controls** (state-bearing) and **informational metrics** (count only, non-contributing). PR-2, PR-4, PR-5, PR-8 moved to informational; PR-3 and PR-6 keep `attention`; **PR-10 added** — the inactive-account *gate* is the control worth watching, and its failure is `critical` |
| **4** | B-9 must not claim the unobservable | Split into **B-9a** (SemantIQ's side; missing → `critical`) and **B-9b** (external Entra acceptance → `unverified`). A configured local redirect URI never proves Microsoft registered it |
| **5** | Remove the new-event contradiction | §2.3 proposed an `access.state.unrecognised`-style event while §7 forbade any. Resolved for the **P1-08 boundary**: fail closed to `unverified`, record nothing, add no vocabulary. **N-SS30** and **N-SS31** guard it |
| **6** | "Live" must not mean probing on every page view | §11.1 states it explicitly, with a does/does-not table, and **N-SS37** asserts no outbound network call on render |
| **7** | Security Events must stay customer-readable | The catalogue remains source truth and log files remain forbidden, but **N-SS33** requires every declared key to map to a category and readable label, and forbids a raw dotted identifier on any rendered surface |

**Still not asked, because this plan decides them:** tab order, copy wording,
icon choice, row grouping, panel counts and the shape of the empty states.

---

## 14. Exit criteria

1. An administrator with no security background can read the four screens and say
   what is wrong and what to do next.
2. **Nothing reports `healthy` without evidence** — N-SS2, N-SS3, N-SS3b hold.
3. **A fully healthy deployment CAN reach Healthy** — N-SS3a, N-SS3c hold.
   Out-of-scope rows never hold the aggregate down.
4. **No approved P1-05 capability is painted as a fault** — N-SS14, N-SS15,
   N-SS16, N-SS17 hold.
5. No mandatory baseline control can be switched off from these screens —
   N-SS24 holds structurally.
6. No secret appears anywhere — N-SS23 holds.
7. The P1-08 boundary is intact: **no table, no event, no vocabulary change, no
   log reading** — N-SS28, N-SS29, N-SS30, N-SS31.
8. **No internal event key reaches a rendered surface** — N-SS33.
9. **Rendering the screen triggers no outbound network call** — N-SS37.
10. The P1-02 carried gate reads honestly as **not verified**, and step-up's
    external half with it — N-SS12a.
11. Product Owner Test Script complete, with everything not observable in
    production named rather than inferred.
