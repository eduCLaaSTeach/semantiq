# P1-09 — System Health: Product Owner acceptance

**P1-09 PRODUCT OWNER ACCEPTED — GATE D CLOSED. P1-09 CLOSED.**
20 September 2026.

| | |
| --- | --- |
| Unit | **P1-09 — System Health** (delivery order 11) |
| Merge SHA | `5b2e10a6cf4c7d844ceeb4a2e583d0c2a0682361` |
| Deploy | run 147 — success. Post-merge CI run 313 — success |
| Schema | **NONE.** No table, no column, no migration |
| Carried finding | **Production session-driver alignment — OPEN / CARRIED** |

---

## 1. What the Product Owner observed in production

| Check | Result |
| --- | --- |
| The five authoritative headings | **Application, Integrations, Jobs, Connections, Service Health** — correct |
| Microsoft Entra ID | **Available**, with a real *"Last checked …"* age |
| *Check sign-in now* | Succeeded **and stayed on System Health** |
| Jobs | Background work **Available**; Background service **Not applicable**; Scheduled tasks **Not configured** |
| Service Health | Green and business-readable |
| Connections → Staying signed in | **Not checked** — and this is what held Gate D open until it was explained |

---

## 2. The one thing that held the gate open, and why it is not a defect

The Product Owner did not accept *Not checked* on sight. They asked what
`SessionStoreCheck` reads, observed that it returns that status only when
`config('session.driver')` is not `database`, and held Gate D open until
production was asked directly.

**Read-only verification over SSH** — workflow `verify-session-store`, run
`35453840140`, which modifies nothing — established:

| Fact | Value |
| --- | --- |
| Effective production `session.driver` | **`file`** |
| Configuration currently cached | **No**, so the value is live |
| Session table name / existence | **Not reported** — read only when the driver is `database` |
| The same check the screen runs | **`not_checked`** — screen and server agree |

**Ruling: the screen is correct and P1-09 is not failed by this.** With a
`file` driver the honest answer is *Not checked*, because no approved safe
file-session round-trip checker exists. **The Product Owner does not require
the row to become green.**

**What was wrong was a claim in the repository, not the code.** The P1-09
DESIGN said *"`SESSION_DRIVER=database` on this deployment (verified)"*. That
value came from `.env.example` — a repository file read and reported as
deployment reality. **Nothing was verified.** It took the Product Owner looking
at a real screen to find it.

---

## 3. The carried finding

**Production session-driver alignment — OPEN / CARRIED.** Recorded in
`PHASE-1-PLAN.md` §10.

- **Intended target: `database`** — unchanged, and `.env.example` still
  declares it. The P1-BASE design records it as the one place the baseline
  deliberately looks ahead.
- **Current production: `file`.** Drift from the approved target, **not a new
  architectural decision**.
- **No present P1-09 defect.**
- **The migration already exists.** Nothing needs building to adopt the target.
- **Switching the driver requires a controlled deployment** — it terminates
  every existing session, so it was deliberately not done as part of P1-09.
- **Resolve before final Phase 1 acceptance**, together with confirmation of
  the required privilege-change / session-revocation behaviour.

**Server-side per-user session revocation is NOT implemented, and this is
stated from the code rather than assumed.** The only two invalidations in the
application are `$request->session()->invalidate()` — sign-out and the expiry
middleware — and both act on the viewer's **own** session and work on any
driver. Nothing reads `sessions.user_id`. The table was prepared for that
control; the control does not exist yet.

---

## 4. What accepting P1-09 closes, and what it does not

**Closes P1-09 and nothing else.**

| Carried elsewhere | Status |
| --- | --- |
| **P1-02** provider-wide SSO Re-check | **OPEN / CARRIED / UNVERIFIED** — still needs a genuine second permanent System Administrator. **Do not create one to close it** |
| **P1-07** carried items | Carried, unchanged |
| **P1-08** carried items — fail-closed behaviour, the tamper warning, concurrency, a second privileged reader | Carried, unchanged, for the reason they were carried: exercising them would mean breaking production on purpose or manufacturing access that should not exist |
| **D-19** | Unchanged. The sidebar is shown to System Administrators only, and P1-09 widened nothing |
| **Production session-driver alignment** | **OPEN / CARRIED**, new — §3 |

**P1-09's own carried items**, unchanged and for the same reason: every failure
state, a real Microsoft Entra outage, the network boundary as a person could
see it, and two people opening the page at the same instant. Their evidence is
automated and is recorded in `P1-09-SYSTEM-HEALTH-VERIFICATION.md` §6 and
`P1-09-MUTATIONS.md`, not implied by a passing test.

**P1-10 — Administration Home** is the last Phase 1 unit and follows.

---

## 5. The documentation correction accepted alongside this

Comment and document only. **No runtime change** — the PHP token streams of
every edited source file are byte-identical before and after.

| File | Correction |
| --- | --- |
| `P1-09-SYSTEM-HEALTH-DESIGN.md` | The false *"(verified)"* removed; target and deployed reality stated separately |
| `P1-09-SYSTEM-HEALTH-PLAN.md` | The `/up` rationale made driver-independent |
| `P1-09-SYSTEM-HEALTH-VERIFICATION.md` | New §5c records the finding, how the wrong claim was made, and what `file` does and does not cost |
| `P1-BASE-APPLICATION-BASELINE-VERIFICATION.md` | Approved target and deployed environment distinguished. **Historical design intent is not rewritten** — `file` was never approved |
| `P1-BASE-APPLICATION-BASELINE-DESIGN.md` | *"so that P1-00 can revoke sessions"* → the table was **prepared** for that control |
| `routes/health.php` | Driver-independent rationale; `/up` stays outside the web/session middleware |
| `create_sessions_table.php` | No longer claims the revocation capability exists |
| `tests/Feature/Platform/LivenessTest.php` | The same false premise, in a test docblock |
| `.env.example` | **`SESSION_DRIVER=database` KEPT**, with one comment naming it the approved target |
| `PHASE-1-PLAN.md` §10 | The carried finding recorded |

`doc/dailyupdates/` is **not** edited. Those are dated logs of what was believed
on the day; correcting them would falsify a historical record rather than a
current claim.
