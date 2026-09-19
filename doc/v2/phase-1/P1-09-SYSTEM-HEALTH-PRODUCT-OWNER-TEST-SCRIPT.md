# P1-09 — System Health: Product Owner Test Script (Gate D)

**Written for you, not for a developer.** Your words, your screens, your
decisions. **Eight checks**, about ten minutes.

---

## 1. Feature being tested

**System Health** — one read-only screen under System Administration that shows
whether SemantIQ and the services it depends on are working.

## 2. Deployed build

| | |
| --- | --- |
| Merge SHA | **`5b2e10a6cf4c7d844ceeb4a2e583d0c2a0682361`** |
| Pull request | #123, squash-merged after CI run 312 |
| Deploy | **run 147 — success**; post-merge CI **run 313 — success** |
| Live bundle | `app-CZrBS9Mq.js`, **byte-identical** to a build of the merge SHA |
| Migrations run | **None.** *"Nothing to migrate."* This unit creates no table |
| URL | `/console/system-health` — or **System Administration → System Health** in the sidebar |

## 3. Preconditions

- You are signed in as a **System Administrator**. Nobody else can reach this
  screen, by menu or by URL.
- Nothing else needs setting up. **This screen has no configuration.**

## 4. Test data required

**None.** You create nothing, change nothing and delete nothing. Every word on
the screen is read from the running system at the moment you open the page.

## 5. Warning about permanent data

**Nothing in this script creates permanent data, and nothing on this screen can
be deleted, because nothing is stored.**

**One thing is recorded, and it is not new.** Pressing **Check sign-in now**
(checks 5 and 6) runs Identity & SSO's existing sign-in check and writes the
same Audit entry it has always written — the same one the *Re-check now* button
on Identity & SSO writes. **This is a normal, expected entry.** As with every
Audit entry, it cannot be removed. If you would rather not add one, skip checks
5 and 6 and mark them SKIPPED; nothing else depends on them.

**This screen cannot restart, clear, re-run or switch off anything.** There is
no button on it that changes the system.

## 6–7. The eight checks, and what should happen

| # | What you do | What you should see | PASS / FAIL |
| --- | --- | --- | --- |
| **1** | Open **System Administration → System Health** and read the five headings down the page | Exactly **Application**, **Integrations**, **Jobs**, **Connections**, **Service Health** — those five names, in that order | ☐ PASS ☐ FAIL |
| **2** | Read every row: the name, the status, and the sentence underneath | **Plain business wording throughout.** No hostname, no tenant or directory ID, no database name, no file path, no driver, no version number, no error or exception text, no password or secret, and no raw figures | ☐ PASS ☐ FAIL |
| **3** | Look at **Jobs** | **Background work — Available.** **Background service — Not applicable.** **Scheduled tasks — Not configured.** All three read as ordinary statements about how this deployment is set up, **not as faults** | ☐ PASS ☐ FAIL |
| **4** | Look at **Integrations** | **Microsoft Entra ID** shows either a genuine result **with a "Last checked …" age beneath it**, or an honest **Not checked**. **Never Available without an age** | ☐ PASS ☐ FAIL |
| **5** | Press **Check sign-in now** once. *(Skip if you would rather not add the Audit entry described in §5.)* | Sign-in is checked, a green confirmation appears, and **you stay on the System Health page** — you are not moved to another screen. The Microsoft Entra ID row updates and its age becomes recent | ☐ PASS ☐ FAIL ☐ SKIPPED |
| **6** | Press **Check sign-in now** again straight away | A polite refusal: **"Health was checked moments ago. Try again shortly."** No countdown, no timer, no error page | ☐ PASS ☐ FAIL ☐ SKIPPED |
| **7** | Look at **Service Health** | **Local service health**, **Record integrity** and **Record keeping** are all understandable. They say the record of what happened is sound — and **they show none of its contents**: no event, no person, no count, no dates from the log | ☐ PASS ☐ FAIL |
| **8** | Narrow your browser to phone width (or use a phone), switch between light and dark, then press **Back** | Nothing runs off the edge of the screen at either width, both themes are readable, and **Back** returns you where you came from | ☐ PASS ☐ FAIL |

## 8. Negative, refusal and security cases

**Check 6 is the refusal case**, and it is the only one you are asked to
exercise. It is the existing protection that stops repeated checks reaching
Microsoft.

**You are NOT asked to induce any failure.** Seeing *Unavailable* on a real row
would mean breaking the database, the session store, the cache, file storage,
the Audit record or sign-in **on purpose, in production**. This script will not
ask you to do that. Those states are automated Gate C evidence — see §12.

**The security boundary was verified in production before this script was
written**, so you are not asked to test it by hand:

| Verified | Result |
| --- | --- |
| `/console/system-health` exists as **exactly one GET** | Confirmed. POST, PUT, PATCH and DELETE all return 405 |
| The route requires **System Administrator** (`platform_admin`) | Confirmed on the deployed route table |
| A signed-out visitor | Redirected to the sign-in page. **No error detail, no stack trace, no internal name** in the response |
| No sibling paths exist | `/console/system-health/clear`, `/recheck`, `/restart` all 404 |

## 9. Visual and UX checks

Covered inside checks 1, 2, 3, 4, 7 and 8. In particular:

- the three neutral statuses — *Not applicable*, *Not configured*,
  *Not checked* — must read as **neutral**, never as red warnings and never as
  green successes;
- **no row should ever say Available without something having been checked**;
- the Microsoft Entra ID row under **Integrations** is the **only** row with an
  age, because it is the only one not measured as the page loads.

## 10. Evidence to capture

1. A screenshot of the **whole page** at your normal window width.
2. A screenshot at **phone width**.
3. A screenshot in the **other theme**.
4. A screenshot of the **refusal** from check 6.
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
| **Every failure state** — *Unavailable* on the database, session store, cache, file storage or Audit record | Would mean breaking production on purpose | Automated tests induce each failure at the dependency boundary and assert the row goes red. Every guard was broken deliberately and observed to fail — `P1-09-MUTATIONS.md`, 54 mutations |
| **A real Microsoft Entra outage** | Would mean breaking sign-in for everyone | The *Unavailable* and *Needs attention* states were rendered in a browser from stored sign-in state, which is exactly what the screen reads in production |
| **"Opening this page contacts nobody"** | **You cannot see this on a screen.** It is a claim about what the server did *not* do | Tests empty the sign-in caches — the condition under which a call would happen — record every outbound request, and assert none was made. Restoring the earlier design makes those tests fail |
| **Two people opening the page at the very same instant** | The local test server handles one request at a time, and forcing true concurrency deadlocked the test database. **This was a real defect and it is fixed** — each check now uses its own private key, so two readers cannot interfere — but the fix is proven by automated tests, not by two people trying it at once |
| **A second person reading this screen** | Would mean creating a second permanent System Administrator | Not done. Carried |

**Carried forward from earlier units and unaffected by this one:** P1-02's
provider-wide SSO re-check remains **OPEN / CARRIED / UNVERIFIED**; P1-07's and
P1-08's carried items remain carried; **D-19 is unchanged** — the sidebar is
shown to System Administrators only, and nothing here widens it. **P1-10 has
not started.**
