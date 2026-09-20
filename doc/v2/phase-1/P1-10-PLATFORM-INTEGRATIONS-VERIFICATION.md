# P1-10 — Platform Integrations & Setup: VERIFICATION

**What was executed and observed.** A passing automated test is not the same
claim as an observed production result, and nothing below is presented as one.

| | |
| --- | --- |
| Unit | **P1-10 — Platform Integrations & Setup** (delivery order 12) |
| DESIGN | merge `a7aef47` — the six Product Owner corrections applied |
| Suite | **1082 tests, 1076 passed, 0 failed, 0 errors** |
| P1-10 cases | **62** under `tests/Feature/Setup`, plus **six** architecture files — `FirstRunRoutesDoNotCollide`, `BootstrapIsNotAUser`, `ConnectionTestsAreNotCapabilities`, `EveryCssTokenIsDeclared`, `OneStatusVocabulary`, `NoKeyRotationTooling` |
| Diff | 90 files, ~+8,900 / −133 |
| Status | **NOT DEPLOYED — awaiting Product Owner Gate C review** |

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
| Screens | 7 — sign-in, overview, the four integration steps, first administrator, complete |
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
