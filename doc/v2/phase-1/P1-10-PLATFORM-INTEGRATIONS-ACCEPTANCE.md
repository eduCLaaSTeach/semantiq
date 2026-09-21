# P1-10 — Platform Integrations & Setup: Product Owner acceptance

**P1-10 PRODUCT OWNER ACCEPTED — GATE D CLOSED. P1-10 CLOSED.**
21 September 2026.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** (delivery order 12) |
| Schema | **Six tables**, all additive. No existing table altered, renamed or dropped |
| Production identity authority | **`env` — unchanged. No Entra cutover was performed** |
| Bootstrap | **Closed**, and was never opened |
| Carried items | **Nine, all still OPEN** — §5 |

---

## 1. The delivery, end to end

| Stage | Merge |
| --- | --- |
| **PLAN** | `b26c07b33046673758ff429452547a9954328644` |
| **DESIGN** | `a7aef47fc1db4afefe0076f98c6cf47deb7cf808` — the six Product Owner corrections applied |
| **Implementation** — PR #131 | `1a4068b8814577282f4133fff9bec4aa361b29b4` |
| **Production correction** — PR #132 | `27904cce53788eae0f451b6594b68a1fc1cb0418` |
| **Production verification** — PR #133 | `3cd4fbf361658cc020001bafd9f417931be523b5` |
| **UI correction** — PR #134 | `eec366eda4fd46e38a444e1d083a865b08707961` |
| **Verification record** — PR #135 | `4133c30ae741bbe755224b1a8fa29a0bf05ef2af` |

**Gate C took three rounds and Gate D took one.** That is recorded rather than
smoothed over: the unit was held four times in total, each time for something a
green CI run had not caught.

| Held at | For |
| --- | --- |
| Gate C round 1 | Six corrections — the unconstrained First-Run route, the handoff, the closing write, the fresh-installation cutover, health invalidation, fail-closed evidence |
| Gate C round 2 | Four — D-159 step-up, D-165 Bootstrap session policy, Identity as a summary on the console, *Not configured* vs *Not checked* |
| Gate C round 3 | Three — post-install SSO was still read-only, D-159 protected only the secret and not the destination, D-153 was documented and not implemented |
| **Gate D** | **One — the screen did not follow the Organisation layout** |

---

## 2. What the Product Owner reviewed, and accepted

**Live production Gate D review. CHECKS 1–8 — PASS.**

Independently confirmed from the production screens:

| | |
| --- | --- |
| Layout | The common SemantIQ **title → description → horizontal tabs → active section** shape |
| Tabs, exactly | **Microsoft Entra ID**, **Email & Notifications**, **AI Provider**, **Microsoft Fabric** |
| Microsoft Entra ID | **Summary only**, with `Manage Identity & SSO` |
| Email & Notifications | The expected SMTP setup surface, reading **Not configured** |
| AI Provider | The approved provider and setup surface, reading **Not configured** |
| Microsoft Fabric | Tenant, client, workspace and secret setup, reading **Not configured** |
| Active-tab treatment | Consistent across the supplied screens |
| Polish | No raw enum, secret, technical exception or inconsistent navigation visible |

The Product Owner additionally confirmed **PASS** for the checks not fully
demonstrable from static screenshots — the Identity change screen,
responsive and theme behaviour, and the sign-in / System Health / regression
pass.

---

## 3. The production facts at acceptance

Read from the live system, not inferred.

| Fact | Value |
| --- | --- |
| `GET /up` | **200**, body `ok` |
| **The six P1-10 tables** | **All present** — `platform_settings`, `integration_configurations`, `integration_secrets`, `bootstrap_administrators`, `bootstrap_recovery_tokens`, `staged_integration_changes` |
| Effective identity authority | **`env`** |
| Cutover timestamps | **Both absent** — no half-finished transition |
| **Entra cutover** | **NOT PERFORMED.** Production still reads Microsoft sign-in from the server environment |
| Bootstrap local sign-in | **Closed.** `/first-run/sign-in` → **302 → `/first-run/closed`** |
| Bootstrap Administrator | **None.** The deployment never created one |
| Recovery tokens | **0 total, 0 live** |
| Staged privileged changes | **0 total, 0 live** |
| Integration secrets stored | **None** |
| Identity health | **healthy** |
| Audit key catalogue | **15** |
| `session_storage` | **`file`** — see §5 |

Established by the two read-only verification workflows,
`Verify P1-10 Platform Setup state` and `Verify P1-02 identity state`, **both of
which are guards rather than narrations**: a wrong answer fails the run. Both
passed.

**The final shared Organisation-style tab layout is accepted.**

---

## 4. What this unit delivered

1. A **local setup administrator** who can sign in before Microsoft sign-in
   exists, so a new installation is no longer a chicken-and-egg problem — with
   a 30-minute idle and 4-hour absolute session policy of its own, and a
   closing write that keeps it closed.
2. A **First-Run setup flow** where that person enters Microsoft sign-in and,
   optionally, email, AI and Fabric details, then hands over to the first
   permanent System Administrator.
3. An **Integrations screen** of four tabs where those details are managed
   afterwards — with Microsoft sign-in owned by P1-02 and shown here as a
   summary and a link.
4. **Post-install Microsoft sign-in change**, in P1-02: verify the candidate,
   then activate atomically, and only after a Microsoft step-up.
5. **D-159 widened to the destination**, so moving where a saved credential is
   sent is as privileged as replacing it, and nothing is written before the
   confirmation.
6. **D-153 Send test email**, with the recipient resolved server-side and no
   parameter, field or signature through which one could be supplied.

**Two defects were found by deploying rather than by testing**, and both are
recorded in the verification document rather than folded in silently: the
Integrations screen told a working deployment that its Microsoft sign-in was
*Not configured* (§14), and Gate C round 3 had invalidated
`verify-identity.yml` without anything saying so (§14, end).

---

## 5. Carried items — **P1-10 closure closes none of these**

**Not one was manufactured to close a checklist.** Each stays open until it can
be observed honestly.

| # | Item | Status |
| --- | --- | --- |
| 1 | P1-02 provider-wide SSO re-check with a genuine **second permanent** System Administrator | **OPEN / CARRIED / UNVERIFIED** |
| 2 | A real completed Microsoft privileged configuration **step-up** observation | **OPEN / CARRIED** |
| 3 | Bootstrap **First-Run** on a genuine fresh or test installation | **OPEN / CARRIED** |
| 4 | Bootstrap **30-minute idle** timeout, live | **OPEN / CARRIED** |
| 5 | Bootstrap **4-hour absolute** timeout, live | **OPEN / CARRIED** |
| 6 | **Recovery flow**, live | **OPEN / CARRIED** |
| 7 | **Real SMTP send test**, when genuine SMTP becomes available | **OPEN / CARRIED** |
| 8 | Production **session-driver alignment**, `file` → `database` | **OPEN / CARRIED** |
| 9 | Privilege-change / **per-user session revocation** | **OPEN / PHASE 1 GATE** |

**None of the nine was executed.** Every one of them needs either a
prerequisite that does not exist yet — a second permanent administrator, a
fresh installation, real SMTP — or a controlled change that is not a UI fix.

**Row 9 remains a statement about what is NOT built.** Nothing in the
application reads `sessions.user_id`; the only two invalidations act on the
viewer's own session. The table was prepared for that control and the control
does not exist.

---

## 6. What P1-10 closure does and does not unlock

**Does:** P1-11 Administration Home. Its only blocker was this unit's
acceptance, and that blocker is now **CLOSED**.

**Does not:** Phase 1. P1-11 is the final *delivery unit*, but final Phase 1
acceptance additionally requires explicit disposition of the phase-level gates
in §5 — rows 1, 8 and 9 in particular. **They are not to be moved silently into
Phase 2.**
