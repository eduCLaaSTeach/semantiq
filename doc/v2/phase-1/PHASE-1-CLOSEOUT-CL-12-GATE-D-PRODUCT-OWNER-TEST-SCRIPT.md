# CL-12 — GATE D — PRODUCT OWNER TEST SCRIPT

Eight checks. Browser only. Genuine existing accounts only.

---

## 1. Feature being tested

**CL-12 — Organisation Administrator navigation.**

An Organisation Administrator was **authorised for eight** System Administration
destinations and could **discover one**. All eight already returned 200 when the
URL was typed in; **seven were missing from the sidebar**. CL-12 stops hiding
those seven.

**This is a discoverability change, not an access change.** No permission, role,
action class or route was altered. Nobody can now open a screen they could not
open yesterday.

## 2. Deployed build

| | |
| --- | --- |
| Merge SHA (squash) | `80c1a603e39bd2a85f92d510439b7e0fd3597af0` |
| Pull request | #147 |
| Reviewed head | `910a52263417413b2d49a82d4b2212619542ffdd` |
| Gate B design merged at | `33c04b94fd0c27be5fe83448a86019407fc1ea82` (#146) |
| Site | https://semantiq.claas2saas.com |
| Deployment run | *see the covering report for this run's ID and result* |

## 3. Preconditions

- The deployment for the SHA above completed successfully.
- You have the **existing genuine Organisation Administrator** account you
  already use. **Do not create one, and do not promote anyone to get one.**
- You have the **existing genuine System Administrator** account (Check 8).
- Sign-in is Microsoft Entra SSO, so you will authenticate with Microsoft.
- Use a normal browser window. If you are already signed in as somebody else,
  sign out first — a stale session is the most common cause of a confusing
  result here.

## 4. Test data required

**None.** Every check is a sign-in and a look at the screen.

## 5. Warning — permanence

**This script creates no data and requires none.** Nothing you do below is
written, so nothing has to be deleted afterwards.

Two cautions anyway:

- **Do not create or promote a user to run this test.** SemantIQ has no hard
  delete for users, so a test account made here would be permanent, real
  organisational history. If you cannot run a check with a genuine account, mark
  it **NOT RUN** and say so — that is the correct outcome, not a failure.
- Opening a screen is recorded in the audit trail as a view. That is normal and
  expected; it is not test pollution.

## 6–7. Numbered steps and expected results

### Check 1 — Sign in as the Organisation Administrator

| | |
| --- | --- |
| **Step** | Go to https://semantiq.claas2saas.com and sign in with the **existing genuine Organisation Administrator** account. |
| **Expected** | Sign-in completes and the console loads. No error page, no "access denied", no blank screen. |
| **PASS / FAIL** | |

---

### Check 2 — System Administration is the only area, and it is open

| | |
| --- | --- |
| **Step** | Look at the left sidebar without clicking anything. |
| **Expected** | **System Administration** is the **only** product area shown, and it is **already expanded** — you should not have to click to reveal its entries. **SemantIQ Workplace** and **Fabric Configuration** must **not** appear. |
| **Why** | Those two belong to phases that are not delivered. An Organisation Administrator seeing either would mean a future phase leaked into production. |
| **PASS / FAIL** | |

---

### Check 3 — Exactly these eight entries, in this order

| | |
| --- | --- |
| **Step** | Read the entries under **System Administration**, top to bottom. |
| **Expected** | Exactly these eight, in exactly this order, with nothing extra and nothing missing: |

1. Administration Home
2. Organisation
3. Users & Groups
4. Roles & Access
5. Business Domains
6. Security Status
7. Access Reviews
8. Audit

| | |
| --- | --- |
| **Also check** | Spelling, capitalisation and the ampersands (`Users & Groups`, `Roles & Access`). Every entry has an icon. No entry is truncated or overlapping. No entry shows a raw key such as `people.view`. |
| **PASS / FAIL** | |

---

### Check 4 — The three platform entries are absent

| | |
| --- | --- |
| **Step** | Confirm these are **not** present anywhere in the sidebar: **Identity & SSO**, **System Health**, **Integrations**. |
| **Expected** | All three absent. |
| **Why** | They are platform-administration screens. An Organisation Administrator is not a Platform Administrator, and could not open them even if the entry were shown. If any of the three appears, **STOP and fail the gate** — that is the one result that would mean something went wrong. |
| **PASS / FAIL** | |

---

### Check 5 — Administration Home, Users & Groups and Audit open

| | |
| --- | --- |
| **Step** | Click **Administration Home**. Then **Users & Groups**. Then **Audit**. |
| **Expected** | Each loads a real, populated screen — a heading, content, and the sidebar entry highlighted as current. No 403, no "access denied", no error, no empty shell, no spinner that never resolves. |
| **Also check** | No browser console errors (press F12 → Console) if you are comfortable doing so. |
| **PASS / FAIL** | |

---

### Check 6 — Roles & Access and Security Status open

| | |
| --- | --- |
| **Step** | Click **Roles & Access**. Then **Security Status**. |
| **Expected** | Both load real, populated screens, as in Check 5. |
| **Why separately** | These two reach the deepest authorisation paths of the eight, so they are the ones most worth opening by hand. |
| **PASS / FAIL** | |

---

### Check 7 — Phone width

| | |
| --- | --- |
| **Step** | Narrow the browser to roughly phone width (or open the site on your phone). Open the navigation. |
| **Expected** | All **eight** entries are reachable and readable. Labels are not cut off or overlapping. If the list is taller than the screen, it **scrolls** and you can reach **Audit** at the bottom. Tapping an entry navigates and the menu closes. |
| **PASS / FAIL** | |

---

### Check 8 — The System Administrator is unchanged

| | |
| --- | --- |
| **Step** | Sign out. Sign back in as the **existing genuine System Administrator** account. |
| **Expected** | Their navigation is **exactly as it was before this change**. In particular **Identity & SSO**, **System Health** and **Integrations** are all **still present**, and all three still open. The other product areas they saw before are still there. |
| **Why** | CL-12 is about one role. If anything moved for the System Administrator, it moved as a side effect, and that fails the gate. |
| **PASS / FAIL** | |

---

## 8. Negative, refusal and security cases

Check 4 and Check 8 **are** the security cases, and they are the two that matter:

- **Check 4** — the three platform destinations stay out of the Organisation
  Administrator's sidebar.
- **Check 8** — nothing was taken away from, or given to, the System
  Administrator.

**A refusal case is deliberately NOT in this script.** Asking you to type a
platform URL into the address bar as an Organisation Administrator would test
route authorisation, which **CL-12 did not change** — the same refusal happened
before this deployment and will happen after it. It is covered by the automated
suite and by the pre-deployment production probe recorded in the covering
report. Running it by hand here would produce evidence about yesterday's
behaviour and invite the reading that CL-12 caused it.

## 9. Visual and UX checks

Built into Checks 2, 3, 5, 6 and 7 rather than listed apart, because they are
the point of this unit: spelling and capitalisation, icons present, no raw
policy keys or internal terminology on screen, no truncation or overflow, the
current entry highlighted, order correct, and the whole thing usable at phone
width.

If your environment uses a dark theme, repeat Check 3 in it.

## 10. Evidence to capture

1. Screenshot of the sidebar at normal desktop width showing all eight entries
   (Checks 2, 3, 4 in one image).
2. Screenshot of any **one** opened screen from Check 5 or 6.
3. Screenshot of the sidebar at phone width (Check 7).
4. Screenshot of the System Administrator's sidebar showing Identity & SSO,
   System Health and Integrations still present (Check 8).
5. This script with PASS / FAIL filled in.

## 11. PASS / FAIL

A field is provided on every check above.

## 12. What CANNOT currently be tested, and why

| Not observable | Why |
| --- | --- |
| **That a Product Owner's browser sees this** — until you run it | The automated suite proves the menu *data* is correct for each role, and the production probe proves every destination exists and refuses anonymous callers. **Neither is a rendered screen.** This script exists precisely because a passing test is not an observed result. |
| **Whether the three platform destinations refuse an Organisation Administrator *in production*** | Establishing it needs a genuine Organisation Administrator session driven against production. The automated suite proves it (A8, redirect to `auth.access-denied`), and the production probe proves the routes exist and fail closed to anonymous callers. Proving it live would mean either manufacturing an account — which is forbidden — or asking you to do something CL-12 did not change. **Carried, not inferred.** |
| **An inactive Organisation Administrator being refused** | Would require deactivating a genuine person to watch it happen. That is real, permanent organisational history created to satisfy a test. **NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA** — automated evidence retained (A12). |
| **An Auditor seeing no System Administration navigation** | Needs a genuine Auditor account. If one exists, this is worth adding as a ninth check; if not, do **not** create one. Automated evidence retained (A10) — this is the single assertion that would catch the rejected class-derived implementation. |
