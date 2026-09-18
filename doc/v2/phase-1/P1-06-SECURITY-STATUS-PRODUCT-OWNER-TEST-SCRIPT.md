# P1-06 — Security Status: Product Owner Test Script

**Everything below is read-only.** Security Status has four screens and every
one of them is a GET: there is no button, no toggle, no acknowledgement and no
setting anywhere on them. **Nothing you do while following this script can
change your deployment.**

---

## 1. Feature being tested

**P1-06 — Security Status.** Four read-only screens that report where this
deployment currently stands: **Secure Baseline**, **Privileged Access Health**
(which includes per-domain posture), **Exceptions** and **Security Events**.

The unit **reports**. It does not enforce, configure or remediate. Every control
it names is enforced on the screen that owns it, and every row links there.

## 2. Deployed build

| | |
| --- | --- |
| PLAN merge | `4d93228c1aa2a603f28db1564a6b6304c4b97ba8` |
| DESIGN merge | `0559315e632d04c071727bc5c8176a2dba475999` |
| Implementation merge | **`a798ded381c197279522feffa8f70e7542391649`** |
| Approved head | `bd498a0148bc963a70ce50a08558afa61f5291af` (Gate C approved 17 September 2026) |
| Deployed | **17 September 2026**, deploy run 127, to `https://semantiq.claas2saas.com` |
| G2 correction merge | **`f282571f9dbb24cb1d49c8d8249d350c18b3763f`** |
| G2 correction deployed | **18 September 2026**, deploy run 129 |

> **The build above is what is live.** A test script run against a different
> build proves nothing about this one.
>
> The G2 correction is a responsive style rule only. It changes **no** posture
> result, refusal or wording, so sections A–F are unaffected and need not be
> re-run. Sections **G2 and G2a** are the ones this build is asking you to
> re-test.

## 3. Preconditions

Before you start, these must already be true:

1. You can sign in to SemantIQ with your Microsoft account.
2. You hold the **System Administrator** role.
3. P1-05 Roles & Access is accepted and deployed — it is.
4. The deployment has **one active System Administrator** (you), **no domain
   entitlements**, **no scopes** and **no sensitivity ceilings**. That is the
   state Product Owner acceptance left production in on 15 September 2026, and
   several steps below depend on it.

## 4. Test data required

**NONE. Do not create any.**

This script asks you to create no organisation, no person, no group, no domain,
no role assignment, no entitlement, no scope and no sensitivity ceiling.

## 5. WARNING — what this script will NOT ask you to do, and why

SemantIQ has **no hard delete** for several of the records a posture screen
reports on. More importantly, a posture screen is only worth reading if it
describes your **real** deployment.

> ### You must NOT create data in order to make a row change colour.
>
> Specifically, **do not**:
>
> - **create a second System Administrator** to see the administrator count turn
>   green — this is the carried P1-02 gate, and manufacturing one is exactly
>   what has been refused since P1-02;
> - **grant yourself or anyone else an entitlement, scope or Restricted
>   ceiling** to make the counts non-zero;
> - **remove the last administrator, unregister step-up, or disable a domain**
>   to see a red state;
> - **deactivate a real person** to watch a count move.
>
> Every one of those would put false organisational structure into permanent
> production history to satisfy a test. **Section 11 lists what cannot be
> observed because of this, and points at the automated evidence instead.**

## 6. Numbered steps, with the expected result for each

### A — Reaching the screens

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| A1 | Sign in and look at the left-hand menu under **System Administration** | **Security Status** appears as an ordinary menu item — not greyed out, and with no "Soon" label beside it | ☐ |
| A2 | **Click** Security Status (do not type a URL) | The **Secure Baseline** screen opens | ☐ |
| A3 | Look at the top of the page | A single status badge in words — one of **Healthy**, **Needs attention**, **Not verified** or **Act now** — and beside it a sentence counting how many controls were reported and how many are **not verified** | ☐ |
| A4 | Read that sentence carefully | It says how many are **not verified**. It does **not** say "All clear" or anything like it | ☐ |

### B — Secure Baseline

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| B1 | Read down the list | Every row is a plain-English statement — *"Everybody signs in with Microsoft"*, *"Sessions expire and are re-checked on every request"*. No codes, no abbreviations, no words like `not_applicable` or `access.step_up` | ☐ |
| B2 | Find **"Sign-in is reachable right now"** | **Not verified**, explaining that no live check has been run on this deployment | ☐ |
| B3 | Find **"Microsoft accepts the return from a fresh sign-in"** | **Not verified**, explaining that SemantIQ cannot check from the inside whether Microsoft accepts the return address | ☐ |
| B4 | Find the row above it, **"Privileged changes need a fresh Microsoft sign-in"** | **Healthy** — SemantIQ's own side is configured. **These two rows are deliberately separate.** One being healthy must not make the other healthy | ☐ |
| B5 | Find the three rows about **encryption**, **internal files** and **backups** | All three are **Not verified**, and each says the control applies but SemantIQ has no way to confirm it. **None of them says "not applicable"** | ☐ |
| B6 | Find **"Provider-wide sign-in re-check"** | **Not verified**, saying it needs a second permanent System Administrator, that one has not been created deliberately, and that nothing is known to be wrong | ☐ |
| B7 | Click any **"Managed in …"** link | It opens the screen that owns that control — Identity & SSO, Roles & Access, Users & Groups or Business Domains | ☐ |
| B8 | Look for anything you could switch, tick, dismiss or acknowledge | **There is nothing.** No toggle, no checkbox, no button | ☐ |

### C — Privileged Access Health

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| C1 | Open the **Privileged Access Health** tab | **"Active System Administrators"** reads **Needs attention**, saying only one remains and recommending another to reduce lockout risk | ☐ |
| C2 | Read the rest of the upper list | Rows about administrators holding business access, access that grants nothing, the fresh-sign-in requirement, and the check that refuses people who have left | ☐ |
| C3 | Scroll to **"Worth knowing"** | A separate panel of **counts** — Organisation Administrators, Restricted grants, preserved access, domain owners, whole-domain grants. **Each carries a number and an explanation, and NONE carries a status badge** | ☐ |
| C4 | Read the sentence under the "Worth knowing" heading | It says these are **counts, not findings**, describing access granted deliberately and working as intended | ☐ |
| C5 | Scroll to **"By business domain"** | Each domain appears in its own card with its own status | ☐ |
| C6 | Find a **disabled** domain, if you have one | It reads **Healthy**, saying *"Disabled — nobody reaches its information"*. **A switched-off domain is not reported as a problem** | ☐ |
| C7 | Read the sentence under "By business domain" | It says **owning a domain grants nothing** — being accountable for information and being able to see it are separate | ☐ |

### D — Exceptions

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| D1 | Open the **Exceptions** tab | A list of everything that applies to this deployment and is not currently healthy, each labelled with who resolves it | ☐ |
| D2 | Compare the tab's number with the number of items listed | **They match exactly** | ☐ |
| D3 | Find the **provider-wide sign-in re-check** entry | Present, labelled **Verification incomplete** | ☐ |
| D4 | Read the **"Not counted here"** section | It explains that counts of legitimate access are shown on Privileged Access Health rather than listed as unresolved conditions | ☐ |
| D5 | Look for any way to dismiss, acknowledge or snooze an entry | **There is none** | ☐ |

### E — Security Events

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| E1 | Open the **Security Events** tab | A panel at the top saying plainly **"This is not a history"**, and that searchable history arrives with Audit | ☐ |
| E2 | Read **"What is recorded"** | **71 kinds of event**, grouped under headings like *Sign-in*, *Access changes*, *Permanent deletions* | ☐ |
| E3 | Read through the event names | Every one is plain English — *"Person permanently deleted"*, *"Identity re-confirmed"*. **None looks like `access.step_up.refused`** | ☐ |
| E4 | Read **"What can never be recorded"** | It states that an event may only carry the listed fields, that there is no field for free text, and that a password, token or one-time code therefore **cannot** be recorded even by mistake | ☐ |
| E5 | Count the fields listed | **15** | ☐ |

### F — Negative, refusal and security cases

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| F1 | Sign out. In a **private browsing window**, go straight to `/console/security` | You are sent to sign in. **No part of any Security Status screen appears** | ☐ |
| F2 | While signed out, try `/console/security/exceptions` directly | The same. No status word — no "Act now", no "Healthy" — appears anywhere | ☐ |
| F3 | Sign back in and try `/console/security/reveal` | **Not found.** There is no reveal anywhere in Security Status | ☐ |

> **F1 and F2 matter more than they look.** A posture screen is a map of where a
> deployment is weak. The refusal must give away nothing at all — not even
> whether the deployment is healthy.

### G — Visual and UX checks

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| G1 | On each of the four tabs, use the light/dark switch in the top bar | Every status is readable in both themes. No text disappears into its background | ☐ |
| G2 | Narrow the browser to roughly phone width, or open it on a phone | The tab strip wraps onto several lines and **all four tab names read in full** — *Secure Baseline*, *Privileged Access Health*, *Exceptions (n)*, *Security Events*. None is cut off at the edge, none is missing, and the page does not scroll sideways | ☐ |
| G2a | Still at phone width, tap or click **each** of the four tabs in turn | Every one of them opens its screen. No tab is present but unreachable | ☐ |
| G3 | Move between tabs, then press the browser **Back** button | You return to the previous tab, and the page content changes with it | ☐ |
| G4 | Look at the status words themselves | Each carries a word **and** a mark, so you are never relying on colour alone | ☐ |
| G5 | Read every screen as a customer would | No developer terminology. No "adapter", "evaluator", "projection", "aggregate", "enum", "null" | ☐ |
| G6 | Look for a security score or percentage | **There is none**, deliberately — a single number hides which thing is actually wrong | ☐ |

## 7. Visual and UX checks

Covered by section G above. If any one of G1–G6 (including G2a) fails, please record it as a
**FAIL with a screenshot** rather than a note — the four defects found during
verification were all found by looking rather than by testing.

## 8. Evidence to capture

Please capture, for the record:

1. A screenshot of each of the **four screens**, in the **light** theme.
2. A screenshot of **Secure Baseline** in the **dark** theme.
3. A screenshot of any one screen at **phone width**.
4. The **exact wording** of the summary sentence on Secure Baseline.
5. A screenshot of the **refusal** in step F1.
6. A note of anything that reads oddly, even if you cannot say why.

## 9. PASS / FAIL

Each numbered step above carries its own PASS / FAIL box. Please also record one
overall result, and **do not** mark the unit as passed if any step in section F
failed — those are the security cases.

| | |
| --- | --- |
| **Overall result** | ☐ PASS ☐ FAIL |
| Tested by | |
| Date | |
| Build tested | |

## 10. If something looks wrong

Please record **what you saw**, not what you think caused it. In particular, two
things are **expected** and are not defects:

- **Many rows read "Not verified".** That is the deployment being described
  honestly. Sign-in reachability has not been probed, Microsoft's acceptance of
  the step-up return cannot be seen from inside SemantIQ, and encryption and
  backups are the hosting platform's to confirm. **A screen that reported those
  as healthy would be lying.**
- **"Active System Administrators" reads Needs attention.** There is one of you.
  That is a lockout risk worth seeing, it is not a fault, and nothing is
  blocked by it.

---

## 11. NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA

**These cannot be exercised on production without creating false or misleading
permanent history.** Each is listed with the automated evidence that covers it
instead. **None is an implementation defect** — every one is a consequence of a
correctly restrained production environment, which is a healthy thing for a
product at this stage to be.

| What cannot be observed | Why not | Automated evidence |
| --- | --- | --- |
| **Administrator count reading Healthy** (two or more) | Requires a second **permanent** System Administrator. Manufacturing one is refused, and has been since P1-02 | `PrivilegedAccessPostureTest::test_the_administrator_count_has_all_three_branches` proves all three branches against fixtures |
| **Administrators holding business access → Needs attention** | Requires granting an administrator a domain entitlement, which would be a false grant in permanent history | `test_a_privileged_person_holding_business_data_is_attention_and_otherwise_healthy` |
| **Incomplete access → Needs attention** | Requires creating an entitlement with no scope or no ceiling | `test_an_incomplete_grant_path_is_attention_and_a_complete_one_is_healthy`, plus the reversed case |
| **Restricted and broad-scope counts above zero** | Requires real grants to real people | `test_a_restricted_grant_is_counted_and_never_changes_the_aggregate`, `test_broad_scopes_are_counted_without_a_threshold` |
| **Any "Act now" state** | Inducing one means breaking a live control — removing the last administrator, unregistering step-up, or disabling the access check | `test_the_administrator_count_has_all_three_branches`, `test_the_local_step_up_half_is_critical_when_a_route_is_missing`, `test_an_unclassified_console_route_makes_the_coverage_row_critical` |
| **What an Organisation Administrator or Auditor sees** (platform rows named but not valued) | Requires a second person holding one of those roles and **not** System Administrator | `ViewerProjectionTest::test_a_platform_value_is_not_inferable_from_anything_an_organisation_administrator_sees`, which requires **byte-identical payloads** for two deployments differing only in a hidden value |
| **Cross-domain isolation** | Requires two domains with genuinely different grant populations | `DomainPostureTest::test_one_domains_posture_never_includes_another_domains_rows`, with deliberately unequal fixtures |
| **The provider-wide sign-in re-check** | **The carried P1-02 gate itself.** Still OPEN | Unchanged. It is reported on the screen as *not verified*, which is the honest answer |

### 11.1 Anything else that cannot be tested

- **Visibility itself cannot be asserted by any automated test in this project.**
  There is no JavaScript test runner, so no CI test renders the page. Every
  check in section G is a **human** check, and that is why they are in this
  script rather than in the suite.
- **The Organisation Administrator and Auditor views cannot be reached through
  the menu at all.** The sidebar is shown to System Administrators only — a
  Phase 1 presentation rule (D-19) that predates this unit and applies to every
  delivered capability, not just this one. It is raised separately in the
  verification record; it is **not** a P1-06 defect and has not been changed
  here.
