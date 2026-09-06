# P1-05 — Roles & Access: VERIFICATION

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing here is presented as one.

| | |
| --- | --- |
| PLAN merge SHA | `c313a39652681bc198045fa1c175e2e70964c9be` |
| DESIGN merge SHA | `73f62bc5ffa3beab17975e8107698f48f4f20f1c` |
| Status | **Gate C blocker corrected. NOT MERGED, NOT DEPLOYED** — awaiting final approval |

**One blocker was found at Gate C review and is fixed here.** Self-granting a
**domain entitlement** did not require step-up: `AccessController::grantEntitlement`
called `EntitlementService::grant` directly, so an Access Administrator could
widen their own reach into a business domain with no re-authentication. The
role half of D-73 had shipped; the entitlement half had not. **It was found by
the Product Owner, not by any test here** — §8 records what that means.

---

## 1. The test suite

| | |
| --- | --- |
| Tests | **641** |
| Passing | **637** |
| Failing | **0** |
| Skipped | **4** |
| Assertions | **15,101** |

Fourteen of those are new, and all fourteen are the Gate C correction.

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
| `SelfEntitlementStepUpTest` | 14 | **The Gate C correction.** D-73's entitlement half, end to end through the real routes |

---

## 2. Mutation testing

**47 run. 47 caught. 0 survived.** Recorded in `P1-05-MUTATIONS.md` — thirty-four
for the build, and thirteen (**M-SE1** to **M-SE13**) for the Gate C correction.

**M-SE11 survived the new file and is caught by the existing `StepUpTest`.** It
is recorded that way rather than quietly counted, and the reason is in
`P1-05-MUTATIONS.md`: a replayed self-grant is refused twice, and the second
refusal is the database's conditional `UPDATE`, so removing the PHP replay
check leaves the new file's assertion true.

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

### MySQL caught two test defects that SQLite hid

**The first CI run of the new Access-on-MySQL step failed**, and it was right to.

Two assertions read the emitted SQL for `"ended_at" is null` and
`from "users"` — **SQLite's double-quote identifier quoting**. MySQL writes
`` `ended_at` `` and `` from `users` ``, so both passed locally and failed on
the engine production actually uses. Both are now quote-agnostic.

**Neither was a defect in the application.** Both were assertions that would
have gone on passing on SQLite forever while proving nothing about production —
which is precisely why the step exists, and it earned its place on its first
run.

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

### The Gate C correction, in the browser

Re-verified at both widths in both themes, against seeded data, signed in as a
System Administrator opening **their own** role record.

| Observed | Result |
| --- | --- |
| Another person's record shows no self-grant warning | Yes |
| The administrator's own record says, in sentence case: *"This role belongs to you. Adding a domain to your own access asks you to confirm your identity with Microsoft first, and nothing is granted until you do."* | Yes, all four viewports |
| Submitting a self-grant lands on the **step-up confirmation card**, not on a grant | Yes |
| The card says *"You are about to **grant access to yourself**"* | Yes |
| The card asks for **no credential** — no password, PIN or passcode field | Yes |
| The card offers *"Cancel and go back — nothing will be changed"* | Yes |
| No horizontal overflow; no raw codes; no page errors | Yes |
| **Database after all four journeys: 9 `self_grant` confirmations, 0 entitlements on the administrator's own assignment** | Yes |

**The verification script itself was wrong on its first run**, and it is worth
recording because it is the same failure class as everything else in this file.
It called `waitForLoadState('networkidle')` immediately after submitting.
Inertia submits over XHR, so "idle" was already true before the round trip
finished, and the script read the *previous* page — then reported, on all four
viewports, that a self-grant had not reached step-up. The database said
otherwise: four `self_grant` rows and no entitlement. The script now waits for
the navigation.

**One observation, not a defect, for the Product Owner.** The card names the
*kind* of action — "grant access to yourself" — but not the domain. That is the
approved wording (DESIGN §9, item 7) and is the same for a role self-grant, so
it has not been changed here. If an administrator should see *which* domain
they are confirming, that is a product decision to raise separately.

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
| 7 | **A completed self-entitlement step-up in a browser** | The middle step is Microsoft's. The browser was driven up to *"Continue to Microsoft"* and the cancel path; the completed grant, the stale `auth_time`, the replay and the expiry are proven in `SelfEntitlementStepUpTest` against the real routes |

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

---

## 8. What the Gate C blocker says about this suite

**Thirteen mutations, thirty-four before them, and none of them could have found
it.** Every mutation in the build run broke a guard that existed. The
entitlement half of D-73 was not a broken guard — it was an **absent** one, and
a mutation framework has nothing to delete.

That is the limit of mutation testing stated plainly: it proves the guards
present are real. It cannot tell you a rule is only half implemented. What
would have found it is reading D-73 as a sentence — *self-granting any role **or
entitlement*** — and checking both halves against the code, which is what the
Product Owner did.

The fourteen new cases and thirteen new mutations do not fix that limitation.
They fix this instance of it.
