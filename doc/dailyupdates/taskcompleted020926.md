# Daily Development Handover — 2 September 2026

**Project:** SemantIQ v2.2
**Current phase:** Phase 1 — P1-03 Users & Groups
**Status at end of day:** **P1-03 READY FOR PRODUCT OWNER TEST**
**`main` at end of day:** `4ed347b`
**Production:** https://semantiq.claas2saas.com — deployed and healthy

This is a technical handover and restart checkpoint, not a meeting summary.
**Failures are retained even where they were subsequently fixed**, because the
failures are the evidence that the gates work. Today produced a lot of them, and
again the most useful ones are the tests that were wrong before they were right.

Three units moved today: **P1-01 was accepted**, **P1-02 was built, tested,
corrected and accepted**, and **P1-03 was planned, designed, built, deployed and
handed over.** Eleven pull requests.

---

## 1. Starting state

`main` at `4f6659a`. P1-01 scope completion was deployed and awaiting Product
Owner testing, with the D-24 and D-25 sections of its script unrun.

---

## 2. Product Owner decisions made today — thirteen

### P1-02 — Identity & SSO

| Decision | Outcome |
| --- | --- |
| **D-26** — Session policy | **APPROVED: read-only.** No editable session setting in Release 1 |
| **D-27** — Tenant and client identifiers | **APPROVED: masked, with an explicit Reveal** |
| **D-28** — Provider enable / disable | **APPROVED: no control in Release 1** |
| **D-29** — Login Experience | **APPROVED: no editable setting in Release 1** |
| **D-30** — Health history | **APPROVED: cache only.** No audit table — P1-08 owns durable storage |
| **D-31** — Idle timeout | **APPROVED: enforce 60 minutes.** Closes a live contradiction — see §5 |
| **D-32** — Signing keys on a forced refresh | **APPROVED: fetch, validate, then replace.** Never delete a known-good cache before the network call |

### P1-03 — Users & Groups

| Decision | Outcome |
| --- | --- |
| **D-33** — User onboarding | **APPROVED: Option A** — the administrator enters the Object ID. **The plan recommended Option B and the Product Owner declined it**, and was right to |
| **D-34** — SemantIQ profile fields | **APPROVED: none in Release 1** |
| **D-35** — Groups | **APPROVED: flat, SemantIQ-owned, no group-derived access** |
| **D-36** — User deactivation | **APPROVED: always permitted**, with the dependency summary shown first. No force-sign-out in P1-03 |
| **D-37** — Directory identifiers | **APPROVED: reuse the P1-02 D-27 reveal.** No second mechanism |
| **D-38** — Screen structure | **APPROVED: two tabs**, Users and Groups |
| **D-39** — Guarded purge | **APPROVED**, far narrower than D-24's |

**D-33 is the one to remember.** Recorded in the Product Owner's words: *"I do
not want to weaken the P1-00 rule that immutable Entra Object ID is the identity
key. The current schema and P1-00 design deliberately exclude email from the
identity key because email can change or be reassigned."* Option B's convenience
was real, and so was the weakening it required. **No Graph permission was added.**

### Acceptances

| Unit | Outcome |
| --- | --- |
| **P1-01 Organisation** | **ACCEPTED** |
| **P1-02 Identity & SSO** | **ACCEPTED**, after one defect the Product Owner found in the browser — §6 |

---

## 3. What was merged today — eleven pull requests

| PR | SHA | What |
| --- | --- | --- |
| #75 | `a486c40` | Confirm a successful write, instead of saying nothing |
| #76 | `f3d801b` | **P1-01 ACCEPTED**, and the P1-02 PLAN |
| #77 | `0f0a45b` | P1-02 DESIGN — Identity & SSO (documentation only) |
| #78 | `8a81283` | **P1-02 EXECUTE**, with D-31 and D-32 |
| #79 | `ced998f` | P1-02 — record the deployed build and the production reading |
| #80 | `6708f80` | **Make the authentication buttons readable once visited** |
| #81 | `3bfbfb4` | Record the production evidence for the CTA fix |
| #82 | `bc18725` | **P1-02 ACCEPTED**, and the P1-03 PLAN |
| #83 | `9e67749` | P1-03 DESIGN — Users & Groups (documentation only) |
| #84 | `cb73f14` | **P1-03 — Users & Groups** (the implementation) |
| #85 | `4ed347b` | P1-03 — deployed build and production observation |

All deployed. Deploy runs #99–#108, all successful.

---

## 4. What P1-03 delivered

| | |
| --- | --- |
| Schema | `groups`, `group_memberships` |
| Module | `App\Modules\People` — 2 services, 2 controllers, 3 models, 1 violation type |
| Screens | Users list, person record, Groups list, group record |
| Routes | 16 under `/console/people`, behind `RequireSystemAdministrator` and `RequireOrganisation` |
| Shared | `PurgeDependencies` moved to `App\Shared\Lifecycle` |
| Tests | **453 passing, 6,772 assertions** |
| Mutations | **70 applied, 70 caught** |
| Browser | **60 screen renderings** — 15 states × 2 themes × 2 widths |

**Nothing in the unit grants anything.** `platform_role` is read by exactly one
guard and written only as literal `null`.

### The five DESIGN-review corrections, all applied

1. **The route collision was removed structurally**, not by declaration order.
   `/console/people/users` and `/console/people/groups` are both static roots,
   and `PeopleRoutingTest` re-matches the whole set against a **reversed** route
   collection to prove order is irrelevant. `whereNumber` is now defence in depth.
2. **No action can leave zero active System Administrators.** The count is
   re-read inside the write transaction with a locking read.
3. **`PurgeDependencies` moved; `RequireOrganisation` did not** — it depends on
   `OrganisationService`, so promoting it would make Platform depend backwards.
4. **Same-day rejoin works** — three membership periods on one calendar day.
5. **The physical schema is asserted** through `Schema::getColumnListing`.

---

## 5. P1-02's finding worth carrying forward — D-31

`EnsureSessionIsCurrent` declared `IDLE_MINUTES = 60` that **nothing ever read**,
while Laravel's own `session.lifetime` quietly enforced **120** in production.
The approved policy and the running system disagreed for as long as that constant
existed to reassure anyone who looked at it.

D-31 removed the constant, moved the timeout to `config/session.php`, and made
the identity health check compare the running value against the approved one. The
deploy workflow now brings `SESSION_LIFETIME` to the approved policy on every
deployment.

**The lesson is the general one:** a constant that documents an intention nobody
enforces is worse than no constant, because it answers the question wrongly.

---

## 6. Defects found today — kept, not smoothed over

### 6.1 The one the Product Owner found — authentication buttons invisible once visited

After signing out, the call-to-action buttons on the authentication screens were
unreadable. **CI was green.** The cause was a global `a:visited` rule winning the
cascade over the button's own colour.

Proving it was harder than fixing it, and the method matters: `getComputedStyle`
**lies about `:visited`** for privacy reasons, and Playwright's `newContext()` is
incognito-like, which disables `:visited` entirely. A control page proved the
headless browser never applied it at all. The fix was verified with a
specificity-equivalent stand-in measuring 1.00:1 in both themes, and
`AuthCallToActionVisibilityTest` now computes CSS specificity so the failure
class cannot return.

### 6.2 Two P1-03 defects found by adversarial reading, not by a failing test

- **A membership from another group could be ended through this group's address.**
  Both ids bind independently and nothing said they belonged together. The
  administrator would have seen *"Membership ended."* where nothing changed,
  while somebody elsewhere silently left a group.
- **Every People record route was reachable by id regardless of organisation.**
  Release 1 has one organisation, which is exactly why it is now asserted rather
  than assumed. A numeric id in the address bar was the whole attack.

### 6.3 Four mutations survived on the first attempt — three were weaknesses in the tests

| Mutation | Why it survived |
| --- | --- |
| Render an empty cell instead of *Not signed in yet* | The assertion read the screen's **raw source**, and the file's own docblock quotes the phrase. It was matching the comment, not the rendering — **a guard that could not fail** |
| Move the sole-administrator check outside the transaction | The assertion was `transactionLevel() > 0`. `RefreshDatabase` wraps every test in its own transaction, so **the test was measuring the harness** |
| Remove the purge re-check inside the transaction | Same cause |
| Key membership on `(group_id, user_id, joined_at)` | A flaw in the **mutation**: the overlap guard repaired the collision, so P1-01's failure was never reproduced. Restated faithfully with DATE-valued timing, and caught |

The first fix was applied to **every** screen-source assertion through a shared
`Tests\Support\ScreenSource` helper, not only to the one that was caught.

### 6.4 The professional-polish gate found four things CI passed over

Three were layout. The fourth is the one worth keeping: **the Inactive status
pill measured 4.40:1 in the light theme**, against a floor of 4.5. It painted
`--chrome-muted` on `--chrome-hover` — tokens for the dark chrome, not for a light
card. It had been in the product **since P1-01**, on a component every list uses,
and it is close enough to passing that no amount of looking would have caught it.
Found by measuring computed colour against the surface it is actually painted on.

### 6.5 A test that failed by coin flip, and a CI step that proved nothing

Both are outside P1-03's scope and both were fixed rather than worked around.

- **`test_the_secret_is_reported_as_presence_only`** asserted that
  `strlen(SECRET)` — **33**, a two-character needle — does not appear in the
  response body. The body also carries Inertia's random 32-hex asset-version
  hash. Roughly one build in three contains `33`. Building the frontend for the
  P1-03 screens landed on one of those. **Re-running would sometimes have
  passed, which is worse than failing.** The length is now measured against the
  props, with the framework's version hash removed.
- **`php artisan test <path>` exits 0 when the path matches nothing.** The new
  MySQL People step would have stayed permanently green after a rename, running
  no tests at all. It now fails unless at least forty tests ran.

### 6.6 One gap found by reading the test script back against the code

Step 21 asks the Product Owner to create a duplicate group **deliberately** — and
a duplicate name raised a database integrity error. They would have been handed a
constraint violation for following the script. Duplicate names and codes are now
refused in business language, with the constraint still the real guard.

### 6.7 The browser verification caught its own measurement error — twice

The first session cookie was missing the framework's `CookieValuePrefix`, so
every request fell through to the entry page. The `h1` assertion caught it. This
is the **same** mistake the P1-02 audit made, where it reported zero defects on
five screens it had never seen. Without that assertion, today's verification
document would have reported clean results on sixty screens it never measured.

---

## 7. What the automated evidence cannot observe — stated, not implied

| # | Not observable | Where it is covered |
| --- | --- | --- |
| 1 | The `FOR UPDATE` clause on the two locking reads | **SQLite compiles `lockForUpdate()` to nothing.** CI now runs `tests/Feature/People` against **MySQL 8.4** as a second step, where the clause exists and the assertion fires. The SQLite run says so in the test itself |
| 2 | A genuine concurrent race | Not reproducible in a single-process suite. What is asserted is the property that makes it impossible |
| 3 | Whether an Object ID names a real person | Nothing in SemantIQ can check it. The first sign-in is the only proof |
| 4 | A clean browser console | **The verification container cannot reach the Google Fonts CDN** — its egress proxy resets the connection, evidenced by the proxy's own failure log. Every screen logged one error that is not SemantIQ's. **Carried to step 56 of the test script** |

---

## 8. Production state at end of day

Read anonymously over HTTPS with cache-busting. **Nothing was signed into, and no
production data was read, created or changed.**

| Request | Observed |
| --- | --- |
| `/console/people/users` | **302 → entry page** — delivered and gated |
| `/console/people/groups` | 302 → entry page |
| `/console/people/users/1` | 302 → entry page |
| `/console/people/users/999999` | 302 → entry page — **identical to a real id** |
| `/console/people` | 302 → entry page |
| `/console/people/users/groups` | **404** |
| `/console/people/groups/users` | **404** |
| `/console/people/users/users` | **404** |
| `/console/people/groups/groups` | **404** |

**The four 404s beside four 302s are the point:** they are *different answers*,
which is what shows the record routes are constrained rather than merely declared
in a lucky order. Negative case 15, observed in production rather than inferred.

Production still holds **one user account**. Nothing created a second.

---

## 9. Carried verification gates

`PHASE-1-PLAN.md` §10 exists so a gate a unit cannot execute is recorded rather
than quietly lost. **Two of P1-02's had reached only its test script and never
that register** — which is exactly how one gets lost. Both were added today.

| From | To | Gate | Status |
| --- | --- | --- | --- |
| P1-01 | **P1-03** | Live multi-user management-cycle refusal | **Open — closable now.** Script step 43 |
| P1-02 | **P1-03** | A real non-administrator refused at Identity & SSO | **Open — closable now.** Steps 44–45. Every user P1-03 creates has no role, so no setup is needed |
| P1-02 | **P1-05** | Provider-wide Re-check limit, with two administrators | **Moved**, by Product Owner decision. P1-03 cannot assign a role to anybody |

---

## 10. What must NOT happen next

- **Do not start P1-04.** P1-03 has not been accepted.
- **Do not create a production user by hand**, reopen bootstrap, or write to
  MySQL. The Product Owner provisions one genuine colleague through the screens.
- **Do not manufacture a second System Administrator.** `platform_role` cannot be
  assigned by P1-03 at all, and the gate that needed one has moved to P1-05.
- **Do not add a Microsoft Graph permission.** D-33 = A depends on its absence.
- **Do not reopen** D-26 through D-39.
- **Do not treat a green CI run as acceptance.**

---

## Resume Here Next Session

The Product Owner is running the P1-03 test plan. Nothing is required from the
delivery side until the results come back.

### What the Product Owner does

**The script is `doc/v2/phase-1/P1-03-USERS-GROUPS-PRODUCT-OWNER-TEST-SCRIPT.md`.**
An interactive checklist of the same 56 steps — tickable, with notes and a
copyable report — is published at
<https://claude.ai/code/artifact/27e8e352-41ee-43df-9573-321b22461fda>.

**Before step 1, two things:**

1. **Get one genuine colleague's Entra Object ID** — <https://entra.microsoft.com>
   → Identity → Users → All users → their record → **Overview** → **Object ID**.
   **Copy it, do not retype it.** One wrong character produces a record that
   looks perfect and that the person can never sign in to, and **SemantIQ cannot
   tell them** — it has no permission to read the directory.
2. **Read the permanence warning.** Once that person signs in, their record can
   **never** be removed, only deactivated. Same for any group anybody has ever
   joined. Step 14 supplies a deliberately unusable Object ID
   (`00000000-…-0001`) so permanent removal can be exercised safely.

**Then the six parts:** A finding the screen (1–4) · B adding a person (5–13) ·
C the duplicate and permanent removal (14–18) · D groups (19–29) · E ending
access (30–34) · F search and filter (35–40). Then refusals and the carried
gates (41–47), and the visual checks (48–56).

**Three steps carry more weight than the rest:**

- **34** — try to deactivate your own account. It must refuse. That guard is what
  stands between the deployment and having nobody who can administer it.
- **43, 44–45** — the two carried gates. They need the colleague, which is why
  they waited for this unit. **Record the refusal at 43 verbatim.**
- **56** — the browser console. This is the one check the delivery side could not
  answer (§7). An error naming `fonts.googleapis.com` is that same limitation;
  **anything else is a finding.**

### What comes back

PASS / FAIL per step, the verbatim refusals for E4 and E5, confirmation that the
colleague could sign in and was refused System Administration, and the acceptance
decision.

### Then, and only then

P1-03 receives explicit acceptance, or a defect list. **P1-04 does not unlock
until acceptance is given.**

---

## 11. Evidence references

| Item | Reference |
| --- | --- |
| P1-03 implementation | PR #84, `cb73f14`, deploy #108 |
| P1-03 production observation | PR #85, `4ed347b` |
| Verification, survivors, browser findings | `P1-03-USERS-GROUPS-VERIFICATION.md` |
| All 70 mutations | `P1-03-MUTATIONS.md` |
| The Product Owner's steps | `P1-03-USERS-GROUPS-PRODUCT-OWNER-TEST-SCRIPT.md` |
| P1-02 acceptance and the CTA defect | `P1-02-IDENTITY-SSO-VERIFICATION.md` |
| D-26 to D-32 | `P1-02-IDENTITY-SSO-PLAN.md` §-decisions |
| D-33 to D-39 | `P1-03-USERS-GROUPS-PLAN.md` §18 |
| Carried gates | `PHASE-1-PLAN.md` §10 |
| Delivery rules | `CLAUDE.md` |
