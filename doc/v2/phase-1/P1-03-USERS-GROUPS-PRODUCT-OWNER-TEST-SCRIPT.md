# P1-03 — Users & Groups: Product Owner Test Script

Written for the Product Owner. Your words, your screens, your decisions.
`CLAUDE.md` §3 requires all twelve elements; they are all here, including §12 —
what cannot be tested yet, and why.

---

## 1. Feature being tested

**Users & Groups** — bringing a person into SemantIQ, grouping people, ending
access, and the two guarded permanent removals.

Four screens: the **Users** list, one **person's record**, the **Groups** list,
and one **group's record**.

**Nothing in this unit grants anybody access to anything.** Adding a user, and
putting somebody in a group, both grant exactly nothing — no business domain, no
scope, no sensitivity, no management authority, no System Administrator. That is
a decision, not an omission, and several steps below check it.

---

## 2. Deployed build

| | |
| --- | --- |
| DESIGN merge SHA | `9e67749cc38e4717e30b9359a1933f28ea9e2b47` |
| Implementation merge SHA | *recorded at handover* |
| Deployed to | *recorded at handover* |

---

## 3. Preconditions

Before you start, all of these must already be true.

| # | Precondition | How to confirm |
| --- | --- | --- |
| P1 | You are signed in to SemantIQ as a **System Administrator** | The left sidebar shows **System Administration** expanded, with **Organisation**, **Users & Groups** and **Identity & SSO** as links rather than greyed labels |
| P2 | The **Company Profile exists** | **System Administration → Organisation** opens the profile rather than an empty form. If it does not, create it first — Users & Groups needs an organisation to exist |
| P3 | You can reach the **Microsoft Entra admin centre** for your tenant, or somebody can send you an Object ID from it | See §4 |
| P4 | You have decided **which real colleague** you are adding | See the warning in §5 — this decision cannot be undone once they sign in |

---

## 4. Test data required

### The one thing you must gather before you start

**The Microsoft Entra Object ID of one genuine colleague.**

SemantIQ identifies a person by their Entra **Object ID**, never by their email
address. An email can be renamed and reassigned; an Object ID cannot. That is why
you have to fetch it rather than simply typing an address.

**How to obtain it:**

1. Sign in to the **Microsoft Entra admin centre** at
   <https://entra.microsoft.com> with an account that can read your directory.
   (If you cannot, ask whoever administers Microsoft 365 for your organisation —
   the value is not a secret, but reading the directory requires access.)
2. In the left navigation choose **Identity → Users → All users**.
3. Search for the colleague by name, and open their record.
4. On the **Overview** tab, find **Object ID**. It looks like
   `3f2504e0-4f89-11d3-9a0c-0305e82c3301` — 32 hexadecimal characters in five
   groups separated by hyphens.
5. Use the copy button beside it. **Copy it — do not retype it.** A single wrong
   character produces a record that looks completely correct and that the person
   can never sign in to.

> **SemantIQ cannot check that the Object ID you type is real.** It has no
> permission to read your Microsoft directory, by decision, and it says so on the
> Add User form rather than implying otherwise. It can only check the *shape* of
> the identifier and whether it is already in use here. **The first successful
> sign-in is the only real proof that the identifier is right.**

### Also needed

| Item | Value |
| --- | --- |
| The colleague's **work email** | Used for display only until they first sign in |
| Their **display name** | Optional. Used for display only until they first sign in |
| Two group names you are content to keep | Suggested: `Finance Approvers` and `Fire Wardens` |

---

## 5. ⚠ PERMANENCE WARNING — read before you type anything

**This unit creates a real person's record in your live system, and most of what
you create here cannot be deleted afterwards.**

| What you create | Can it be removed later? |
| --- | --- |
| A user who has **never signed in**, is in **no group**, **no team** and **no reporting line** | **Yes.** *Remove permanently* is offered on their record. This exists for the mistyped Object ID |
| A user who **has signed in — even once** | **NO. NEVER.** Their record becomes part of your organisation's history. From that moment the only action is **Deactivate**, which ends their access and keeps the record |
| A user who is in **any group, team or reporting line — current or ended** | **No.** Deactivate instead |
| The **first System Administrator** of this deployment | **No, ever.** That account is a permanent record of how administration began |
| A group **nobody has ever joined** | **Yes** |
| A group **anybody has ever joined**, even if they have since left | **No.** Deactivate instead |
| A **membership** | **No.** Ending it keeps the row, because a membership that can be erased is not evidence that somebody was ever a member |

**Therefore: choose a real colleague who should genuinely have access.** Not a
placeholder, not a test account, not "someone I will delete afterwards" — because
the moment they sign in, that record is permanent. If you want to exercise the
permanent-removal path safely, use step 14, which uses a **deliberately invalid
Object ID that nobody can ever sign in with**.

**Nothing in this script asks you to enter inaccurate business data or to
falsify your organisational structure.**

---

## 6–11. The steps

Record **PASS** or **FAIL** for every step, and a note where the result differs
from what is written.

### Part A — Finding the screen

| # | Do this | Expect this | P/F |
| --- | --- | --- | --- |
| 1 | From anywhere in the console, look at the left sidebar under **System Administration** | **Users & Groups** is a link, not a greyed roadmap label | |
| 2 | Click **Users & Groups** | The **Users** screen opens. The page heading is **Users & Groups**; below it a two-tab strip reads **Users \| Groups**, with **Users** marked as the current tab | |
| 3 | Read the paragraph under the page heading | It says the unit brings people in, groups them and ends access, and states plainly that **nothing here grants access to business data** | |
| 4 | Click the **Groups** tab, then the **Users** tab | Each opens its own screen and the address in the browser bar changes. The tab you are on is visibly marked | |

### Part B — Adding a real person

| # | Do this | Expect this | P/F |
| --- | --- | --- | --- |
| 5 | On **Users**, click **Add User** | A short form appears with three fields: **Microsoft Entra Object ID**, **Work email**, **Display name** (optional) | |
| 6 | Read the note under the Object ID field **before typing** | It says SemantIQ **cannot check** that this ID exists in Microsoft Entra, tells you to copy it from the Entra admin centre, and warns that a wrong value means the person can never sign in. **It must not claim the ID was found, verified or confirmed** | |
| 7 | Type `not-a-real-id` into the Object ID field and submit | **Refused**, with a message about the field. **No user is created.** (This is the server refusing, not just the browser — the check cannot be bypassed) | |
| 8 | Now paste your colleague's real **Object ID**, enter their work email, optionally their display name, and submit | A green confirmation reads **User added.** You are taken to their record | |
| 9 | On their record, read the **Signed in** row | It reads **Not signed in yet** — those words, not a blank | |
| 10 | Read the note beside **Name and email** | It says these are **provisional**, entered by an administrator, **not confirmed by Microsoft**, and will be replaced when the person first signs in | |
| 11 | Look for any control to change the **name** or **email** | **There is none.** Both are shown as read-only, and the screen says Microsoft owns them | |
| 12 | Find the **Platform role** row | It reads **None**, with a note that roles are assigned in a later release. **There is no control to set one** | |
| 13 | Click **Reveal** beside **Object ID**, then **Hide** | The full identifier appears, then hides again. Compare it against what you copied from Entra — **this is your chance to catch a paste error before the person tries to sign in** | |

### Part C — The duplicate and the permanent removal

| # | Do this | Expect this | P/F |
| --- | --- | --- | --- |
| 14 | Go back to **Users**, click **Add User**, and enter the Object ID `00000000-0000-0000-0000-000000000001` with any email you like — for example `unused@yourdomain` | **User added.** This record is deliberately unusable: `00000000-…-0001` is not a real Entra object, so nobody can ever sign in with it. It exists so you can exercise permanent removal without risking a real person's record | |
| 15 | On that record, scroll to the bottom | A section headed **Remove permanently** explains the record has never signed in and has no history, and offers a red **Remove permanently** button | |
| 16 | Try to add `00000000-0000-0000-0000-000000000001` **a second time** | **Refused**, with: *"That person is already in SemantIQ. Open their record instead of adding them again."* No second record is created, and **no database or technical wording appears** | |
| 17 | Open the unusable record again and click **Remove permanently** | Confirmation reads **User removed permanently.** and the record is gone from the list | |
| 18 | Open your **real colleague's** record and scroll to the bottom | Because they hold no history yet, **Remove permanently** is offered here too. **Do not click it** unless you have changed your mind about adding them | |

### Part D — Groups

| # | Do this | Expect this | P/F |
| --- | --- | --- | --- |
| 19 | Go to the **Groups** tab | The Groups screen opens. The description states that **a group does not give anybody access to anything** | |
| 20 | Click **Add Group**, enter the name `Finance Approvers`, code `FIN-APP`, a description, and submit | **Group added.** You are taken to the group's record | |
| 21 | Try to add a **second** group also called `Finance Approvers` | Refused. One group of a given name per organisation | |
| 22 | On the `Finance Approvers` record, use **Add a member** to add your colleague | **Member added.** They appear in the members table with a **Joined** date and **Current** in the Left column | |
| 23 | Open your colleague's **user record** | Their group appears under **What this person is part of**, and the summary sentence says they belong to 1 group | |
| 24 | Return to the group and click **End membership** beside them | **Membership ended.** The row **stays**, quietened, with today's date in the **Left** column. It is not deleted | |
| 25 | Try to **add them to the same group again** | Permitted — they appear as a new current period, and the ended one remains above it. Two honest periods, not one rewritten | |
| 26 | Scroll to the bottom of the group record | Because the group now has membership history, **Remove permanently is not offered.** Instead a sentence says the group is kept and to deactivate it instead | |
| 27 | Create a second group, `Fire Wardens`, and **do not add anybody**. Scroll to the bottom | **Remove permanently** *is* offered, because nobody has ever been in it | |
| 28 | Click **Deactivate** on `Fire Wardens` | Confirmation reads **Group deactivated.** The status pill changes to Inactive. The members it holds — none here — would have been kept | |
| 29 | Click **Reactivate** | **Group reactivated.** | |

### Part E — Ending somebody's access

| # | Do this | Expect this | P/F |
| --- | --- | --- | --- |
| 30 | Open your colleague's record and click **Deactivate** | A confirmation panel appears **before** anything happens. It states what they are currently part of — *"This user currently belongs to 1 group…"* — and says deactivation stops their access but **does not remove those relationships** | |
| 31 | Confirm | **User deactivated.** Their status pill reads Inactive | |
| 32 | Look at their groups on the record | **Unchanged.** Their membership is still there and still current. Nothing was ended by them losing access | |
| 33 | Click **Reactivate** | **User reactivated.** They are Active again, with exactly the relationships they had before — nothing rebuilt, because nothing was removed | |
| 34 | Try to **deactivate your own account** (you are the only active System Administrator) | **Refused**: *"This is the only active System Administrator. Add or retain another active System Administrator before deactivating this account."* **This is the guard that stops a deployment being locked out with no way back in** | |

### Part F — Searching and filtering

| # | Do this | Expect this | P/F |
| --- | --- | --- | --- |
| 35 | On **Users**, type part of your colleague's name into **Search** | The list narrows as you type. Searching part of their **email** works too | |
| 36 | Set **Status** to **Inactive** | Only inactive people are listed | |
| 37 | Set **Search** to something that matches nobody, e.g. `zzzzzz` | A **no-results** message appears with a **Clear filters** button — not an empty table with no explanation | |
| 38 | Click **Clear filters** | Everybody is listed again | |
| 39 | Set **Organisation** to **Not assigned** | Only people with no organisation are listed — probably none, which is correct and shows the no-results state again | |
| 40 | Open `Finance Approvers` and set the **Membership** filter to **Past** | Only ended memberships are listed. Set it to **Current** for the reverse | |

---

## 8. Negative, refusal and security cases

Several are already in the steps above (7, 16, 21, 26, 34). These are the rest.

| # | Do this | Expect this | P/F |
| --- | --- | --- | --- |
| 41 | In the browser address bar, change a user id to a number that does not exist — e.g. `/console/people/users/999999` | **Page not found.** Not an error page, not a stack trace | |
| 42 | Try `/console/people/users/groups` in the address bar | **Page not found.** A collection name in a record position is never treated as somebody's name | |
| 43 | **Carried gate 1 — the management cycle.** Go to **Organisation → Hierarchy**. **Set** your colleague a manager, then **Change** it, then **Clear** it. Then make yourself report to them, and try to make them report to you | The cycle is **refused**, with a message written for a person. **Record the message verbatim** — closing this gate is what it is for | |
| 44 | **Carried gate 2 — a real non-administrator is refused.** Ask your colleague to sign in to SemantIQ and to open **System Administration → Identity & SSO** | They can sign in. **Identity & SSO is refused** — and in fact the whole System Administration area is not offered to them at all. **Every user this unit creates has no role, so this needs no special setup** | |
| 45 | While they are signed in, ask what they can see in the sidebar | **Nothing under System Administration is a link for them.** Adding a person, and putting them in a group, granted them nothing | |
| 46 | After they have signed in, open their record in SemantIQ | **Signed in** now shows a real date and time. Their **name and email** now come from Microsoft, and the note beside them says so instead of saying *provisional* | |
| 47 | Scroll to the bottom of that record now | **Remove permanently is gone.** In its place: *"This person's record is kept as part of the organisation's history. Deactivate them instead of removing them."* **This is the permanence in §5, made visible** | |

---

## 9. Visual and UX checks

Do these on the four screens: **Users**, a **person's record**, **Groups**, a
**group's record**.

| # | Check | P/F |
| --- | --- | --- |
| 48 | **Light theme** — every label, value, link and button is comfortably readable | |
| 49 | **Dark theme** (theme switcher, top right) — the same, with nothing washed out, invisible or the wrong colour. Pay particular attention to the red **Remove permanently** button and the refusal banner | |
| 50 | **Narrow window** — drag the browser to roughly a phone's width. Nothing is cut off; the page does not scroll sideways; wide tables scroll **inside their own box** rather than pushing the page | |
| 51 | **Empty states** — a group nobody has joined says so in a sentence; a filter matching nobody says something different and offers **Clear filters**. The two are not confused | |
| 52 | **Refusals** are red, start with **Refused.**, and are written as sentences about people and groups — never about columns, constraints or SQL | |
| 53 | **Confirmations** are green, past tense, and **never contain anybody's name** | |
| 54 | **Buttons** say what they do — *Add user*, *End membership*, *Remove permanently* — with no internal names, codes or abbreviations anywhere on any screen | |
| 55 | **Keyboard** — tab through a screen. Focus is always visible | |
| 56 | Press **F12** and look at the browser **Console** on each screen | No red errors | |

---

## 10. Evidence to capture

| # | Evidence | Why |
| --- | --- | --- |
| E1 | The colleague's record **before** their first sign-in — showing **Not signed in yet** and the *provisional* note | Half of the pair that proves D-33 |
| E2 | The **same record after** they sign in — real date, Microsoft-owned name and email, and **no Remove permanently** | The other half, and the permanence in §5 made visible |
| E3 | The **deactivation confirmation panel**, showing the dependency summary | D-36 |
| E4 | The **sole-administrator refusal** at step 34, verbatim | Correction 2 |
| E5 | The **management cycle refusal** at step 43, verbatim | Closes carried gate 1 |
| E6 | What your colleague sees in the sidebar at step 45 | Closes carried gate 2 |
| E7 | The group with membership history refusing permanent removal (step 26) | D-39 |

---

## 11. PASS / FAIL

Every numbered step above carries its own PASS / FAIL box. A FAIL anywhere means
P1-03 is not accepted; note what you saw instead.

---

## 12. What cannot be tested here, and why

Stated rather than inferred from a passing test, and never silently omitted.

| # | Not testable now | Why, and where it is covered |
| --- | --- | --- |
| U1 | **Whether an Object ID names a real person** | SemantIQ has no Microsoft Graph permission, by decision (D-33 = A). It cannot ask. The form says so instead of implying otherwise, and **your colleague's first successful sign-in is the only real proof**. This is a product decision, not an implementation defect |
| U2 | **Search, filter and pagination at scale** | At acceptance your production system holds a handful of people. You will *exercise* these at steps 35–40, not *stress* them. Volume is covered by automated tests against 60 seeded users and 40 groups, which is evidence about the code and **not** an observation of your production system. Stated plainly rather than implied |
| U3 | **Two administrators deactivating each other at the same moment** | The guard is a locking read inside the write transaction. Reproducing a genuine race by hand is not possible, and manufacturing a second privileged production account to try would be exactly what you asked not to do. Automated evidence stands; the SQLite test run cannot even observe the lock clause, and CI therefore runs the People suite against MySQL as well, where it can |
| U4 | **The P1-02 provider-wide SSO Re-check lock** (carried gate 3) | **Moved to P1-05** by your decision. It needs a second System Administrator, and P1-03 cannot assign `platform_role` to anybody. Automated evidence stands |
| U5 | **Permanent removal of a user who has signed in** | There is deliberately no way to do this, so there is nothing to test. Step 47 observes its **absence**, which is the honest form of the check |
| U6 | **Group-derived access** | Does not exist in P1-03 and is not scheduled here. Step 45 observes that being in a group grants nothing |

---

## What to do when you are finished

Reply with:

- the numbered steps that **PASSED** and any that **FAILED**, with what you saw;
- the two verbatim refusal messages (E4, E5);
- confirmation that your colleague **could sign in** and **was refused** System
  Administration (E6);
- whether you consider P1-03 **ACCEPTED**.

**A green CI run does not unlock P1-04. Only your acceptance does.**
