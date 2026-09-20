# P1-10 — Platform Integrations & Setup: PRODUCT OWNER TEST SCRIPT

**Written for you, not for a developer.** Your words, your screens, your
decisions.

---

## 1. Feature being tested

**P1-10 — Platform Integrations & Setup.** Three things, which are really one
thing:

1. A **local setup administrator** who can sign in *before* Microsoft sign-in
   exists — so a brand-new installation is no longer a chicken-and-egg problem.
2. A **First-Run setup flow** where that person enters Microsoft sign-in, and
   optionally email, AI and Fabric details.
3. An **Integrations screen** inside System Administration where those same
   details are managed afterwards.

---

## 2. Deployed build

| | |
| --- | --- |
| DESIGN merge | `a7aef47` — the six corrections you required |
| Implementation | **NOT DEPLOYED.** This script describes what to test **after** you approve Gate C and it is deployed |
| Suite at handover | **1079 tests, 0 failures** |

> **This has not been deployed.** Nothing below has been done to production.

---

## 3. Preconditions

- You can sign in to SemantIQ as a System Administrator, as you do today.
- Someone with SSH access to the server is available for steps 4 and 9. You
  cannot do those parts yourself, and that is deliberate.
- **Production already has a System Administrator (you).** That matters: it
  means **the First-Run flow is closed on production and you cannot see it
  there.** See §12.

---

## 4. Test data required

| What | Value to use | Permanent? |
| --- | --- | --- |
| A setup administrator address | A real address you control | **Yes — see §5** |
| A setup administrator password | At least 16 characters, chosen by you | Replaced when setup closes |
| Email server details | **Only if you already have real ones.** Otherwise skip | Yes |
| AI service details | **Only if you already have real ones.** Otherwise skip | Yes |
| Fabric details | **Only if you already have real ones.** Otherwise skip | Yes |

> **DO NOT create AI, Fabric or email accounts in order to test this.** If you
> do not already have them, leave those steps alone. They are optional by
> design, and testing that they are optional is more valuable than testing that
> they work.

---

## 5. WARNING — what cannot be deleted

**Read this before typing anything.**

| | |
| --- | --- |
| **The setup administrator** | Once created it cannot be deleted through any screen. It can only be **closed**, which happens automatically. There is exactly one, ever |
| **Audit entries** | Every save, test and sign-in below writes a permanent audit entry. There is no delete |
| **The handover link** | Shown **once**. Not stored, not re-displayable. Leaving the page loses it |
| **Integration settings** | Saving overwrites the previous value. There is no history and no undo |
| **Secrets** | Once saved, a secret is **never shown again by any screen**, deliberately. You cannot read back what you typed |

**Nothing below asks you to enter false business data.**

---

## 6–11. The checks

Eight checks. **Record PASS / FAIL and what you saw** for each step.

---

### CHECK 1 — The Integrations screen is discoverable

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 1.1 | Sign in normally. Open **System Administration** in the left sidebar | You see **Integrations** listed, below System Health | |
| 1.2 | Click **Integrations** | The screen opens. Four sections: Microsoft Entra ID, Email delivery, AI service, Microsoft Fabric | |
| 1.3 | Read the top of the page | It says only Microsoft sign-in is required | |
| 1.4 | Look at each section's status label | Each shows a status in plain words — *Available*, *Not checked*, and so on. **No raw values like `not_checked`** | |

**Evidence:** a screenshot of the Integrations screen.

---

### CHECK 2 — A saved secret is never shown back to you

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 2.1 | In **Email delivery**, look at the **Password** field | It is empty, and says *"No value is saved yet."* | |
| 2.2 | Type any placeholder password and press **Save** | The page confirms it saved | |
| 2.3 | Reload the page and look at the Password field again | Still **empty**, now saying *"A value is saved. SemantIQ never shows it again. Leave this blank to keep it."* | |
| 2.4 | Right-click the page → **View page source**. Search for the password you typed | **It is not there** | |

> **CHECK 2.4 IS THE POINT OF THIS CHECK.** A field that merely *looks* blank
> can still have the value in the page. It does not.

**Evidence:** confirm you searched the page source, and what you searched for.

---

### CHECK 3 — Changing a setting withdraws the old result

**This is Correction 5, and it is the one most worth your attention.**

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 3.1 | In **Email delivery**, enter a mail server address and port. Save | Saved | |
| 3.2 | Press **Test connection** | A result appears — most likely *Not working*, because the details are placeholders. **That is a valid result for this check** | |
| 3.3 | Note the status and the "Last checked" time | Both shown | |
| 3.4 | Now change the **mail server address** to something different. Save | | |
| 3.5 | Look at the status and the "Last checked" time again | Status is **Not checked**. **The "Last checked" time has GONE** | |

> **WHY 3.5 MATTERS.** The old time was true, and the claim it appeared to
> support — *"this configuration was tested"* — was false. If you still see a
> time beside "Not checked", **that is a FAIL.**

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 3.6 | Test again, then change **only the Password** and save | Status returns to **Not checked**, time gone again | |
| 3.7 | Test again, then change **only "Send from name"** and save | Status **stays** as it was. A display-only change does not withdraw a result | |

**Evidence:** screenshots before and after 3.4, showing the status and time.

---

### CHECK 4 — The setup administrator is created over SSH only

**Your SSH person does this. You watch.**

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 4.1 | Ask them to run `php artisan semantiq:bootstrap-administrator --email=you@yourdomain` on a **non-production** environment | It **refuses**, saying this deployment already has a System Administrator — if run on production | |
| 4.2 | Ask them to try passing the password on the command line, e.g. `--password=something` | The command **rejects it as an unknown option**. There is no way to pass a password that way | |
| 4.3 | On a test environment with no administrator, have them run it properly | It asks for the password **twice**, and **does not show what is typed** | |
| 4.4 | Ask them to read back the password from anywhere — the database, the log | **They cannot.** Only a hash is stored | |

> **4.2 IS A SECURITY CHECK, NOT A CONVENIENCE ONE.** A password on a command
> line ends up in the shell history and in the server's process list.

**Evidence:** the exact refusal text from 4.1 and 4.2.

---

### CHECK 5 — The First-Run screens (test environment only)

**Only possible where no System Administrator exists.** See §12.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 5.1 | Open `/first-run/sign-in`. Enter the setup address with a **wrong** password | *"Those sign-in details were not accepted."* | |
| 5.2 | Enter a **completely unknown** address with the right password | **Exactly the same message** | |
| 5.3 | Sign in correctly | The setup overview opens | |
| 5.4 | Read the overview | Microsoft sign-in is marked **Required**; the other three **Optional** | |
| 5.5 | Try to reach `/console` or `/console/integrations` in the address bar | **Refused.** You do not get in | |
| 5.6 | Look at the page | There is **no sidebar and no product menu**. This does not look like the console | |

> **5.1 AND 5.2 MUST MATCH WORD FOR WORD.** If they differ, the screen can be
> used to find out whether a deployment has a setup administrator.

**Evidence:** both refusal messages, copied exactly.

---

### CHECK 6 — Handing over to the first permanent administrator

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 6.1 | On the overview, before entering Microsoft details, open **The first administrator** | It says Microsoft sign-in must be entered first, **and gives you a button to go there** | |
| 6.2 | Enter and save real Microsoft Entra details for the test directory. Press **Test connection** | A result appears | |
| 6.3 | Return to **The first administrator**. Enter the address of the person who will run it | | |
| 6.4 | Press **Create the handover link** | A link appears, with a clear warning that it is shown **once** | |
| 6.5 | Reload the page **without copying the link** | **The link is gone.** You must nominate again | |
| 6.6 | Nominate again, copy the link, and open it **as the nominated person** | They are asked to sign in with Microsoft | |
| 6.7 | Have **someone else** sign in with that link | **Refused** — and the link **still works** for the right person afterwards | |
| 6.8 | Have the **right** person sign in | They become the first System Administrator | |

> **6.7 IS THE ONE TO WATCH.** If the wrong person's attempt *uses up* the
> link, the installation is stranded: no administrator, and the one-time link
> is spent.

**Evidence:** the wrong-person refusal, and confirmation the link still worked.

---

### CHECK 7 — Setup closes, and stays closed

**This is Correction 3 — the most important one.**

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 7.1 | Immediately after 6.8, go back to `/first-run/sign-in` and enter **the setup password you chose** | **Refused** | |
| 7.2 | Now deactivate the new System Administrator so **no active System Administrator remains** | | |
| 7.3 | Try `/first-run/sign-in` with the **original setup password** again | **STILL REFUSED** | |

> **7.3 IS THE WHOLE CHECK.** Before this correction, losing every
> administrator brought the original setup password back — a permanent way in,
> reachable by deactivating one account. **If that password works at 7.3, this
> is a FAIL and must not ship.**

**Evidence:** confirm 7.3 refused, and that no administrator existed at the time.

---

### CHECK 8 — Recovery is deliberate, and closes again

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 8.1 | With still no administrator, ask your SSH person to run `php artisan semantiq:bootstrap-recovery` | A token is printed **once**, valid 30 minutes | |
| 8.2 | Ask them to show it to you again | **They cannot.** It is not stored | |
| 8.3 | Open `/first-run/recover`, enter the token and choose a new setup password | Accepted. You can sign in to setup again | |
| 8.4 | Try the **same token** a second time, in a new browser session | **Refused** | |
| 8.5 | Restore a System Administrator and have them sign in with Microsoft | | |
| 8.6 | Try the **recovery password** you set at 8.3 | **Refused.** Recovery has closed again | |

**Evidence:** the refusals at 8.4 and 8.6.

---

## 11. PASS / FAIL

Each step above has a P/F box. **A single FAIL on 3.5, 5.1/5.2, 6.7, 7.3 or
8.6 should stop acceptance** — those are the five that protect a guarantee
rather than a convenience.

---

## 12. WHAT CANNOT CURRENTLY BE TESTED, AND WHY

**Stated plainly, and not inferred from a passing test.**

### 12.1 The First-Run flow cannot be exercised on production

**NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA.**

First-Run exists only while a deployment has **no** System Administrator.
Production has you. The only way to see it there would be to deactivate every
System Administrator on the live system — which would lock everyone out of a
running deployment to look at a setup screen.

**So CHECKS 5, 6, 7 and 8 need a separate test environment.** The automated
evidence is kept: B10–B15, C4–C5 and the handoff cases all exercise these paths,
and the mutations that would break them are recorded. **The live observation is
carried forward as a gate**, per `PHASE-1-PLAN.md` §10.

### 12.2 Real AI, Fabric and email connections are not tested

**NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA**, by your instruction.
No AI, Fabric or email credentials were created for testing. What IS proven
automatically: the AI test performs no inference, the Fabric test reads no
business data and touches only the one configured workspace, the email test has
**no recipient field at all**, and every failure returns SemantIQ's own words
rather than the provider's.

**What is not proven: that a real provider answers as expected.** That needs
real credentials, and creating them to satisfy a test is what you ruled out.

### 12.3 The production Entra cutover has not been performed

Explicitly excluded from Gate C by your instruction. Production still reads its
Microsoft configuration from the server environment, unchanged.

### 12.4 MySQL behaviour is proven in CI, not by hand

The suite runs on SQLite locally; the five new tables, the singleton
constraints and the atomic closing write are exercised against **MySQL 8.4 in
CI**. There is no MySQL server in the development environment, so this was not
run by hand — it is reported as what it is.

### 12.5 The browser evidence is from a local server, not production

Seven screens at 1440px and 390px, light and dark, were driven in a real
Chromium browser against a local server. **No screen was opened on production**,
because the implementation is not deployed.

### 12.6 There is still no JavaScript test runner

Unchanged and carried: **no CI test renders the DOM.** The browser evidence
above is a manual sweep recorded in the verification document, not an automated
gate that would catch a regression next month.

### 12.7 P1-02's provider-wide SSO re-check remains unverified

**OPEN / CARRIED / UNVERIFIED**, unchanged by this unit. It needs a genuine
second **permanent** System Administrator, and no second privileged account was
created to close it.

### 12.8 Production session-driver alignment remains open

**OPEN / CARRIED**, unchanged. Production runs `file` sessions; the target is
`database`. **P1-10 did not touch it**, and D-166 was designed around the
current reality rather than the target.
