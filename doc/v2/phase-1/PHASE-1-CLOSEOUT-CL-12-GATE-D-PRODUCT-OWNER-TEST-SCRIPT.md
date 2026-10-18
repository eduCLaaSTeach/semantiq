# CL-12 — REVISED GATE D — PRODUCT OWNER TEST SCRIPT

**Ten checks. Browser only. Genuine existing accounts only.**

> ## ⚠️ THIS SUPERSEDES THE EIGHT-CHECK SCRIPT
>
> The earlier script was written against the first CL-12 ruling. **Do not use
> it.** Every one of its core expectations is now wrong: it said one product
> area, exactly eight entries, and Identity & SSO / System Health /
> Integrations necessarily hidden. The Product Owner has since clarified the
> role model and all three of those are now expected to be **present and
> openable**.

---

## 1. Feature being tested

**CL-12 (amended) — Organisation Administrator receives the full administration
surface.**

Fabric Configuration and System Administration are both **administration**
areas. An Organisation Administrator is an administrator of the organisation
and is entitled to the complete current administration surface. SemantIQ
Workplace is the **business** area and stays out: administration authority is
not business-data authority.

**This release widens a real authorisation.** An Organisation Administrator now
holds platform administration and can reach Identity & SSO, System Health and
Integrations — which includes deployment-wide credentials. That was named in
the design before approval and chosen deliberately. It is stated here because
you are about to verify it on live production.

## 2. Deployed build

| | |
| --- | --- |
| **Build under test** | **`9546f954b888bb8b2354e3d11f2ff47e316d7317`** |
| Pull request | **#149**, squash-merged |
| Previous CL-12 merges | #147 (first ruling), #148 (query efficiency) |
| Site | https://semantiq.claas2saas.com |
| Deployment run | *see the covering report* |

## 3. Preconditions

- The deployment for the SHA above completed successfully.
- You have the **existing genuine Organisation Administrator** account.
- You have the **existing genuine System Administrator** account (Check 10).
- Sign-in is Microsoft Entra SSO.
- Sign out first if a different account is already signed in — a stale session
  is the commonest cause of a confusing result here.

## 4. Test data required

**None.** Every check is a sign-in and a look at the screen.

## 5. Warning — permanence

**This script creates no data and requires none.**

- **Do not create or promote a user to run this test.** SemantIQ has no hard
  delete for users, so a test account made here would be permanent, real
  organisational history. A check you cannot run with a genuine account is
  marked **NOT RUN** — that is the correct outcome, not a failure.
- **Check 8 is look-only.** Do not submit a role grant to prove a refusal.
- Opening a screen is recorded in the audit trail as a view. That is normal.
- Identity & SSO and Integrations hold live credentials. **Open them, read
  them, change nothing.**

## 6–7. Numbered steps and expected results

### Check 1 — Sign in as the Organisation Administrator
**Step:** Go to https://semantiq.claas2saas.com and sign in with the existing
genuine Organisation Administrator.
**Expected:** `/console` loads normally. No error, no access-denied, no blank
screen.
**PASS / FAIL / NOT RUN:** ______

---

### Check 2 — Exactly two product areas
**Step:** Read the left sidebar without clicking.
**Expected:** Exactly **two** areas — **Fabric Configuration** and **System
Administration**. **SemantIQ Workplace is ABSENT.**
**Why:** Workplace is the business area. An administrator must not receive it
for being an administrator. **If Workplace appears, STOP and fail the gate.**
**PASS / FAIL / NOT RUN:** ______

---

### Check 3 — Fabric Configuration: all 14, all locked
**Step:** Open Fabric Configuration and read every entry.
**Expected:** All fourteen present —
Overview · Data Sources · Connect Source · Discovery · Data Classification ·
Ingestion · Data Quality · Business Model · Security Mapping · Semantic Model ·
AI Readiness · Pipelines & Refresh · Power BI Publication · Monitoring
— **every one visibly locked / "Soon"**, and **none opens a delivered page**.
Click one: it must go nowhere.
**Why:** These are roadmap entries with no route behind them. A Fabric entry
that opens a real page would mean an undelivered phase leaked into production.
**PASS / FAIL / NOT RUN:** ______

---

### Check 4 — System Administration: exactly these 11
**Step:** Read the entries under System Administration, top to bottom.
**Expected:** Exactly these eleven —
1. Administration Home 2. Organisation 3. Users & Groups 4. Roles & Access
5. Business Domains 6. Identity & SSO 7. Security Status 8. Access Reviews
9. Audit 10. System Health 11. Integrations
**Also check:** spelling, the ampersands, every entry has an icon, nothing
truncated or overlapping, no raw key such as `people.view`.
**PASS / FAIL / NOT RUN:** ______

---

### Check 5 — Existing organisation administration still opens
**Step:** Open Administration Home, then Users & Groups, then Roles & Access,
then Business Domains, then Audit.
**Expected:** Each loads a real populated screen with the sidebar entry
highlighted. No 403, no access-denied, no error, no empty shell, no endless
spinner.
**PASS / FAIL / NOT RUN:** ______

---

### Check 6 — Newly granted platform administration opens
**Step:** Open **Identity & SSO**, then **System Health**, then
**Integrations**.
**Expected:** All three load normally. **No access-denied.**
**This is the heart of the amended ruling** — these three were refused before
this release.
**⚠️ Read only. Do not edit a credential, do not run a test-send, do not remove
a secret.**
**PASS / FAIL / NOT RUN:** ______

---

### Check 7 — Role disclosure
**Step:** Open **Roles & Access** and find how **Organisation Administrator**
is described.
**Expected, word for word:**

> Full organisation and platform administration, including Identity & SSO,
> System Health and Integrations. No business data. Cannot grant System
> Administrator.

**Why:** Before this release the picker said *"No platform authority"* while
delegating exactly that. A description that understates a role does not merely
fail to inform — it reassures.
**PASS / FAIL / NOT RUN:** ______

---

### Check 8 — Security boundary: System Administrator cannot be granted

> ### ⚠️ READ THIS BEFORE RUNNING CHECK 8 — expectation corrected
>
> The check was drafted as *"verify no UI offers Organisation Administrator the
> ability to grant System Administrator."* **Measured against the real
> application, that is not what happens, and the check would fail as drafted.**
>
> **What is actually true, verified:** the Roles & Access role dropdown lists
> **all seven** roles including System Administrator, to every administrator.
> Submitting System Administrator as an Organisation Administrator is
> **refused** and **no assignment is created**. The boundary holds in the
> backend; the dropdown simply is not filtered by who is signed in.
>
> This is **pre-existing** behaviour and not introduced by #149 — the picker
> has never filtered by grantability. It matters more now, because the role
> summary in Check 7 states "Cannot grant System Administrator" while the
> dropdown still offers it.
>
> **This needs a Product Owner ruling and is NOT being changed unprompted.**

**Step (look only):** On Roles & Access, open the role dropdown and note
whether System Administrator is listed.
**Expected (what is true today):** System Administrator **is** listed. The
backend refuses the grant.
**Do NOT submit a grant to prove the refusal.** Automated evidence is retained
— `test_an_organisation_administrator_cannot_grant_the_system_administrator_role`
drives the real route and asserts no row is written.
**Record what you observe, then rule:** leave as is, or raise filtering the
dropdown by grantability as its own unit.
**PASS / FAIL / NOT RUN:** ______

---

### Check 9 — Responsive navigation
**Step:** Narrow the browser to roughly phone width (or use your phone) and
open the navigation.
**Expected:** Both areas reachable; all entries scrollable and readable; labels
not cut off or overlapping; **locked Fabric entries still visibly locked**;
tapping an entry navigates and the menu closes.
**PASS / FAIL / NOT RUN:** ______

---

### Check 10 — System Administrator regression
**Step:** Sign out. Sign in as the existing genuine System Administrator.
**Expected:** **All three** product areas visible — **SemantIQ Workplace**,
**Fabric Configuration**, **System Administration** — and Identity & SSO,
System Health and Integrations still open normally.
**Why:** Workplace visibility is now one of only two things that distinguish
the two administrator roles. If the System Administrator lost it, CL-12 moved
something it had no business moving.
**PASS / FAIL / NOT RUN:** ______

---

## 8. Negative, refusal and security cases

Checks **2**, **8** and **10** are the security cases.

- **Check 2** — the business area stays out of an administrator's sidebar.
- **Check 8** — System Administrator cannot be granted by an Organisation
  Administrator. Verified by observation of the UI plus retained automated
  evidence, **not** by submitting a grant.
- **Check 10** — nothing was taken from the System Administrator.

**A route-refusal case is deliberately absent.** Typing a URL would test route
authorisation, and the three screens that changed are now *allowed* — there is
no refusal left to demonstrate for this role that CL-12 did not deliberately
remove.

## 9. Visual and UX checks

Built into Checks 2, 3, 4, 5, 6 and 9: spelling and capitalisation, icons,
locked styling on all 14 Fabric entries, no raw policy keys or internal
terminology, no truncation or overflow, current entry highlighted, correct
order, usable at phone width. Repeat Check 4 in dark theme if your environment
uses one.

## 10. Evidence to capture

1. Sidebar at desktop width showing **both** areas (covers 2 and 4).
2. Fabric Configuration expanded, showing all 14 locked (Check 3).
3. **Identity & SSO open as the Organisation Administrator** (Check 6) — the
   single most important screenshot in this gate.
4. The Organisation Administrator description on Roles & Access (Check 7).
5. The role dropdown showing what is listed (Check 8).
6. Sidebar at phone width (Check 9).
7. System Administrator sidebar showing all three areas (Check 10).
8. This script with PASS / FAIL / NOT RUN filled in.

## 11. PASS / FAIL

A field is provided on every check.

## 12. What CANNOT currently be tested, and why

| Not observable | Why |
| --- | --- |
| **That a Product Owner's browser sees this** — until you run it | The automated suite proves the menu data and route authorisation per role; the production probe proves every destination exists and refuses anonymous callers. **Neither is a rendered screen.** This script exists because a passing test is not an observed result. |
| **The System Administrator grant refusal, live** | Proving it by hand means submitting a real grant of the highest role in the product. Automated evidence is retained (the real route is driven and no row is written). **Carried, not inferred.** |
| **An inactive Organisation Administrator being refused** | Would require deactivating a real person to watch it happen. **NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA** — automated evidence retained. |
| **An Auditor seeing no administration areas** | Needs a genuine Auditor account. If one exists, repeat Check 2 expecting **no** areas at all; if not, **do not create one**. Automated evidence retained — this is the single assertion that would catch a class-derived navigation rule. |
| **Fabric Configuration behaviour when delivered** | All 14 are locked roadmap entries. Nothing behind them exists to test. |
