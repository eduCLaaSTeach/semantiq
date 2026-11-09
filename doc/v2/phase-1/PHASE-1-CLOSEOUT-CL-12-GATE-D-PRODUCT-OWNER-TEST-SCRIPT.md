# CL-12 — FINAL GATE D — PRODUCT OWNER TEST SCRIPT

**Ten checks. Browser only. Genuine existing accounts only.**

> ## ⚠️ THIS REPLACES EVERY EARLIER GATE D SCRIPT
>
> Two earlier versions exist and **neither may be used**. The eight-check
> script was written against the first CL-12 ruling (one product area, eight
> entries, the platform screens hidden) — all of that is now wrong. The
> ten-check draft that followed carried a **Check 8 that would have failed**,
> because the role picker had not yet been corrected. **This is the version to
> run.**

---

## 1. What you are verifying

**CL-12 — the Organisation Administrator administration surface, complete.**

Three merges make up what you are testing:

| Merge | What it did |
| --- | --- |
| #147 / #148 | The first ruling — eight navigation entries, then the query-efficiency fix |
| #149 | **The amended ruling** — full administration access, `PlatformAdmin` granted, role disclosure corrected |
| **#150** | **The final correction** — the role picker now offers only what you can actually grant |

**This release gave the Organisation Administrator real platform authority**, including Identity & SSO, System Health and Integrations. That was named in the design before approval and chosen deliberately. You are verifying it on live production.

## 2. Deployed build

| | |
| --- | --- |
| **Build under test** | **`03f238ee52d95645b91a41d6271069b75f6b2320`** |
| Site | https://semantiq.claas2saas.com |
| Deployment run | *see the covering report* |

## 3. Preconditions

- The deployment for the SHA above completed successfully.
- You have the **existing genuine Organisation Administrator** account.
- You have the **existing genuine System Administrator** account (Check 10).
- Sign-in is Microsoft Entra SSO.
- Sign out first if another account is already signed in — a stale session is
  the commonest cause of a confusing result.

## 4. Test data required

**None.** Every check is a sign-in and a look at the screen.

## 5. Warning — permanence and live configuration

**This script creates no data and requires none.**

- **Do not create or promote a user.** SemantIQ has no hard delete, so a test
  account would be permanent organisational history. A check you cannot run
  with a genuine account is **NOT RUN** — the correct outcome, not a failure.
- **Check 6 touches live credentials. Look only.** No edit, no test-send, no
  secret removal.
- **Check 8 is look-only. Do not submit a grant.**
- Opening a screen is recorded in the audit trail as a view. That is normal.

## 6–7. The ten checks

### Check 1 — Organisation Administrator login
**Step:** Sign in at https://semantiq.claas2saas.com as the existing genuine
Organisation Administrator.
**Expected:** `/console` loads normally. No error, no access-denied, no blank
screen.
**PASS / FAIL / NOT RUN:** ______

---

### Check 2 — Product areas
**Step:** Read the sidebar without clicking.
**Expected:** Exactly **two** — **Fabric Configuration** and **System
Administration**. **SemantIQ Workplace is ABSENT.**
**Why:** Workplace is the business area. Administration authority must not
confer business access. **If Workplace appears, STOP and fail the gate.**
**PASS / FAIL / NOT RUN:** ______

---

### Check 3 — Fabric Configuration
**Step:** Open Fabric Configuration and read every entry. Click one.
**Expected:** All **14** present —
Overview · Data Sources · Connect Source · Discovery · Data Classification ·
Ingestion · Data Quality · Business Model · Security Mapping · Semantic Model ·
AI Readiness · Pipelines & Refresh · Power BI Publication · Monitoring
— **all visibly locked / "Soon"**, and **none opens a delivered route**. The
one you click must go nowhere.
**Why:** These are roadmap entries with nothing behind them. One that opens a
real page would mean an undelivered phase leaked into production.
**PASS / FAIL / NOT RUN:** ______

---

### Check 4 — System Administration
**Step:** Read the entries, top to bottom.
**Expected:** Exactly these **eleven** —
1. Administration Home 2. Organisation 3. Users & Groups 4. Roles & Access
5. Business Domains 6. Identity & SSO 7. Security Status 8. Access Reviews
9. Audit 10. System Health 11. Integrations
**Also check:** spelling, the ampersands, every entry has an icon, nothing
truncated, no raw key such as `people.view`.
**PASS / FAIL / NOT RUN:** ______

---

### Check 5 — The organisation administration screens
**Step:** Open Administration Home, Users & Groups, Roles & Access, Business
Domains, Audit.
**Expected:** Each loads a real populated screen with its sidebar entry
highlighted. No 403, no access-denied, no error, no empty shell, no endless
spinner.
**PASS / FAIL / NOT RUN:** ______

---

### Check 6 — The platform screens
**Step:** Open **Identity & SSO**, then **System Health**, then
**Integrations**.
**Expected:** All three load normally. **No access-denied.**
**This is the heart of the amended ruling** — all three were refused before
this release.
**⚠️ READ ONLY. Change no credential, send no test message, remove no secret.**
**PASS / FAIL / NOT RUN:** ______

---

### Check 7 — Role disclosure
**Step:** Open **Roles & Access** and find how **Organisation Administrator**
is described.
**Expected, word for word:**

> Full organisation and platform administration, including Identity & SSO,
> System Health and Integrations. No business data. Cannot grant System
> Administrator.

**Why:** Before #149 the picker said *"No platform authority"* while delegating
exactly that. A description that understates a role does not merely fail to
inform — it reassures.
**PASS / FAIL / NOT RUN:** ______

---

### Check 8 — The role picker

> **This check failed on the previous script and is why #150 exists.** The
> picker used to list every role, so an Organisation Administrator was offered
> System Administrator and refused on submit. It now offers only what they can
> actually grant.

**Step (look only):** Still signed in as the Organisation Administrator, open
**Grant a role** and read the role dropdown.
**Expected — exactly these six, and no others:**
1. Organisation Administrator 2. Executive 3. Domain Owner / Director
4. Manager 5. Business User 6. Auditor

**System Administrator must NOT be offered.** If it appears, **fail the gate**.
**Do NOT submit a grant.** The backend refusal is covered by automated
evidence that drives the real route and asserts no row is written.
**PASS / FAIL / NOT RUN:** ______

---

### Check 9 — Responsive navigation
**Step:** Narrow to roughly phone width (or use your phone) and open the
navigation.
**Expected:** Both areas reachable; navigation scrolls; labels readable and not
overlapping; **Fabric entries still visibly locked**; tapping an entry
navigates and the menu closes.
**PASS / FAIL / NOT RUN:** ______

---

### Check 10 — System Administrator regression
**Step:** Sign out. Sign in as the existing genuine System Administrator.
**Expected:**
- **All three** areas visible: **SemantIQ Workplace**, Fabric Configuration,
  System Administration;
- Identity & SSO, System Health and Integrations still open;
- **Grant a role offers all seven roles, including System Administrator.**

**Why:** Workplace visibility and the ability to grant System Administrator are
now the only two things separating the administrator roles. If either moved,
CL-12 moved something it had no business moving.
**PASS / FAIL / NOT RUN:** ______

---

## 8. Negative, refusal and security cases

Checks **2**, **8** and **10** are the security cases.

- **Check 2** — the business area stays out of an administrator's sidebar.
- **Check 8** — System Administrator is neither offered nor grantable.
- **Check 10** — nothing was taken from the System Administrator.

**No route-refusal case is included.** The three screens that changed are now
*allowed* for this role, so there is no refusal left to demonstrate that CL-12
did not deliberately remove.

## 9. Visual and UX checks

Built into Checks 2, 3, 4, 5, 6, 8 and 9: spelling and capitalisation, icons,
locked styling on all 14 Fabric entries, no raw policy keys, no truncation,
current entry highlighted, correct order, usable at phone width. Repeat Check 4
in dark theme if your environment uses one.

## 10. Evidence to capture

1. Sidebar at desktop width showing **both** areas (covers 2 and 4).
2. Fabric Configuration expanded, all 14 locked (Check 3).
3. **Identity & SSO open as the Organisation Administrator** (Check 6).
4. The Organisation Administrator description on Roles & Access (Check 7).
5. **The role dropdown showing six roles, no System Administrator** (Check 8).
6. Sidebar at phone width (Check 9).
7. System Administrator: three areas, and a dropdown with all seven (Check 10).
8. This script with PASS / FAIL / NOT RUN filled in.

## 11. PASS / FAIL

A field is provided on every check.

## 12. Accepted limitation — ruled, not a defect

> **An Organisation Administrator cannot select System Administrator from the
> role FILTER**, because the filter shares the grantable-role projection.
> **System Administrator assignments remain visible** in the unfiltered list.

Ruled **non-blocking** by the Product Owner. Not a security defect:
authorisation is untouched and the backend still interprets a valid role query
independently. **Not to be raised as a further CL item.**

**You may notice this during Check 8** — the filter dropdown beneath the grant
form is the same shortened list. That is expected. It is **not** a FAIL.

## 13. What CANNOT be tested, and why

| Not observable | Why |
| --- | --- |
| **That a Product Owner's browser sees this** — until you run it | The automated suite proves the data per role; the production probe proves the destinations exist and refuse anonymous callers. **Neither is a rendered screen.** |
| **The System Administrator grant refusal, live** | Proving it by hand means submitting a real grant of the highest role in the product. Automated evidence is retained: the real route is driven and no row is written. **Carried, not inferred.** |
| **An inactive Organisation Administrator being refused** | Would require deactivating a real person. **NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA** — automated evidence retained. |
| **An Auditor seeing no administration areas** | Needs a genuine Auditor account. If one exists, repeat Check 2 expecting **no** areas; if not, **do not create one**. This is the single assertion that would catch a class-derived navigation rule. |
| **Fabric Configuration behaviour when delivered** | All 14 are locked roadmap entries. Nothing behind them exists. |
