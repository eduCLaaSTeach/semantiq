# P1-10 — Platform Integrations & Setup: PRODUCT OWNER GATE D TEST SCRIPT

**Eight checks. All of them safe to run on the live system.**

This is the script to use for acceptance. It is not a shortened version of the
Gate C script for convenience — it is a *different* script, because the Gate C
one asks for things that must never be done to production.

---

## 1. Feature being tested

**P1-10 — Platform Integrations & Setup**, as deployed.

Three things, which are really one:

1. A **local setup administrator** who can sign in before Microsoft sign-in
   exists, so a brand-new installation is no longer a chicken-and-egg problem.
2. A **First-Run setup flow** where that person enters Microsoft sign-in and,
   optionally, email, AI and Fabric details.
3. An **Integrations screen** inside System Administration where those same
   details are managed afterwards — including, now, **changing Microsoft
   sign-in after installation**.

Checks 1 to 7 look at the third. Check 8 confirms nothing else moved.

---

## 2. Deployed build

| | |
| --- | --- |
| Merge | `1a4068b` — squash of PR #131 into `main` |
| Gate C head reviewed | `020de16` |
| Post-merge CI | run **341** — SUCCESS |
| Deployment | **Deploy to cPanel (SSH)** run **154** — SUCCESS |
| Suite at merge | **1176 tests, 1170 passed, 0 failures** |
| Suite now | **1186 tests, 1180 passed, 0 failures** — ten cases added for the defect in §9.6 |
| Follow-up merge | `27904cc` — PR #132: the §9.6 defect, the read-only production verification, and this script |
| Follow-up CI / deploy | CI run **343**, deploy run **155** — SUCCESS, first attempt |
| Production state verified | `Verify P1-10 Platform Setup state` run 1 and `Verify P1-02 identity state` run 4 — both SUCCESS |

> Deploy run 154 needed a second attempt. The first attempt stopped at the
> pre-flight identity check when the SSH connection to the server timed out.
> **Production was not touched**: the maintenance window never opened and no
> file was synced. The second attempt ran all 29 steps. This is recorded
> because "it passed on a re-run" and "it passed" are different statements.

---

## 3. Preconditions

- You can sign in to SemantIQ as a System Administrator, as you do today.
- **You do not need anyone with SSH access.** Nothing below requires it.
- **You do not need any new account, credential or test data.** Nothing below
  asks you to create one.
- Production already has a permanent System Administrator (you), which is why
  the setup screens are closed there. That is the correct state, not a gap.

---

## 4. Test data required

**None.**

That is the whole entry, and it is deliberate. Every check below reads a screen
that already exists or navigates between screens you already have. Nothing asks
you to invent a mail server, an endpoint, a key or an address.

---

## 5. WARNING — what cannot be deleted

**Read this before you click anything, even though this script asks you to
save nothing.**

| | |
| --- | --- |
| **Integration settings** | Saving overwrites the previous value. There is no history and no undo |
| **Secrets** | Once saved, a secret is **never shown again by any screen**, deliberately. You cannot read back what you typed |
| **Audit entries** | Every save and every test writes a permanent audit entry. There is no delete |
| **Microsoft sign-in** | Changing it is the one setting whose failure locks *everybody* out, including whoever changed it. This script does not ask you to change it |

**CHECK 3 opens the Microsoft sign-in change screen and stops there.** Do not
press Save on it. If you do, SemantIQ will send you to Microsoft to
re-authenticate and will verify the details before anything is activated — but
the controlled cutover is a separate, scheduled exercise and this is not it.

**Nothing below asks you to enter inaccurate business data.**

---

## 6. The eight checks

---

### CHECK 1 — Navigation, and the four integrations

**Open:** System Administration → **Integrations**

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 1.1 | Find Integrations from the main navigation, without a typed address | It is reachable by clicking, and you can tell from the label what it is | |
| 1.2 | Count the cards | **Four**: Microsoft Entra ID, Email delivery, AI service, Microsoft Fabric | |
| 1.3 | Read each card's name and description | Business wording. **No** raw key, enum, route name or class name — nothing like `not_checked`, `fabric`, `console.integrations` | |
| 1.4 | Read each status | You can tell what it means without asking anybody | |
| 1.5 | Look for anything that reads like an error dump | There is none — no stack trace, no exception text, no provider error body | |

---

### CHECK 2 — Who owns Microsoft sign-in

**Stay on:** System Administration → Integrations

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 2.1 | Look at the **Microsoft Entra ID** card | A status is shown, and it is **not** "Not configured" — sign-in works, so the card must not say otherwise. It reads **Not checked**: configured, and nobody has tested it from this screen yet | |
| 2.2 | Try to type into it | **There is nothing to type into.** No field, no dropdown | |
| 2.3 | Look for a **Test connection** button on that card | **There is none.** The other three have one; this one does not | |
| 2.4 | Click **Manage Identity & SSO** | You arrive at the Identity & SSO area — a different screen, owned by a different part of the product | |

> Why this matters: Microsoft sign-in is managed in exactly one place. If it
> could also be edited here, two screens could disagree about how people sign
> in, and one of them would be wrong.

---

### CHECK 3 — The change screen, opened and not saved

**Open:** Identity & SSO → Microsoft Entra ID → **Change configuration**

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 3.1 | Read the page heading and description | It says this is where Microsoft sign-in is changed. It does **not** say the settings are read-only or server-only — that wording was removed | |
| 3.2 | Look at the directory, application and return-address fields | Each shows the current value, so you can see what you are changing from | |
| 3.3 | Look at the **client secret** box | It is **empty**, and says the saved one is kept if you leave it blank. The saved secret is **not** shown | |
| 3.4 | Read the explanation above the fields | It tells you, before you type: SemantIQ will ask Microsoft to confirm it is you, then check the new details actually work, and only then switch over | |
| 3.5 | Use your browser's **View source** and search for the secret | It is not there. Nor is it in the page in any other form | |
| 3.6 | Press **Back** / **Cancel** — **do not save** | You return to the Microsoft Entra ID screen and **nothing has changed** | |

---

### CHECK 4 — "Not configured" means not configured

**Open:** System Administration → Integrations

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 4.1 | Look at Email delivery, AI service and Microsoft Fabric | Each reads **Not configured** | |
| 4.2 | Confirm none of them claims to be working | None says Available, Connected, Healthy or anything green. **Deploying the application did not make them work** | |
| 4.3 | Read each card's supporting line | It agrees with the badge. You never see "Not configured" beside a sentence implying it was tested | |
| 4.4 | Now look back at **Microsoft Entra ID** | It does **not** say Not configured. It is configured — from the server environment, where there is no row for this screen to find. See §9.6 | |

> Why this matters: a status that is optimistic by default is worse than no
> status. "Not configured" and "configured but never checked" are different
> facts, and this screen must not blur them.

---

### CHECK 5 — How a secret behaves

**Open:** any optional integration — **Email delivery** is the clearest

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 5.1 | Look at the password / key / secret box | It is **empty** | |
| 5.2 | Read the wording next to it | It explains that a saved secret is never shown again, and that leaving the box blank keeps whatever is saved | |
| 5.3 | Use **View source** and search for anything resembling a stored secret | There is none | |
| 5.4 | Leave the screen **without saving** | Nothing changed | |

> **Do not type a placeholder secret to see what happens.** Saving one is a
> permanent write, and a made-up credential in a live system is worse than an
> untested screen.

---

### CHECK 6 — The two email actions are not the same action

**Open:** System Administration → Integrations → **Email delivery**

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 6.1 | Find the actions | There are **two**, named differently: **Test connection** and **Send test email** | |
| 6.2 | Read what each one says it does | Test connection checks the mail server accepts the details **and sends nothing**. Send test email actually sends one | |
| 6.3 | Look for somewhere to type who the test email goes to | **There is none.** No recipient box, no CC, no BCC, no subject, no message body | |
| 6.4 | Read who it says it will go to | It says so on the screen, in words: it sends one short message to **your own email address**, using the send-from address above, and **you cannot send it anywhere else** | |
| 6.5 | Check the other three integrations | **Send test email appears on Email delivery only** | |

> **Do not press Send test email** unless real, approved SMTP settings are
> already saved. There are none on production today, so the honest outcome of
> pressing it would be a refusal — which proves nothing you cannot see above.

---

### CHECK 7 — Does this look like a product

**Do this on every screen you opened above.**

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 7.1 | Look at a normal desktop window | Nothing overlaps, nothing is cut off, headings and cards line up | |
| 7.2 | Narrow the window to about phone width (~390px) | The layout reflows. **Nothing scrolls sideways.** No word is broken mid-word | |
| 7.3 | Switch to dark mode, then back to light | Both are readable. Nothing disappears into its own background | |
| 7.4 | Press **Tab** through the screen | Every control you land on is visibly outlined. Nothing is focusable but invisible | |
| 7.5 | Read every label and button out loud | They are sentences a customer would accept. No developer shorthand | |
| 7.6 | Open the browser console (F12) | **No red errors** | |

Then answer, honestly:

> **Would a professional SaaS product team be comfortable showing these exact
> screens to a customer?**

| | Answer | |
| --- | --- | --- |
| 7.7 | Yes / No — and if No, which screen and why | | |

---

### CHECK 8 — Nothing else moved

| # | Step | Expected | P/F |
| --- | --- | --- | --- |
| 8.1 | Sign out and sign in again with Microsoft | It works exactly as it did before this deployment | |
| 8.2 | Open **System Health** | It opens and reports as it did before | |
| 8.3 | Open two or three System Administration screens you have already accepted — People, Business Domains, Roles & Access, Audit | Each opens and looks unchanged | |
| 8.4 | Use the browser **Back** button several times | You go back through the screens you visited. Nothing breaks and nothing logs you out | |
| 8.5 | Confirm you were never asked to sign in locally, with a password | You were not. SemantIQ signs you in through Microsoft, as before | |

---

## 7. Evidence to capture

Keep it light — a screenshot is enough for most of these.

1. The Integrations screen, desktop, showing all four cards (CHECK 1, 4).
2. The Microsoft Entra ID card, showing no fields and no Test connection
   button (CHECK 2).
3. The Change configuration screen with the empty secret box and the
   explanation above the fields (CHECK 3).
4. The Email delivery section showing both actions and no recipient box
   (CHECK 6).
5. One narrow-width screenshot and one dark-mode screenshot (CHECK 7).
6. Your written answer to 7.7.

---

## 8. PASS / FAIL

Each step has a P/F box.

**A single FAIL on any of these should stop acceptance**, because each protects
a guarantee rather than a convenience:

| Step | What it protects |
| --- | --- |
| 2.2, 2.3 | Microsoft sign-in has exactly one owner |
| 3.3, 3.5 | A saved secret is never shown back to anybody |
| 3.6 | Opening the change screen changes nothing |
| 4.1, 4.2 | A status is never optimistic by default |
| 5.1, 5.3 | The same secret rule on every integration |
| 6.3 | The test email cannot be pointed at an address |
| 6.5 | Sending is an email capability, not a general one |
| 8.1 | Sign-in still works |

A FAIL anywhere else is a defect to raise, not necessarily a block.

---

## 9. WHAT THIS SCRIPT DOES NOT TEST, AND WHY

**Stated plainly. None of it is inferred from a passing test.**

### 9.1 Everything destructive or setup-only was removed from this script

The Gate C script has **seventeen** checks. Nine of them cannot be run on
production without doing something that should never be done to a live system:
creating a setup administrator, issuing a recovery token, deactivating the only
System Administrator, deleting a saved credential, typing a fake SMTP password
or a placeholder endpoint, waiting four hours for a session to expire, or
changing production sign-in.

**Those checks are not abandoned.** They remain in
`P1-10-PLATFORM-INTEGRATIONS-PRODUCT-OWNER-TEST-SCRIPT.md`, which is now kept
as **engineering evidence** and as the script for a test environment. Their
automated evidence — the B, C, T and H cases, and the mutations recorded in
`P1-10-MUTATIONS.md` — stands unchanged.

### 9.2 The completed Microsoft step-up round trip

**NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA.**

CHECK 3 opens the change screen and stops. What happens *after* you press Save
— Microsoft asks you to confirm, SemantIQ checks the new details against
Microsoft, and only then switches over — is proven by automated tests, including
the cases where the confirmation is refused, expires, or the new details do not
answer. In every one of those the old configuration stays live.

**Carried forward as a live-observation gate.** It needs a deliberate,
scheduled cutover, not a checklist step.

### 9.3 A real email, AI or Fabric connection

**NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA**, by your instruction.
No credentials were created to test with. CHECK 6 proves the *shape* of the
feature — two distinct actions, no recipient field anywhere — which is the part
that matters for safety. It does not prove a real mail server accepts a real
message, and that needs real approved SMTP settings.

### 9.4 First-Run, the setup session timeouts and recovery

**NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA.**

First-Run exists only while a deployment has no System Administrator.
Production has you. Seeing it live would mean deactivating every System
Administrator on a running system. The 30-minute idle and 4-hour absolute setup
session limits, and the recovery flow, are in the same position.

**All carried forward as gates**, per `PHASE-1-PLAN.md` §10.

### 9.5 No screen in this script was opened on production by the delivery team

The browser evidence behind Gate C was produced against a **local** server. The
delivery environment cannot open production in a browser: it reaches the
internet through an inspecting proxy whose certificate authority this
environment's Chromium does not trust, and the tool needed to add it is not
installed.

What *was* observed on production directly: the site answers, `/up` returns
`ok`, and the local setup sign-in address redirects to a closed page with no
password field. **CHECKS 1 to 7 are therefore genuinely your first look at
these screens on the live system**, and that is stated rather than implied.

### 9.6 One defect was found by this deployment, and fixed before this script

The Integrations screen asked "is there a saved row with every field and a
stored secret" for all four integrations. For Email, AI and Fabric that is the
whole question. For Microsoft Entra ID it is not: until the controlled cutover,
a deployment reads Microsoft sign-in from the **server environment**, where
there is no row for that screen to find.

So this deployment — the one whose sign-in demonstrably works, because you sign
in through it — would have shown **Microsoft Entra ID: Not configured**, on a
required integration, on the one screen where acting on that advice locks
everybody out.

It survived Gate C because the browser verification ran against a local server
that had no Microsoft configuration either, so "Not configured" was the right
answer there for entirely the wrong reason.

The card now asks the same authority the sign-in path asks. It does **not**
report anything positive as a result: configured and known-to-work stay
different facts, and the card reads **Not checked** until somebody checks it.

### 9.7 There is still no JavaScript test runner

No automated test renders these screens. Every visual guarantee in CHECK 7 is
either a human looking, or a guard asserting on the source of the component.
This is a known Phase 1 gap and is not new to this unit.

---

## 10. Carried live-observation gates

These are **not** manufactured to turn a checklist green. Each stays open until
it can be observed honestly.

| # | Gate | Status |
| --- | --- | --- |
| 1 | P1-02 provider-wide SSO re-check with a genuine second permanent System Administrator | OPEN / CARRIED / UNVERIFIED |
| 2 | Completed Microsoft step-up round trip for a real P1-10 privileged configuration change | OPEN / CARRIED |
| 3 | Bootstrap First-Run on a genuine fresh or test installation | OPEN / CARRIED |
| 4 | Bootstrap 30-minute idle timeout, live | OPEN / CARRIED |
| 5 | Bootstrap 4-hour absolute timeout, live | OPEN / CARRIED |
| 6 | Recovery flow, live | OPEN / CARRIED |
| 7 | Real SMTP send test, once approved real SMTP exists | OPEN / CARRIED |
| 8 | Production session-driver alignment, `file` → `database` | OPEN / CARRIED |
| 9 | Privilege-change / per-user session revocation | OPEN / Phase 1 gate |
