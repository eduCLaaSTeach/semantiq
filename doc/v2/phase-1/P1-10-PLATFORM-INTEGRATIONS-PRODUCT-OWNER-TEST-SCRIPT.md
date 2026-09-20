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
| Suite at handover | **1137 tests, 1131 passed, 0 failures** |
| Gate C corrections | round 2's four — **CHECKS 9 to 14** — and round 3's three — **CHECKS 15 to 17** |

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

**Seventeen checks.** Checks 1–8 are the original unit. Checks 9–14 are the four
Gate C corrections from round 2. **Checks 15–17 are the three from round 3: SSO
you can manage after installation, destination changes that need confirming, and
the test email.** Record PASS / FAIL and what you saw for each step.

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

### CHECK 9 — Microsoft sign-in is no longer editable on the Integrations screen

**This is your Correction 3.** Identity belongs to **Identity & SSO**, and the
Integrations screen must only report it and send you there.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 9.1 | Open **System Administration → Integrations** | The **Microsoft Entra ID** card is at the top | |
| 9.2 | Look at that card carefully | It shows a status and *Last checked*, and **has no text boxes, no dropdowns and no Test connection button** | |
| 9.3 | Read the sentence under the button | It says sign-in settings are managed on the Identity & SSO screen and are not edited here | |
| 9.4 | Click **Manage Identity & SSO** | You arrive on the existing **Identity & SSO** screen — the one you already know | |
| 9.5 | Go back to Integrations. Right-click → **View page source**. Search for your **real directory (tenant) ID** | **It is not there.** The screen is not given those values at all | |

> **9.5 IS THE POINT.** Removing the *form* is not the same as removing the
> *data*. A screen that still carried the identifiers and simply declined to
> draw boxes around them would look identical to you and be a different thing.

**Evidence:** a screenshot of the Microsoft Entra ID card, and confirmation of
what you searched for at 9.5.

---

### CHECK 10 — Replacing a saved credential asks you to prove it is you

**This is your Correction 1.** Somebody who can silently replace the mail
credential can redirect this deployment's outbound mail.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 10.1 | In **Email delivery**, change **Port** only. Leave the Password box empty. Press **Save** | Saves immediately. **No Microsoft prompt** — you changed nothing privileged | |
| 10.2 | Reload and confirm the Password hint still says a value is saved | It does. **A blank box means "keep it"**, and it still does | |
| 10.3 | Now type a **new** password into the Password box and press **Save** | You are sent to **Microsoft to sign in again** before anything is saved | |
| 10.4 | **Cancel** at Microsoft, or simply close that tab and return to Integrations | The old credential is **unchanged**. Nothing was replaced | |
| 10.5 | Repeat 10.3 and this time **complete** the Microsoft sign-in | You return to Integrations with a confirmation that the credential was replaced | |
| 10.6 | Press the browser **Back** button and try to re-submit the same confirmation | It does **not** apply a second time | |
| 10.7 | Press **Test connection** on any integration | It runs **without** asking you to sign in again | |

> **10.4 and 10.6 are the two that matter.** A refusal must leave things exactly
> as they were, and one confirmation must authorise exactly one change, once.

**Evidence:** the state of the Password hint after 10.4, and what happened at
10.6.

---

### CHECK 11 — Removing a saved credential is deliberate, and never accidental

**This is your Correction 4B.**

> ### ⚠ WARNING — THIS ONE IS PERMANENT
>
> **A removed credential cannot be recovered by SemantIQ.** It is deleted, not
> deactivated. If you remove the email password you will have to obtain and
> re-enter it. **Do CHECK 11 on an integration you are willing to re-enter**,
> or stop after 11.4.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 11.1 | Scroll to the bottom of the **Email delivery** card | There is a **Saved credentials** section, separated by a line, listing *Password — saved* | |
| 11.2 | Read the button | **Remove saved credential** — a separate action. Nothing about clearing the password box does this | |
| 11.3 | Click it | A confirmation appears naming the credential, saying SemantIQ cannot recover it, and saying Email delivery will stop working until a new one is entered | |
| 11.4 | Click **Keep it** | The confirmation closes. **Nothing was removed** | |
| 11.5 | *(Only if you accept the warning above.)* Click **Remove saved credential** then **Remove saved password** | You are sent to **Microsoft to sign in again** first | |
| 11.6 | Complete the Microsoft sign-in | You return with a confirmation. The **Saved credentials** section is gone, the hint reads *"No value is saved yet."*, and the status now reads **Not configured** | |
| 11.7 | Look at *Last checked* on that card | It is **gone**. The old test result was withdrawn with the credential | |
| 11.8 | Open **Audit** and find the entry for this change | It records that the integration changed. It does **not** contain the credential, the mail host or any endpoint | |

**Evidence:** screenshots at 11.3 and 11.6, and the Audit entry from 11.8.

---

### CHECK 12 — "Not configured" and "Not checked" say different things

**This is your Correction 4A.**

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 12.1 | Look at an integration you have never set up — **Microsoft Fabric**, most likely | Status **Not configured**, and the sentence reads *"This has not been set up yet."* | |
| 12.2 | Check that the badge and the sentence agree | They do. It does **not** say "has not been checked" for something nobody has set up | |
| 12.3 | Fill in every field of an integration, including its secret, and **Save** — but do **not** press Test connection | Status changes to **Not checked**. Now there *is* something to test | |
| 12.4 | Press **Test connection** | Status becomes a real result — *Available*, *Needs attention* or *Unavailable* — with *Last checked* beside it | |
| 12.5 | Now clear one required field (for example the mail server address) and **Save** | Status returns to **Not configured**, and *Last checked* disappears | |

> **12.5 IS THE ONE TO WATCH.** Removing a required field must not leave the
> screen reporting a successful test for a configuration that can no longer
> work.

**Evidence:** screenshots of the four states at 12.1, 12.3, 12.4 and 12.5.

---

### CHECK 13 — The setup session times out on its own schedule

**This is your Correction 2.** The local setup administrator is the most
powerful account a deployment ever has, and an unattended browser must not
leave it open.

> **This check requires a deployment where First-Run is open** — see §12.1.
> On production it is **NOT CURRENTLY OBSERVABLE** — see §12.1 and §12.9.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 13.1 | Sign in to First-Run and note the time | You reach the setup overview | |
| 13.2 | Leave the browser completely idle for **over 30 minutes**, then click any setup link | You are signed out and returned to setup sign-in | |
| 13.3 | Sign in again. Click something every few minutes so you are never idle for 30 | You stay signed in | |
| 13.4 | Keep doing that past the **4-hour** mark from 13.1's sign-in | You are signed out **anyway**. Continuous activity does not extend it | |
| 13.5 | Sign in to SemantIQ normally as yourself and leave that idle for 35 minutes | Your **normal** session is unaffected by the setup policy | |

**Evidence:** the times at 13.1, 13.2 and 13.4.

---

### CHECK 14 — Setup asks for your setup password before anything irreversible

**This is the Bootstrap half of Correction 1.** Microsoft step-up cannot be used
here, because during setup Microsoft may not exist yet.

> **Same precondition as CHECK 13** — a deployment where First-Run is open.
> See §12.1 and §12.9.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 14.1 | On a First-Run integration screen where a secret is **already saved**, type a new one | A **Confirm with your setup password** box is shown | |
| 14.2 | Press Save with that box **empty** | Refused. The credential is unchanged | |
| 14.3 | Press Save with a **wrong** password | Refused, with **the same wording** as 14.2 | |
| 14.4 | Press Save with the **correct** setup password | Saved | |
| 14.5 | Go to **The first administrator**. Enter an address and press the button with the password box **empty** | Refused. **No handover link is created** | |
| 14.6 | Repeat with the correct setup password | The one-time handover link appears | |
| 14.7 | On a First-Run integration screen, look for a way to remove **Microsoft sign-in's** saved secret | There is **none**. Setup can enter and replace it; removing it belongs to Identity & SSO | |

> **14.3 MATTERS.** If a wrong password produced different wording from a blank
> one, the difference would itself be information.

**Evidence:** the two refusal messages from 14.2 and 14.3, side by side.

---

### CHECK 15 — Microsoft sign-in can be changed after installation

**This is your round 3 correction 1, and the biggest one.** Until now First-Run
could set sign-in up and nobody could ever change it. A customer whose Entra
client secret expired — which they all do — had no way back except SSH.

> ### ⚠ THIS CHANGES HOW EVERYONE SIGNS IN
>
> Do CHECK 15 **before** you do anything else that day, and have the current
> working details to hand. SemantIQ checks the new details against Microsoft
> before they take effect and keeps the old ones if the check fails — but read
> the screen, not this paragraph, and stop if it does not say the same thing.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 15.1 | Open **Integrations**. On the Microsoft Entra ID card, click **Manage Identity & SSO** | You arrive on the Identity & SSO screen you already know | |
| 15.2 | Read that screen | It no longer says the details are "set on the server" or "cannot be changed from this screen" | |
| 15.3 | Find **Change configuration** at the bottom | It is there, with a sentence saying you will be asked to sign in with Microsoft again | |
| 15.4 | Click it | A change screen opens. It lists, before the fields: you enter details → SemantIQ asks you to sign in again → SemantIQ checks them → only then do they take effect | |
| 15.5 | Check what is pre-filled | Directory, application and return address carry your **current** values. The **client secret box is empty** | |
| 15.6 | Right-click → **View page source** and search for your real client secret | **It is not there** | |
| 15.7 | Clear the **Directory (tenant) ID** and press Continue | **Refused**, saying it cannot be left empty while sign-in is in use. Nothing is staged | |
| 15.8 | Put the correct directory back. Change the **return address** to something wrong — e.g. add `-x` — and press **Continue to Microsoft** | You are sent to Microsoft to sign in | |
| 15.9 | Complete the Microsoft sign-in | You return to Identity & SSO with a message saying the details did not check out, **and sign-in has not been changed** | |
| 15.10 | Confirm you can still sign out and sign back in normally | You can. The old configuration is still live | |
| 15.11 | Now do a **real** change you actually want — most likely a new client secret from Microsoft Entra — and complete the Microsoft sign-in | You return with a confirmation that sign-in has been updated and checked | |
| 15.12 | Sign out completely and sign back in | It works, using the new details | |
| 15.13 | Open **System Health** | The sign-in row does **not** still show the result from before the change | |

> **15.9 AND 15.10 ARE THE POINT.** A confirmed but wrong change must leave
> you able to sign in. Everything else on this screen depends on that being
> true.

**Evidence:** screenshots at 15.4, 15.9 and 15.11, and confirmation you signed
out and back in at 15.12.

---

### CHECK 16 — Moving where a credential is sent also needs confirming

**This is your round 3 correction 2.** A saved password does not have to be
stolen if the destination can be moved to meet it.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 16.1 | In **Email delivery**, with a password already saved, change only the **Send from name** and press Save | Saves immediately. **No Microsoft prompt** — a display name cannot redirect anything | |
| 16.2 | Now change only the **Mail server address** and press Save | You are sent to **Microsoft to sign in again**, even though you did not touch the password | |
| 16.3 | Cancel at Microsoft, return to Integrations and reload | The mail server address is **unchanged**. Nothing was saved | |
| 16.4 | Repeat 16.2 and complete the Microsoft sign-in | The new address is saved, and the status returns to **Not checked** | |
| 16.5 | Press **Save** again without editing anything | Saves immediately. **No Microsoft prompt** — nothing changed | |
| 16.6 | Try the same with **AI service** → Service address, if a key is saved | Same behaviour: confirmation required | |
| 16.7 | Try the same with **Microsoft Fabric** → Directory or Application ID, if a secret is saved | Same behaviour: confirmation required | |
| 16.8 | On an integration with **no** saved credential, change its address and Save | Saves immediately. There is nothing to redirect | |

> **16.3 IS THE ONE THAT MATTERS.** If the new address were saved before you
> confirmed, the deployment would be holding a new destination beside an old
> credential — and the next connection test would offer that credential to it.

**Evidence:** screenshots at 16.2 and 16.3.

---

### CHECK 17 — Send test email, and only to you

**This is your round 3 correction 3, D-153.** Test connection proves the mail
server accepts the username and password. It proves nothing about whether the
server will let SemantIQ actually **send** — which is a different permission,
and the one that fails in production.

> **This sends a real email.** It goes to your own address and nowhere else.
> Do not do CHECK 17 on production until you are content with 17.5.

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 17.1 | Look at the Email delivery card | Below the form there is a **Send a test email** section, separate from Test connection | |
| 17.2 | Read the sentence under **Test connection** | It now says **Test connection** checks reachability and does not send anything — it no longer reads as covering the whole card | |
| 17.3 | Read the sentence under **Send test email** | It says the message goes to your own address and that you cannot send it anywhere else | |
| 17.4 | Look for a recipient box, a subject box or a message box | **There are none.** There is only a button | |
| 17.5 | Press **Send test email** | A confirmation appears saying a message was sent to your own address | |
| 17.6 | Check your own inbox | One short message arrives, subject **SemantIQ email delivery test**, from the **Send from address** you configured — not from the SMTP username | |
| 17.7 | Press the button again straight away | **Refused**, saying to wait a minute | |
| 17.8 | Wait a minute and press it again | It sends | |
| 17.9 | Open **Audit** and find the entry | It records that a connection was tested and the outcome. It does **not** contain your email address, the mail host or the password | |
| 17.10 | Check that **AI service** and **Microsoft Fabric** have no such button | They do not. Only Email can send anything | |

> **17.4 AND 17.6 TOGETHER ARE THE CHECK.** A test that could be pointed at an
> address would let anyone with console access send mail from your own domain,
> through your own server, to anywhere. And a test that sent from the SMTP
> username rather than your configured sender would pass while proving nothing
> about the address you actually send from.

**Evidence:** the received message's headers from 17.6 — specifically the From
address — and the Audit entry from 17.9.

---

## 11. PASS / FAIL

Each step above has a P/F box. **A single FAIL on 3.5, 5.1/5.2, 6.7, 7.3, 8.6,
9.5, 10.4, 10.6, 11.6, 12.5, 14.2, 14.5, 15.7, 15.9, 15.10, 16.3, 17.4 or 17.6
should stop acceptance** — those protect a guarantee rather than a convenience.

Checks 13 and 14 cannot be run on production (see §12.9). A blank P/F there is
expected and is **not** a FAIL.

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

Nine First-Run screens **and the Integrations screen** at 1440px and 390px,
light and dark, were driven in a real Chromium browser against a local server,
including the first-administrator screen carrying a live handover link.
**No screen was opened on production**, because the implementation is not
deployed.

**This has since been resolved, and the note is kept rather than deleted.** At
the first Gate C submission the Integrations screen was NOT browser-rendered:
reaching it needs an authenticated System Administrator session, and minting one
by hand had failed. The cause was found during the Gate C corrections — this
deployment serialises sessions as **JSON**, not PHP — so a valid session can now
be created without adding anything to the product.

**The Integrations screen has therefore been opened in a real browser**, at
1440px light and 390px dark, and the observations are in §9 of the verification
document. Two defects were found by looking at it that no test had caught.

**CHECK 1 still stands**, because a local server is not production.

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

### 12.9 The setup session timeouts and setup-password reconfirmation (CHECKS 13, 14)

**NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA.**

Both live behind First-Run, which exists only while a deployment has **no**
System Administrator. Production has you. Exercising them there would mean
deactivating every System Administrator on a running system — see §12.1.

There is a second reason for CHECK 13 specifically: observing the limits means
waiting **30 minutes** and then **4 hours** at a browser. Automated evidence
covers both boundaries from each side — valid at 29 min 59 s and at 3 h 59 min,
refused at 30 min and at 4 h even with continuous activity — and mutations
removing either limit fail the build.

**This is carried forward as a live-observation gate on a later unit**
(`PHASE-1-PLAN.md` §10), together with the completed Microsoft step-up round
trip for a credential replacement (CHECK 10.5), which needs a live Entra tenant.

**It is not an implementation defect.** The controls are built, and the reason
they cannot be watched on production is that production is past the point where
they apply.

### 12.10 Nothing in CHECK 11 was rehearsed against a real credential

The removal path was exercised against **placeholder** credentials on a local
server. No real email, AI or Fabric credential was created or destroyed to
produce this evidence, per your instruction — which is also why CHECK 11 carries
its own warning rather than assuming you will read §5.
