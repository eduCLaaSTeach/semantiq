# P1-02 — Identity & SSO Administration — VERIFICATION

**Unit:** P1-02 (Phase 1 delivery order 4)
**DESIGN:** `P1-02-IDENTITY-SSO-DESIGN.md` — Revision 2, **APPROVED**, merged as
`0f0a45be11b0905cb12c8abad3273aa77ea4fffd` (PR #77)
**Decisions implemented:** D-26 to D-32

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
| 18 | Product Owner acceptance | **Outstanding.** A green CI run does not unlock P1-03 |
