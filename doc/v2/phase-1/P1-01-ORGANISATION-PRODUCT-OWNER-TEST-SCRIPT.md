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

This completes the **live verification** of P1-01. The implementation is
unchanged and is not being redesigned. What is outstanding is the observation of
six behaviours on the real deployment with real data — checks **2, 3, 4, 5, 7
and 9** of `P1-01-ORGANISATION-VERIFICATION.md` §7.5.

## 2. Deployed build / merge SHA

| | |
| --- | --- |
| P1-01 merge SHA | `9afe33d` — *P1-01 Organisation — EXECUTE* |
| Correction | `4f99c46` — Organisation was not reachable after sign-in |
| Deployed head at issue | `c5cec56` |
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

Read from production on 1 September 2026, counts only — no names, no
identities:

| | |
| --- | --- |
| Organisations | 1 |
| Business units | 1 (inactive) |
| Legal entities, departments, teams, memberships, management links | 0 |
| Users | 1, and that user carries an organisation |

So the Company Profile already exists and one business unit has already been
created and deactivated.

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

**SemantIQ has no hard delete in P1-01.** There is no DELETE route anywhere in
the unit, by design and asserted by test.

That means:

- every legal entity, business unit, department and team you create is
  **permanent**;
- deactivating something **does not remove it** — the record and its history
  remain;
- a team membership you end keeps its row, with the end date recorded;
- there is no undo, and no screen that will let you take it back.

**Only enter structure that genuinely exists.** If you are unsure whether
something belongs, leave it out — you can add it later, but you cannot remove
it.

Steps 1 to 4 and step 12 change nothing and are safe to run at any time.

## 6–7. Numbered steps and expected results

Record PASS / FAIL / NOT APPLICABLE for every step.

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
| 15 | Look for any way to delete a record. | **There is none** — no delete button, no delete menu item, anywhere in Organisation. Deactivate is the only removal-shaped action, and it preserves the record. | |
| 16 | Open the browser's developer console (F12) and reload. | No red errors. | |

## 8. Negative, refusal and security cases

Step 9 is the primary refusal case, and it is the one that proves the lifecycle
rule rather than merely exercising the happy path. Step 15 is the "no hard
delete" guarantee. Both are observations, not data changes.

Twenty-one further negative cases are covered automatically and each was proven
non-vacuous by mutation — `P1-01-ORGANISATION-VERIFICATION.md` §3. **Those are
automated evidence and are not a substitute for the live observations above.**

## 9. Visual and UX checks

Steps 2, 3, 4, 13 and 14.

## 10. Evidence to capture

1. The Company Profile screen.
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

4. **I did not perform any of these steps.** Every one requires real business
   data, which I must not create. Steps 1, 2, 4, 13, 14, 15 and 16 were
   exercised against a local throwaway database to confirm the screens behave;
   that is a development observation and is **not** recorded as production
   evidence.
