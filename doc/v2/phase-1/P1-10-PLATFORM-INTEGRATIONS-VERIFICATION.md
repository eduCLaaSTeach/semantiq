# P1-10 — Platform Integrations & Setup: VERIFICATION

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below is presented as one.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** (delivery order 12) |
| DESIGN | merge `a7aef47` — the six Product Owner corrections applied |
| Suite | **1186 tests, 1180 passed, 0 failed, 0 errors** (6 skipped, 1 risky — all pre-existing) — 1176/1170 at Gate C, plus the ten cases §14 added |
| P1-10 cases | **105** under `tests/Feature/Setup`, plus **nine** architecture files — `FirstRunRoutesDoNotCollide`, `BootstrapIsNotAUser`, `ConnectionTestsAreNotCapabilities`, `EveryCssTokenIsDeclared`, `OneStatusVocabulary`, `NoKeyRotationTooling`, `IdentityHasOneSource`, `IdentityIsNotWritableOnTheConsole`, `OneSecretPerFamily` |
| Merge | `1a4068b` — squash of PR #131 into `main`, after Gate C approval at `020de16` |
| Status | **DEPLOYED. Gate C approved; awaiting Product Owner Gate D acceptance** |

**§8 records Gate C round 2's four corrections; §10–§12 record round 3's three,
and the six-table schema amendment. §13 records the deployment and the
production verification, and §14 the defect that deployment found.** Sections
1–7 describe the state at the first Gate C submission and are left as written.

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

---

## 10. DESIGN AMENDMENT RECORD — the schema is six tables, not five

The approved DESIGN described **five** tables. The implementation has **six**.
This section states the difference, and why, rather than leaving the document
and the database disagreeing.

### The five the DESIGN approved

| Table | What it holds |
| --- | --- |
| `platform_settings` | the singleton row: identity authority, revision, cutover timestamps |
| `integration_configurations` | one row per family: the typed non-secret fields, status, explanation, last-tested and last-changed |
| `integration_secrets` | one row per named secret: ciphertext, key version, who changed it and when |
| `bootstrap_administrators` | the local setup principal: email, password hash, closure flag |
| `bootstrap_recovery_tokens` | single-use tokens that reopen a closed bootstrap session |

### The sixth, added at Gate C round 2

| Table | What it holds |
| --- | --- |
| `staged_integration_changes` | a privileged change held between "the administrator asked" and "Microsoft confirmed it was really them" |

**It exists because D-159 requires a round trip.** Replacing a credential needs
step-up; step-up means leaving for Microsoft and coming back; and the new
credential has to survive that. Every place it could have been kept is worse:

| Considered | Why not |
| --- | --- |
| the session | the session store on this deployment **is a database table**, so this puts a plaintext credential in a row that is not the credential store, with none of its protections |
| `pending_step_ups` | P1-05's privileged-action table. Its columns are structural and safe to read; a secret there makes every listing of pending confirmations one careless select away from rendering one |
| the URL | in the access log, the referrer header and browser history before anybody decides how to store it |
| ask again on return | a credential typed twice, and a confirmation screen that becomes a second thing worth phishing |

So it is **encrypted, short-lived (10 minutes), single-use**, in a table whose
only job is this. The step-up row carries only an opaque id, through the
`subject_type` / `subject_id` seam P1-07 established.

### The one column added at Gate C round 3

`staged_integration_changes.fields` — a nullable JSON column, and the
`reconfigure` operation that uses it.

**Why a column and not a seventh table.** Correction 2 required that changing a
credential's DESTINATION be staged too, and that nothing be written before the
confirmation. What is being staged is *one privileged change to one family*,
and it already has a row here. Splitting the fields into a table of their own
would make "the whole change" something the completion handler reassembles from
two places — and a partial apply is precisely the mixed old-secret /
new-destination state the correction exists to prevent.

`fields` is **not encrypted, deliberately**: a host, a port and a directory
identifier are values the administrator typed and can see on the screen they
typed them into. Encrypting them would imply a protection the screen itself
does not offer, and would route non-secret data through the one decryption path
that exists to stay small and auditable.

**The same row now serves P1-02's identity change** (correction 1) rather than
a seventh table for that. An identity reconfiguration is a staged privileged
change with an encrypted payload and some non-secret fields, which is what this
row already is.

### What did NOT change

No table was added for the test email (D-153) — it stores nothing. No table was
added for the post-install SSO change. **`ALLOWED_KEYS` remains 15.** The audit
tables are P1-08's and are untouched.

---

## 11. Gate C round 3 — three product and security gaps closed

### Correction 1 — Microsoft sign-in is manageable after installation

**The gap.** First-Run could establish an identity configuration. After
installation the Entra screen was read-only and said so: *"These are set on the
server. They cannot be changed from this screen."* A customer whose Entra client
secret expired — which they all do — had no route back except SSH, which is the
thing this unit exists to remove. The Product Owner's requirement — *the
customer can set up and manage SSO through SemantIQ* — was met during setup and
nowhere else.

**P1-02 remains the sole owner.** Platform Integrations still has no identity
write route; it shows a summary and links here. What changed is that here now
has somewhere to link to:

```
Integrations → Manage Identity & SSO → Microsoft Entra ID
    → Change configuration → Microsoft step-up
    → verify the candidate → atomic activation
```

**Verify, then activate — never the other way round.** This is the only
configuration in SemantIQ whose failure locks everybody out of the deployment
that holds it, including whoever broke it. So:

| Claim | Evidence |
| --- | --- |
| An edit without step-up changes nothing | redirect to `/console/access/step-up/`; the live directory is unchanged |
| The candidate is verified before anything is written | `ProviderProbe` runs on the staged candidate, through its own discovery client and cache namespace, so it cannot pass by reading what a previous sign-in cached nor poison live trust |
| **A failed candidate probe leaves the old SSO active** | `test_a_failed_candidate_probe_leaves_the_old_configuration_active` — directory *and* secret unchanged |
| A failed or expired step-up applies nothing | the staged row is consumed by a conditional UPDATE whose guard is in the `WHERE`; an expired one returns `false` |
| One confirmation applies exactly once | a second `activate()` on the same row returns `false` |
| The secret never reaches React, the session, Audit or the log | the change screen's box is bound to its own empty form state; `secretIsSet` is a boolean; the step-up row carries an id |
| P1-09's old identity health does not survive | the revision increments, so the previous directory's cached result is unreadable |
| **A required field cannot be partially cleared while SSO is in use** | refused before anything is staged, in both the `null` and literal-`''` shapes |
| Platform Integrations still contains no Identity edit form | props assertion plus the route-constraint equality |

### Correction 2 — D-159 protects the destination, not only the secret

**The gap, in one sequence:**

```
the SMTP password is already saved
  → somebody changes only the mail server address
  → the password is untouched, so nothing is privileged
  → the change saves immediately
  → the next test or send offers that password to a host they chose
```

Nothing was stolen and nothing was replaced. The credential was handed
somewhere new. The same shape existed for the AI endpoint and the Fabric
directory and application.

**`IntegrationFamily::destinationFields()`** now names them in the type:

| Family | Privileged once a credential exists |
| --- | --- |
| Email | `host`, `port`, `encryption`, `username` |
| AI | `provider`, `endpoint`, `deployment` |
| Fabric | `tenant_id`, `client_id`, `workspace_id` |
| Identity | *(none — correction 1 owns it)* |

`from_address` and `from_name` are deliberately absent: they are display
identity, and changing them cannot cause the stored password to be offered to a
different server.

**Nothing is saved before the confirmation.** The whole change is staged —
fields and secret together — and applied in one transaction, so the live
configuration never holds a new destination beside an old credential. The
previous behaviour saved the fields immediately on the reasoning that they were
"not the privileged part"; both halves of that were wrong, and the test that
asserted it has been rewritten to assert the opposite.

**A change is privileged only when it actually changes something.** Re-submitting
the same values, or editing a display field, saves directly — a confirmation
people meet for no reason is one they learn to click through.

### Correction 3 — D-153 send test email

**The gap.** `EmailConnectionTester` proved the server accepts the credentials.
It proved nothing about whether the server will accept a *message* from the
configured send-from address, which is a different permission and the one that
actually fails in production. D-153 was documented and not delivered.

| Claim | Evidence |
| --- | --- |
| **The recipient is the authenticated principal, server-side** | System Administrator → their own address; Bootstrap Administrator → the configured setup address |
| **The request cannot redirect it** | eight payload shapes (`to`, `recipient`, `email`, `address`, `cc`, `bcc`, an array, a custom subject/body) all leave the recipient unchanged |
| ...and the signature makes it unrepresentable | `send()` takes one `string $recipient` and nothing else; subject and body are class constants; no `cc`, `bcc` or second `to` anywhere in the module |
| The configured From identity is used | a missing send-from address is refused rather than falling back to the SMTP username |
| An SMTP refusal is reported safely | one of the sender's own declared sentences; the provider's message is inspected for shape and discarded |
| One per administrator per minute | a second attempt inside the minute is refused; it releases after 61 seconds |
| The evidence carries no address | the family and the outcome only |
| No other integration can send anything | there is no route for one |

**The screen was corrected too:** *"Testing … does not send anything"* now names
**Test connection** explicitly, because it sat two inches above a button that
sends an email.

---

## 12. Gate C round 3 — browser verification

Chromium via Playwright, **1440×1000 light** and **390×844 dark**, against a
locally served build of this branch.

| Observation | 1440 light | 390 dark |
| --- | --- | --- |
| Horizontal overflow, all four screens | none | none |
| Identity read screen — inputs | **0** | **0** |
| Identity read screen — `Change configuration` link | present | present |
| Stale "cannot be changed from this screen" wording | **gone** | **gone** |
| Change screen — directory pre-filled | yes | yes |
| Change screen — client secret box | `type=password`, **empty**, "Saved — leave blank to keep it" | same |
| Change screen — the four-step notice above the fields | present | present |
| Focus ring, every visible control | `2px` | `2px` |
| Send test email block | on **Email only** | on **Email only** |
| Send test email — recipient/subject/body inputs | **0** | **0** |
| "does not send anything" sentence | scoped to **Test connection** | same |
| First-Run send control | present, 0 inputs | present, 0 inputs |
| Raw keys or enum values on screen | none | none |
| Any saved secret in the page source | **none** | **none** |
| Browser console errors | **none** | **none** |

**Three defects were found by looking and fixed** — the contradicting page
description, the flashed refusal that would never have rendered, and the
"does not send anything" sentence sitting above a send button. None of them was
caught by a test, and the first two would have been invisible until a customer
hit them.

**A fourth observation was investigated and is NOT a defect.** At 390px three
navigation buttons report no focus ring. They are `.shell-area-label` elements
in the **collapsed** rail — `getBoundingClientRect()` reports them as not
rendered — and they behave identically on System Health, a screen this unit
never touched. It is pre-existing AppShell behaviour on hidden elements, not a
reachable control without a focus indicator.

### What was NOT done, by instruction

| Not done | Why |
| --- | --- |
| The production SSO cutover | explicitly held by the Product Owner |
| A real test email from production | explicitly held; the send path is exercised against a faked transport, so what is proven is **which address SemantIQ would send to**, not that SMTP works |
| Real AI or Fabric credentials | explicitly held |
| The completed Microsoft step-up round trip | needs a live Entra tenant. The redirect, the staging, the single-use consumption, the verification and every refusal path are covered automatically; **the live round trip is carried forward** |

---

## 13. Deployment, and what was verified on production

### 13.1 The deployment itself

| | |
| --- | --- |
| Gate C head approved | `020de1603be02707aed67fe6220cdda40c3e3838` |
| Merge | **`1a4068b8814577282f4133fff9bec4aa361b29b4`** — squash of PR #131, the repository's normal strategy |
| Post-merge CI | run **341** — SUCCESS |
| Deployment | **Deploy to cPanel (SSH)** run **154** — SUCCESS, on attempt **2** |

**THE FIRST ATTEMPT FAILED, AND IT IS RECORDED RATHER THAN RE-RUN QUIETLY.**

Attempt 1 stopped at step 15, the pre-flight check that the four Microsoft
settings are present on the server:

```
ssh: connect to host *** port ***: Connection timed out
Process completed with exit code 255
```

Three earlier steps in the same job had used the same SSH connection
successfully, seconds before, and the failure is in the connection layer rather
than in anything the step did. **Production was not touched**: steps 16 to 29
were all skipped, so the maintenance window never opened, no file was synced
and no migration ran. `/up` was confirmed still serving `200 ok` while the
deployment was in its failed state.

That is the one case where a re-run is a diagnosis rather than a hope, and it
was spent there. Attempt 2 ran all 29 steps.

**What the deployment did:** synced the application and built assets, verified
the deployed `.htaccess` and front controller against the repository copies,
ran `php artisan migrate --force`, ran `php artisan optimize:clear`, ran
`php artisan semantiq:health`, closed the maintenance window, verified the site
over HTTPS and ran the web-exposure negative tests.

**What it did not do, structurally rather than by intention:** `.env` is
excluded from the rsync, so no Microsoft setting on the server was read,
written or moved. The only `.env` key the workflow touches is
`SESSION_LIFETIME`, which is the existing approved D-31 behaviour and is
unrelated to identity. `SESSION_DRIVER` appears nowhere in the workflow.

**Every P1-10 migration is additive.** Six `Schema::create` calls on tables
that did not exist, and one `Schema::table` adding a nullable column to a table
the same set creates. No existing table is altered, renamed or dropped. The
**only** data write in the whole set is the `platform_settings` singleton, and
it is inserted with `identity_source = 'env'` — so an existing deployment keeps
the environment-backed authority it already had. There is no import, no
fallback combination and no automatic store activation anywhere in the path.

### 13.2 Observed directly on production

| What | How | Result |
| --- | --- | --- |
| Site root | `GET /` | **200** |
| Health endpoint | `GET /up` | **200**, body `ok` |
| Deployment health | `php artisan semantiq:health`, deploy step 26 | **passed** — and on a cache cleared by step 25, so its identity check was a genuine cold-cache round trip to Microsoft |
| Site over HTTPS | deploy step 28 | **passed** — root 200, `/up` ok, built assets served from the deployment root, no legacy `public/` layer |
| Web-exposure negatives | deploy step 29 | **passed** — every protected path 403 from Apache, denial body 9 bytes, ACME challenge path intact |
| Local setup sign-in | `GET /first-run/sign-in` | **302 → `/first-run/closed`** |
| The closed page | `GET /first-run/closed` | **200**, and its source contains **no password input and no secret marker** |

**Bootstrap is closed on production, and was not opened by the deployment.**
The redirect above is the observable proof: `BootstrapAccess::isOpen()` returns
false first of all when a deployment has an active System Administrator, which
production has. No Bootstrap Administrator row and no recovery token can have
been created, because nothing in the migration set or the deployment workflow
creates one — the only paths that do are two artisan commands nobody ran.

### 13.3 No outbound action was caused by the deployment

| | |
| --- | --- |
| SMTP test message | none — no mail configuration exists on production, and nothing in the deployment sends |
| AI provider call | none |
| Fabric connection test | none |
| New integration credential | none — `integration_secrets` is created empty and nothing writes to it without an administrator on a screen |

The one outbound call the deployment does make is Microsoft **discovery**,
inside `semantiq:health`, which is the existing pre-P1-10 behaviour of that
command and is how the identity row in its report is produced.

### 13.4 What could NOT be verified from the delivery environment

**Stated plainly, and not inferred from anything that passed.**

**The console screens were not opened on production.** Signing in requires
Microsoft credentials the delivery environment does not have, and cannot
obtain. Separately, this environment reaches the internet through an inspecting
proxy whose certificate authority the bundled Chromium does not trust, and
`certutil` is not installed to add it — so even the unauthenticated surfaces
could not be rendered in a browser here.

**So Gate D CHECKS 1, 2, 3, 4, 5, 6 and 7 are genuinely the Product Owner's
first look at these screens on the live system.** That is the honest position
and it is why the Gate D script is written the way it is.

---

## 14. THE DEFECT THIS DEPLOYMENT FOUND

**A false red on the one screen where acting on it locks everybody out.**

### What it was

`SetupProjection::isConfigured()` asked one question of all four integrations:
is there a saved row holding every meaningful field, and does its secret exist?

For Email, AI and Fabric that is the whole question. **For Microsoft Entra ID
it is not**, because identity has two possible authorities and the row is only
one of them. Until the controlled cutover, a deployment reads its Microsoft
configuration from the **environment**, where there is no row at all.

So production — the deployment whose sign-in demonstrably works, because people
are signing in through it — would have been told:

> **Microsoft Entra ID — Not configured**

on a **required** integration, on the screen whose only remedy is to re-enter a
configuration that was already correct.

`ProviderProbe` already carries a comment about precisely this class of
mistake: *"A false red on a working system is worse than no check."* This was
the same mistake, one screen away.

### Why three Gate C rounds did not catch it

**Because the local server used for every browser verification had no Microsoft
configuration either.** "Not configured" was the correct answer there — for a
reason entirely unrelated to the rule being checked. That is the failure
`CLAUDE.md` §2 names in as many words, and it is worth recording that it was
found by deploying rather than by testing.

It was not visible in the automated suite for the same reason: no case
established an environment-backed authority and then asked what the card said.

### The correction

`isConfigured()` now has an explicit Identity branch that asks the same
authority the sign-in path asks:

```php
if ($family === IntegrationFamily::Identity) {
    return $this->identityConfiguration->resolve()->isComplete()
        || $this->identityConfiguration->storedCandidate()->isComplete();
}
```

**Both sides are needed, and each one alone is wrong.**

| | Answers | Needed for |
| --- | --- | --- |
| `resolve()` | what is **in force** | production before the cutover, and any deployment after it |
| `storedCandidate()` | what has been **typed and saved** | First-Run, where the administrator has just entered the details and the environment is still empty |

`resolve()` alone would tell an administrator mid-setup that the configuration
they had just saved was not configured. The row alone is the defect above.
`IdentityConfigurationSource` draws exactly this distinction in its own
docblock; this is the one caller that needs both halves of it.

### The direction of the fix, pinned

**Nothing here makes the card report a positive result.** Configured and
known-to-work remain different facts. With no test result the card reads **Not
checked** — "configured, and nobody has tested it from this screen" — which is
the Gate C round 2 correction 4A semantics applied correctly for the first
time.

`tests/Feature/Setup/IdentityCardReflectsTheAuthorityTest.php`, seven cases:

| Case | Pins |
| --- | --- |
| `test_an_environment_backed_deployment_is_not_reported_as_unconfigured` | the production shape, and that it reads **Not checked** |
| `test_a_fresh_deployment_with_no_authority_is_still_unconfigured` | the fix did not make identity always configured |
| `test_a_saved_candidate_counts_before_the_cutover` | First-Run is not broken by asking `resolve()` alone |
| `test_a_store_backed_deployment_is_configured` | after the cutover |
| `test_a_working_deployment_is_not_reported_as_tested` | it never becomes a fake green |
| `test_the_optional_integrations_are_unaffected` | Email, AI and Fabric still answer from their row |
| `test_the_setup_step_list_agrees` | the card and the First-Run step list cannot disagree |

**Five mutations, five killed, each by the case its docblock names:**

| # | Mutation | Killed by |
| --- | --- | --- |
| M-GD-1 | remove the Identity branch — the pre-fix code | the production-shape case, and the step-list case |
| M-GD-2 | Identity is always configured | the fresh-deployment case |
| M-GD-3 | ask `resolve()` alone | the saved-candidate case |
| M-GD-4 | a configured identity reports `Available` | the production-shape case, and the not-tested case |
| M-GD-5 | every family asks the identity authority | the optional-integrations case, and the step-list case |

### And the guard that P1-10 had already invalidated

Separately, and found by reading the verification tooling rather than by
running it: **Gate C round 3 broke `verify-identity.yml` and nothing would have
said so.**

That workflow held `if 'PUT' in identity_route_methods … problem`. Round 3 added
`PUT console/identity/entra` — the approved post-install sign-in change. The
old guard's verdict was **run** against the deployed route set rather than
reasoned about:

```
OLD GUARD VERDICT: FAIL - "A write route exists under console/identity."
```

It is dispatched by hand and never runs in CI, so the first sign would have been
a red run against a healthy deployment — and a gate that fails on a healthy
system is a gate people learn to ignore.

The guard is **narrowed, not relaxed**: "no write route" becomes "exactly the
one write route that was approved", as an equality, so a second one added
anywhere under the prefix still fails it.

The same workflow was also reading the **wrong authority** — `config
("identity.microsoft.*")`, straight out of `.env`. After the cutover the store
is the authority and `.env` is not read at all, so that report would have said
PRESENT for a deployment that was not configured. It now asks
`IdentityConfigurationSource`.

**`ProductionVerificationMatchesTheApplicationTest` makes this class of drift a
CI failure** rather than a discovery. It pins each hard-coded expectation in
both verification workflows to the thing it is a copy of: the approved identity
write set to the registered routes, the six table names to tables that exist,
and the Audit key count to the catalogue. Three mutations, three killed.
