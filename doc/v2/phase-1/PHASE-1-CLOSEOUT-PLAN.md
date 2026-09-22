# Phase 1 Closeout — PLAN

**GATE A. PLAN ONLY.** No implementation, no production change, no deployment,
no Phase 2.

| | |
| --- | --- |
| Baseline | `main` = **`bb03958c57eaa882a3fe2e21899e62b71818f6dc`** |
| Delivery units | **All 13 Product Owner accepted.** P1-11 closed 22 September 2026 |
| Canonical unresolved register | **13 items, CL-01 … CL-13** — §3 |
| Discrepancies found and reconciled | **Six** — §2 |
| **Product Owner rulings** | **Recorded 22 September 2026 — every one of the 13 items now has a disposition, §3A** |
| Status | **Gate A, amendment round 1. Awaiting approval of the amended PLAN** |

> **THE NUMBER 11 WAS NOT COPIED, AND IT WAS WRONG.** The Product Owner was
> right to refuse it. The register below is derived from the source records, and
> reconciling them found **two items that had gone missing**, **one whose scope
> is understated by nearly half**, and **one whose owning unit never recorded
> it at all**. §2 shows the evidence for each.

---

## 1. What this document is

**Phase 1 is not finished when the last unit ships.** `PHASE-1-PLAN.md` §7 has
said so since 21 September:

> Final Phase 1 acceptance additionally requires **explicit disposition** — a
> decision recorded, not an omission — of every row in §10.

This plan produces that register, proposes how each item is disposed of, and
asks for the decisions that are the Product Owner's rather than mine.

**It changes nothing.** No application code, no schema, no production
configuration, no deployment. The only repository change is this file.

---

## 2. Reconciliation — what the source records actually say

**The Product Owner's instruction was to derive the inventory from the sources
rather than trust a number. Doing that found six discrepancies.** Each is shown
with its evidence, and none is silently corrected: every proposed disposition is
put to the Product Owner in §6.

### D-1 — "Eleven carried items" is a miscount, and the artefact is visible

`PHASE-1-PLAN.md` contains **eleven** lines matching `OPEN / CARRIED`. Counting
those lines produces "eleven". But **line 711 is not an item** — it is an
instruction inside the P1-02 revisit table:

> | The prerequisite still does not exist | Keep it **OPEN / CARRIED / UNVERIFIED** and bring it to the Product Owner **explicitly** … |

**A register counted by grep is not a register.** Ten of those eleven lines are
items; the eleventh is a rule about one of them. This is exactly the failure
`PHASE-1-PLAN.md` §10 warns about in its own opening — *"this register is only
useful if it stays true"*.

### D-2 — Two real carried items were dropped from the acceptance records

`PHASE-1-PLAN.md` §10 carries **eight** OPEN P1-10 rows. The
**P1-10 acceptance record §5** lists **nine** items — but they are not the same
nine. Comparing them:

| Item | Plan §10 | P1-10 acceptance §5 | P1-11 acceptance §7 |
| --- | --- | --- | --- |
| Production **Entra cutover**, `env` → store | **OPEN** | **ABSENT** | **ABSENT** |
| Console screens opened on production **by the delivery team** | **OPEN** | **ABSENT** | **ABSENT** |

Both are genuinely open. The P1-10 acceptance record's own §3 states
*"**Entra cutover** — NOT PERFORMED"* on the same page that omits it from the
carried list.

**The defect originated in the P1-10 acceptance record on 21 September, and the
P1-11 acceptance record inherited its shape verbatim on 22 September — including
the omission.** I wrote the second one. It is a documentation error of mine, and
it is the reason two live obligations were one merge away from vanishing.

### D-3 — Two registers exist, and they do not reference each other

P1-11's two Gate C items — the Organisation Administrator navigation gap and the
P1-06 performance finding — are recorded in `PHASE-1-PLAN.md` **§7**, under
*"And P1-11 adds two of its own"*. They are **not** in the **§10** carried
register:

```
sed -n '/^## 10\. Carried verification gates/,/^## 11\./p' PHASE-1-PLAN.md \
  | grep -ciE "four System Administration|per-domain evaluation"    →  0
```

So "the carried register" means different things in different sections. **Two
partial registers are how the two items in D-2 got lost.**

### D-4 — The navigation gap is understated: it is SEVEN screens, not four

Every document says *"the four System Administration screens an Organisation
Administrator can reach and still cannot see"*, naming Organisation, Users &
Groups, Roles & Access and Business Domains.

**Three sources in the code disagree, and they agree with each other:**

`app/Modules/Access/Support/RoleCatalogue.php:46` — an Organisation
Administrator holds **three** action classes:

```php
RoleCode::OrganisationAdministrator->value => [
    ActionClass::OrgAdmin,
    ActionClass::AccessAdmin,
    ActionClass::EvidenceRead,
],
```

Cross-referencing `routes/web.php` and `app/Shared/Navigation/ApprovedMenu.php`:

| System Administration node | Policy | Class required | OrgAdmin authorised? | Visible? |
| --- | --- | --- | --- | --- |
| Administration Home | `administration.view` | OrgAdmin | **yes** | **yes** (D-182) |
| Organisation | `organisation.view` | OrgAdmin | **yes** | no |
| Users & Groups | `people.view` | OrgAdmin | **yes** | no |
| Roles & Access | `access.view` | **AccessAdmin** | **yes** | no |
| Business Domains | `domains.view` | OrgAdmin | **yes** | no |
| **Security Status** | `security.view` | **EvidenceRead** | **yes** | **no — not in the "four"** |
| **Access Reviews** | `access-reviews.view` | **AccessAdmin / EvidenceRead** | **yes** | **no — not in the "four"** |
| **Audit** | `audit.view` | **EvidenceRead** | **yes** | **no — not in the "four"** |
| Identity & SSO | `identity.view` | PlatformAdmin | no | no |
| System Health | `system-health.view` | PlatformAdmin | no | no |
| Integrations | `integrations.view` | PlatformAdmin | no | no |

**An Organisation Administrator is authorised for eight of the eleven System
Administration nodes, sees one, and cannot see seven.** The "four" counted only
the `OrgAdmin` class and missed the two classes the role also holds.

**This enlarges CL-12 materially** — it is not four product screens but seven,
three of which are security and evidence surfaces (Security Status, Access
Reviews, Audit) where the visibility question carries more weight than it does
for Organisation or Business Domains.

**Derived from code, not observed live.** It is consistent with the P1-11
navigation test, which asserts an Organisation Administrator's node set equals
exactly `['Administration Home']`, and with Check 6 of the Gate D script, which
the Product Owner passed.

> **SUPERSEDED.** This paragraph originally ended *"CL-12 proposes confirming the
> route-level half live before any decision is taken."* **The Product Owner
> independently verified this finding and has since ruled** — all seven
> destinations are to be made discoverable. The live confirmation is no longer a
> precondition of the decision; it is **post-deployment verification evidence for
> CL-12**, listed in that item's *Live evidence required* row.

### D-5 — P1-06's own records never recorded the P1-06 finding

The `PostureEvaluator` / `DomainAdapter` per-domain query cost appears in
`P1-11-*` records and in `PHASE-1-PLAN.md` §7. It does **not** appear in
`P1-06-SECURITY-STATUS-VERIFICATION.md`, which mentions per-domain correlated
subqueries only as a **MySQL correctness** concern:

> depends on correlated subqueries resolving per domain — exactly the shape that
> silently returns the wrong rows when an engine plans it differently

**That is a different claim about the same code.** The finding was discovered by
P1-11 while measuring its own budget, and the owning unit's record has never
carried it. CL-13 proposes recording it against P1-06 as part of closeout, so
the finding lives with the code that causes it.

### D-6 — ID collision in the requested numbering

The Product Owner's brief uses `CL-01` both as an **item ID** (*"ID, e.g.
`CL-01`"*) and as a **workstream ID** (*"CL-01 — Session Security & Revocation"*),
while `CL-00`…`CL-10` name ten workstreams and the register holds thirteen items.
**Two different things sharing one identifier is how D-2 happened.**

**Proposed, for Product Owner decision:** register items keep **`CL-01`…`CL-13`**;
workstreams are renamed **`WS-0`…`WS-11`**. The mapping is in §5. Nothing is
dropped — only disambiguated.

> **AMENDED — Product Owner review, Gate A.** An earlier draft of this document
> wrote the range as `WS-0…WS-10` here and in §5 while the execution table
> already carried **`WS-11`** for the transition baseline. **The authoritative
> range is `WS-0`…`WS-11`**, and the shorter form is superseded wherever it
> appeared. Items remain **`CL-01`…`CL-13`**.

---

## 3. The canonical register — CL-01 to CL-13

**Thirteen items. Derived from the source records, not from a count.**

Every item carries the same fields. `PO?` marks the ones that cannot be resolved
without a Product Owner decision.

---

### CL-01 — Provider-wide SSO Re-check with a genuine second permanent System Administrator

| | |
| --- | --- |
| **Originating unit** | **P1-02** (accepted 2 Sep 2026) |
| **Requirement** | Observe the provider-wide Re-check limit with **two** genuine administrators |
| **Authoritative source** | `PHASE-1-PLAN.md` §10 row 3; reassigned to Phase 1 orchestration by Product Owner decision 18 Sep 2026 |
| **Current production state** | One permanent System Administrator exists. The prerequisite does not |
| **Why still open** | The second administrator must arise **in normal operation**. It was carried to P1-05, then P1-06; both closed and it still does not exist |
| **Code required** | No |
| **Schema required** | No |
| **Production config change** | No |
| **Deployment required** | No |
| **Prerequisite** | A genuine second **permanent** System Administrator |
| **Risk** | Low technically. The risk is procedural: manufacturing the prerequisite to close a row would prove nothing |
| **Automated evidence available** | Full. Every refusal path is covered |
| **Live evidence still required** | The two-administrator round trip |
| **Proposed method** | Execute at the first genuine opportunity. **Do not create, promote or borrow an account for it** |
| **PO decision?** | **RULED** — see below |
| **Proposed final status** | `CLOSED — VERIFIED` if executed, else **`UNVERIFIED — PREREQUISITE UNAVAILABLE`** |
| **Category** | Prerequisite currently unavailable |

> **PRODUCT OWNER RULING — do not create or promote an account for testing.** If a
> genuine second permanent System Administrator still does not exist at **WS-10**,
> the disposition is **`UNVERIFIED — PREREQUISITE UNAVAILABLE`**.

### CL-02 — Microsoft privileged step-up, genuine live observation

| | |
| --- | --- |
| **Originating unit** | **P1-10** (accepted 21 Sep 2026) |
| **Requirement** | A real completed Microsoft step-up for a privileged configuration change |
| **Authoritative source** | `PHASE-1-PLAN.md` §10, P1-10 carried row 1; `P1-10-…-ACCEPTANCE.md` §5 row 2 |
| **Current production state** | Never exercised live. Redirect, staging, single-use consumption, candidate verification and every refusal path are covered automatically |
| **Why still open** | It needs a **real privileged change** to hang off. None has been made |
| **Code required** | No |
| **Schema required** | No |
| **Production config change** | No — but its natural vehicle (CL-08) is one |
| **Deployment required** | No |
| **Prerequisite** | A genuine privileged configuration change to perform |
| **Risk** | Low on its own; inherits CL-08's risk if coupled |
| **Automated evidence available** | Extensive |
| **Live evidence still required** | One completed round trip |
| **Proposed method** | **Couple to CL-08.** The Entra cutover *is* a privileged configuration change, so performing it produces this observation as a by-product. **One controlled change, two items closed** |
| **PO decision?** | **RULED** — see below. **The coupling proposal is withdrawn** |
| **Proposed final status** | **`UNVERIFIED — PREREQUISITE UNAVAILABLE`** unless a genuine privileged change arises |
| **Category** | Live verification only |

> **PRODUCT OWNER RULING — the coupling to CL-08 does not happen, because CL-08 is
> deferred.** **Do not manufacture another privileged configuration change solely
> to exercise step-up.** The automated evidence stands and is kept.
>
> If a **genuine** privileged production change arises naturally during closeout,
> perform the live observation then. Otherwise the disposition at WS-10 is
> **`UNVERIFIED — PREREQUISITE UNAVAILABLE`**.
>
> **The §5 proposal to close CL-02 as a by-product of the cutover is SUPERSEDED.**

### CL-03 — Bootstrap First-Run, end to end

| | |
| --- | --- |
| **Originating unit** | **P1-10** |
| **Requirement** | First-Run observed on a genuine fresh or test installation |
| **Authoritative source** | `PHASE-1-PLAN.md` §10, P1-10 carried row 2 |
| **Current production state** | **Closed and correctly so** — `/first-run/sign-in` → 302 → `/first-run/closed`; no Bootstrap Administrator has ever existed |
| **Why still open** | First-Run exists only while a deployment has **no** System Administrator. Production has one, so seeing it live would mean deactivating every administrator on a running system |
| **Code required** | No |
| **Schema required** | No |
| **Production config change** | **No — and explicitly must not** |
| **Deployment required** | A separate installation, not this one |
| **Prerequisite** | A genuine fresh or test installation |
| **Risk** | **HIGH if attempted on production.** This item must never be closed against production |
| **Automated evidence available** | B1–B15 |
| **Live evidence still required** | The flow on a fresh installation |
| **Proposed method** | Stand up a genuine test installation, or accept as unverified |
| **PO decision?** | **RULED** — see below |
| **Proposed final status** | `CLOSED — VERIFIED` only if a genuine installation arises, else **`UNVERIFIED — PREREQUISITE UNAVAILABLE`** |
| **Category** | Prerequisite currently unavailable |

> **PRODUCT OWNER RULING — covering CL-03, CL-04, CL-05 and CL-06.**
> **Do not create a fresh installation solely to obtain green closeout evidence.**
> If a genuine fresh or test installation becomes available naturally during
> closeout, execute all four validations against it. Otherwise all four are
> **`UNVERIFIED — PREREQUISITE UNAVAILABLE`** at WS-10.
>
> **Never damage production to make Bootstrap observable.**

### CL-04 — Bootstrap 30-minute idle expiry, live

| | |
| --- | --- |
| **Originating unit** | **P1-10** · **Source** `PHASE-1-PLAN.md` §10 row 3 · **Decision** D-165 |
| **Current production state** | No Bootstrap session has ever existed to expire |
| **Why still open / prerequisite / risk** | Same as CL-03 — requires a fresh installation with an active Bootstrap session. Not attemptable on production |
| **Code / schema / config / deployment** | No · No · No · Separate installation |
| **Automated evidence** | Covered | **Live evidence required** | One observed idle expiry |
| **Proposed method** | Bundle with CL-03 on the same test installation |
| **PO decision?** | Inherits CL-03's |
| **Proposed final status** | `CLOSED — VERIFIED`, else `UNVERIFIED — PREREQUISITE UNAVAILABLE` |
| **Category** | Prerequisite currently unavailable |

### CL-05 — Bootstrap 4-hour absolute expiry, live

| | |
| --- | --- |
| **Originating unit** | **P1-10** · **Source** `PHASE-1-PLAN.md` §10 row 4 · **Decision** D-165 |
| **Current production state / why open** | As CL-04. Additionally needs a **four-hour** observation window |
| **Code / schema / config / deployment** | No · No · No · Separate installation |
| **Proposed method** | Bundle with CL-03 and CL-04. Note the wall-clock cost when scheduling |
| **PO decision?** | Inherits CL-03's |
| **Proposed final status** | `CLOSED — VERIFIED`, else `UNVERIFIED — PREREQUISITE UNAVAILABLE` |
| **Category** | Prerequisite currently unavailable |

### CL-06 — Bootstrap recovery flow, live

| | |
| --- | --- |
| **Originating unit** | **P1-10** · **Source** `PHASE-1-PLAN.md` §10 row 5 |
| **Current production state** | **0 recovery tokens, 0 live.** None has ever been issued |
| **Why still open** | Needs a Bootstrap installation in a recoverable state |
| **Code / schema / config / deployment** | No · No · **No — issuing a recovery token on production is explicitly forbidden** · Separate installation |
| **Proposed method** | Bundle with CL-03 |
| **PO decision?** | Inherits CL-03's |
| **Proposed final status** | `CLOSED — VERIFIED`, else `UNVERIFIED — PREREQUISITE UNAVAILABLE` |
| **Category** | Prerequisite currently unavailable |

### CL-07 — Real SMTP send

| | |
| --- | --- |
| **Originating unit** | **P1-10** · **Source** `PHASE-1-PLAN.md` §10 row 6 |
| **Requirement** | A genuine send, only when genuine SMTP exists |
| **Current production state** | **No mail server on this deployment, and none was created to satisfy a test** |
| **Why still open** | What D-153 proves today is *which address SemantIQ would send to*, structurally — **not that SMTP works** |
| **Code / schema / config / deployment** | No · No · **Yes, if and only if genuine SMTP is provisioned** · Possibly |
| **Prerequisite** | Real SMTP credentials the organisation actually owns |
| **Risk** | Manufacturing mail configuration for a green test would be a false pass |
| **Live evidence required** | One real send to a real mailbox |
| **Proposed method** | Execute when SMTP is genuinely provisioned |
| **PO decision?** | **RULED** — see below |
| **Proposed final status** | `CLOSED — VERIFIED` only on a real send, else **`UNVERIFIED — PREREQUISITE UNAVAILABLE`** |
| **Category** | Prerequisite currently unavailable |

> **PRODUCT OWNER RULING — do not manufacture SMTP configuration.** If genuine
> organisation-owned SMTP becomes available, run the real send test. Otherwise the
> disposition is **`UNVERIFIED — PREREQUISITE UNAVAILABLE`**.

### CL-08 — Production Entra configuration cutover, `env` → encrypted store

> **RESTORED TO THE REGISTER.** Missing from both acceptance records — D-2.

| | |
| --- | --- |
| **Originating unit** | **P1-10** · **Source** `PHASE-1-PLAN.md` §10 row 7 |
| **Current production state** | **`identity_source` = `env`. Cutover NOT performed; cutover timestamps absent.** Explicitly withheld by the Product Owner at every gate |
| **Why still open** | A deliberate, scheduled controlled change that has not been authorised |
| **Code required** | **No** — C2–C5 built and tested the cutover path |
| **Schema required** | No |
| **Production config change** | **YES — this is the item** |
| **Deployment required** | **Yes**, controlled |
| **Prerequisite** | Product Owner authorisation and a scheduled window |
| **Risk** | **HIGHEST in the register.** A wrong value in the encrypted store means **nobody can sign in, including every administrator.** Rollback needs server access |
| **Automated evidence available** | C1–C5, H6 |
| **Live evidence required** | The cutover itself, then sign-in proven on the store |
| **Proposed method** | One scheduled controlled change with a rehearsed rollback, **producing CL-02's step-up observation**. **Never expose, request or echo a secret** |
| **PO decision?** | **RULED — DEFERRED** |
| **Final status** | **`DEFERRED BY EXPLICIT PRODUCT OWNER DECISION`** |
| **Category** | Controlled production alignment/change |

> ### PRODUCT OWNER RULING — CL-08 IS DEFERRED. DO NOT PERFORM THE CUTOVER.
>
> **The production cutover is not performed during Phase 1 closeout.** Current
> production `env` authority is functioning and accepted. The cutover carries the
> **highest lockout risk in the register** and has been explicitly withheld at
> every previous gate.
>
> **Final disposition: `DEFERRED BY EXPLICIT PRODUCT OWNER DECISION`** — which is
> a decision recorded, not an omission, and is **not** `UNVERIFIED`: the
> prerequisite exists and the capability is built. It was deliberately not used.
>
> **The Phase 1 → Phase 2 transition baseline (WS-11) must state plainly:**
>
> - production Microsoft identity authority **remains `env`**;
> - the **encrypted-store cutover capability exists** and is tested (C2–C5);
> - it was **intentionally not exercised**.
>
> **It must not be silently reopened in Phase 2.** Reopening is a new Product
> Owner decision, not an inherited task.

### CL-09 — Console screens opened on production by the delivery team

> **RESTORED TO THE REGISTER.** Missing from both acceptance records — D-2.

| | |
| --- | --- |
| **Originating unit** | **P1-10** · **Source** `PHASE-1-PLAN.md` §10 row 8 |
| **Requirement** | The delivery team opens the console screens on production |
| **Current production state** | **Never done.** Signing in needs Microsoft credentials the delivery environment does not have, and its browser does not trust the inspecting proxy's certificate authority |
| **Why still open** | Both blockers are environmental and neither has changed |
| **Code / schema / config / deployment** | No · No · No · No |
| **Prerequisite** | Delivery-team Microsoft credentials **and** a trusted CA path |
| **Risk** | Granting delivery-team production credentials is itself a security decision, and a larger one than the evidence it would buy |
| **Automated evidence available** | Full local browser evidence for every unit |
| **Live evidence still required** | **Arguably none — see below** |
| **Proposed method** | **Recommend explicit supersession, with rationale** |
| **PO decision?** | **RULED — ACCEPTED LIMITATION** |
| **Final status** | **`ACCEPTED LIMITATION`** |
| **Category** | Accepted limitation |

> ### PRODUCT OWNER RULING — ACCEPTED LIMITATION
>
> **Do not issue production Microsoft credentials or modify CA trust solely to
> duplicate evidence already obtained through Product Owner live production
> testing.** The Product Owner's live Gate D observations remain **authoritative
> for product acceptance**.
>
> **Recorded as:** *ACCEPTED LIMITATION — delivery-team authenticated production
> browser observation not performed.*
>
> **This must never be relabelled `CLOSED — VERIFIED`.** It was not executed. An
> accepted limitation and a verified closure are different claims, and collapsing
> them is the failure this register exists to prevent.

**Recommendation, with the reasoning stated rather than assumed.** This gate
exists so that *somebody* looks at the real screens on the real deployment. The
Product Owner has now done exactly that for **P1-09, P1-10 and P1-11**, most
recently 8/8 on the live P1-11 screens with a screenshot as evidence.

**I am not proposing it be marked closed** — it was not executed, and marking an
unexecuted gate `CLOSED` is the failure this whole register exists to prevent.
**I am proposing it be explicitly superseded** as `ACCEPTED LIMITATION`, on the
ground that the evidence it sought has been obtained by a better observer, and
that the remaining delta — a second pair of eyes from the delivery team — does
not justify issuing production credentials to the delivery environment.

**The Product Owner may reasonably disagree**, on the ground that a delivery
team that cannot see production cannot diagnose it. That is a real cost and it
is theirs to weigh.

### CL-10 — Production session driver, `file` → `database`

| | |
| --- | --- |
| **Originating unit** | **P1-09** (found in production verification, 20 Sep 2026) |
| **Requirement** | Align production with the approved target `SESSION_DRIVER=database` |
| **Authoritative source** | `PHASE-1-PLAN.md` §10, *Production session-driver alignment*; `P1-BASE-APPLICATION-BASELINE-DESIGN.md` |
| **Current production state** | **`file`** — established by read-only SSH verification, workflow `verify-session-store` run `35453840140`. Configuration is not cached, so the value is live |
| **Why still open** | Switching drivers **terminates every existing session**. That is a controlled deployment correction, not a UI fix, and `file` is functioning |
| **Code required** | **No** |
| **Schema required** | **No** — `0001_01_01_000000_create_sessions_table.php` already exists |
| **Production config change** | **YES — this is the item** |
| **Deployment required** | **Yes**, controlled |
| **Prerequisite** | Product Owner authorisation and an accepted sign-out window |
| **Risk** | **Moderate and bounded.** Everyone is signed out; sign-in itself is unaffected because identity configuration is untouched. Rollback is a flip back, at the cost of a second sign-out |
| **Automated evidence available** | The migration exists and the driver is supported |
| **Live evidence required** | Driver observed as `database` after the change, and a real sign-in on it |
| **Proposed method** | One controlled configuration change in a scheduled window |
| **PO decision?** | **RULED — EXECUTE IN PHASE 1 CLOSEOUT** |
| **Target final status** | **`CLOSED — CONTROL IMPLEMENTED AND VERIFIED`** |
| **Category** | Controlled production alignment/change |

> ### PRODUCT OWNER RULING — EXECUTE. Production moves `file` → `database`.
>
> **This is WS-1, and it comes before session revocation.** **No execution in this
> task.**
>
> **Everyone being signed out is understood and accepted** as an expected effect
> of this controlled change — not a defect, and not a reason to defer.
>
> **WS-1 must carry all six of these when it is designed:**
>
> | | |
> | --- | --- |
> | **1 — Pre-change checks** | Explicit, executed and recorded **before** the change |
> | **2 — Window** | A maintenance / sign-out window, agreed in advance |
> | **3 — Rollback** | A written, rehearsed rollback procedure |
> | **4 — Post-change sign-in** | A **real** sign-in performed after the change |
> | **5 — Driver proof** | Production **observed** reporting `database` |
> | **6 — Table proof** | Confirmation the **`sessions` table is actually being used** |
>
> **Item 6 is not item 5.** A deployment can report `database` while nothing has
> yet written a row — reporting the setting is a claim about configuration;
> rows in the table are a claim about behaviour. Both are required.
>
> **Execution still requires a separate go / no-go immediately before the
> production change.** Approval of this PLAN is not that authorisation.

**Why this is not cosmetic.** It breaks no delivered control today. **It will
matter the day CL-11 is built**: on the `file` driver, per-user revocation would
**silently fail rather than error** — the worst of the three outcomes, and the
stated reason this was carried rather than closed.

### CL-11 — Privilege-change / server-side per-user session revocation

| | |
| --- | --- |
| **Originating unit** | **P1-09 / P1-10** · **Source** `PHASE-1-PLAN.md` §10; P1-10 acceptance §5 row 9, `OPEN / PHASE 1 GATE` |
| **Requirement** | When a privilege changes, that user's **other** sessions are revoked server-side |
| **Current production state** | **THE CONTROL DOES NOT EXIST.** Established from the code, not assumed: the only two invalidations are `$request->session()->invalidate()` — sign-out and the expiry middleware — and both act on the **viewer's own** session. **Nothing reads `sessions.user_id`** |
| **Why still open** | It was never built. The table was prepared for it |
| **Code required** | **YES — one of TWO closeout items requiring application code; the other is CL-12** |
| **Schema required** | **No** — the sessions table already carries `user_id` |
| **Production config change** | **Indirectly: it requires CL-10 first** |
| **Deployment required** | **Yes** |
| **Prerequisite** | **CL-10 must land first.** On `file`, the control silently no-ops |
| **Risk** | A revocation control that silently fails is worse than none, because it is believed |
| **Automated evidence available** | **None — there is nothing to test yet** |
| **Live evidence required** | A privilege change observed to terminate the target's other sessions |
| **Proposed method** | Full lifecycle: **DESIGN → APPROVE → EXECUTE → TEST → VERIFY → ACCEPT** |
| **PO decision?** | **RULED — MUST BE BUILT IN PHASE 1 CLOSEOUT. NOT deferred to Phase 2** |
| **Target final status** | **`CLOSED — CONTROL IMPLEMENTED AND VERIFIED`** |
| **Category** | Technical control/change required |

> ### PRODUCT OWNER RULING — BUILD IT. It is a Phase 1 security control.
>
> **CL-11 is not deferred to Phase 2.** **No implementation now.**
>
> **The hard dependency stands: CL-10 must be implemented AND verified first.**
> On the `file` driver this control would silently no-op, and a revocation
> control that silently fails is worse than none because it is believed.
>
> **CL-11 requires all six:**
>
> 1. **DESIGN**;
> 2. **Product Owner DESIGN approval** (Gate B);
> 3. implementation;
> 4. **automated tests**;
> 5. **MySQL verification**;
> 6. **live verification that changing a user's privilege revokes that user's
>    other active sessions.**
>
> **Item 6 is the whole point.** Everything above it can pass while the control
> does nothing to a real second session.

### CL-12 — Organisation Administrator navigation gap — **SEVEN screens, not four**

| | |
| --- | --- |
| **Originating unit** | **P1-11** (raised at Gate C) · **Source** `PHASE-1-PLAN.md` §7; `P1-11-…-VERIFICATION.md` §7.2; D-19, D-182, PO-R2 |
| **Requirement** | **Make the seven authorised System Administration destinations discoverable** for an Organisation Administrator |
| **Current production state** | Their System Administration navigation is **exactly `['Administration Home']`** — Product Owner observed, Gate D Check 6 PASS. **Seven other authorised destinations are reachable by URL and absent from navigation** — §2 D-4 |
| **Why still open** | D-182 deliberately exposed **one** node and left the rest as a decision. **The decision is now made; the change is not yet built** |
| **Code required** | **YES.** Narrow — the same authorizer D-182 already modified. **No route, middleware or action-class change** |
| **Schema required** | **No** |
| **Production config change** | **No** |
| **Deployment required** | **Yes** — after an approved DESIGN and implementation |
| **Prerequisite** | **An approved Gate B DESIGN** |
| **Risk** | Exposing **Security Status, Access Reviews and Audit** is a **security-visibility** decision, not a navigation tidy-up. Route authorisation already permits them, so this is about discoverability — but "already permitted" is not the same as "intended to be prominent" |
| **Automated evidence available** | The equality assertion holding PO-R2; the full route authorisation matrix |
| **Live evidence required** | **After deployment:** verify the **exact navigation set** an Organisation Administrator sees, and that each of the seven destinations is genuinely reachable |
| **Proposed method** | **DESIGN → approve → implement → test → verify → accept** |
| **PO decision?** | **RULED — EXPOSE ALL SEVEN. FIX IN PHASE 1 CLOSEOUT** |
| **Target final status** | **`CLOSED — CONTROL IMPLEMENTED AND VERIFIED`** |
| **Category** | **Product/navigation TECHNICAL CHANGE REQUIRED** — changed by this ruling |

> ### PRODUCT OWNER RULING — MAKE ALL SEVEN DISCOVERABLE
>
> **The Product Owner independently verified the D-4 finding** — that
> `OrganisationAdministrator` holds `OrgAdmin`, `AccessAdmin` **and**
> `EvidenceRead`, and that route permissions therefore already authorise more
> than the four previously recorded screens. **The corrected scope of eight
> authorised nodes, one visible, is accepted.**
>
> **Make all seven discoverable in System Administration navigation for an
> Organisation Administrator:**
>
> | # | Destination |
> | --- | --- |
> | 1 | **Organisation** |
> | 2 | **Users & Groups** |
> | 3 | **Roles & Access** |
> | 4 | **Business Domains** |
> | 5 | **Security Status** |
> | 6 | **Access Reviews** |
> | 7 | **Audit** |
>
> **Continue to hide the three `PlatformAdmin` destinations** — **Identity & SSO**,
> **System Health**, **Integrations**. They are not authorised for the role and
> nothing here changes that.
>
> **THIS DECISION DOES NOT WIDEN ROUTE ACCESS.** It aligns navigation with access
> that **already exists**. No middleware, no action class and no route changes.
> If a design proposes touching route authorisation, it has misread this ruling.
>
> **CL-12 therefore changes category** — from *product/navigation decision only*
> to **product/navigation technical change required**, and it **now requires a
> DESIGN gate (Gate B) before any code**.
>
> **REQUIRED: exact-set navigation tests for an Organisation Administrator**, so
> that a future widening or narrowing **cannot pass silently**. The assertion must
> be an **equality on the whole node set** — the same shape that held PO-R2 — not
> a list of "can see X" checks. Eight separate presence assertions are satisfied
> by a menu that also renders Integrations; only an equality catches that.
>
> **No code in this Gate A task.**

**PO-R2 is preserved historically, and is not contradicted.** **P1-11 itself
delivered only Administration Home**, and that remains permanently true of
P1-11. D-182 exposed one node deliberately; **closeout now resolves the carried
navigation gap that D-182 deliberately left open.** The earlier statement that
the alternative was `ACCEPTED LIMITATION` is **SUPERSEDED by this ruling**.

### CL-13 — P1-06 `PostureEvaluator` / `DomainAdapter` per-domain query cost

| | |
| --- | --- |
| **Originating unit** | **P1-06** — **measured by P1-11** · **Source** `PHASE-1-PLAN.md` §7; `P1-11-…-VERIFICATION.md` §6.1 |
| **Requirement** | Decide whether the per-domain cost must be reduced before Phase 1 closes |
| **Current production state** | `DomainAdapter` issues **five aggregates per business domain**. At twenty domains: `/console/security` **189 queries**, `/console/administration` **236**. Production has a handful of domains and responds in ~0.6 s |
| **Why still open** | Raised at P1-11 Gate C and carried by explicit Product Owner ruling — *"not a defect, not a blocker, not fixed here"* |
| **Code required** | **Only if optimisation is chosen** — and it would be **P1-06's** code, not P1-11's |
| **Schema required** | Probably not |
| **Production config change** | No |
| **Deployment required** | Only if optimised |
| **Prerequisite** | A Product Owner decision on whether current scale justifies the work |
| **Risk** | **Refactoring an accepted unit's evaluator carries more risk today than the cost it removes.** The cost grows linearly with business domains, so the risk is future scale, not present behaviour |
| **Automated evidence available** | Measured at two data sizes; `AdministrationHomeBudgetTest` asserts P1-11 adds no N+1 of its own and pays no more for posture than Security Status does |
| **Live evidence required** | None. It is measured |
| **Proposed method** | **Do not refactor merely because closeout exists.** Record the finding against P1-06 (see D-5), decide, and if deferred make it an explicit backlog item with a scale trigger |
| **PO decision?** | **RULED — ACCEPTED LIMITATION** |
| **Final status** | **`ACCEPTED LIMITATION`** |
| **Category** | Performance finding |

> ### PRODUCT OWNER RULING — ACCEPTED LIMITATION, WITH REOPEN TRIGGERS
>
> **Do not refactor `PostureEvaluator` / `DomainAdapter` during Phase 1
> closeout.** Current production scale is acceptable and measured performance
> remains **inside D-140**.
>
> **Record the finding against the P1-06 owning record**, as proposed in D-5 — so
> it lives with the code that causes it rather than only in P1-11's records.
>
> **Reopen the optimisation when EITHER trigger fires:**
>
> 1. a deployment is expected to **materially exceed the tested 20-business-domain
>    scale**; or
> 2. measured **Security Status** or **Administration Home** performance
>    **threatens or exceeds the approved D-140 response-time target**.
>
> **Preserve the finding in the Phase 1 → Phase 2 transition baseline (WS-11)**,
> with both triggers. An accepted limitation with no trigger is a finding that
> quietly becomes permanent.

---

## 3A. Product Owner dispositions — all 13 items ruled

**Recorded 22 September 2026, Gate A amendment round 1.** Every item now has a
disposition. **CL item numbering is unchanged** — a disposition becoming known is
not a reason to renumber a register.

| ID | Item | Product Owner ruling | Final / target status | Code? |
| --- | --- | --- | --- | --- |
| **CL-01** | P1-02 second-administrator SSO re-check | **Do not create or promote an account.** Execute only if one genuinely exists | `UNVERIFIED — PREREQUISITE UNAVAILABLE` at WS-10 if absent | No |
| **CL-02** | Microsoft privileged step-up, live | **Do not manufacture a privileged change.** Coupling to CL-08 withdrawn | `UNVERIFIED — PREREQUISITE UNAVAILABLE` unless one arises naturally | No |
| **CL-03** | Bootstrap First-Run | **Do not create an installation for evidence** | `UNVERIFIED — PREREQUISITE UNAVAILABLE` unless one arises | No |
| **CL-04** | Bootstrap 30-minute idle | As CL-03 | `UNVERIFIED — PREREQUISITE UNAVAILABLE` unless one arises | No |
| **CL-05** | Bootstrap 4-hour absolute | As CL-03 | `UNVERIFIED — PREREQUISITE UNAVAILABLE` unless one arises | No |
| **CL-06** | Bootstrap recovery flow | As CL-03 | `UNVERIFIED — PREREQUISITE UNAVAILABLE` unless one arises | No |
| **CL-07** | Real SMTP send | **Do not manufacture SMTP configuration** | `UNVERIFIED — PREREQUISITE UNAVAILABLE` unless genuine SMTP arrives | No |
| **CL-08** | Entra `env` → encrypted store cutover | **DEFER. Do not perform during closeout** | **`DEFERRED BY EXPLICIT PRODUCT OWNER DECISION`** | No |
| **CL-09** | Delivery-team production observation | **Accept the limitation.** No production credentials, no CA change | **`ACCEPTED LIMITATION`** | No |
| **CL-10** | `SESSION_DRIVER` `file` → `database` | **EXECUTE IN CLOSEOUT.** WS-1, before revocation | target **`CLOSED — CONTROL IMPLEMENTED AND VERIFIED`** | No — config |
| **CL-11** | Per-user session revocation | **MUST BE BUILT IN CLOSEOUT.** Not deferred to Phase 2 | target **`CLOSED — CONTROL IMPLEMENTED AND VERIFIED`** | **YES** |
| **CL-12** | Organisation Administrator navigation | **FIX IN CLOSEOUT — expose all seven** | target **`CLOSED — CONTROL IMPLEMENTED AND VERIFIED`** | **YES** |
| **CL-13** | P1-06 per-domain query cost | **Accept the limitation**, with two reopen triggers | **`ACCEPTED LIMITATION`** | No |

**THREE dispositions are final now:**

| | |
| --- | --- |
| **CL-08** | `DEFERRED BY EXPLICIT PRODUCT OWNER DECISION` |
| **CL-09** | `ACCEPTED LIMITATION` |
| **CL-13** | `ACCEPTED LIMITATION` |

**THREE are active change / build work:** **CL-10** (production configuration),
**CL-11** and **CL-12** (both application code, both behind Gate B).

**SEVEN remain opportunistic** — **CL-01 to CL-07** — and each becomes
`UNVERIFIED — PREREQUISITE UNAVAILABLE` at WS-10 unless a genuine prerequisite
appears. **3 + 3 + 7 = 13.**

> **CORRECTED — an earlier draft of this paragraph said "four dispositions are
> final" and then listed three.** The count was wrong, not the list. It is stated
> here as three, with the arithmetic shown, because a register whose own totals
> do not add up is the shape of defect this document was written to find.

**`UNVERIFIED — PREREQUISITE UNAVAILABLE` is a disposition, not a failure**, and
it is not interchangeable with `ACCEPTED LIMITATION` or `DEFERRED`. The three say
different things: *nobody could check*, *we checked and chose to live with it*,
and *we could have done it and deliberately did not*.

---

## 4. Classification summary

| Category | Items | Count |
| --- | --- | --- |
| **Technical control/change required** | **CL-11, CL-12** | **2** |
| **Controlled production alignment/change** | CL-08, CL-10 | **2** |
| **Live verification only** | CL-02 | **1** |
| **Prerequisite currently unavailable** | CL-01, CL-03, CL-04, CL-05, CL-06, CL-07 | **6** |
| **Performance finding** | CL-13 | **1** |
| **Accepted limitation** | CL-09 | **1** |
| **Already satisfied but documentation stale** | **none** | **0** |

**Nothing qualified as "already satisfied".** Every item was checked against
production or against the code, and not one turned out to be done already.

> ### AMENDED — "Only CL-11 requires new application code" is SUPERSEDED
>
> That statement was true when CL-12 was a decision. **The Product Owner's CL-12
> ruling makes it an implementation item**, so the register now carries **two**
> code items, not one:
>
> | Item | Code | Schema | DESIGN gate |
> | --- | --- | --- | --- |
> | **CL-11** — session revocation | **YES** | No | **Gate B required** |
> | **CL-12** — Organisation Administrator navigation | **YES** | No | **Gate B required** |
>
> **Gate B / design discipline applies to BOTH.** Neither proceeds to code
> without an approved DESIGN.
>
> **Neither needs schema.** CL-11's `sessions` table already carries `user_id`;
> CL-12 touches navigation presentation only and **does not widen route access**.

**Two items require new application code (CL-11, CL-12).** Two are production
configuration changes with no code (CL-08 — now deferred — and CL-10). Six are
blocked on prerequisites nobody can conjure. Two are accepted limitations.

---

## 5. Proposed execution sequence — and where I disagree with the brief

**Workstreams are renamed `WS-0`…`WS-11`** to end the ID collision in D-6. The
Product Owner's ordering is preserved except where repository evidence supports
a safer order; **each change is argued, not asserted.**

| WS | Name | Register items | Depends on |
| --- | --- | --- | --- |
| **WS-0** | Inventory & evidence reconciliation | all | — |
| **WS-1** | Session driver alignment | **CL-10** | WS-0 |
| **WS-2** | Per-user session revocation — **DESIGN → implement → accept** | **CL-11** | **WS-1** |
| **WS-3** | Microsoft identity & privileged configuration | **CL-08 deferred · CL-02 awaiting a genuine change** | WS-0 |
| **WS-4** | Provider-wide SSO re-check | CL-01 | prerequisite |
| **WS-5** | Bootstrap & recovery validation | CL-03–CL-06 | test installation |
| **WS-6** | SMTP operational validation | CL-07 | real SMTP |
| **WS-7** | Organisation Administrator navigation — **DESIGN → implement → accept** | **CL-12** | WS-0 |
| **WS-8** | P1-06 performance finding | **CL-13** | WS-0 |
| **WS-9** | Delivery-team production observation | **CL-09** | WS-0 |
| **WS-10** | Final Phase 1 reconciliation | all | everything |
| **WS-11** | Phase 1 → Phase 2 transition baseline | — | **WS-10 accepted** |

### Three challenges to the proposed order

**Challenge 1 — split the brief's CL-01 into two gated workstreams, and make the
dependency explicit.** The brief pairs revocation and the driver switch as
"related but separate controls". The evidence says the relationship is a **hard
ordering constraint**, not a theme: on the `file` driver, per-user revocation
**silently fails rather than errors**. Shipping WS-2 before WS-1 would deliver a
security control that is believed and does not work. **WS-1 must complete and be
verified before WS-2 goes live.**

**Challenge 2 — do the session driver (WS-1) BEFORE the Entra cutover (WS-3).**
The brief orders identity work after session work, and I agree, but the reason is
worth stating because it decides the order:

| | Blast radius if it goes wrong | Can you still sign in? |
| --- | --- | --- |
| **WS-1** session driver | Everyone signed out | **Yes** — identity untouched |
| **WS-3** Entra cutover | **Nobody can sign in, including every administrator** | **No** |

Perform the recoverable change first, on a substrate you can still authenticate
into. Doing the cutover first would mean attempting the session change afterwards
on a deployment whose sign-in path had just been rebuilt.

**Challenge 3 — WS-7, WS-8 and WS-9 should not wait behind the risky changes,
for two different reasons.**

> **SUPERSEDED, AND REPLACED ABOVE.** The original Challenge 3 argued these three
> were *"decision-only"* and needed *"no production action at all"*. **That was
> true when it was written and is no longer true.** The Product Owner's CL-12
> ruling made **WS-7 build work** — DESIGN, code and deployment. The reasoning
> below is the current authority; the old wording is kept only so the change is
> visible.

**WS-8 and WS-9 are record-only, because their decisions are final.** CL-13 and
CL-09 are both `ACCEPTED LIMITATION` with nothing left to build, execute or
observe. They need writing down — CL-13 against P1-06's own record with its two
reopen triggers, CL-09 with its rationale — and nothing more. **Holding them
behind WS-1 buys nothing**, and disposing of them early shrinks what WS-10 has
to reconcile.

**WS-7 is real build work, and it should still start early — because it has no
dependency on the session work.** CL-12 touches navigation presentation only: no
route, no middleware, no action class, no session or identity behaviour.
**Sequencing it after WS-1 and WS-2 would be queueing, not ordering.** Its DESIGN
can run in parallel with WS-1, and its implementation gated only on its own
Gate B approval.

**WS-7 must not be described as decision-only anywhere.** It carries the full
lifecycle, exactly as WS-2 does.

**And WS-4, WS-5, WS-6 cannot be scheduled at all.** They are blocked on
prerequisites that may never arrive. **They must not sit on the critical path** —
they are opportunistic, executed if and when the prerequisite appears, and
otherwise disposed of explicitly at WS-10.

### Proposed order

> **AMENDED — the approved path after the Product Owner rulings.** The diagram
> below replaces the one in the first draft. **WS-3 no longer executes a
> cutover**, and **WS-7 is now build work, not a decision**.

```
WS-0  Inventory reconciliation
      (this document)
        │
        ├──────────────────────►  WS-8   CL-13  ACCEPTED LIMITATION   record only
        ├──────────────────────►  WS-9   CL-09  ACCEPTED LIMITATION   record only
        └──────────────────────►  WS-3   CL-08  DEFERRED  ·  CL-02 awaiting
                                          NO cutover executed. Record only.

WS-1  Session driver  file → database   (CL-10)      ── ACTIVE BUILD PATH ──
      controlled change · six required proofs
      separate go / no-go immediately before
        │
        ▼
WS-2  Per-user session revocation  (CL-11)
      DESIGN → PO approval → implement → tests → MySQL → LIVE

WS-7  Organisation Administrator navigation  (CL-12)   ── SEPARATE BUILD PATH ──
      DESIGN → PO approval → implement → exact-set tests → accept
      NOT downstream of WS-1 or WS-2 — runs in parallel, depends on neither

WS-4 (CL-01)    WS-5 (CL-03–CL-06)    WS-6 (CL-07)
      opportunistic only — NEVER critical-path blockers
      if the prerequisite never arrives → UNVERIFIED — PREREQUISITE UNAVAILABLE

                          ▼
WS-10  Final Phase 1 reconciliation — all 13 explicitly disposed
                          ▼
WS-11  Phase 1 → Phase 2 transition baseline — only after WS-10 is accepted
```

**WS-7 has no dependency on WS-1 or WS-2.** It touches navigation presentation
and no session or identity behaviour, so it can proceed in parallel with the
session work rather than queueing behind it.

---

## 6. Decisions required from the Product Owner

> ### ANSWERED — Gate A amendment round 1, 22 September 2026
>
> **All twelve were ruled on.** The table is kept as asked rather than deleted,
> with each answer recorded beside the question, because a decision log that
> deletes the questions loses why the answer was needed.
>
> | # | Answer |
> | --- | --- |
> | 1 | **Register of 13 ACCEPTED.** D-1–D-5 accepted findings. The two restored items stay in the register |
> | 2 | **ACCEPTED** — items `CL-01…CL-13`, workstreams `WS-0…WS-11` |
> | 3 | **CL-08 DEFERRED** |
> | 4 | **Coupling WITHDRAWN** — CL-08 is deferred, so CL-02 has no vehicle |
> | 5 | **CL-10 AUTHORISED to execute in closeout**; window and go/no-go still to be set |
> | 6 | **CL-11 MUST BE BUILT in Phase 1 closeout.** Not deferred |
> | 7 | **No installation will be manufactured** — CL-03–CL-06 opportunistic |
> | 8 | **No SMTP will be manufactured** — CL-07 opportunistic |
> | 9 | **Expose all seven.** Keep the three `PlatformAdmin` destinations hidden |
> | 10 | **CL-13 ACCEPTED LIMITATION**, with two reopen triggers |
> | 11 | **CL-09 ACCEPTED LIMITATION** |
> | 12 | **APPROVED** — record the finding against P1-06's own record, in its workstream |
>
> **One decision remains open, and it is deliberately not taken here:** the
> **go / no-go immediately before the CL-10 production change**, with its window.
> Approving this PLAN is not that authorisation.

**The original questions, kept for the record.** They are reproduced **as they
were asked**, so the reasoning behind each answer stays legible. **Any claim in
this table is historical and is superseded by §3 and §3A**, which carry the
current authority.

| # | Decision | Item | Why it is yours |
| --- | --- | --- | --- |
| **1** | Accept the canonical register of **13**, and the six reconciliations in §2 | all | The number was wrong and two items were nearly lost |
| **2** | Accept `CL-01…CL-13` for items and `WS-0…WS-11` for workstreams | D-6 | Two things shared one ID |
| **3** | **Authorise or defer the Entra cutover** | CL-08 | Withheld at every previous gate. Highest risk in the register |
| **4** | Confirm coupling the step-up observation to the cutover | CL-02 + CL-08 | One controlled change, two items |
| **5** | **Authorise or defer the session driver switch**, and set the window | CL-10 | It signs everyone out |
| **6** | **Build per-user revocation in Phase 1 closeout, or defer it explicitly to Phase 2** | CL-11 | *(as asked — **SUPERSEDED**: "the only item needing new code" stopped being true when CL-12 was ruled an implementation item)* |
| **7** | Will a genuine **test installation** be provisioned? | CL-03–CL-06 | Four items hang on it |
| **8** | Will genuine **SMTP** be provisioned? | CL-07 | |
| **9** | **Navigation: expose the seven, expose some, or accept the current behaviour** | CL-12 | The scope changed from four to seven, three of them security surfaces |
| **10** | **P1-06: optimise now, or accept with a scale trigger** | CL-13 | |
| **11** | **Delivery-team observation: execute, or supersede as an accepted limitation** | CL-09 | |
| **12** | Approve recording the P1-06 finding against **P1-06's own** record | D-5 | The owning unit never carried its own finding |

---

## 7. Gate structure for closeout

| Gate | What it covers |
| --- | --- |
| **Gate A** | **This PLAN approved.** ← we are here |
| **Gate B** | **DESIGN approved** — required for **CL-11 and CL-12**, the two items needing new code |
| **Gate C** | EXECUTE → TEST → VERIFY, per item |
| **Gate D** | Product Owner acceptance, per item |
| **Final** | **Phase 1 acceptance** — only once **every** register item has an explicit disposition |

**Pure live-verification items do not get invented design work.** CL-01 to CL-07
and CL-09 need no DESIGN phase — inventing one would be ceremony that proves
nothing. They need a prerequisite, an execution, and an honest record.

**CL-12 now takes the full lifecycle, exactly as CL-11 does.** It is a small
change, and small changes to authorisation-adjacent presentation are precisely
where an exact-set test earns its place — so it gets **DESIGN → APPROVE →
EXECUTE → TEST → VERIFY → ACCEPT**, not a shortcut.

**Controlled production changes (CL-08, CL-10) need no DESIGN either** — the
code already exists and is tested. They need **authorisation, a window and a
rehearsed rollback**, which is a different kind of rigour, not a lesser one.

**Final Phase 1 acceptance requires the whole register disposed.** Not "mostly".
`UNVERIFIED — PREREQUISITE UNAVAILABLE` is a legitimate disposition; **silence
is not.**

---

## 8. The transition baseline — contents, not content

**WS-11 only, and only after WS-10 is accepted.** Recorded here so the
requirement is not lost, **not started**.

`doc/v2/PHASE-1-TO-PHASE-2-TRANSITION-BASELINE.md` must record, for every
accepted Phase 1 capability: purpose · accepted behaviour · owning module ·
authoritative **read** seam · authoritative **write** seam · roles and access
class · organisation / scope / sensitivity boundaries · security boundaries ·
Product Owner decisions · events and Audit expectations · projections and
contracts Phase 2 may consume · **what Phase 2 must not duplicate** · accepted
limitations and findings.

### Phase 2 inheritance rules — to be preserved verbatim

| Phase 2 needs | Must reuse |
| --- | --- |
| Organisation | Phase 1 Organisation authority |
| Users / Groups | **People** |
| Business Domains | **P1-04** |
| Access / security mapping | **P1-05** |
| Sensitivity | The Phase 1 sensitivity model |
| Fabric credentials | **P1-10** integration authority |
| Operational issues | **System Health**, later Monitoring |
| Administrative state changes | **Audit** |

**Phase 2 must not build:** a second Fabric credential model · a second
role/access model · a second sensitivity model · a second organisation model ·
a second audit/history system.

### Three things the baseline must carry, added by the Gate A rulings

| From | The baseline must state |
| --- | --- |
| **CL-08** | Production Microsoft identity authority **remains `env`**; the **encrypted-store cutover capability exists and is tested**; it was **intentionally not exercised**. **It must not be silently reopened in Phase 2** — reopening is a new Product Owner decision, not an inherited task |
| **CL-13** | The `PostureEvaluator` / `DomainAdapter` per-domain finding, as an **accepted limitation**, **with both reopen triggers** — material excess over the tested 20-business-domain scale, or measured performance threatening D-140 |
| **CL-09** | **ACCEPTED LIMITATION — delivery-team authenticated production browser observation not performed.** Product Owner live Gate D observation is the authoritative evidence for product acceptance |

**An accepted limitation with no trigger becomes permanent by default**, which is
why CL-13's two triggers are part of the baseline rather than a footnote here.

---

## 9. What this task did not do

**No Phase 2 work of any kind.** No `doc/v2/phase-2/`, no MASTER PLAN, no
transition baseline file. `doc/v2/` still contains only `phase-1/`.

**No closeout item implemented.** CL-11 is not designed or built. CL-08 and
CL-10 are not executed. Nothing was deployed.

**No application code, schema, CI, deployment workflow, `.env` or production
configuration was touched.** The only change on this branch is this file.

**No carried item was closed, reworded to look closed, or removed.** The two
restored in D-2 are restored as **OPEN**.

---

## 10. Status

**GATE A — AMENDMENT ROUND 1. PLAN ONLY. AWAITING APPROVAL OF THE AMENDED PLAN.**

| | |
| --- | --- |
| Canonical register | **13 items, CL-01 … CL-13 — unchanged** |
| Workstreams | **WS-0 … WS-11**, one range, used consistently |
| Dispositions | **All 13 ruled.** 4 final · 3 active work · 6 opportunistic |
| Requires code and a DESIGN gate | **CL-11 and CL-12** |
| Executed in this task | **Nothing.** No code, no production change, no deployment |

**No item was renumbered because its disposition became known.** Superseded
statements are **marked as superseded**, not rewritten away — the first draft's
coupling proposal, its "only CL-11 requires code" line, its `WS-0…WS-10` range
and its CL-12 accepted-limitation alternative all remain visible with their
supersession recorded beside them.

**No existing accepted unit record was modified.** The corrections in §2 belong
to their workstreams and to WS-10, not to this task.

**Phase 1 is not closed. Phase 2 has not begun.**
