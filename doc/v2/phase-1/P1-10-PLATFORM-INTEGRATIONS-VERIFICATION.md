# P1-10 — Platform Integrations & Setup: VERIFICATION

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below is presented as one.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** (delivery order 12) |
| DESIGN | merge `a7aef47` — the six Product Owner corrections applied |
| Suite | **1137 tests, 1131 passed, 0 failed, 0 errors** (6 skipped, 1 risky — all pre-existing) |
| P1-10 cases | **105** under `tests/Feature/Setup`, plus **nine** architecture files — `FirstRunRoutesDoNotCollide`, `BootstrapIsNotAUser`, `ConnectionTestsAreNotCapabilities`, `EveryCssTokenIsDeclared`, `OneStatusVocabulary`, `NoKeyRotationTooling`, `IdentityHasOneSource`, `IdentityIsNotWritableOnTheConsole`, `OneSecretPerFamily` |
| Status | **NOT DEPLOYED — awaiting Product Owner Gate C review** |

**§8 records the four Gate C corrections.** Sections 1–7 describe the state at
the first Gate C submission and are left as written.

---

## 1. The six corrections, and how each was proven

| # | What it required | How it is proven | Mutation |
| --- | --- | --- | --- |
| **1** | Constrain the grant route structurally; prove it under a challenged route order | `FirstRunRoutesDoNotCollide` re-registers the First-Run routes **in reverse declaration order** and resolves **all ten** static method+URI pairs — one before this unit, so the guard is now materially stronger than when it was written. It also asserts the constraint **accepts** twenty real `Str::random(64)` tokens | **M-P10-4 KILLED.** The forward case still passes without the constraint; the reversed case fails |
| **2** | Route step 7 through the existing `GrantIssuer` / `GrantRedeemer`, no email dependency | `FirstAdministratorHandoffTest` — 7 cases. Ordinary grant, 30-minute TTL, tenant from the **stored** configuration, wrong identity refuses **without consuming**, the plaintext appears in no column and no audit event, and a source guard asserts the class names no mail path | — |
| **3** | Close bootstrap by a write, atomically; UNCONFIGURED alone must not reopen it | `BootstrapDoesNotReopenTest` — 11 cases, B10–B14 plus **B11b** | **M-P10-1 SURVIVED** and is recorded. **M-P10-2 KILLED** |
| **4** | A fresh installation reaches `store` with no SSH step, verified-first | `FreshInstallationReachesTheStoreTest` — 7 cases, run with an **empty identity `.env`** | **M-P10-5 KILLED** |
| **5** | A configuration change withdraws the old result; one resolved identity source | `ConfigurationChangeInvalidatesHealthTest` — 8 cases, H1–H6 | **M-P10-6 KILLED by H5 only**, exactly as the DESIGN predicted |
| **6** | `bootstrap.signin.succeeded` fails closed | `BootstrapSignInFailsClosedTest` — 4 cases, with the **correct** credential | **M-P10-3 KILLED** |

---

## 2. The mutation that survived

**M-P10-1 survived on its first run and is the most useful entry in the
record.** Re-deriving bootstrap openness from the administrator count alone —
the exact defect Correction 3 exists to close — left **all eleven cases green**.

The closing write sets `disabled_at` **and** replaces the password hash, and an
unusable hash refuses on its own. So **B11 was being satisfied by the hash
while claiming to guard the predicate** — a test passing for a reason unrelated
to what it says it checks.

`B11b` was added: a **closed principal with a working bcrypt hash**, the state
an operator `UPDATE` or a half-finished recovery path would produce. Re-running
M-P10-1 kills it. The two locks are now guarded separately.

Full detail, including three further mutations and five flaws found in the
tests themselves, is in `P1-10-MUTATIONS.md`.

---

## 3. What the guards found that the design had not

**Eleven direct identity-configuration reads, not six.** Widening
`NoDirectIdentityConfigRead` from the call shape to the dotted key found four
more inside `IdentityConfigurationReport::missingKeys()` — held in an array and
read as `config($key)` — and an eleventh in
`ConfigurationRequirements::requiredInProduction()`.

**That eleventh one mattered most.** It listed the four `MICROSOFT_*` keys as
production requirements read straight from `config()`, so a correctly
configured store-backed deployment would have been reported as misconfigured,
naming four environment variables it is **right** not to have — and a genuinely
fresh installation, which Correction 4 exists to serve, has none of them by
definition.

**`/up` returned 500 instead of 503 with the database down.** `HealthInspector`
wrapped the identity check in a `try/catch` that looked complete and was not:
**constructor injection built the check, the provider and the discovery client
before the method body ran**, so a failure while constructing them escaped the
guard written to contain it. A health inspector must stay constructible when
the thing it inspects is broken.

**`PlatformSetting::current()` used `firstOrCreate`**, so every identity
resolution — including the one behind the unauthenticated entry page — was a
**write**.

**`BootstrapCloser` treated `disabled_at` alone as "already closed"**, but
during recovery `disabled_at` **is** set, so the second close was a no-op and
the recovery password survived a restored administrator. Found by B14.

**`BootstrapRecovery::issue()` wrote the token row and its evidence outside any
transaction.** Found by P1-08's static atomicity guard. A token with no record
of who issued it reopens local login with nothing to audit.

**`integration.configuration.changed` was recorded in the controller**, outside
the writer's transaction. Found by the same guard, a second time.

**`Hash::check` against the unusable sentinel RAISES** rather than returning
false, so a closed principal would have produced a 500 with a stack trace
instead of the generic refusal every other case gets.

**Two guards the DESIGN names existed only in comments.**
`NoDirectIdentityConfigRead` and `EnvIsNotIdentityAuthorityAfterCutover` were
referenced in three code comments as though they were tests. They were not —
found by walking the Gate C proof list against the code rather than against the
document. Both are now written, and both are killed by mutation: a dotted key
restored **inside an array** (the shape a call-shape guard misses) and a
`?: config(…)` fallback on the store branch (the edit somebody makes in good
faith).

**Three pre-existing `var(--edge)` usages** in P1-05 and P1-08 code, found by a
new CSS guard. An undefined custom property invalidates the whole declaration,
so the Audit filter controls, the Audit rows and the access-decision path panel
have been rendering **with no border at all**. Fixed in passing, marked as
carried-in.

---

## 4. Browser verification — what was actually observed

**Chromium at `/opt/pw-browsers/chromium`, driven by Playwright, against a
local server with a seeded setup administrator.** Not production.

| | |
| --- | --- |
| Screens rendered | **9** — sign-in, overview, the four integration steps, first administrator, complete, and recovery |
| Also rendered | the **first-administrator screen carrying a live handover link**, driven end to end: sign in, enter Entra details, nominate, and read the one-time link off the page |
| **NOT browser-rendered** | **`/console/integrations`.** See below — this is stated rather than glossed |
| Widths | **1440px and 390px** |
| Themes | **light and dark** |
| Per-element overflow | measured per element, per P1-09's rule, not per page |
| Console errors | none, after excluding the sandbox's blocked Google Fonts request |
| Implementation terms | 22 forbidden strings swept on every screen at every size |
| Focus rings | every control on the identity form focused and its computed outline measured |

**Three defects were found by looking, and fixed:**

1. **A raw enum value on screen.** The AI provider field was a text input whose
   *label* read *"AI service (azure_openai or openai)"*. It and the mail
   security field are now **selects** with readable options; the stored value
   is unchanged.
2. **The layout broke at 390px** once a realistic nominated address was used.
   `Send this link to the.new.administrator@example.test` is one unbreakable
   33-character word in a heading, and it made the main column **402px wide
   inside a 390px viewport**. A short address fits and hides it entirely.
3. **`.org-action-quiet` is a modifier**, and every other use in the codebase
   pairs it with `.org-action`. Used alone, "Sign out" and "Test connection"
   rendered as bare browser buttons. The setup inputs also deviated from
   `.org-form input` in four ways, including a canvas background on a white
   card that reads as *disabled*.

### The one screen that was not browser-rendered — RESOLVED AT GATE C

> **This section is kept as written, and is now superseded by §9.**
>
> `/console/integrations` HAS since been opened in a real browser, at 1440px
> light and 390px dark. The obstacle below was real but was diagnosed wrongly:
> minting a session by hand failed because this deployment serialises sessions
> as **JSON**, not PHP — not because it required a Microsoft round trip or a
> test-only route. Once that was found, a valid session could be created
> through the framework's own session store with nothing added to the product.
>
> **Looking at the screen immediately found two defects no test had caught** —
> a status badge contradicting the sentence beside it, and headings breaking
> mid-word at 390px. That is the cost of the paragraph below having been
> accepted rather than pushed on.

**`/console/integrations` was verified by rendered HTTP response, not by a
browser.** Reaching it needs an authenticated System Administrator session, and
the only way to obtain one is a real Microsoft round trip. Minting a session
cookie by hand was attempted and abandoned rather than bodged: it would have
meant either adding a test-only route to the product or reproducing the
encryption and middleware chain outside it, and a screen "verified" through a
path the application does not use is not verified.

**What IS proven about it:**

- `test_s1_no_secret_appears_in_the_rendered_integrations_page` requests the
  real route, asserts **200**, asserts the page renders the stored
  configuration (`smtp.example.test` appears), and asserts **no stored secret
  appears anywhere in the body**;
- it renders `IntegrationForm` — **the same component**, with the same labels,
  selects, secret hints and buttons, that **was** browser-verified on four
  First-Run screens at 1440px and 390px in both themes;
- `AppShell` around it is P1-01's, unchanged and long since verified.

**What is NOT proven: how that specific page looks in a browser.** It is carried
as a check on the Product Owner script (CHECK 1) rather than claimed here.
*(Superseded — see the note at the head of this section and §9.)*

**Two things were fixed from reading the rendered screens rather than from a
test:** the overview rendered the step rail **and** a list of the same four
integrations — two identical lists on one screen — and the blocked nomination
state named the blocker without offering any way to act on it, at the last step
of a setup flow.

---

## 5. MySQL

**A CI step was added**, and it is reported as what it is: **CI evidence, not a
hand-run result.** There is no MySQL server in the development environment.

`tests/Feature/Setup` now runs against **MySQL 8.4** in CI, with the same
"a path that matches nothing exits 0" count guard the People step documents.
Three things there are engine-sensitive: the **singleton unique constraints**,
the **atomic closing write** inside `GrantRedeemer`'s transaction, and the
**conditional UPDATE** that consumes a recovery token.

---

## 6. What this unit did NOT do

- **No production `.env` change.** Production still reads its Microsoft
  configuration from the server environment.
- **No Entra cutover.** Excluded from Gate C by instruction.
- **No AI, Fabric or email credentials** were created.
- **No second privileged account** was created, so P1-02's provider-wide
  re-check stays **OPEN / CARRIED / UNVERIFIED**.
- **`ALLOWED_KEYS` unchanged at 15.** Ten events added, no key.
- **`bootstrap_administrator` is not in `RoleCatalogue`**, and the setup module
  names no part of the access model at all.
- **`/up` and `semantiq:health` unchanged** — and the one regression that did
  reach them was found and fixed before handover.
- **No `APP_KEY` rotation tooling**, and that is now a **guard** rather than a
  sentence: `NoKeyRotationToolingTest` fails the build on a rotation command, a
  re-encryption method, a second key in the environment, or any decrypt path
  that branches on `key_version`. *"We recorded a key version"* reads like a
  mitigation, and the next person will be tempted to treat it as one.
- **No deployment.**

---

## 7. Carried items, unchanged

**P1-02 provider-wide SSO re-check: OPEN / CARRIED / UNVERIFIED.**
**Production session-driver alignment: OPEN / CARRIED.** P1-10 did not touch
it; D-166 was designed around the current `file` reality rather than the
`database` target.
**The privilege-change / session-revocation phase gate stands.**
**P1-07, P1-08 and P1-09 carried items remain carried. D-19 unchanged.
P1-11 deferred with D-130 – D-147 preserved.**

**No JavaScript test runner exists.** The browser evidence in §4 is a manual
sweep, recorded as such — not an automated gate that would catch a regression
next month.

---

## 8. Gate C corrections — what was built, and how each is proven

The Product Owner held the merge of PR #131 and named four implementation gaps
against the approved D-148 / D-159 / D-165. Each is below with the evidence.
**Nineteen mutations were run; every one is killed or recorded as equivalent
with what it taught.** Full detail in `P1-10-MUTATIONS.md`.

### Correction 1 — D-159 step-up and Bootstrap local reconfirmation

**Normal console.** Replacing or removing an *established* credential now
requires Microsoft step-up through the **existing** P1-05/P1-07 framework — two
new `StepUpAction` cases and one `StepUpCompletion`, registered in the same
registry P1-07 established. No second step-up system was built.

**The staging problem, and how it is solved without storing a secret unsafely.**
The new credential has to survive a round trip to Microsoft. Every obvious place
to keep it is worse than a dedicated table: the session store on this deployment
*is* a database table; `pending_step_ups` is P1-05's structural table that every
listing reads; the URL is in the access log and the referrer; and asking again
on return makes the confirmation screen a second thing worth phishing.

So `staged_integration_changes` holds it **encrypted, short-lived (10 minutes),
single-use**, and the step-up row carries only the staged row's **id**.
Consumption is a conditional `UPDATE` inside the same transaction that consumes
the step-up, and the ciphertext column is **nulled the moment it is used**, so
the window in which a credential exists in two places is one transaction.

| Claim | Evidence |
| --- | --- |
| Replacing an established secret without step-up is refused | `IntegrationSecretRequiresStepUpTest` — redirect to `/console/access/step-up/`, credential unchanged |
| Establishing a *first* credential needs no step-up | same file. Requiring it would make First-Run unsatisfiable before Microsoft exists |
| A successful step-up applies the change exactly once | conditional `UPDATE` with the guard in the `WHERE`; a lost race returns null and rolls the caller back |
| An expired or refused step-up changes nothing | `test_an_expired_step_up_leaves_the_configuration_unchanged` |
| `Test connection` requires no step-up | `test_a_connection_test_requires_no_step_up` |
| **The plaintext never enters step-up storage, the log or Audit** | `test_the_plaintext_never_enters_step_up_storage_or_audit`; `StagedIntegrationChange` hides `ciphertext` and has no decrypt accessor. Mutation **M-C1-2b** writes it into `subject_intent` and is killed |

**Bootstrap half.** First-Run cannot use Microsoft step-up, because Microsoft may
not exist yet. It reconfirms the **current Bootstrap password** instead, before
anything is written or staged — for replacing or removing an integration secret,
and for the first permanent administrator handoff. The password is read from the
request body only, is never session-persisted, never logged, never written to
Audit, and every refusal is the same generic sentence. The check goes through
`Hash::check` via the existing bootstrap credential boundary.

`IntegrationChangeAuthority` answers *"is this change privileged?"* for **both**
surfaces, so the console and First-Run cannot come to disagree about it.

### Correction 2 — D-165 Bootstrap session policy

30-minute idle, 4-hour absolute, both **server-side**, both independent of
Laravel's `SESSION_LIFETIME`. The session id is regenerated at authentication
and the bootstrap state is re-evaluated on every request.

`RequireBootstrapSession` runs the checks **in the required order** — access
still open, then principal identity, then idle, then absolute — and **updates
last-activity only after the request has passed them all.** On expiry it clears
the bootstrap principal keys and the recovery marker, regenerates the session,
redirects to local sign-in, and records the one new event
`bootstrap.session.expired`.

| Boundary | Result |
| --- | --- |
| 29 min 59 s idle | valid |
| 30 min idle | **refused** |
| 3 h 59 min absolute | valid |
| 4 h absolute, with continuous activity | **refused** |
| stale recovery context | expires with its bootstrap session |
| normal SemantIQ user session policy | **unchanged** |

Mutations removing either timeout fail (**M-C2-1**, **M-C2-2**), as does moving
the activity update before the checks (**M-C2-3**) — the tidy-looking edit that
makes the absolute limit unreachable.

**`ALLOWED_KEYS` remains 15.** The expiry event carries only keys that already
existed; the catalogue grew from 87 events to 88, and the tripwire in
`SystemHealthArchitectureTest` was moved deliberately with the reason recorded
beside it.

### Correction 3 — Identity is a summary and a link on the normal console

`PUT /console/integrations/identity` and `POST /console/integrations/identity/test`
**no longer exist**. The boundary is in the route constraint (`email|ai|fabric`),
not in a controller check: a controller refusal is something somebody can weaken;
a route that does not resolve has nothing to weaken.

The screen renders `IntegrationSummaryCard` for Microsoft sign-in — status
badge, last-checked, and **Manage Identity & SSO** → `/console/identity`. It has
**no input, no select and no test button**, and the props it receives contain
**no `fields`, `secrets` or `choices` keys at all**: the server sends a
different shape, so the identifiers are not in the page source to be revealed by
a later edit.

**First-Run keeps its identity form**, which is the explicit exception — the
Bootstrap principal is not a `User` and can reach no `/console/*` route — and it
still writes through the **P1-02-owned** `IntegrationConfigurationWriter` rather
than a second model. First-Run may establish and replace Microsoft sign-in;
**removing** it is not routed there, because P1-02 owns taking it away.

`IdentityIsNotWritableOnTheConsoleTest` asserts the writable set as an
**equality** (`email`, `ai`, `fabric`), that no console route pattern accepts
`identity` while all three others still match, the console route set exactly,
that First-Run can still set identity up, and — behaviourally — that the props
carry identity as a summary only. Mutations **M-C3-1/2/3** are all killed.

### Correction 4 — Not configured, and explicit credential removal

**A. Status semantics.** `SetupProjection` now derives:

| State | Shown |
| --- | --- |
| nothing entered, or a required field missing | **Not configured** |
| complete, never tested | **Not checked** |
| tested | Available / Needs attention / Unavailable |
| explicitly not applicable | **Not applicable** — the one stored status the derivation must not touch |

It is **derived, not stored**, so a meaningful edit that *removes* a required
field lands on **Not configured** without anything having to notice it was a
removal. All four families are covered.

**B. Removal.** `Remove saved credential` is an **explicit action with its own
verb, route and confirmation** — `DELETE .../secret/{name}`. It is **never**
inferred from a blank password field: the form promises that leaving the box
empty keeps the saved value, and that promise now has a test that survives the
framework middleware being taken away (see M-C4-4 in the mutation record).

Removal requires D-159 step-up on the console and local-password reconfirmation
during First-Run. It deletes **only the named allowed secret**, clears the
previous test evidence and explanation through the owning writer in one
transaction, recalculates the status to **Not configured**, and records evidence
carrying **the family and the outcome only** — no credential, host or endpoint.
An unknown secret name is refused; an absent credential is a no-op that reveals
nothing either way.

### Defects the corrections themselves surfaced

| Found by | Defect |
| --- | --- |
| `actorId()` behaviour test | `property_exists($user, 'id')` is **false** for an Eloquent model — `id` lives in `$attributes` and is reached through `__get`. So the actor was `null` for **every console request**: configuration changes recorded no author, and the D-159 step-up refused to begin because it could not identify who was asking. It failed silently in both directions — nothing threw, the write still happened, `last_changed_by_user_id` was simply empty. **This had been broken since the original EXECUTE** |
| P1-08's atomicity guard | `IntegrationSecretStepUpCompletion::complete()` relied on its caller's transaction. It now opens its own |
| `SecretsAreDecryptedInOnePlace` | `StagedChangeStore` had become a **second place a secret becomes readable**. The ciphertext is now handed to `IntegrationSecretStore::adoptStaged()` |
| Adversarial reading | `confirmThroughMicrosoft` stages one secret and would have **silently dropped** a second. It now refuses, and `OneSecretPerFamilyTest` makes that assumption's end a red build |

---

## 9. Gate C browser verification — what was actually observed

Chromium via Playwright, at **1440×1000 light** and **390×844 dark**, against a
locally served build of this branch. Two servers: one with a permanent
administrator (console) and one without (First-Run still open).

| Observation | 1440 light | 390 dark |
| --- | --- | --- |
| `/console/integrations` horizontal overflow | none, `scrollWidth` 1440 | none, `scrollWidth` 390 |
| Identity card inputs / buttons | **0 / 0** | **0 / 0** |
| Identity card link | `Manage Identity & SSO → /console/identity` | same |
| Identity card badge | `✓ Available` | same |
| `Saved credentials` blocks | Email (Password), AI (API key) | same |
| Removal confirmation | names the credential, says it cannot be recovered, says the integration will stop working, and states that Microsoft re-authentication follows | same |
| Focus ring on the danger button | `2px solid` | `2px solid` |
| First-Run integration form | reconfirmation field present, labelled *Confirm with your setup password* | same |
| First-Run **identity** removal control | **absent**, as designed | **absent** |
| First-Run nomination password field | present | present |
| Raw enum / key / route names on screen | none | none |
| Any saved secret in the page source | **none** | **none** |
| Browser console errors | only `fonts.googleapis.com` blocked by the sandbox's TLS interception, and a dev-server favicon 404 — **no product errors** | same |

**Two defects were found by reading the rendered screen and fixed:**

1. The AI and Fabric cards showed a **"Not configured"** badge beside the
   sentence *"This has not been checked yet."* Both halves were individually
   correct; only the combination was wrong, which is why no test caught it.
2. At 390px, `overflow-wrap: anywhere` on panel headings broke ordinary titles
   mid-word — **"Microso / ft Fabric"**, "AI / service". The obvious repair
   reintroduced the 426px overflow that rule originally existed to stop, so
   **both cases were re-measured**: headings are now one line each at both
   widths, and the long nominated address still fits at 390 with
   `scrollWidth` 390.

### What was NOT observed, and why

| Not observed | Why |
| --- | --- |
| A real Microsoft step-up round trip for a credential replacement | It needs a live Entra tenant. The redirect, the staged row, the single-use consumption and the refusal paths are covered by automated cases; **the completed round trip is carried to live observation** |
| The 30-minute and 4-hour expiries in a real browser session | Observing them means waiting 30 minutes and 4 hours. The boundaries are asserted at 29 m 59 s / 30 m and 3 h 59 m / 4 h with a travelled clock |
| Any connection test against a real mail, AI or Fabric endpoint | No real credentials were created, per the Product Owner's instruction |
