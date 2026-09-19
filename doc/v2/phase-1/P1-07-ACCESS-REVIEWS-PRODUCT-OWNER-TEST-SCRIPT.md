# P1-07 — Access Reviews: Product Owner Test Script

**Access Reviews can change real access.** Confirming access changes nothing;
**removing it takes effect immediately and cannot be undone from this screen** —
re-granting is a separate act in Roles & Access. Please read §5 before you start.

---

## 1. Feature being tested

**P1-07 — Access Reviews.** Three screens for periodically confirming or removing
privileged and sensitive access: **Privileged Reviews**, **Domain Reviews** and
**Overdue Reviews**.

A review **confirms or removes** access that already exists. It never creates,
widens or repairs access.

## 2. Deployed build

| | |
| --- | --- |
| PLAN merge | `da79fd01947f0972f9d04f161e2c188d25b884d4` |
| DESIGN merge | `a830488f36e0c8b9040a81cae7b458f991103134` |
| Implementation merge | **`c7069f7f87fe9d7aa7d89256c39ed3de9e30430f`** |
| Deployed | **19 September 2026**, to `https://semantiq.claas2saas.com` |

> **The build above is what is live.** A test script run against a different
> build proves nothing about this one.

## 3. Preconditions

- You can sign in as a System Administrator.
- Roles & Access is reachable, so you can check what changed.
- **No review cycle has been started yet**, or you know which one is open.

## 4. Test data required

**None is created for you and none should be created by you.** The population
comes from access that already exists. On this deployment today that is a small
number of privileged assignments and no sensitive domain access at all.

## 5. ⚠️ WARNING — what this script asks, and what it will NOT ask

| | |
| --- | --- |
| **A review cycle is permanent** | Starting one creates review records. There is no delete. They remain as evidence, which is the point |
| **"Remove this access" really removes it** | It calls the same revocation the Roles & Access screen calls. The access ends at that moment |
| **A decision cannot be undone** | Reviews are decided once. A later review is a new review |

**This script will NOT ask you to:**

- create a second System Administrator, or promote anybody for testing;
- grant anybody access they should not have, so a screen has something to show;
- enter inaccurate business data.

Several checks therefore **cannot be performed on production** and are listed in
§11 with the automated evidence that covers them instead. **That is honest
reporting, not a gap in the build.**

## 6. Numbered steps

### A — Reaching the screens

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| A1 | Sign in and look at the **System Administration** menu | **Access Reviews** is listed, and it is not marked "Soon" | ☐ |
| A2 | Click it | The **Privileged Reviews** screen opens | ☐ |
| A3 | Read the heading and the sentence under it | *"Periodic confirmation of privileged and sensitive access. Confirming access changes nothing about it; removing it takes effect immediately."* | ☐ |
| A4 | Look at the tabs | Three: **Privileged Reviews**, **Domain Reviews**, **Overdue Reviews** | ☐ |

### B — Starting a cycle

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| B1 | Before starting, note the tabs | All three show **no count**, because nothing is awaiting review | ☐ |
| B2 | Click **Start a review cycle** | The page returns with a confirmation, and Privileged Reviews now lists the privileged access that exists today | ☐ |
| B3 | Read each row | Person, role, due date, and **"You can review this as"** — each a business sentence, no identifiers | ☐ |
| B4 | Compare with **Roles & Access** | Every privileged assignment that exists appears exactly once. Nothing has been invented | ☐ |
| B5 | Click **Start a review cycle** again while reviews are outstanding | Refused in business words: *"A review cycle is already in progress. Complete the outstanding reviews before starting another cycle."* | ☐ |
| B6 | Look at **Domain Reviews** and **Overdue Reviews** | **Neither offers Start a review cycle.** One cycle covers both populations, and it is started from Privileged Reviews | ☐ |
| B7 | Look at Privileged Reviews after a previous cycle has been completed | Only the **current** cycle's reviews are listed. Completed cycles are kept permanently but are not this screen's work | ☐ |

### C — Confirming access (the safe one)

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| C1 | Note exactly what one person's access is, in **Roles & Access** | Write it down — role, and anything beneath it | ☐ |
| C2 | Return to Access Reviews and click **Confirm this access** on that row | *"Access confirmed. Nothing about the access was changed."* | ☐ |
| C3 | The row now | Reads **Access confirmed** and offers no buttons | ☐ |
| C4 | **Go back to Roles & Access and check that person again** | **Identical to C1. Nothing changed at all** — that is the whole claim of "confirm" | ☐ |

### D — Domain Reviews

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| D1 | Open **Domain Reviews** | Either rows, or *"No sensitive domain access is awaiting your review."* On this deployment today, expect the empty sentence | ☐ |
| D2 | If empty | That is **correct** — nobody currently holds confidential, restricted or whole-domain access. It is not a failure | ☐ |
| D3 | If rows appear | Each shows the domain, what records the access reaches **in words**, and the most sensitive information it allows | ☐ |
| D4 | Look for business records | **There are none.** No customer, no invoice, no row of data anywhere on this screen | ☐ |

### E — Overdue Reviews

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| E1 | Open **Overdue Reviews** | *"Nothing is overdue."* — the cycle you started is not yet due | ☐ |
| E2 | Read the description | It says plainly that **nothing is removed automatically** | ☐ |
| E3 | Confirm your understanding | Being overdue is **visibility only**. No access is ever removed by a timer | ☐ |

### F — Negative, refusal and security cases

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| F1 | Open a review, then in another tab decide it, then decide it again in the first | The second attempt is refused: *"This review has already been decided."* | ☐ |
| F2 | Try to remove your **own** System Administrator access while you are the only one | You are asked to **confirm your identity with Microsoft first**, and then it is **refused** — the last administrator cannot be removed | ☐ |
| F3 | Edit the address bar to a review id that does not exist | You are refused. **The refusal is the same one you would get for a review you are not allowed to see** — it never tells you whether the review exists | ☐ |
| F4 | Look at any refusal message | It names no person and no access you are not permitted to see | ☐ |

### G — Visual and UX checks

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | :---: |
| G1 | Use the light/dark switch on each of the three tabs | Everything readable in both. No text disappears into its background | ☐ |
| G2 | Narrow to roughly phone width, or open on a phone | The tabs wrap onto several lines and **all three names read in full**. None is cut off, and the page does not scroll sideways | ☐ |
| G3 | Still at phone width, tap **each** of the three tabs | Every one opens its screen. No tab is present but unreachable | ☐ |
| G4 | Move between tabs, then press browser **Back** | You return to the previous tab and the content changes with it | ☐ |
| G5 | Read every screen as a customer would | No developer terminology — no "entitlement", "grant path", "enum", "null" | ☐ |
| G6 | Look at a decided row | It says **what happened in words** — "Access confirmed" or "Access removed" — never a code | ☐ |
| G7 | Look for a number that scores your reviews | **There is none**, deliberately | ☐ |

## 7. Visual and UX checks

Covered by section G. If any of G1–G7 fails, please record it as a **FAIL with a
screenshot** rather than a note — the two defects found during verification were
both found by looking rather than by testing.

## 8. Evidence to capture

1. A screenshot of each of the three tabs, desktop and phone width.
2. The Roles & Access "before" and "after" for step C — the proof that confirming
   changed nothing.
3. The exact wording of any refusal.
4. Anything that reads oddly, even if you cannot say why.

## 9. PASS / FAIL

| | |
| --- | --- |
| **Overall result** | ☐ PASS ☐ FAIL |
| Tested by | |
| Date | |
| Build tested | |

**Do not mark the unit passed if any step in section F failed** — those are the
security cases.

## 10. If something looks wrong

Record **what you saw**, not what you think caused it. Two things are **expected
and are not defects**:

- **Domain Reviews is empty.** Nobody currently holds confidential, restricted or
  whole-domain access. A screen inventing rows would be lying.
- **Your own privileged review can be decided by you.** You are the only System
  Administrator, so nobody else could. It requires confirming your identity with
  Microsoft and is recorded distinctly as a self-review — see §11.

## 11. NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA

**These cannot be exercised on production without creating false or misleading
permanent history.** Each keeps the automated evidence that covers it. **None is
an implementation defect.**

| What cannot be observed | Why not | Automated evidence |
| --- | --- | --- |
| **Removing access and seeing it disappear** | Needs a real grant, to a real person, that should genuinely go | `RevokeChangesEffectiveAccessTest` |
| **Two grants to one person, one removed and one kept** | Needs two real grants | `IndependentPathsTest` |
| **Somebody else reviewing your access** | Needs a genuine second privileged person. **One will not be created for a test** | `ReviewerAuthorityTest`, `AuthorityRevalidatedOnSubmitTest` |
| **A domain owner performing a review** | An owner cannot reach the screen yet: the sidebar is shown to System Administrators only (D-19). **Carried forward** | `ReviewAuthorityTest`, `OwnershipGrantsNoAccessTest` |
| **Two reviewers deciding at the same moment** | Not performable by one person in a browser | `ReviewConcurrencyTest`, run against **MySQL** in CI |
| **Removing the last System Administrator** | Would mean removing your own access with nobody to restore it. F2 shows the **refusal**, which is the safe half | `LastAdministratorRefusedFromReviewTest` |

### 11.1 Anything else that cannot be tested

- **Visibility itself cannot be asserted by any automated test in this project.**
  There is no JavaScript test runner. Every check in section G is a **human**
  check, which is why they are in this script rather than in the suite.
- **Whether Microsoft asks for a second factor is your Entra policy**, not
  something SemantIQ can claim. SemantIQ requires a **fresh sign-in**; what
  happens at Microsoft is configured there.

---

# ADDENDUM — Gate D final correction: ONE RETEST

## A1. Feature or task being tested

The last-administrator refusal, reached **from Access Reviews**. Previously it
returned you to **Roles & Access**; it must now return you to **Access Reviews**
and show the refusal there.

## A2. Deployed build / merge SHA

Recorded in `P1-07-ACCESS-REVIEWS-VERIFICATION.md` §15 and in the reply that
accompanied this addendum.

## A3. Preconditions

- You are signed in as the **System Administrator**.
- A review cycle is in progress and **your own System Administrator access** is
  listed on **Privileged Reviews** as *Awaiting review*.
- You are still the **only** active System Administrator. If a second one has
  since been added, this refusal will not occur and the retest does not apply —
  say so rather than removing anybody.

## A4. Test data required

**None.** Nothing is created and nothing is entered.

## A5. ⚠️ WARNING — permanence

**This step is expected to be REFUSED, and it changes nothing.** Your System
Administrator role is not removed, the review stays *Awaiting review*, and no
permanent record of a decision is written.

The one thing it **does** consume is the Microsoft confirmation itself. That is
deliberate: one confirmation authorises one attempt. If you want to try again
you will be asked to confirm with Microsoft again.

## A6–A7. Numbered steps, and what must happen

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| 1 | Open **Access Reviews → Privileged Reviews** | Your own **System Administrator** review is listed, marked *Awaiting review*, with **Confirm this access** and **Remove this access** | |
| 2 | Click **Remove this access** | You are taken to the confirmation card, which names the action before sending you anywhere | |
| 3 | Continue, and complete the **Microsoft** sign-in | Microsoft asks you to sign in freshly | |
| 4 | Observe where you land | **Access Reviews → Privileged Reviews.** **NOT** Roles & Access | |
| 5 | Read the banner | **Refused.** *This is the only active System Administrator. Add or retain another before removing this one.* | |
| 6 | Look at your review row | Still **Awaiting review**, with **both** buttons still offered | |
| 7 | Open **Roles & Access** | Your **System Administrator** role is **still there**, unchanged | |
| 8 | Press the browser **Back** button | You are not returned into a half-finished confirmation. Starting again asks you to confirm with Microsoft again — the spent confirmation cannot be reused | |

## A8. Negative, refusal and security cases

Step 4 **is** the refusal case. Steps 6, 7 and 8 are the security cases: nothing
decided, nothing removed, nothing replayable.

## A9. Visual and UX checks

| # | Check | PASS / FAIL |
| --- | --- | --- |
| V1 | The refusal reads as a refusal — red left edge, **Refused.** label, plain business English | |
| V2 | No success message appears beside it | |
| V3 | The sentence is complete and not cut off, on your normal window **and** on a narrow window | |
| V4 | No technical words on screen — no codes, no identifiers, no error text | |

## A10. Evidence to capture

1. The screen you land on after Microsoft, **including the address bar**.
2. The refusal banner.
3. The review row, still *Awaiting review*.
4. **Roles & Access**, showing your role still present.

## A11. Anything that cannot currently be tested, and why

- **Everything in §11 of the main script still stands.** Nothing in this
  correction makes any of it observable.
- **A second System Administrator has NOT been created** to make anything
  testable, and must not be.
- **P1-02's provider-wide SSO re-check remains OPEN / CARRIED / UNVERIFIED.**
