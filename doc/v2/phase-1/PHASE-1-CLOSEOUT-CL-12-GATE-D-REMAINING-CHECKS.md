# CL-12 Gate D — what is left

**Six of the ten are already evidenced by your test-results document. Four are
not.** This sheet is only the four, so you do not repeat work you have done.

| | |
| --- | --- |
| Build | `03f238ee52d95645b91a41d6271069b75f6b2320` |
| Site | https://semantiq.claas2saas.com |
| Sign in as | **Sowjanya Chukka — Organisation Administrator** (Checks 2, 8, 9) |
| Then as | **Salil Mhatre — System Administrator** (Check 10) |
| Time | About 5 minutes |

---

## Part 1 — Confirm one thing about what you already ran

**Your document does not say which account was signed in.** Everything in it is
consistent with the Organisation Administrator, but Gate D turns on it:
Checks 2–8 are *about* that role, and if they were run as the System
Administrator they prove something else entirely.

> **Q. Were the screenshots in `SemantIQ_Test_results.docx` taken while signed
> in as Sowjanya Chukka (Organisation Administrator)?**
>
> **YES / NO:** ______

**If NO, Checks 3–7 below must be re-run as the Organisation Administrator.**

---

## Part 2 — What your document already evidences

**Proposed, for your confirmation — not recorded as PASS until you say so.**

| # | Check | Evidence in your document |
| --- | --- | --- |
| 1 | OrgAdmin sign-in, `/console` loads | Console renders throughout — *pending Part 1* |
| 3 | Fabric: 14 entries, all locked | Screenshot: all 14 with **Soon** |
| 4 | System Administration: exactly 11 | Screenshot: the 11, including Identity & SSO, System Health, Integrations |
| 5 | Admin screens open | Organisation, Users & Groups, Roles & Access, Business Domains, Audit all shown |
| 6 | Platform screens open | Identity & SSO shown, **Configured**, identifiers masked, secret only *Present* |
| 7 | Role disclosure | Reads **verbatim**: *"Full organisation and platform administration, including Identity & SSO, System Health and Integrations. No business data. Cannot grant System Administrator."* |

**Confirm 1, 3, 4, 5, 6, 7 as PASS:** ______

---

## Part 3 — The four still to run

### ☐ Check 2 — Product areas
Signed in as **Sowjanya**, look at the whole sidebar, **scrolled to the top**.

**Expected:** exactly **two** areas — **Fabric Configuration** and **System
Administration**. **SemantIQ Workplace must be ABSENT.**

**If Workplace appears, STOP and fail the gate.** Administration authority must
not confer business access.

📷 *Screenshot the whole sidebar from the top.*

**PASS / FAIL:** ______

---

### ☐ Check 8 — The role picker  ← **the important one**

> **This is what the final correction (#150) delivered, and nothing in your
> document tests it.** Before that change the picker listed every role, so an
> Organisation Administrator was offered System Administrator and refused on
> submit.

Still signed in as **Sowjanya**: **Roles & Access → Grant a role**, and open
the **Role** dropdown.

**Expected — exactly these six, and nothing else:**
1. Organisation Administrator 2. Executive 3. Domain Owner / Director
4. Manager 5. Business User 6. Auditor

**System Administrator must NOT be listed.** If it is, fail the gate.

⚠️ **Look only. Do not submit a grant.** The backend refusal is already covered
by automated evidence that drives the real route and asserts no row is written.

ℹ️ The **filter** dropdown lower down the page shows the same six. **That is
expected and is not a FAIL** — it is the accepted limitation you ruled
non-blocking.

📷 *Screenshot the opened dropdown.*

**PASS / FAIL:** ______

---

### ☐ Check 9 — Phone width
Narrow the browser to roughly phone width, or use your phone. Open the
navigation.

**Expected:** both areas reachable; the list scrolls; labels readable and not
overlapping; **Fabric entries still show Soon**; tapping an entry navigates.

📷 *Screenshot the navigation at phone width.*

**PASS / FAIL:** ______

---

### ☐ Check 10 — System Administrator regression
**Sign out. Sign in as Salil Mhatre (System Administrator).**

**Expected, all three:**
- [ ] **Three** product areas — **SemantIQ Workplace**, Fabric Configuration,
      System Administration
- [ ] Identity & SSO, System Health and Integrations still open
- [ ] **Grant a role** offers **all seven** roles, **including System
      Administrator**

**Why:** Workplace visibility and the ability to grant System Administrator are
now the only two things separating the two administrator roles. If either
moved, CL-12 moved something it had no business moving.

📷 *Screenshot the sidebar and the opened role dropdown.*

**PASS / FAIL:** ______

---

## Part 4 — Then, separately, acceptance

All ten passing is **not** closure. Under the delivery lifecycle only an
explicit Product Owner statement accepting CL-12 closes it.

> **I accept CL-12 as closed.**  ______

Once you give both — the four results and that sentence — I prepare the
documentation-only closeout PR recording build `03f238e`, PRs #146–#150, CI
`42582543599`, deployment `42582543469`, the 10/10 result, the accepted filter
limitation and your acceptance. **That PR is not merged until you instruct it.**

---

## If anything FAILS

CL-12 does not close. Capture the exact screen and what you observed, and I
stop for defect review before any code is touched.
