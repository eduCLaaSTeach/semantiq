# P1-05 — Roles & Access: VERIFICATION

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing here is presented as one.

| | |
| --- | --- |
| PLAN merge SHA | `c313a39652681bc198045fa1c175e2e70964c9be` |
| DESIGN merge SHA | `73f62bc5ffa3beab17975e8107698f48f4f20f1c` |
| Status | **Implementation complete. NOT MERGED, NOT DEPLOYED** — awaiting Gate C review |

---

## 1. The test suite

| | |
| --- | --- |
| Tests | **627** |
| Passing | **623** |
| Failing | **0** |
| Skipped | **4** |
| Assertions | **15,025** |

**The four skips are deliberate and each states its reason.** They are the MySQL
lock and race measurements: SQLite has no `SELECT … FOR UPDATE`, so the locking
reads compile away entirely and a test running there would report a lock against
a guard holding none. They fire in CI's MySQL steps, which **fail if anything
skips**.

### New suites

| Suite | Cases | Subject |
| --- | :---: | --- |
| `EngineBoundaryTest` | 11 | What a role is worth alone; the P1-04 gate, all five cases; every fail-closed state |
| `ScopeUnionTest` | 11 | Several scopes union; duplicates refused; targets required and refused |
| `GrantPathIndependenceTest` | 7 | Independent paths; `decide()`/`explain()` parity; deterministic ordering |
| `AdministratorLockoutTest` | 7 | The floor of 1; both reducing operations; P1-00 recovery preserved |
| `StepUpTest` | 10 | Five actions; binding; single use; replay; expiry; three freshness failures |
| `RouteAuthorizationMatrixTest` | 8 | Every route's declared class; no payload on denial; no JavaScript authorization |
| `LifecycleTest` | 41 | Parentage; re-grant resurrects nothing; disable/re-enable preserves |
| `MigrationTest` | 6 | The D-49 data contract, both directions |
| `PresentationTest` | 34 | Reason mapping total; incomplete entitlements; D-74 on screen |
| `Architecture/AccessBoundaryTest` | 6 | One engine; the question's shape; no second model |
| `AdministratorConcurrencyTest` | 2 | **MySQL only** — the set lock and the three races |

---

## 2. Mutation testing

**34 run. 34 caught. 0 survived.** Recorded in `P1-05-MUTATIONS.md`.

**Seven survived the first run, and every one of them was a defect in a TEST
rather than in the code.** They are recorded rather than quietly fixed, because
the failure they share — an assertion passing for a reason unrelated to what it
claims to check — is the one this project keeps producing.

**One of the seven was also a product defect.** Removing the guard that stops an
Organisation Administrator granting `system_administrator` changed nothing,
because the request was diverted to step-up **before it reached the service
holding the guard** — so an Organisation Administrator was sent to Microsoft,
re-authenticated, and refused afterwards. A confirmation somebody can never
complete is a trap. The check now runs first, and both layers are tested
separately.

---

## 3. MySQL

| Measurement | Where | Status |
| --- | --- | --- |
| `migrate` on MySQL 8.4 | CI | Runs on every push |
| **`migrate → rollback → migrate` across BOTH D-49 migrations**, with a seeded administrator, asserting the administrator survives | CI | **Added by this unit.** The previous step rolled back one migration, which only undoes the column drop; the data migration's `down()` is the half that can lose somebody |
| **The administrator set is genuinely held** against a second connection | CI — `AdministratorConcurrencyTest` | Both halves: the assignment set and its owning users rows |
| **C-A** deactivation racing revocation | CI | One completes, the other gets the business refusal |
| **C-B** revocation racing revocation | CI | As above |
| **C-C** deactivation racing deactivation | CI | As above |
| **No raw database error reaches the administrator** in any race | CI | A `QueryException` fails the test explicitly |

**No MySQL is available in the development environment**, so these were written
against the P1-04 pattern and run in CI. That is stated rather than implied: the
races have not been observed locally.

---

## 4. Browser verification

Chromium, **1440×900 and 390×844**, **light and dark**, against seeded data
created through the real services.

| Checked | Result |
| --- | --- |
| Every P1-05 route serves 200 | Yes |
| **No raw reason code, enum value or field name on any surface** | Yes |
| No horizontal overflow at either width | Yes |
| No page errors | Yes |
| Console | Clean apart from `ERR_CONNECTION_RESET` on the **Google Fonts stylesheet**, which this environment's network policy blocks. **An artifact of the sandbox, not the application** — screenshots render with the fallback stack |

### Three defects found in the browser that every test passed

| # | Defect | Cause |
| --- | --- | --- |
| **1** | Pagination read **"Page of · 3 role assignments"** | The paginator was passed straight through. Laravel serialises `current_page`; the component reads `currentPage`. `lastPage` was `undefined`, which is not `<= 1`, so the full navigation rendered with the numbers missing |
| **2** | **Explanatory sentences in ALL CAPS** | `.org-hint` is uppercase-transformed and is meant for short field labels. The same defect P1-04 shipped once already |
| **3** | **"What is in the way" under an ALLOWED result** | Failed candidates from other grants were listed on a successful check. An administrator would read *"Allowed"* followed by *"the assigned scope does not include this record"* and reasonably conclude something was broken |

**Defect 3 is the one worth noting.** It was not a rendering fault — the screen
was showing true information in a place that made it misleading, which no
assertion about correctness would have caught.

---

## 5. What is NOT verified

**Stated rather than inferred from a passing test.**

| # | Not verified | Why |
| --- | --- | --- |
| 1 | **Anything in production** | Not deployed. This unit is at Gate C |
| 2 | **A live Entra step-up round trip** | Requires the second redirect URI registered in Entra — `P1-05-DEPLOYMENT-NOTE.md` §1 — and a real tenant. Every branch on this side of it is tested |
| 3 | **The three MySQL races observed locally** | No MySQL in the development environment. They run in CI |
| 4 | **Row-level filtering of real business records** | No business data exists in Phase 1 |
| 5 | **That AI and Fabric receive exactly the requesting user's access** | No AI surface exists. The contract is defined and guarded; the integration is Phase 2/3 |
| 6 | **The P1-02 SSO Re-check lock** | Needs a genuine second System Administrator. **None was manufactured** |

---

## 6. Carried gates

| Gate | Status |
| --- | --- |
| **P1-04 — the disabled-domain gate** | **Automated evidence complete.** All five cases, including *no enabled domains grants nothing*; **N-D5** breaks it as one line and is caught. **Awaiting the Product Owner's own observation** — test script §H |
| **P1-02 — provider-wide SSO Re-check** | **REMAINS OPEN and carried.** No genuine second System Administrator exists, and no account was created to close it. A fake privileged account would make the evidence worth less than leaving the gate open |

---

## 7. Production changes made by this unit

**None. Nothing has been deployed and no production data has been touched.**

The `srikanth@lithan.com` record and the `software` custom domain are both
unchanged, and remain open operational items.
