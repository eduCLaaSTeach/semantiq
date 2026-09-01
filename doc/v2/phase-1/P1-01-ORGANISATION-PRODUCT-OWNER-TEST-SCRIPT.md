# Product Owner Test Script — P1-01 Organisation

Written for the Product Owner, in your words, on your screens. Required by
`CLAUDE.md` §3. All twelve required parts are present.

**Read section 5 before you type anything.** This is the one script in Phase 1
that creates permanent records.

---

## 1. Feature or task being tested

**P1-01 — Organisation.** The company profile, legal entities, business units,
departments, teams and the management hierarchy, plus the lifecycle rules that
govern them.

This covers two things:

1. **The P1-01 scope completion** — the Update operations that were missing from
   Legal Entities, Business Units, Departments and Teams, the jurisdiction list,
   the registered address, the two code fields, and Set / Change / Clear on the
   Management Hierarchy. Section **S** below.
2. **D-24 — guarded permanent delete.** Section **P** below. Read section 5
   first: what can and cannot be permanently deleted has changed.
3. **The live verification** of P1-01 — the observation of six behaviours on the
   real deployment with real data, checks **2, 3, 4, 5, 7 and 9** of
   `P1-01-ORGANISATION-VERIFICATION.md` §7.5. Sections A to D below.

Work through the sections in the order they appear. Section **S** comes before
sections B and C deliberately: several of its steps are corrections you will want
in place before you enter the rest of your structure.

## 2. Deployed build / merge SHA

| | |
| --- | --- |
| P1-01 merge SHA | `9afe33d` — *P1-01 Organisation — EXECUTE* |
| Correction | `4f99c46` — Organisation was not reachable after sign-in |
| Tab navigation | `3c2b021` — presentation and navigation only |
| **Scope completion + D-24** | see the pull request for this change — the missing Update operations and guarded permanent delete |
| Deployed head at issue | recorded on merge |
| Site | https://semantiq.claas2saas.com |

The UI foundation replaced the shell these screens render inside. All six were
re-opened in a browser afterwards and behave as before
(`P1-01-ORGANISATION-VERIFICATION.md` §7.3c). No P1-01 behaviour changed.

## 3. Preconditions

1. You can sign in to production as the System Administrator.
2. You have accepted the UI foundation, so the sidebar is the finished one.
3. **You know the real structure of your organisation.** Several checks below
   ask you to record what genuinely exists. If you do not have that to hand,
   stop and come back with it rather than inventing something.

### What is already recorded, before you start

Read from production at 06:55 on 1 September 2026, counts only — no names, no
identities:

| | |
| --- | --- |
| Organisations | 1 |
| Business units | 1, **active** |
| Legal entities, departments, teams, memberships, management links | 0 |
| Users | 1, and that user carries an organisation |

So the Company Profile exists, and one business unit has been created,
deactivated and reactivated — each step permitted, because it has no
departments. **Steps 1 to 4 and step 12 are already effectively done**; what
remains is the structure itself and the lifecycle rules that depend on it.

**Already observed, so you do not need to prove them again:** check 2 (you reach
Organisation) and check 5a (creating the profile gave your account its
organisation). The four that remain are checks 3, 4, 5, 7 and 9 — steps 5 to 12
below.

## 4. Test data required

**Only real data.** The organisation, legal entities, business units,
departments and teams that genuinely exist in your business.

Product Owner direction, 31 August 2026, restated here because it governs every
step below:

> Do not create inaccurate business data, and do not falsify real organisational
> structure to satisfy a test.

Where a check cannot be exercised without inventing structure, **do not do it**.
Mark the step **NOT APPLICABLE — no genuine case exists**, and it is carried
forward. That is a data condition, not a defect, and it will not block
acceptance.

## 5. ⚠ Warning — this script creates permanent records

**Changed by D-24, 1 September 2026.** This section previously said SemantIQ had
no hard delete at all. That is no longer true, and the difference matters before
you type anything.

### What you can now undo, and what you still cannot

**You can permanently delete a Legal Entity, Business Unit, Department or Team —
but only while nothing uses it.** The moment it has a child, an association or
any membership history, permanent deletion is refused and Deactivate is your
only option. So:

- a record you add **by mistake and never use** can be removed for good;
- a record that is **used** cannot, no matter how much you would like it to be;
- an **inactive** child still counts as usage, and so does an **ended** team
  membership. Deactivating the children first does **not** unlock the parent.

**These can never be deleted, by anyone:**

- the Organisation / Company Profile;
- team membership history — ending a membership sets a leaving date and keeps
  the row;
- management relationship history — clearing a manager ends the link and keeps
  it.

### What is still permanent

- **A permanent delete is permanent.** There is no undo, no recycle bin and no
  screen that will bring the record back. The confirmation dialog is the last
  point at which you can change your mind.
- **Deactivating still does not remove anything** — that is the point of it.
- If you are unsure whether structure belongs, **leave it out**. Adding it later
  is easy; deleting it later works only while nothing has used it, and something
  usually has.

**Only enter structure that genuinely exists.**

Steps 1 to 4 and step 12 change nothing and are safe to run at any time.

## 6–7. Numbered steps and expected results

Record PASS / FAIL / NOT APPLICABLE for every step.

### A0. Organisation tab navigation — UX check (no data is created)

Added 1 September 2026. The five section buttons that used to sit at the bottom
of Company Profile are gone; every Organisation screen now carries one tab strip
directly below the page heading.

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| A1 | Open Organisation. | Below the heading **Organisation** and its one-line description, a strip of **six tabs**: Company Profile · Legal Entities · Business Units · Departments · Teams · Management Hierarchy. | |
| A2 | Look at which tab is selected. | **Company Profile**, and only that one — it is filled, outlined and joined to the content below it. The section title *Company Profile* sits beneath the strip, one step down from *Organisation*. | |
| A3 | Look at the bottom of the page. | The old **ORGANISATION SECTIONS** heading and its row of buttons are **gone**. | |
| A4 | Click **Business Units**, then **Departments**. | Each click changes the address in the browser bar to that section's own URL, and the clicked tab becomes the selected one. | |
| A5 | Press the browser **Back** button twice. | You return to Business Units, then to Company Profile — and the selected tab follows you each time. | |
| A6 | Press the browser **Forward** button. | You go forward to Business Units, tab selected correctly. | |
| A7 | **Refresh** the page. | You stay on the same section with the same tab selected. | |
| A8 | Copy a section URL, open it in a new browser tab, and sign in if asked. | It opens that section directly with the correct tab selected. | |
| A9 | Open **Business Units** and click a business unit's name. | The detail screen opens, **Business Units stays the selected tab**, and a **← Back to Business Units** link sits above the title. Click it — you return to the list. Browser Back does the same. | |
| A10 | Press **Tab** on the keyboard from the top of the page. | Focus reaches the tab strip and each tab shows a clear focus ring. | |
| A11 | Narrow the browser to a phone width. | The tab strip **scrolls sideways within itself**. It never wraps onto a second row, and **the page itself never scrolls sideways**. | |
| A12 | Look at the Company Profile form. | Compact: Name beside Legal name, Country beside Timezone, and the card sized to its content rather than running the width of the screen. | |

### S. The P1-01 scope completion ⚠ some steps change data

Added 1 September 2026. Four of the five things you manage here could be created
and deactivated but **never corrected**. That is now fixed, and this section is
where you check it.

**What changes data, and what does not.** Steps S1, S2, S8, S9, S13 and S14 only
look at a screen — they are safe. The rest save a correction, and a correction is
permanent in the same way everything else in P1-01 is: the new value replaces the
old one on the record, and there is no undo screen. **Only correct things that
are genuinely wrong.**

#### S1–S7 · Legal Entities

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| S1 | Open **Legal Entities** and look at the table headings. | Six columns: Name, Registration, Jurisdiction, **Registered address**, Status, and an unlabelled column of actions. The registered address is now something you can see. | |
| S2 | Look at the **Add a legal entity** form. | Four fields: Name, Registration number, **Jurisdiction** (a dropdown, not a typing box) and **Registered address**. | |
| S3 | Open the **Jurisdiction** dropdown. | A long alphabetical list of countries and territories, starting *Not recorded*, then *Afghanistan*. **Singapore is present.** You cannot type into it. | |
| S4 | On an existing legal entity, click **Edit**. | That row turns into editable fields **in place** — no new page, no pop-up. The other rows are unchanged and their buttons stay on one line. | |
| S5 | Change the name, then click **Cancel**. | The row returns to exactly what it was. **Nothing was saved.** | |
| S6 | Click **Edit** again. Set the **Jurisdiction** to the correct country and type the **Registered address**. Click **Save**. ⚠ *saves* | The row closes and shows your new values. Reload the page — they are still there. | |
| S7 | For the entity that is already recorded as **Singapore**: click Edit and look at the Jurisdiction dropdown. | It is **already showing Singapore** — the existing value was preserved, not lost or reset. Click Cancel. | |

#### S8–S9 · Business Units

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| S8 | Open **Business Units**. | Each row has **Edit** and Deactivate/Reactivate, in that order — the same pair, in the same place, as every other list. | |
| S9 | Click **Edit** on one, correct the name or code if either is genuinely wrong, and **Save**. If nothing is wrong, click **Cancel** instead and mark this NOT APPLICABLE. ⚠ *saves* | The corrected value appears and survives a reload. | |

#### S10–S12 · Departments and Teams — correcting a name is **not** a move

This is the point of the section. A spelling correction must not restructure your
company.

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| S10 | Open **Departments**. | A **Code** column is now shown. Under the Business unit column each row has a small **MOVE TO** caption above its dropdown — so the dropdown is visibly the *move* control, not part of editing. | |
| S11 | Find **Singapore Retai Sales**. Click **Edit**, correct it to **Singapore Retail Sales**, and **Save**. ⚠ *saves* | The name is corrected. **The business unit is unchanged** — the department has not moved, and nothing about your structure changed except the spelling. | |
| S12 | Open **Teams**. Check the **Code** column and the **Add a team** form. | Teams show their code, and the add form now has a **Code** field — it was missing before. Each row has Edit, and the department dropdown carries the same **MOVE TO** caption. | |

#### S13–S16 · Management Hierarchy

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| S13 | Open **Management Hierarchy**. | You are listed. The Manager column reads **No manager recorded** — plain words, not a dash or a blank. | |
| S14 | Read the box below the table. | It explains, in business language, that a manager can be assigned once at least two organisation users are available, that nobody can report to themselves, and that adding users belongs to User Management. **There is no Set manager button**, because there is nobody to point it at. | |
| S15 | **Only when a second user exists** — this is the P1-03 gate, so expect to mark it NOT APPLICABLE today. Click **Set manager** on one person. | The Manager cell becomes a dropdown listing the other users. **That person is not offered as their own manager.** | |
| S16 | **Only when a second user exists.** Choose a manager and Save; then use **Change manager** to pick a different one, then **Clear**. ⚠ *saves* | The button reads *Set manager* when there is none and *Change manager* when there is. Clearing removes the current manager without erasing the record of the previous one. | |

**Expected today: S15 and S16 are NOT APPLICABLE.** Production has one user, and
the second arrives with P1-03. That is a data condition, not a defect — and it is
not to be solved by creating a user by hand.

#### S17 · Superseded by D-24

This step read *"look for any way to delete a record — there is none"*. That was
true when it was written and stopped being true the same day, when you approved
D-24. **Deletion is now section P**, which tests both that it works and that it
refuses. Mark S17 **SUPERSEDED** and go to section P.

### P. Permanent deletion — D-24 ⚠ one step destroys a record

Added 1 September 2026, and this is the section to read before you run it.

**P1 to P3 create a deliberately fake record and then destroy it.** That is the
one place in this script where inventing data is not only allowed but required:
you must not test a permanent delete on a record you actually need, and you
cannot test it at all without something safe to delete. Label it so obviously
that nobody mistakes it for real structure.

**P4 to P8 change nothing.** They are refusals and observations.

#### P1–P3 · A safe permanent delete ⚠ creates then destroys a record

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| P1 | Open **Business Units** and add one named exactly **ZZ TEST — DELETE ME**. Give it no departments and no legal entity associations. | It is saved and listed as active. | |
| P2 | On that row, click **Delete permanently**. | A dialog opens. It **names the record you are deleting**, says the deletion cannot be undone, and tells you to use Deactivate instead if the record is real. The highlighted starting button is **Cancel**, not the delete. | |
| P3 | Click **Delete permanently** in the dialog. ⚠ *destroys the record* | The dialog closes and **ZZ TEST — DELETE ME is gone from the list**. Reload the page — it is still gone. Every other business unit is untouched. | |

If you would rather not create a fake record at all, mark P1 to P3 **NOT
APPLICABLE** and say so. The refusals below are the more important half and need
nothing created.

#### P4–P7 · The guard — nothing is created or changed

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| P4 | Pick a business unit that **has at least one department** — active or inactive, it does not matter. Click **Delete permanently**, then confirm. | **Refused**, in business language: *"This business unit cannot be permanently deleted because it has 1 department. Deactivate it instead."* No database words, no error codes. **The business unit and its departments are all still there.** | |
| P5 | Read that message again and check the count in it. | The number matches the departments that actually exist under it, **counting inactive ones**. Deactivating a department does not make it stop counting. | |
| P6 | **Only if a team genuinely has membership history** — anybody added and then removed. Try to **Delete permanently** that team. | **Refused**, saying membership history exists. The team and the history remain. If no team has any membership history yet, mark **NOT APPLICABLE**. | |
| P7 | Open the dialog on any record, then press **Escape**, and open it again and press **Cancel**. | Both close the dialog and **nothing is deleted**. | |

#### P8 · What has no delete at all — nothing is created

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| P8 | Look at **Company Profile** and at **Management Hierarchy**. | Neither offers Delete permanently anywhere. The hierarchy offers **Clear**, which ends the current reporting line and keeps the record of it — it is not a delete. Team membership is the same: **Remove** ends a membership and keeps the row. | |

#### P9 · The distinction is legible — nothing is created

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| P9 | On each of Legal Entities, Business Units, Departments and Teams, read the note under the table. | It explains, in business language, that **Edit** corrects a wrong detail, **Deactivate** retires a real record and keeps its history, and **Delete permanently** is only for a record entered by mistake. **Delete permanently** is the only action shown in red. | |

### A. Reaching the screens — check 2 (no data is created)

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| 1 | Sign in and look at the sidebar. | **System Administration** is open and **Organisation** is the only entry without a *Soon* pill. | |
| 2 | Click **Organisation**. | The **Company Profile** screen opens inside the shell, with the sidebar still on the left and Organisation marked as the current page. | |
| 3 | Read the profile. | It shows the organisation you already created. Name, legal name, country and timezone are as you entered them. | |
| 4 | Visit each section in turn: Legal Entities, Business Units, Departments, Teams, Management Hierarchy. | Each opens. Empty ones say so in plain words — no error, no blank screen, no developer text. | |

### B. Recording the structure that genuinely exists — check 3 ⚠ permanent

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| 5 | On **Legal Entities**, add the legal entities that genuinely exist in your business. Add nothing else. | Each is saved and listed as active. | |
| 6 | On **Business Units**, add the business units that genuinely exist. One already exists and is inactive — reactivate it if it is real and current; leave it inactive if it is not. | Each is saved and listed. | |
| 7 | On **Departments**, add the departments that genuinely exist, each under its real business unit. | Each is saved under the correct business unit. | |
| 8 | On **Teams**, add the teams that genuinely exist, each under its real department. | Each is saved under the correct department. | |

### C. The lifecycle rules

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| 9 | **Check 7 — the refusal.** Pick a business unit that genuinely has at least one **active** department, and try to deactivate it. | **Refused.** The message names the active children preventing it. **Nothing changes** — the business unit stays active and the departments are untouched. *This step is safe: a refusal writes nothing.* | |
| 10 | **Check 4 — the many-to-many.** Only if your business genuinely works this way: associate one business unit with a second legal entity, and associate one legal entity with a second business unit. | Both are permitted. The associations appear on both sides. **If your structure is not genuinely like this, mark NOT APPLICABLE and move on.** | |
| 11 | **Check 9 — a department move.** Only if a department genuinely belongs under a different business unit than the one it is recorded against: move it. | Permitted. The department appears under its new business unit. **If no genuine move exists, mark NOT APPLICABLE.** | |
| 12 | **Check 5 — team membership.** Only if it is factually correct: add yourself to a team, then remove yourself. | Both permitted. After removal you are no longer a current member, and **the record of the membership remains** with its end date — nothing is erased. **If no factually correct membership exists, mark NOT APPLICABLE.** | |

### D. Presentation and security (nothing is created)

| # | Step | Expected result | Result |
| --- | --- | --- | --- |
| 13 | Look over every Organisation screen you have visited. | Labels and buttons read as business language. No raw column names, no codes, no "undefined" or "null", no developer terminology. | |
| 14 | Narrow the browser to a phone width and open a list with several rows. | The table scrolls **inside its own box**. The sidebar and top bar stay put, and the page itself does not slide sideways. | |
| 15 | Look at the removal-shaped actions on each list. | Each row offers **Deactivate** *and* **Delete permanently**, and they are visibly different — Delete permanently is the only one in red. A short note under each table explains when to use Edit, Deactivate and Delete permanently. **The Company Profile and the Management Hierarchy offer no delete at all.** | |
| 16 | Open the browser's developer console (F12) and reload. | No red errors. | |

## 8. Negative, refusal and security cases

Step 9 is the primary refusal case for deactivation, and **steps P4 and P6 are
the refusal cases for permanent deletion** — those three prove the lifecycle
rules rather than merely exercising the happy path, and none of the three
changes any data. Step 15 and step P8 are the D-24 guarantee: delete exists,
and it does not reach the records whose history the unit is built to keep.

Twenty-one further negative cases are covered automatically and each was proven
non-vacuous by mutation — `P1-01-ORGANISATION-VERIFICATION.md` §3. **Those are
automated evidence and are not a substitute for the live observations above.**

## 9. Visual and UX checks

Steps **A1 to A12** (the tab navigation), steps **S1, S2, S4, S10, S12, S13 and
S14** (the new controls and the hierarchy's explanatory state), steps **P2, P7
and P9** (the confirmation dialog, its keyboard behaviour and the legend), plus
steps 2, 3, 4, 13 and 14.

The dialog is worth a moment of attention rather than a glance. It should name
the record, read as plain business language, put **Cancel** where your hand
lands first, and close on **Escape**. It is the last thing between a click and a
record that no longer exists.

Three layout defects were found in a browser during this change and fixed before
handover — the page sliding sideways at phone and tablet widths on Legal
Entities, and the other rows' buttons breaking onto two lines while one row was
being edited. Steps 14, S4 and S10 are where you would see them if any survived.

## 10. Evidence to capture

1. The Company Profile screen.
1a. The **Jurisdiction dropdown open** (step S3), showing the alphabetical list.
1b. A **row being edited in place** (step S4 or S11), showing the surrounding
   rows undisturbed.
1c. The **Management Hierarchy** screen with its explanatory box (step S14).
1d. The **confirmation dialog** open (step P2), showing the record name.
1e. **The refusal message from step P4** — this one matters as much as step 9's.
2. Each section you added structure to, after adding it.
3. **The refusal message from step 9** — this one matters most.
4. The team screen after step 12, showing the ended membership still recorded.
5. A list at phone width, showing the table scrolling inside its own box.
6. The developer console from step 16.

Please also say, for steps 10, 11 and 12, whether you marked them NOT
APPLICABLE and why — that answer is the record.

## 11. PASS / FAIL

The right-hand column of every table above.

## 12. What cannot currently be tested, and why

Stated plainly, and **not** inferred from a passing test:

1. **Check 6 — the multi-user management cycle — cannot be tested in P1-01 and
   is not in this script.** A genuine management cycle needs at least two
   SemantIQ users; production has one, and P1-03 provisions the second. It is
   **not** solved by inserting a user, reopening bootstrap, writing to the
   database, building P1-03 early, or weakening the rule — none of which was
   done. It is a **mandatory carried verification gate for P1-03**
   (`PHASE-1-PLAN.md` §10). The rule itself is covered by case 8, mutation
   *remove the chain walk*, **CAUGHT**.

2. **Checks 4, 5 and 9 (steps 10, 11, 12) may be NOT APPLICABLE.** They depend
   on your real structure genuinely containing a many-to-many association, a
   legitimate department move, and a factually correct team membership. If it
   does not, they carry forward with their automated evidence intact. **This is
   a data condition, not an implementation defect**, and it must not be resolved
   by inventing structure.

3. **Check 5a was observed without your involvement** and is not in this script.
   The organisation you created set your own `organisation_id`, and the counts
   moved 0 → 1 (§7.3b). No further action is needed.

4. **The A-series steps were exercised in a browser** at 1440px and 390px, and
   the recorded observations are in `P1-01-ORGANISATION-VERIFICATION.md`. Your
   run of them on production is the acceptance observation.

5. **S15 and S16 cannot be tested today** — Set, Change and Clear on the
   Management Hierarchy need a second user, and production has one. The
   operations are covered automatically, including the case proving that changing
   a manager **ends** the previous link rather than deleting it (mutation
   *delete the previous link instead of ending it*, **CAUGHT**). The live
   observation is carried to P1-03 alongside check 6. **This was not solved by
   creating a user**, and must not be.

6. **The scope completion was exercised on a local throwaway database**, not on
   production. Section S is the production observation. The jurisdiction list,
   the inline editing, the department rename and the two-user hierarchy were all
   driven in a real browser and the results recorded in
   `P1-01-ORGANISATION-VERIFICATION.md` §7.3h — that is development evidence, and
   it is **not** the same claim as an observed production result.

7. **I did not perform steps P1 to P9 on production.** The whole D-24 flow —
   the dialog, the refusal wording, the successful delete of an unused record,
   Escape and Cancel, both themes, three viewport widths — was driven in a real
   browser against a local throwaway database, and the observations are in
   `P1-01-ORGANISATION-VERIFICATION.md` §7.3i. Sixteen mutations were run
   against the purge guards and all sixteen were caught. **None of that is a
   production observation**, and steps P1 to P9 are.

8. **One scope gap is reported and deliberately NOT built.** The plan lists a
   **primary legal entity** among the Organisation's data points; the design
   dropped it without saying so, and there is no column, no field and no
   decision recording the omission. Closing it needs a schema change, and the
   Product Owner's standing instruction is to stop and explain before making
   one. Nothing about it is in this script, and it is not a defect in what you
   are testing — it is an open question, set out in §7.3i.

9. **I did not perform steps 5 to 12.** Every one requires real business
   data, which I must not create. Steps 1, 2, 4, 13, 14, 15 and 16 were
   exercised against a local throwaway database to confirm the screens behave;
   that is a development observation and is **not** recorded as production
   evidence.
