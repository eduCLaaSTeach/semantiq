# P1-02 — Identity & SSO Administration — VERIFICATION

**Unit:** P1-02 (Phase 1 delivery order 4)
**DESIGN:** `P1-02-IDENTITY-SSO-DESIGN.md` — Revision 2, **APPROVED**, merged as
`0f0a45be11b0905cb12c8abad3273aa77ea4fffd` (PR #77)
**Implementation merge SHA:** `8a812836270826ad5dd6b2e7af21cb9d15161621` (PR #78)
**Deployment:** run #102, succeeded — including the D-31 session-policy step
**Decisions implemented:** D-26 to D-32
**Status:** **PRODUCT OWNER ACCEPTED — 2 September 2026**

> **What was executed and observed**, not what was expected to be true. Where
> something is unverified, blocked or not observable, it says so and says why.

---

## 1. What was built

| Area | Delivered |
| --- | --- |
| Screens | Five route-backed tabs: Microsoft Entra ID, Other Identity Providers, Login Experience, SSO Health, Session Policy |
| Routes | Five GETs, two POSTs. No PUT, PATCH or DELETE anywhere under `console/identity` |
| Read model | `IdentityConfigurationReport`, `IdentitySafeValue`, `SecretPresence`, `SessionPolicy`, `ApprovedProviders`, `ProviderInventory` |
| Health | `IdentityHealthCheck` / `IdentityHealthReport`, and one entry in the existing `HealthInspector` |
| Live probe | `EntraDiscovery::probe()` — two read-only GETs, provider-wide lock |
| D-31 | Idle timeout corrected to 60; `IDLE_MINUTES` removed; `deployment/ensure-session-lifetime.sh` |
| D-32 | Forced JWKS refresh now fetches before it replaces |
| Middleware | `RequireSystemAdministrator` promoted to Platform |
| Events | `identity.health.checked`, `identity.health.state_changed` |
| Schema | **None.** No migration, table, column or index |

---

## 2. Automated evidence

**374 tests, 4,999 assertions, all passing.** Pint clean. The P1-02 suites:

| Suite | Cases |
| --- | --- |
| `IdentityHealthTest` | 19 |
| `IdentityAccessBoundaryTest` | 7 |
| `SigningKeyRefreshTest` (D-32) | 8 |
| `SessionPolicyEnforcementTest` (D-31) | 6 |
| `ApprovedProviderBoundaryTest` | 4 |
| `SessionPolicyDriftTest` | 4 |
| `SecretAndLeakageTest` | 9 (4 added) |
| `IdentityArchitectureTest` | 11 |
| `SessionLifetimeDeploymentTest` | 13 |

### P1-01 tests that changed, and why

Five files changed, and none of them was weakened. Four record the **delivered
set**, which grew by one capability; the fifth follows a class that moved.

| File | Change |
| --- | --- |
| `ConsoleNavigationTest` | The reachable set is now two entries, still exhaustive, and both hrefs are fetched and asserted to serve |
| `AuthenticationFlowTest` | Same expected set. The claim — administration confers no business-domain access — is unchanged |
| `NoBusinessSchemaTest` | `Identity` joins the delivered modules, on the day it was delivered |
| `P1BoundaryTest` | The event-family list gains `identity` |
| `AccessBoundaryTest`, `OrganisationBoundaryTest` | One `use` line each, following the middleware to Platform |

**One requirement was not met exactly as written, and this says so.** The DESIGN
required that every P1-01 authorisation test pass *unedited*. Two needed their
`use` statement updated, which is unavoidable when a class changes namespace —
the alternative, a `class_alias`, would leave two names for one gate. **No
assertion, fixture or expectation was altered**, and `OrganisationBoundaryTest`
now resolves the gate by class name rather than by path so a future move cannot
make that guard silently read nothing.

---

## 3. Mutation testing

Every guard was broken deliberately and observed to fail. **Fifty mutations,
all ultimately CAUGHT.** Six are worth recording in full, because four were
failures of the *measurement* rather than of the code, and this project keeps
producing exactly that.

### 3.1 Three tests that passed for the wrong reason

| # | What was wrong |
| --- | --- |
| **D1** | The idle-expiry test travelled `idleMinutes() + 1` — past whatever happened to be configured. It asked the handler to honour its own setting, so it passed at 120, which is the defect. It now travels past the **approved** value |
| **H11** | The live-probe test asserted `Http::recorded()` was non-empty. A mutation reading metadata from cache still touched the network for the keys, so the assertion held while the thing it exists to prove did not. It now asserts a request to the **discovery endpoint** specifically |
| **H17** | "A failed probe with no usable trust is an outage" asserted the **overall** state — which another row drove to Failed anyway. It now asserts the row |

### 3.2 Two guards that read their own documentation

| # | What was wrong |
| --- | --- |
| **A-save** | The "no Identity screen offers a save" guard matched the word `Save` in its own docblock explaining why there is no Save. Comments are stripped first now |
| **D12** | The umask ordering guard matched `umask 077` in a comment above the executable line, so a mutation moving the real one below the write sailed through **twice**. Comments are stripped first now |

### 3.3 Two mutations that were not really applied

**K4** — "accept a token whose signature could not be verified" first reported
SURVIVED. The patch had replaced a `throw` the code never reaches; the reachable
one is inside the catch. Re-applied correctly: **CAUGHT**. The same shape of
tooling failure was recorded during P1-01, and it is why a survivor is now
always checked before it is believed.

**S5** was run under a filter that never executes the mutated line. Re-run under
the right suite: **CAUGHT**.

### 3.4 Two mutations that survived because a test did not exist

`A5b` (the providers screen reading the container instead of the catalogue) and
`A9` (`unapprovedKeys()` always empty) both survived the whole suite. Nothing
anywhere registered an **unapproved provider**, so the two sources of truth gave
the same answer and no test could tell them apart. `ApprovedProviderBoundaryTest`
now binds one. Both **CAUGHT**.

This is the P1-01 root cause again in a new place: *a condition nothing produces
has no test to fail.*

---

## 4. Defects found in the browser that CI passed over

Chromium, five screens, three widths (1440 / 768 / 390), both themes — 30
combinations, plus a scripted interaction pass.

| # | Found | Fix |
| --- | --- | --- |
| 1 | **The first verification run measured the wrong pages entirely.** A hand-built session cookie did not authenticate, so every screen was the Login page — and it reported zero overflow and zero console errors. Caught by *looking at a screenshot* | The script now fails if the `h1` is not `Identity & SSO`, and the session is created through Laravel's own session store |
| 2 | **The health table's prose column was cut off mid-sentence** — "…the Microsoft Entra ID screen names which are" just stopped. A P1-01 rule sets `white-space: nowrap` on the last cell so row *buttons* never reflow; on this table the last cell holds sentences, so the column was forced onto one unbreakable line 425px wider than its card | Fixed layout and explicit column widths for the two prose tables, and the nowrap rule undone for them |
| 3 | **The responsive check reported zero defects while defect 2 was on screen**, because the clipping happens *inside* the scroll container and the page itself never overflowed | The check now measures `scrollWidth` against `clientWidth` on every scroll container |
| 4 | **`Reveal` was offered beside values reading `Not set`** — an action that would have returned an empty string | Reveal, and its guidance, appear only when there is something to reveal |
| 5 | Two awkward sentences: *"The Microsoft Entra ID screen names which are missing"* and *"…however busy you have been"* | Rewritten |

**Interaction pass, observed:** masked value `11111111…1111`; the full tenant
**absent from the page payload**; Reveal fetched it from the server and showed
it; Copy appeared only after Reveal and confirmed *Copied*; Hide restored the
mask; `Re-check now` ran a real probe and confirmed *"Health re-checked."*
through `role="status"`; a second press refused with *"Health was checked moments
ago. Try again shortly."* through `role="alert"`. **Zero console errors.**

After the fixes: **0 of 30 combinations overflow, 0 clip, 0 console errors** —
the only console output is the font CDN, which this sandbox blocks.

---

## 4b. A gap found by re-reading the change, not by a test

Before merging, D-32 was re-read adversarially. A JWKS response of `200` with an
**empty key list** would have satisfied `$fresh !== null` and been written to the
cache — destroying a working key set just as thoroughly as the forget-then-fetch
the correction removes. The defect would simply have moved from the network path
to the parsing one.

An empty set now counts as a failed refresh, and a case covers it. **Nothing
found this; reading the diff did.**

## 5. A defect found in the deployment script, by killing it

The D11 mutation ("remove the trap") initially survived, because the test
injected its failure *before* the temporary file existed. Injecting a real
mid-write kill (`ulimit -f 0`) showed something worse than the mutation:

> **The script was killed by `SIGXFSZ`, which the trap did not cover, and left a
> complete copy of `.env` — `APP_KEY`, client secret and all — sitting on the
> host.**

Fixed two ways: the trap now covers more endings, and because `SIGKILL` can
never be trapped at all, the script **sweeps any copy a previous run left before
writing a new one**. Both are asserted by behavioural cases.

---

## 6. What is NOT verified, and why

Recorded honestly rather than inferred from a passing test.

| # | Not observable | Evidence that stands |
| --- | --- | --- |
| 1 | **The non-administrator refusal, against real production data.** Production has one user. P1-01 carried the same gap | `IdentityAccessBoundaryTest` B2, on every screen and both actions. **Carried to P1-03**, which owns user provisioning. The Product Owner is not asked to create a fake user |
| 2 | **A real Microsoft outage**, staged in production | Cases H1, H13, H16, H17. Not staged live: it would break sign-in for real people |
| 3 | **The provider-wide probe lock across two people** — needs a second administrator account | Case H15. **Carried to P1-03** |
| 4 | **Client-secret expiry** — undetectable in this unit at all. It needs a Microsoft Graph call this design deliberately does not make | **Named as a known limit, not a check that passed.** The screen says so where the claim is |
| 5 | **The `ulimit` kill test** reproduces one interrupted-write ending, not every one. `SIGKILL` cannot be trapped by anything | The startup sweep is what covers the untrappable case, and it is asserted |
| 6 | **D12** is a presence guard read from the script, not a behaviour test. The permission window is a race that cannot be observed honestly from outside the process | Labelled as a presence guard in the test itself |

---

## 6b. Production verification — observed, read-only

*Verify P1-02 identity state*, run #1 against production on the deployed build.
**Booleans and counts only. No value from `.env` and no log content was read or
printed, and no live probe was run.**

| Reported | Value |
| --- | --- |
| Identity settings present | tenant, client id, client secret, redirect address — **all present** |
| **Enforced idle timeout** | **60 minutes** |
| Approved idle timeout | 60 minutes |
| Enforced absolute lifetime | 12 hours |
| Enforced policy matches approved | **true** |
| Idle shorter than absolute | true |
| `IDLE_MINUTES` constant removed | **true** |
| Approved providers | `microsoft` |
| Runtime providers | `microsoft` |
| Unapproved providers | **none** |
| Overall health | **healthy** |
| Live-check row | `not_checked` — correct: a deployment clears the cache and nobody has pressed the button |
| Identity trust, directory identity, return address | all healthy — production genuinely obtained Microsoft's settings and signing keys |
| Identity route methods | five `GET`, two `POST`. No `PUT`, no `DELETE` |

**D-31 is confirmed in production: the enforced idle timeout is 60 minutes, and
the constant that claimed it without enforcing it is gone.** Before this
deployment the same reading would have been 120.

The workflow is a guard as well as a report: it exits non-zero if the enforced
policy stops matching the approved one, if the dead constant returns, if an
unapproved provider is registered, or if a write route appears under
`console/identity`.

---

## 6c. Product Owner functional testing — PASS, with a shared-foundation finding

**P1-02 Product Owner functional testing — PASS.** All functional checks against
the five Identity screens passed.

**P1-02 PRODUCT OWNER ACCEPTANCE HELD** — a shared authentication CTA visibility
regression was discovered during sign-out negative-path verification.

**This is a shared P1-00 / UI-foundation regression found while completing
P1-02, not a failure of the five P1-02 Identity screens.** None of the Identity
screens uses the affected classes.

### The finding

On `/auth/signed-out` the primary button rendered as a blue rectangle with **no
visible label**, and the Login page's `Continue with Microsoft` was equally
unreadable — logo visible, text not. Logout itself was correct: P1-00's
redirect to `/auth/signed-out` behaved exactly as designed, and nothing about
the sign-out destination or the authentication flow was changed.

### Root cause — proven, not assumed

The Product Owner proposed the cascade as the likely cause. It was **not taken
on trust**; it was reproduced.

```
a:visited      { color: var(--accent) }           specificity (0,1,1)
.auth-action   { color: var(--accent-contrast) }  specificity (0,1,0)
```

`a:visited` outranks the button's own colour, so a **visited** CTA takes the
accent colour and paints it on an accent background. And a button whose entire
job is to send you back where you came from is visited almost by definition.

Measured in the real cascade, on the real page:

| Theme | Label colour when visited | Button background | Contrast |
| --- | --- | --- | --- |
| Light | `rgb(25, 62, 107)` | `rgb(25, 62, 107)` | **1.00:1** |
| Dark | `rgb(127, 173, 225)` | `rgb(127, 173, 225)` | **1.00:1** |

Identical values. The label was painted in exactly its own background colour, in
both themes.

### ⚠ Two failed attempts to observe it, recorded because they matter

1. A first probe compared the button unvisited and visited in a fresh
   `newContext()` and found **no difference** — which looks like evidence the
   diagnosis was wrong. It is not: an incognito-style context has no history
   database and Chromium **disables `:visited` styling there entirely**.
2. A second attempt with a persistent profile also found no difference. A
   control page — a plain link with `a:visited { color: #ff0000 }` — stayed
   green after its target was visited, proving **this headless Chromium does not
   apply `:visited` styling at all**.

**So the rendered visited state cannot be observed in this environment.** The
cascade was therefore resolved with a *specificity-equivalent stand-in*: every
`a:visited` rule in the live stylesheet rewritten to `a.is-visited` — identical
specificity `(0,1,1)` — and the class applied to the real button. The cascade
cannot tell the two apart, so what wins there is what wins for a genuinely
visited link.

**This is a limit worth stating plainly: the cascade is proven; the pixels in a
truly visited state are not observable here.** The Product Owner's retest in a
real browser is the confirming observation.

### The fix

Solid CTA anchors now own their text colour in every link state, at `(0,2,0)`,
which outranks `a:visited` **without touching it** — so ordinary textual links
keep the accent treatment they are meant to have. The theme token is used, never
a hardcoded white: white would have fixed the light theme and left the dark one,
whose contrast colour is the ink navy, exactly as unreadable.

### Audit — all eight rendered CTA surfaces

Every surface, both themes, desktop and narrow, in normal / hover / focus /
visited. **32 combinations, none below WCAG AA.**

| | Light | Dark |
| --- | --- | --- |
| Visited (was **1.00:1**) | **13.48:1** | **8.58:1** |
| Normal | 10.81–13.48:1 | 7.31–8.58:1 |
| Hover / focus | 13.29–13.48:1 | 8.49–8.58:1 |

Ordinary textual links confirmed unchanged: still `rgb(25, 62, 107)` — the
accent — in the visited state.

**One surface could not be rendered locally:** First Run
(`Sign in with Microsoft`) needs a live single-use bootstrap grant. It uses the
same `.auth-action` class as the seven state screens, so it is covered by the
fix and by the guard, but it is **not separately observed** and is recorded here
rather than counted.

### Regression guard

`AuthCallToActionVisibilityTest` — five cases, five mutations, all caught:

| Mutation | Result |
| --- | --- |
| Revert the fix (the defect exactly as found) | **CAUGHT** |
| CTA visited set to the accent instead of its contrast | **CAUGHT** |
| Drop `:visited` from the CTA states | **CAUGHT** |
| A future `.auth-card a:visited` that silently outranks the CTA | **CAUGHT** |
| Weaken the global ordinary-link treatment | **CAUGHT** |

The fourth is the one that matters most: the first three would all pass while a
future rule at `(0,2,1)` quietly beat the CTA's `(0,2,0)` and put the defect
straight back. The guard compares **computed specificity**, so it fails on any
new visited colour rule that could win, unless that rule names a CTA class or is
listed as deliberately scoped elsewhere with a reason.

**A component test would not have caught any of this.** Tests already asserted
the label exists in the component and reaches the page, and every one of them
passed while the button was blank — because the text *was* there, painted in the
background colour. Text that exists and text that can be read are different
claims.

### Production verification of the fix

Merged as PR #80, `6708f80b88341b2ef1ec30513c0c55696c5c451c`. **Deploy run #104
succeeded.**

Chromium in this environment cannot traverse the egress proxy to the production
host, though `curl` can — so production's **own deployed HTML and CSS bundle**
were pulled down byte for byte and rendered locally. It is the same stylesheet
that is live, so what the cascade does to it is what it does on production.

The deployed bundle (`app-D-bq8Cyk.css`) carries, verbatim:

```
.signin-action:link,.signin-action:visited,.signin-action:hover,.signin-action:focus,
.signin-action:active,.auth-action:link,.auth-action:visited,.auth-action:hover,
.auth-action:focus,.auth-action:active{color:var(--accent-contrast)}
```

…and still carries `a:visited{color:var(--accent)}` for ordinary links.

| Production surface | Label present | Visited contrast, light | Visited contrast, dark |
| --- | --- | --- | --- |
| Login — `Continue with Microsoft` | Yes | **10.81:1** | **7.31:1** |
| Signed Out — `Return to sign in` | Yes | **10.81:1** | **7.31:1** |

Both were **1.00:1** before. The signed-out CTA's `href` is `/`, and production's
`/` serves the Login page (`"component":"Entry"`, HTTP 200), so the return
journey is intact.

**Not observed on production:** the click-through itself, and a genuinely
visited link rendered by a real browser — both blocked by the same proxy and
`:visited` limitations. The click-through *was* driven end to end locally.
**The Product Owner's retest is the confirming observation**, and steps R1–R6 in
the test script exist for exactly that.

The read-only identity verification was re-run after this deployment (run #2) and
**succeeded**: enforced idle timeout still 60 minutes, policy matching, no
unapproved provider, five GET and two POST Identity routes. The CSS-only change
disturbed nothing in P1-02.

---

## 7. Definition of Done

| # | Criterion | State |
| --- | --- | --- |
| 1 | Sign-in health determinable without a shell | Met |
| 2 | Client secret never in a rendered response | Met, against real responses on all five screens |
| 3 | No token, code, verifier, nonce, state, session id or `APP_KEY` in any payload | Met |
| 4 | Identifiers masked, revealed only by an authorised round-trip, never applied to the secret | Met, observed in the browser |
| 5 | Return-address consistency shown and correct | Met |
| 6 | Three states exactly as defined, action on every non-Healthy row, no overclaiming | Met |
| 7 | Health non-destructive; probe locked and rate limited; failed probe destroys no cache | Met |
| 8 | Displayed policy is enforced policy, proven by a drift test | Met |
| 9 | Idle expiry 60 minutes, proven by behaviour | Met |
| 10 | `IDLE_MINUTES` gone | Met |
| 11 | `.env`: one key, tested script, no value printed, mode and ownership preserved, no residual copy | Met |
| 12 | An unconfigured, unhealthy or unapproved provider creates no bypass | Met |
| 13 | No schema change | Met |
| 14 | Login page and UI foundation unchanged | Met |
| 15 | Five screens verified in a real browser, both themes, responsive | Met, with five defects found and fixed |
| 16 | Every guard proven non-vacuous | Met, 50 mutations |
| 17 | Product Owner test script delivered | Met |
| 18 | Product Owner acceptance | **MET — accepted 2 September 2026** |

---

## 8. Product Owner acceptance

**P1-02 — IDENTITY & SSO — PRODUCT OWNER ACCEPTED, 2 September 2026.**

Functional testing of the five Identity screens: **ALL PASS**.

Acceptance was **held** on one finding and released once it was corrected. The
finding was a shared P1-00 / UI-foundation regression discovered on the sign-out
negative path — the primary authentication button's label was invisible — not a
failure of the five Identity screens. §6c records the cause, the fix and the
audit; the final retest, in the Product Owner's own browser:

| Retest | Result |
| --- | --- |
| Login CTA — `Continue with Microsoft` visible | **PASS** |
| Sign out → `/auth/signed-out` | **PASS** |
| `Return to sign in` label visible | **PASS** |
| Click returns to Login | **PASS** |

### Accepted deviation — the middleware namespace move

`RequireSystemAdministrator` moved from Organisation to Platform, so two P1-01
authorisation tests changed. **Accepted by the Product Owner as a namespace-only
consequence of the approved middleware ownership move, explicitly not a
weakening of the P1-01 gate**, on these conditions, each verified against the
merged code:

| Condition | Verified |
| --- | --- |
| Only namespace/import references changed | `AccessBoundaryTest`: the `use` line only. `OrganisationBoundaryTest`: the `use` lines **plus one line in a method body** — the gate is now located by `ReflectionClass` instead of a hardcoded path into the directory it left. Reported rather than described as import-only |
| No P1-01 assertion changed | Assertion lines byte-identical in both files (20 and 35) |
| No fixture changed | Nothing under `tests/Support/` touched |
| No expected authorization result changed | The gate's body is byte-identical apart from its `namespace` line |
| Exactly one `RequireSystemAdministrator` | One file, in Platform. No `class_alias` anywhere, and none will be added |

The one body line also makes that guard stronger: a hardcoded path would silently
read an empty file after any future move, and reading nothing passes
`assertStringNotContainsString` for the wrong reason.

**P1-02 is not reopened unless a genuine defect is subsequently found.**
