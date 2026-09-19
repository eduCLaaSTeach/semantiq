# P1-08 — Audit: PLAN

**PLAN ONLY.** No DESIGN, no implementation, no schema, no deployment.

| | |
| --- | --- |
| Unit | **P1-08 — Audit** (delivery order 10, the last Phase 1 unit) |
| Menu | System Administration → **Audit** — currently a **locked** node in `ApprovedMenu` |
| Categories | **User Access · Admin Changes · Security Events · Configuration Changes** |
| Purpose | Searchable, tamper-resistant evidence **appropriate to the viewer** |
| Exit | Core Phase 1 security and administrative activity is **evidenced** |
| Status | **AWAITING PRODUCT OWNER REVIEW** — D-95 to D-111 |

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

**Consequence the Product Owner must accept or overrule:** Audit can only
evidence activity **from the moment P1-08's storage exists**. Everything before
that is in rotating log files. See **D-109**.

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
- Export in any form, unless the Product Owner requires it (**D-110**).
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

**Open question inside D-98:** a sign-in *refusal* is both User Access and
Security Events. One row, one category, or one row visible under two?

### 4.5 Viewer permissions — **D-99**

`EvidenceRead` is held by all three of System Administrator, Organisation
Administrator and Auditor. Reaching the screen is therefore **not** the decision
— what each may **value** is, exactly as P1-06's projection already establishes.

Recommendation as a starting position:

| Viewer | Sees |
| --- | --- |
| **System Administrator** | Their organisation's evidence, all four categories |
| **Organisation Administrator** | Their organisation's evidence; **not** platform-scoped events |
| **Auditor** | **Read-only**, their organisation, all four categories, **no administration action of any kind** |

**The problem to decide:** many events carry **no `organisation_id`** — first-run
setup, every login refusal, engine failures. Under strict organisation scoping
they would be invisible to everyone. Three options in D-99.

**D-19 still applies:** the sidebar is shown to System Administrators only, so
an Auditor cannot currently *reach* any console screen. This is the **same
carried limitation** P1-07 recorded for domain owners and it must not be
silently closed by P1-08.

### 4.6 Field-level protection — **D-100**, **D-101**

Today **no network detail is captured at all** — `ip_address` and `user_agent`
are not in `ALLOWED_KEYS`. So there are two separate questions:

- **D-101 — should Audit capture them?** Capturing an IP address is capturing
  new personal data that this product does not hold today. Not capturing it is
  also a defensible Phase 1 position.
- **D-100 — if captured, who may read them?** Recommendation: **withheld by
  default**, System Administrator only, using P1-06's existing *withheld row*
  pattern — the field is visibly withheld rather than silently absent.

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

### 4.14 The write path and its failure mode — **D-110**, **D-111**

- **D-110 — where the durable write happens.** Recommendation: inside
  `SecurityEventLogger::record()`, in the same call, not through a queued
  listener. There are no queue workers on cPanel, and evidence that depends on a
  worker nobody runs is evidence that does not exist.
- **D-111 — what happens when the audit write fails.** Two honest answers, and
  the Product Owner must choose: *fail the operation* (strongest evidence,
  a full database is an outage) or *complete the operation and record the gap*
  (available, and the gap is itself visible). **Silently swallowing it is not an
  option** — that is an audit trail that lies by omission.

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

## 6. Product Owner decisions — **D-95 to D-111 — ALL OPEN**

| # | Decision | Recommendation |
| --- | --- | --- |
| **D-95** | Canonical audit source and model | `SecurityEventLogger` stays the one emit boundary; persistence added behind it |
| **D-96** | Is existing storage sufficient? | **No.** One dedicated append-only `audit_events` table |
| **D-97** | Tamper resistance | No update/delete path + per-row hash chain + honest statement of what it cannot prevent |
| **D-98** | Category mapping | Derived from `EventCatalogue`. **Open:** does a sign-in refusal appear under one category or two? |
| **D-99** | Viewer permissions | Organisation-scoped for all three roles. **Open:** who sees events that carry no organisation? |
| **D-100** | Field-level protection | Network detail withheld by default, System Administrator only, visibly withheld |
| **D-101** | Capture network detail at all? | **Open.** Capturing IP and user agent is new personal data this product does not hold today |
| **D-102** | Search model | Server-side; category, event, actor, subject, outcome, date range. **No free-text search** |
| **D-103** | Actor for failed/anonymous attempts | `actor_type` of person / external subject / system. **Never a fabricated user id** |
| **D-104** | Actor, action, target, outcome, timestamp | All five as first-class columns; server timestamp only |
| **D-105** | Retention | **None in Phase 1.** No purge path |
| **D-106** | Audit vs P1-06 Security Events | Both remain; P1-06 keeps the catalogue, Audit owns occurrences |
| **D-107** | Audit vs P1-07 review evidence | Audit shows the **events**; the review tables stay P1-07's |
| **D-108** | Refusal behaviour | Identical response whether or not the record exists |
| **D-109** | Backfill of pre-P1-08 history | **Do not backfill.** Partial history presented as complete is worse than an honest start date, and P1-06 already refused to parse log files |
| **D-110** | Export | **Not in Release 1** unless the Product Owner requires it. Nothing in the authority requires one |
| **D-111** | Audit write failure | **Open.** Fail the operation, or complete it and record the gap. Silence is not an option |

---

## 7. Carried gates — untouched by this plan

| Item | State |
| --- | --- |
| **P1-02 provider-wide SSO Re-check** | **OPEN / CARRIED / UNVERIFIED.** P1-08 does not own it, does not close it, and is not blocked by it |
| **P1-07 live-verification items** | **CARRIED**, unchanged |
| **D-19 — sidebar shown to System Administrators only** | **CARRIED.** An Auditor cannot reach any console screen today. P1-08 must not close this silently, and must not close it by widening the sidebar as a side effect |

---

## 8. What would make this plan wrong

- **If the Product Owner expects Audit to show history from before it ships.**
  It cannot, honestly (§1, D-109). This is the single most likely mismatch.
- **If "immutable" is expected to mean an operator cannot delete rows.** It
  cannot mean that on this hosting; it means alteration is **detectable**
  (D-97).
- **If Auditor is expected to use this screen at acceptance.** D-19 prevents it,
  and no account will be manufactured to make it observable.

---

## 9. Exit criteria

1. Every Phase 1 security and administrative event is **durably recorded**.
2. Audit is reachable, searchable and correct across the four categories.
3. Restricted fields are unavailable to unauthorised viewers, **observed on the
   rendered screen**, not inferred.
4. No second logger, no second access model, no duplicate review evidence.
5. Every guard proven non-vacuous, with its mutation recorded.
6. A Product Owner Test Script that states plainly what cannot be observed with
   real production data, and why.

---

## 10. Status

**PLAN ONLY — AWAITING PRODUCT OWNER REVIEW.**
No DESIGN. No implementation. No schema. No deployment.
D-95 to D-111 are open and none is assumed answered.
