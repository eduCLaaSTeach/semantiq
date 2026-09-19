# P1-08 — Audit: PLAN

**PLAN ONLY.** No DESIGN, no implementation, no schema, no deployment.

| | |
| --- | --- |
| Unit | **P1-08 — Audit** (delivery order 10). **P1-09 System Health and P1-10 Administration Home still follow** — P1-08 is not the last Phase 1 unit |
| Menu | System Administration → **Audit** — currently a **locked** node in `ApprovedMenu` |
| Categories | **User Access · Admin Changes · Security Events · Configuration Changes** |
| Purpose | Searchable, tamper-resistant evidence **appropriate to the viewer** |
| Exit | Core Phase 1 security and administrative activity is **evidenced** |
| Status | **PLAN APPROVED. D-95 to D-111 ANSWERED** — 19 September 2026, §6 |

---

## 0. The four sentences this unit serves

1. **Audit consumes evidence. It does not invent a second one.** Every event
   P1-00 – P1-07 already emits is the input; P1-08 adds no parallel logger, no
   parallel access model and no parallel notion of who an actor is.
2. **Evidence nobody can alter is the point.** Searchable is useful;
   append-only is the requirement.
3. **What a viewer may READ is narrower than what is recorded**, and narrower
   again for sensitive and network detail.
4. **A refusal leaks nothing** — not the existence of a record, not a count,
   not a category.

---

## 1. THE FINDING THAT SHAPES EVERYTHING — there is no durable evidence today

This is the first thing the Product Owner must know, and it is not a defect.

`SecurityEventLogger::record()` ends at **`Log::info()`**. Its own docblock has
said so since P1-00: *"No audit table — P1-08 owns durable storage and adopts
these events later."*

| Claim | Reality today |
| --- | --- |
| 77 events are **declared** | **True** — `SecurityEventLogger::EVENTS` |
| Context is **redacted by construction** | **True** — 15 `ALLOWED_KEYS`; a token, code, nonce or free-text note has nowhere to go |
| Events are **written somewhere durable** | **FALSE.** Laravel log files only |
| Events are **queryable** | **FALSE** |
| Events are **tamper-resistant** | **FALSE** — an ordinary file |
| Events are **complete** | **FALSE** — rotation, and nothing guarantees a write |
| P1-06 Security Events shows **history** | **FALSE.** It shows the **catalogue** — what is recorded and how it is protected. `SecurityEventsController` reads **no file**, and `N-SS28` asserts it |

**RULED (D-109).** Audit evidences activity **from the moment P1-08's storage
exists**, and does not backfill. The Audit screen **must state the actual
evidence start date and time** so nobody mistakes it for complete earlier
history.

---

## 2. What already exists that P1-08 must consume

| Source | What it gives | What it does NOT give |
| --- | --- | --- |
| `SecurityEventLogger` | The **one** event vocabulary: 77 keys, 15 permitted context keys, hard failure on an unknown key | Durability, query, ordering guarantees |
| `Security\Catalogue\EventCatalogue` | Every key mapped to a **category and a business label**, asserted complete | Occurrences |
| P1-06 Security Status | Posture, exceptions, privileged access, and the **viewer projection** that already decides what each viewer may see | A history |
| P1-07 review evidence | `access_review_cycles` / `access_review_items` — **durable, queryable review decisions with actor, subject, basis and timestamp** | A general audit surface |
| Authentication | `auth.login.*`, `auth.logout`, `auth.session.expired` — including **four distinct refusal reasons** | A durable record |
| `RoleCatalogue` / `ActionClass` | `EvidenceRead`, held by System Administrator, Organisation Administrator and Auditor | Field-level rules |
| `sessions` table | `ip_address`, `user_agent` per session | Any link to an event |

**Nothing in that list is to be duplicated.**

---

## 3. Scope of Release 1

**In scope**

- One durable evidence store, written from the **existing** logger boundary.
- One Audit feature with **four tabs**, matching the approved categories.
- Search, filter and pagination over that store.
- Permission-controlled access, organisation-scoped, with **field-level**
  protection for sensitive and network detail.
- The `Audit` menu node changes from **locked** to **delivered**.

**Explicitly out of scope**

- A second event logger, a second access model, a second review evidence store.
- **P1-09 System Health.**
- **Export in any form (D-110 — ruled out of Release 1).**
- Business-record audit payloads — *what* a record said is never audit content.
- Secrets, tokens, passwords, PKCE verifiers, bootstrap grants. The existing
  `ALLOWED_KEYS` guard already makes these unrepresentable, and it stays.
- Backfill of pre-P1-08 history (**D-109**).
- Any change to P1-02's carried SSO re-check.

---

## 4. The planning areas

### 4.1 Canonical source and model — **D-95**

Recommendation: **`SecurityEventLogger` remains the single emit boundary**, and
P1-08 adds durable persistence **behind it**. Every existing call site keeps
working unchanged; no unit learns about Audit; the `ALLOWED_KEYS` guard keeps
protecting the durable store for free.

The alternative — each module writing its own audit row — creates 77 new call
sites and a second vocabulary that drifts.

### 4.2 Storage — **D-96**

Log files are not sufficient (§1). Recommendation: **one dedicated append-only
table**, `audit_events`, holding exactly the existing permitted context plus
`category`, `occurred_at` and a sequence.

**No other table is altered.** P1-07 is the precedent: two new tables, nothing
touched.

### 4.3 Tamper resistance — **D-97**

"Immutable" must mean something checkable. Recommendation, three layers:

1. **No route, controller, service or model method that updates or deletes an
   audit row.** A lifecycle-completeness test asserts the absence as an
   equality, the way P1-01 asserts its four `DELETE` routes.
2. **A per-row hash chained to its predecessor**, so a row altered directly in
   the database breaks the chain and a verification read says so.
3. **The application's database user is not the mechanism.** On shared cPanel
   hosting we do not control grants; claiming a privilege we cannot verify would
   be exactly the kind of unearned assurance §6 forbids.

**What this does NOT claim:** it does not prevent somebody with database or SSH
access from deleting rows. It makes alteration **detectable**, and that limit
gets stated on the screen.

### 4.4 Category mapping — **D-98**

Four approved categories; `EventCatalogue` already groups all 77 keys into
finer business groups. Recommendation: map the existing groups onto the four,
**derived from the catalogue rather than listed a second time**, so an event
added later is categorised by construction.

| Category | Existing catalogue groups |
| --- | --- |
| **User Access** | Sign-in; first-run setup; user and group lifecycle; role, entitlement, scope and ceiling changes; step-up; access reviews |
| **Admin Changes** | Organisation structure changes; business domain changes |
| **Security Events** | Sign-in refusals; step-up refusals; review refusals; engine and state failures |
| **Configuration Changes** | Sign-in configuration (identity health) |

**RULED (D-98):** **exactly one canonical category per occurrence.** A
successful sign-in is **User Access**; a sign-in **refusal** is **Security
Events**. One occurrence is never duplicated into two categories — a count that
depends on which tab you are standing on is not evidence.

### 4.5 Viewer permissions — **D-99**

`EvidenceRead` is held by all three of System Administrator, Organisation
Administrator and Auditor. Reaching the screen is therefore **not** the decision
— what each may **value** is, exactly as P1-06's projection already establishes.

**RULED (D-99):**

| Viewer | Sees |
| --- | --- |
| **System Administrator** | Authorised organisation evidence **plus platform and no-organisation evidence** |
| **Organisation Administrator** | **Own organisation only** |
| **Auditor** | **Read-only, own organisation only.** No administration action of any kind |

This answers the problem the plan raised: many events carry **no
`organisation_id`** — first-run setup, every login refusal, engine failures.
They are **System Administrator only**, so they are neither invisible to
everyone nor visible to the wrong viewer.

**D-19 still applies:** the sidebar is shown to System Administrators only, so
an Auditor cannot currently *reach* any console screen. This is the **same
carried limitation** P1-07 recorded for domain owners and it must not be
silently closed by P1-08.

### 4.6 Field-level protection — **D-100**, **D-101**

Today **no network detail is captured at all** — `ip_address` and `user_agent`
are not in `ALLOWED_KEYS`. So there are two separate questions:

- **D-101 — should Audit capture them? ANSWERED: NO.** The `sessions` table
  already holds both, as §2 records. What Audit would add is **durable
  evidence**: *Audit does not currently persist those values as durable
  evidence*, and Phase 1 will not start. Copying session storage into permanent
  audit history would introduce **indefinite network and personal-data
  retention** — which, with D-105's no-purge ruling, means forever.
- **D-100 — field-level protection. ANSWERED.** Sensitive technical identity
  fields are **independently projected and redacted**. An Organisation
  Administrator and an Auditor never receive platform-sensitive identifiers, and
  **secrets are never stored** — the existing `ALLOWED_KEYS` guard already makes
  that unrepresentable. Where a field is withheld it is **visibly withheld**,
  using P1-06's existing *withheld row* pattern, rather than silently absent.

### 4.7 Search, filter, pagination — **D-102**

Recommendation: server-side only, one indexed query, filters on **category,
event, actor, subject, outcome and date range**, with the same pagination shape
P1-07 uses. **No free-text search over context**, because there is no free text
to search — and adding one would be the leak channel P1-06 refused.

### 4.8 Actor for failed and anonymous attempts — **D-103**

A refused login has **no user id** — that is the point of refusing it. It
carries `provider`, `subject` and `tenant`: a directory identifier, not a
SemantIQ person.

Recommendation: record `actor_type` as one of **person / external subject /
system**, never a fabricated user id, and never a guessed one. An unknown actor
that renders as "Unknown" is honest; an unknown actor rendered as somebody's
name is evidence that lies.

### 4.9 Actor, action, target, outcome, timestamp — **D-104**

The required five. Four are already present (`user_id`/`related_id`, the event
key, `entity_type`/`entity_id`, `result`, and the logger's `at`).

Recommendation: the durable row carries all five as **first-class columns**, and
the timestamp is the **server's**, recorded at write, never client-supplied.

### 4.10 Retention — **D-105**

Nothing in the blueprint states a retention period, and *"audit cannot be
disabled by ordinary administrators"* argues against a delete path.
Recommendation: **Phase 1 has no purge and no retention job.** If the Product
Owner requires one, it is an approved operator procedure and not an application
feature.

### 4.11 Audit and P1-06 Security Events — **D-106**

They answer different questions and both must remain.

| | Answers |
| --- | --- |
| **P1-06 Security Events** | *What is recorded, and how is it protected?* — the catalogue and the redaction contract |
| **P1-08 Audit** | *What actually happened?* — occurrences |

Recommendation: P1-06 keeps the catalogue; its *"this is not a history"*
limitation panel is **updated, not removed**, to point at Audit. Audit does not
re-render the catalogue.

### 4.12 Audit and P1-07 review evidence — **D-107**

P1-07 already stores durable review decisions in its own two tables, **and**
emits six event keys.

Recommendation: Audit shows the **events**, and does not read, copy or
re-project the review tables. Review decisions stay owned by P1-07; a second
projection of them would be a second interpretation of a review.

### 4.13 Refusal behaviour — **D-108**

Recommendation: an unauthorised viewer gets the **same** answer for a record
that exists and one that does not — no count, no category list, no "you may not
see this one". P1-07's directory-enumeration finding is the precedent: the test
asserts the two responses are **identical**, not that a particular status code
was returned.

### 4.14 The write path and its failure mode — **D-111**

**Where the durable write happens is part of D-95 and D-96**, not a decision of
its own: the persistence sits **behind `SecurityEventLogger::record()`**, in the
same synchronous call, never through a queued listener. There are no queue
workers on cPanel, and evidence that depends on a worker nobody runs is evidence
that does not exist. **D-110 is Export and nothing else.**

**D-111 — what happens when the audit write fails. ANSWERED: FAIL CLOSED.**

An audited security or administrative operation must **not silently complete
without evidence**.

| Situation | Behaviour |
| --- | --- |
| A state-changing security or admin action cannot be evidenced | **The action fails** |
| A successful authentication cannot be evidenced | **It is not treated as successfully completed** |
| An **already-refused** authentication cannot be evidenced | **It remains refused.** Failing closed never turns a refusal into anything else |
| Operational diagnostics | `Log::critical` **may** be used as a fallback for operators, and is **not accepted as audit evidence** |

**This is the hardest thing in the unit and DESIGN owns it.** DESIGN must define
the transaction and ordering boundaries so that the product never reports a
failure **after** an irreversible business or security change has already
committed. An audit trail that lies by omission and a screen that lies about
what happened are the same defect in two places.

---

## 5. Minimum tests, as required

| # | Case |
| --- | --- |
| A1 | A **successful** login produces durable evidence with the right actor and timestamp |
| A2 | A **failed** login produces durable evidence, with **no fabricated actor**, for each of the four refusal reasons |
| A3 | A **privileged change** — role assigned, role revoked, entitlement granted — produces evidence naming actor, target and outcome |
| A4 | A **denied action** where policy requires it produces evidence; an ordinary business denial does **not** (D-71 — volume buries what matters) |
| A5 | **Actor identity correctness**: the actor recorded is the person who acted, never the subject acted upon. The `user_id` = subject / `related_id` = actor convention is exactly the one an implementer gets backwards |
| A6 | **Restricted fields are unavailable** to an unauthorised viewer — asserted on the **rendered payload**, not on the session |
| A7 | An audit row **cannot be updated or deleted** through any application path |
| A8 | A row altered directly in the database **breaks the chain** and is reported |
| A9 | Category assignment is **derived**; an event added to the catalogue is categorised without a second edit |
| A10 | An unauthorised viewer's refusal is **byte-identical** for an existing and a non-existent record |

**Every one of these will be broken deliberately and observed to fail**
(CLAUDE.md §2). A6 and A10 are the two most likely to pass vacuously.

---

## 6. Product Owner decisions — **D-95 to D-111 — ALL ANSWERED**

**Approved 19 September 2026.** Where a ruling differs from the recommendation
the plan offered, the **ruling** is what DESIGN implements.

| # | Decision | **RULING** |
| --- | --- | --- |
| **D-95** | Canonical audit source and model | **APPROVED.** `SecurityEventLogger` remains the **single** emit boundary. Durable persistence sits behind it, synchronously. **No second logger** |
| **D-96** | Is existing storage sufficient? | **APPROVED — no.** One dedicated **append-only `audit_events`** table |
| **D-97** | Tamper resistance | **APPROVED.** No application update or delete path, **plus** hash-chain tamper detection, **plus** an explicit statement that hosting and database operators **cannot be technically prevented** from deleting rows |
| **D-98** | Category mapping | **APPROVED.** Exactly **one canonical category per occurrence**, derived from `EventCatalogue`. Successful sign-in → **User Access**. Sign-in refusal → **Security Events**. **One occurrence is never duplicated into two categories** |
| **D-99** | Viewer permissions | **APPROVED.** System Administrator: authorised organisation evidence **plus** platform / no-organisation evidence. Organisation Administrator: **own organisation only**. Auditor: **read-only, own organisation only**. Platform and no-organisation events are **System Administrator only** |
| **D-100** | Field-level protection | **APPROVED.** Sensitive technical identity fields are **independently projected and redacted**. Organisation Administrator and Auditor **never** receive platform-sensitive identifiers. **Secrets are never stored** |
| **D-101** | Capture network detail at all? | **APPROVED — DO NOT.** IP address and user agent are **not persisted into Audit in Phase 1**. Existing session storage is **not copied** into permanent audit history. Audit does not currently persist those values as durable evidence, and Phase 1 will not begin — avoiding indefinite network and personal-data retention |
| **D-102** | Search model | **APPROVED.** Server-side filtering by category, event, actor, subject, outcome and date range, with pagination. **No free-text context search** |
| **D-103** | Actor for failed/anonymous attempts | **APPROVED.** `actor_type` of **person / external subject / system**. **Never fabricate a SemantIQ user** for an unknown or failed sign-in actor |
| **D-104** | Actor, action, target, outcome, timestamp | **APPROVED.** All five are **first-class durable fields**; the timestamp is the **server's** |
| **D-105** | Retention | **APPROVED.** **No application purge or retention job in Phase 1** |
| **D-106** | Audit vs P1-06 Security Events | **APPROVED.** P1-06 remains the **catalogue**; P1-08 owns **actual occurrences**. P1-06 may point users to Audit but **does not become a history** |
| **D-107** | Audit vs P1-07 review evidence | **APPROVED.** Audit consumes P1-07's **emitted events only**. It does **not** re-read or reinterpret the review tables |
| **D-108** | Refusal behaviour | **APPROVED.** An unauthorised viewer's response for an existing and a non-existent record must be **indistinguishable**, leaking **no counts and no category existence** |
| **D-109** | Backfill of pre-P1-08 history | **APPROVED — no backfill** from rotating log files. The Audit screen **must state the actual evidence start date and time**, so nobody mistakes it for complete earlier history |
| **D-110** | Export | **APPROVED — no Audit export in Release 1.** D-110 is Export and nothing else |
| **D-111** | Audit write failure | **APPROVED — FAIL CLOSED.** §4.14 carries the four cases. `Log::critical` is operational diagnostics, **never** audit evidence. DESIGN owns the transaction and ordering boundaries |

---

## 7. Carried gates — untouched by this plan

| Item | State |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED.** P1-08 does not own it, does not close it, and is not blocked by it |
| **P1-07 live-verification items** | **CARRIED**, unchanged |
| **D-19 — sidebar shown to System Administrators only** | **CARRIED, and D-19 is NOT changed by P1-08.** Auditor and Organisation Administrator **route-level** evidence permissions may be implemented and tested; the System-Administrator-only **sidebar** limitation stays carried unless separately authorised. **The System Administration navigation is not widened as a side effect of Audit** |

---

## 8. What would have made this plan wrong — now settled

| Risk | Settled by |
| --- | --- |
| Expecting Audit to show history from before it ships | **D-109.** It cannot, honestly, and the screen must say so |
| Expecting "immutable" to mean an operator cannot delete rows | **D-97.** On this hosting it means alteration is **detectable**, and the plan says that plainly rather than implying more |
| Expecting an Auditor to use this screen at acceptance | **D-19, carried.** Route-level permissions are implemented and tested; the sidebar is not widened, and **no account is manufactured** to make it observable |

**The one that remains live is D-111.** Failing closed is correct and it is the
hardest thing to implement correctly: the product must never report a failure
**after** an irreversible change has committed. DESIGN owns that ordering.

---

## 9. Exit criteria

1. Every Phase 1 security and administrative event is **durably recorded**.
2. Audit is reachable, searchable and correct across the four categories, and
   **states its evidence start date** (D-109).
3. Restricted fields are unavailable to unauthorised viewers, **observed on the
   rendered screen**, not inferred.
4. No second logger, no second access model, no duplicate review evidence.
5. Every guard proven non-vacuous, with its mutation recorded.
6. A Product Owner Test Script that states plainly what cannot be observed with
   real production data, and why.

---

## 10. Status

**PLAN APPROVED — 19 September 2026. D-95 to D-111 ANSWERED.**
No implementation, no schema and no deployment were produced by this plan.
**DESIGN is the next gate**, and it owns the D-111 ordering boundaries.

**P1-08 is not the last Phase 1 unit** — P1-09 System Health and P1-10
Administration Home follow, and neither is started.
**P1-02 remains OPEN / CARRIED / UNVERIFIED.** **P1-07's carried items remain
carried.** **D-19 is unchanged.**
