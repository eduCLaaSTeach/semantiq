# P1-11 — Administration Home: Product Owner Test Script

**For you, not for a developer. Your words, your screens, your decisions.**

---

## 1. What you are testing

**Administration Home** — the System Administration landing screen. It shows,
in one place, whether SemantIQ is set up, secure and working, and lists what
needs your attention. **It is read-only.** Nothing on it changes a setting,
starts a check or contacts an outside service.

## 2. Build

| | |
| --- | --- |
| DESIGN merged as | **`59a3f73`** — Gate B, D-182 and three corrections |
| Gate C approved at | **`8f69569`** — PR #139 head, CI run 35586681413 SUCCESS |
| **Merge SHA** | **`59cacedbc0e4fd099a1e0ad51cc2a963be68f7b1`** — PR #139 squashed to `main` |
| **Deployed build** | **`59caced`** — deploy run 35587621169, SUCCESS, 21 September 2026 |
| Deployed to | `https://semantiq.claas2saas.com` |
| Status | **COMPLETED — 8 / 8 PASS. GATE D CLOSED. P1-11 ACCEPTED / CLOSED** |
| Executed by | **The Product Owner, live on production** |
| Observation date | **22 September 2026** |
| Evidence supplied | The production **Administration Home screenshot** |

> ## ✅ SCRIPT COMPLETED — ALL EIGHT CHECKS PASS
>
> **Checks 1–8: PASS.** Observed by the Product Owner on the live production
> deployment on **22 September 2026**, against real production data. **No check
> was recorded as FAIL, and none was recorded as NOT OBSERVABLE.**
>
> **These are live observations, not automated results**, and the record does not
> restate them as anything weaker.
>
> **No production data was created, changed or deleted to make any check
> observable**, and no privileged account was manufactured — including for
> Check 6, where a genuine Organisation Administrator already existed.
>
> **The expected behaviours below are unchanged.** They are what was asked for
> before the run, and they are left exactly as written: a script rewritten after
> the fact to match what happened is not evidence of anything.
>
> The build is `59caced`, verified independently rather than taken from the
> deployment's own report — the Vite asset hashes come from the bundle's content,
> production serves `app-BppKLV8o.js`, which is what this commit builds, and the
> previous release built `app-D4pobQzZ.js`.
>
> **P1-11 is now CLOSED. Phase 1 is not** — see
> `P1-11-ADMINISTRATION-HOME-ACCEPTANCE.md` §8.

## 3. Before you start

| | |
| --- | --- |
| You are signed in | As a **System Administrator**, through Microsoft |
| The deployment is the real one | Your organisation, your people, your business domains. **Nothing is seeded for this script** |
| Nothing has been changed for you | The numbers you see are whatever is genuinely true today |

## 4. Test data required

**NONE. Do not create anything.**

Every check below reads what already exists. If your deployment happens to have
no unowned business domains and no open exceptions, several tiles will read
zero and the Action Queue will say *"Nothing needs your attention."* **That is a
pass, not a gap** — record what you saw.

## 5. ⚠️ Warning — permanence

**This script asks you to create nothing, change nothing and delete nothing, so
nothing here is permanent.**

It is worth saying anyway, because two things on this screen are LINKS to
screens that do write: Organisation and Business Domains. **Follow a link, look,
and come back. Do not edit anything to make a check pass.**

**Never enter inaccurate business data or falsify your organisation's structure
to exercise a check.** If a check cannot be seen without doing that, mark it
**NOT OBSERVABLE** and move on — §8 already expects some of them.

---

## 6. The checks

### Check 1 — It looks like the rest of SemantIQ

1. Sign in. In the sidebar, under **System Administration**, click
   **Administration Home** — it is the first item.
2. Open **Organisation**, then **System Health**, then come back.

**Expected.** The same shell, the same heading size and weight, the same
spacing, the same cards and the same status pills as the other two. Title reads
**Administration Home**. Four areas in order: **Readiness**, **Security**,
**Reviews & Operations**, **Action Queue**. **It is a dashboard, not a form, and
it has no tab strip** — that is deliberate.

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026.

---

### Check 2 — Readiness tells the truth about YOUR deployment

Read the four Readiness tiles.

**Expected.**

| Tile | Should say |
| --- | --- |
| **Organisation** | **Configured** — your company profile exists |
| **Users & Groups** | Three plain counts: active people, inactive people, active groups. **No status word** — a count is not a verdict |
| **Business Domains** | **Ready**, **Needs attention** or **Not configured**, plus how many are switched on and how many have nobody accountable |
| **Platform Integrations** | The four services — Microsoft Entra ID, Email & Notifications, AI Provider, Microsoft Fabric — each with the same status word the Integrations screen shows |

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026.

---

### Check 3 — The dashboard agrees with the screens it summarises

**This is the most important check.** A roll-up that disagrees with its source
is worse than no roll-up.

1. Note every number on Administration Home.
2. Open **Users & Groups** and count. Open **Business Domains** and count the
   enabled ones and the ones with no owner. Open **Security Status** and read
   the posture badge and the exception count. Open **Access Reviews**. Open
   **System Health**. Open **Integrations**.
3. Come back to Administration Home.

**Expected.** **Every number and every status word matches its own screen,
exactly.**

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026. **No figure disagreed.**

---

### Check 4 — The Action Queue is real

Read the Action Queue at the bottom.

**Expected.**

- **Every row is something that genuinely needs attention** — an unowned
  business domain, an open security exception, an overdue review, a failing
  check, an integration that needs setting up;
- **Nothing is listed merely because it is zero.** No row says "you have no
  groups" or "you have no domains";
- **Every link opens**, and lands where its words say it lands;
- If nothing is outstanding it reads **"Nothing needs your attention."** — not
  an empty box.

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026.

---

### Check 5 — Nothing is invented

Look for anything the screen could not honestly know.

**Expected.**

- **No count anywhere a value is withheld from you.** A tile you may not see
  says **Withheld** and shows **no number at all**;
- **No `0` where the honest answer is "not available"**;
- **No "Not configured" where the honest answer is "withheld"**;
- **No link to a screen you cannot open**;
- **No person's name, no email address, no business domain record, and no
  credential anywhere on the page.** It is a summary of counts and statuses;
- No raw codes, no field names, no developer words.

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026.

---

### Check 6 — An Organisation Administrator sees the right, smaller screen

**Only if a genuine Organisation Administrator account already exists. DO NOT
CREATE ONE FOR THIS CHECK** — see §8.

Sign in as them and open Administration Home.

**Expected.**

- **They can find it.** It is in their sidebar — that is decision **D-182**;
- **It is the ONLY System Administration item they see.** Organisation, Users &
  Groups, Roles & Access and Business Domains stay hidden even though they can
  reach them by address. **That is deliberate and is a known gap**, recorded to
  be decided separately;
- **Platform Integrations** and **System Health** read **Withheld**, with no
  number and **no link** — these are deployment-wide, not one organisation's;
- Their Action Queue contains **no row** pointing at either of those.

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026. **Observed, not recorded as NOT OBSERVABLE** — a genuine Organisation Administrator existed, and none was created for this check.

---

### Check 7 — Responsive, both themes, keyboard

1. At normal desktop width, switch between **Light** and **Dark**.
2. Narrow the window to roughly a phone width.
3. Press **Tab** repeatedly from the top of the page.

**Expected.** Readiness shows four tiles across on a wide screen and **one
column** on a narrow one. **No sideways scrolling** at any width. No word broken
across lines. Both themes readable, every status pill legible in both. Every
link reachable by Tab with a **visible blue focus ring**. **No browser console
errors.**

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026.

---

### Check 8 — Nothing else moved

1. Sign out and back in.
2. Go to `/console` — the page you land on after signing in.
3. Open each of the previously accepted System Administration screens.

**Expected.** Sign-in unchanged. **`/console` is unchanged** — it still just
confirms who you are signed in as; it did **not** become the dashboard. Every
previously accepted screen works exactly as it did. Administration Home is
**first** in System Administration.

**PASS / FAIL: ✅ PASS** — Product Owner, live on production, 22 September 2026.

---

## 7. Evidence to capture

| | |
| --- | --- |
| **1** | Administration Home, full page, **desktop light** |
| **2** | Administration Home, full page, **desktop dark** |
| **3** | Administration Home, **phone width** |
| **4** | Any figure from Check 3 that disagreed with its own screen — **both screens, side by side** |
| **5** | The Organisation Administrator's view, if Check 6 was observable — **including their sidebar** |
| **6** | Anything that looked wrong, whatever these checks asked for |

---

## 8. What CANNOT currently be tested, and why

**Never inferred from a passing test, and never silently omitted.**

| | |
| --- | --- |
| **Check 6, if you have no Organisation Administrator** | ~~Would have been NOT OBSERVABLE — creating one would put a role assignment into your real access records to satisfy a test.~~ **RESOLVED 22 September 2026: a genuine Organisation Administrator already existed, and Check 6 was OBSERVED — PASS. None was created.** The automated evidence stands alongside it |
| **A genuinely empty deployment** | **NOT OBSERVABLE.** Your organisation exists and cannot be removed to see what a day-one deployment looks like. The rule — Organisation reads *Not configured*, the queue leads with setting it up, and **no other tile is rewritten as `0` or `Not configured`** — is proved automatically, including by breaking it deliberately |
| **A source failing** | **NOT OBSERVABLE.** It would mean breaking your database, your cache or your audit chain on purpose. Proved automatically by forcing **each of the six sources** to fail in turn — People, Business Domains, Access Reviews, Platform Integrations, System Health and Security Posture: its own tile reads **Not available**, every other tile is unaffected, and it contributes no Action Queue row |
| **The screen with a large number of business domains** | Not observable today — you have a handful. **And there is a finding here you should know about:** Security Status already asks five questions per business domain, and Administration Home shows that summary, so it inherits the cost. At 20 domains the page took about 0.6 s locally, comfortably inside target. **It is P1-06's, it is already live on Security Status, and it is raised for your decision rather than changed here** |
| **MySQL** | The application runs on MySQL in production and on SQLite locally. **The MySQL run was not observed by me** — there is no MySQL server in the environment I work in. It runs in the build, and a build step was added specifically for this unit |
| **Production rendering** | **Still not seen by me** — the browser available here does not trust this environment's certificate authority, and I did not disable certificate checking to work around it. **This is no longer outstanding: the Product Owner observed the rendered screen on production on 22 September 2026 and supplied the screenshot as Gate D evidence.** That observation, not mine, is the live evidence |

**None of the eleven carried Phase 1 items is closed by this unit** — nine inherited, plus the two P1-11 raised at Gate C. The System
Health tile may SHOW a carried gate's state; showing it closes nothing.

---

## 9. Three things put to you, not decided

Each is explained in full in `P1-11-ADMINISTRATION-HOME-VERIFICATION.md`.

| | |
| --- | --- |
| **A small Access Reviews change beyond the agreed scope** | The approved DESIGN asked for two new read seams and then, elsewhere, forbade the way it described reading Access Reviews. One more small seam was added to Access Reviews rather than weakening the rule. **§7.1** |
| **An Organisation Administrator's sidebar has one item in it** | D-182 was granted narrowly and on purpose. The consequence is a sidebar showing only Administration Home. **You should see that before testing, not during it. §7.2** |
| **The System Health tile does not show one overall status** | The DESIGN said it would. System Health itself refuses to roll its rows into one verdict, for a reason that applies here too, so the tile shows two plain counts instead — what needs attention, and what nobody has checked yet. **§7.3** |

### All three have since been ruled on — **you have already decided these**

| | Your ruling |
| --- | --- |
| The Access Reviews seam | **PO-R1 — APPROVED.** Kept, and it stays owned by Access Reviews rather than moving into Administration. Review visibility is still decided by Access Reviews' own authority, and Administration Home is still forbidden to read review records directly |
| The one-item sidebar | **PO-R2 — ACCEPTED.** An Organisation Administrator sees **Administration Home and nothing else** in System Administration. Nothing was widened, and nothing needed changing |
| The System Health tile | **PO-R3 — APPROVED.** The two plain counts are kept. **No single overall System Health status is to be created.** The old "one overall state" wording is superseded, and System Health itself was not changed |

**Check 3 and Check 6 below still stand as written** — they are how you confirm
on the real screen that these rulings are what actually shipped: **Check 3** that
the System Health tile's two counts agree with the System Health screen itself
(PO-R3), and **Check 6** that an Organisation Administrator's sidebar holds
Administration Home and nothing else (PO-R2).
