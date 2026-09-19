# P1-09 — System Health: Product Owner Test Script

**Written for you, not for a developer.** Your words, your screens, your
decisions. Eight checks, about ten minutes.

---

## 1. Feature being tested

**System Health** — one read-only screen under System Administration that shows
whether SemantIQ and the services it depends on are working.

## 2. Deployed build

| | |
| --- | --- |
| Merge SHA | **to be completed at deployment.** This unit is **not merged and not deployed** at the time of writing |
| Deploy run | to be completed |
| URL | `/console/system-health` — or **System Administration → System Health** in the sidebar |

## 3. Preconditions

- You are signed in as a **System Administrator**. Nobody else can reach this
  screen, by menu or by URL.
- The release has been deployed and its database changes applied.
- Nothing else needs setting up. **This screen has no configuration.**

## 4. Test data required

**None.** You create nothing, change nothing and delete nothing. Every number
and word on the screen is read from the running system at the moment you open
the page.

## 5. Warning about permanent data

**Nothing in this script creates permanent data, and nothing on this screen can
be deleted, because nothing is stored.**

**One thing is recorded, and it is not new.** Pressing **Check sign-in now**
runs Identity & SSO's existing sign-in check and writes the same entry to the
Audit record it has always written — the same one the *Re-check now* button on
Identity & SSO writes. **This is a normal, expected entry.** It cannot be
removed, as with every Audit entry. If you would rather not add one, skip
**Check 6** and record it as not run; nothing else depends on it.

**This screen cannot restart, clear, re-run or switch off anything.** There is
no button on it that changes the system.

## 6–7. The checks, and what should happen

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | --- |
| **1** | From any console screen, open the sidebar, find **System Administration**, and look for **System Health**. Click it | The entry is there, is not greyed out, does **not** say "Soon", and takes you to a page headed **System Health**. It should be highlighted as the page you are on | ☐ PASS ☐ FAIL |
| **2** | Read the five headings down the page | Exactly **Application**, **Integrations**, **Jobs**, **Connections**, **Service Health** — in that order, each with a plain-English line under it saying what it covers | ☐ PASS ☐ FAIL |
| **3** | Read every row: the name on the left, the status on the right, the sentence underneath | **Every row makes sense without a developer.** No dotted key like `session.driver`, no file path, no server name, no database name, no version number, no error text, no raw numbers | ☐ PASS ☐ FAIL |
| **4** | Look at **Jobs** | **Background work — Available.** **Background service — Not applicable.** **Scheduled tasks — Not configured.** All three read as ordinary statements about how this deployment is set up, **not as faults**. Neither of the last two should look like a warning | ☐ PASS ☐ FAIL |
| **5** | Look at the **Integrations** area | Either a genuine state with an age under it — *"Last checked 3 hours ago"* — or an honest **Not checked** saying sign-in has not been checked on this release yet. **It must not simply say Available with no age.** Opening this page deliberately does not contact Microsoft, so what you see is the last real answer | ☐ PASS ☐ FAIL |
| **6** | Press **Check sign-in now**. *(Skip if you would rather not add the Audit entry described in §5.)* | Sign-in is checked, a green confirmation says **"Health re-checked."**, and **you stay on the System Health page** — you are not moved to another screen. The Sign-in row updates and its age becomes recent | ☐ PASS ☐ FAIL ☐ SKIPPED |
| **7** | Press **Check sign-in now** again straight away | A polite refusal: **"Health was checked moments ago. Try again shortly."** No countdown, no timer, no error page. This is the existing protection that stops repeated checks reaching Microsoft | ☐ PASS ☐ FAIL |
| **8** | Look at **Service Health**, then narrow your browser window (or use a phone), then switch between light and dark, then press **Back** | **Local service health** reads as *this application's own checks*, and says sign-in is reported separately under **Integrations** — **not** as a verdict on Microsoft. Nothing runs off the edge of the screen at any width, both themes are readable, and **Back** returns you where you came from | ☐ PASS ☐ FAIL |

## 8. Negative, refusal and security cases

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | --- |
| **N1** | While signed in as a System Administrator, look for any button on this screen that restarts, clears, re-runs, dismisses or acknowledges anything | **There is none.** The only button is *Check sign-in now*, and that runs a check — it changes nothing | ☐ PASS ☐ FAIL |
| **N2** | Sign out. Paste `/console/system-health` into the address bar | You are sent to the sign-in page. You do **not** see the health screen, and you see no error detail | ☐ PASS ☐ FAIL |

**Check 7 is already covered above** and doubles as the refusal case.

**Not asked of you, deliberately:** every failure state. Seeing *Unavailable*
on a real row would mean breaking the database, the session store, the cache,
file storage or sign-in **on purpose, in production**. This script will not ask
you to do that. Those states are proven by automated tests and were rendered in
a browser against broken dependencies — see §12.

## 9. Visual and UX checks

Covered inside checks 3, 4, 5 and 8. In particular:

- the three neutral statuses — *Not applicable*, *Not configured*,
  *Not checked* — must read as **neutral**, never as red warnings and never as
  green successes;
- **no row should ever say Available without something having been checked.**
  If you see Available, a check ran;
- the Microsoft Entra ID row under **Integrations** is the **only** row with an
  age, because it is the only one that is not measured as the page loads.

## 10. Evidence to capture

1. A screenshot of the **whole page** at your normal window width.
2. A screenshot at a **narrow width** (or on your phone).
3. A screenshot in the **other theme** (light or dark, whichever you did not
   use above).
4. A screenshot of the **refusal** from check 7.
5. If any check fails: a screenshot of that row or message, and what you
   expected instead.

## 11. PASS / FAIL

A field is provided against every check above. Please also record:

| | |
| --- | --- |
| Overall result | ☐ ACCEPTED ☐ ACCEPTED WITH FINDINGS ☐ NOT ACCEPTED |
| Date and time | |
| Anything that read oddly, even if it passed | |

## 12. What cannot currently be tested, and why

**Never inferred from a passing test, and never silently omitted.**

| Not testable by you | Why | Where its evidence is |
| --- | --- | --- |
| **Every failure state** — *Unavailable* on the database, session store, cache, file storage or audit record | Would mean breaking production on purpose | Automated tests induce each failure at the dependency boundary and assert the row goes red. Every one was broken deliberately and the test observed to fail — `P1-09-MUTATIONS.md` |
| **A real Microsoft Entra outage** | Would mean breaking sign-in for everyone | The *Unavailable* and *Needs attention* states were rendered in a browser from stored sign-in state, which is exactly what the screen reads in production |
| **"Opening this page contacts nobody"** | **You cannot see this on a screen.** It is a claim about what the server did not do | Tests empty the sign-in caches — the condition under which a call would happen — record every outbound request, and assert none was made. Restoring the earlier design makes those tests fail | 
| **A second person reading this screen at the same time** | Would mean creating a second permanent System Administrator | Not done. Carried |
| **Two people opening this page at the very same instant** | The local test server handles one request at a time, so it cannot produce a genuine collision, and forcing one deadlocked the test database. **This was a real defect and it is fixed** — each check now uses its own private key, so two readers cannot interfere with each other — but the fix is proven by automated tests rather than by two people trying it at once |
| **The session-store check under load or contention** | Would mean generating artificial load on production | Covered by tests only |

**Carried forward from earlier units and still open — not affected by this
one:** P1-02's provider-wide SSO re-check remains **OPEN / CARRIED /
UNVERIFIED**; P1-07's and P1-08's carried items remain carried; **D-19 is
unchanged** — the sidebar is shown to System Administrators only, and nothing
here widens it.
