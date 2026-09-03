# P1-04 — Business Domains: Product Owner Test Script

Written for you, not as a developer test plan. Your words, your screens, your
decisions. `CLAUDE.md` §3 requires all twelve elements; they are all here,
including §12 — **what cannot be tested, and why.**

---

## 1. Feature being tested

**P1-04 — Business Domains.** Naming the intelligence domains this organisation
has, and who is accountable for each one.

**The one thing this unit is built to make true, and the thing to keep an eye on
throughout:**

> A domain existing, being enabled, or having an owner **gives nobody access to
> anything.** Not its owner. Not anybody.

If at any point a screen implies otherwise — a lock icon, the word *policy*, a
sentence suggesting the owner can now see the data — **that is a failure**, and
it is worth more than any of the numbered steps below.

---

## 2. Deployed build

| | |
| --- | --- |
| PLAN merge SHA | `b083b30f261820a00f8fdfc37addcd1a6e063789` (PR #88) |
| DESIGN merge SHA | `bffa100032cf9fe2d18869961b1659f4003c7aae` (PR #89) |
| Implementation merge SHA | `920600279561224d2955debc78c71892eecd5f73` (PR #90) |
| Deployed to | <https://semantiq.claas2saas.com> |
| Baseline initialisation | **Run once, 3 September 2026.** Created all seven; `Already there: (none)`. Full output in the verification document §9.2 |

---

## 3. Preconditions

Before you start, all of these must already be true:

1. You are signed in as **`salil@lithan.com`**, a System Administrator.
2. The organisation (Company Profile) exists — it does.
3. **The seven baseline domains have been created**, by the one-time
   initialisation. You should see them on the first screen.
4. At least **two other genuine users exist** and are **active**. They already
   do: `semantiq@educlaas.com`, and yourself.

---

## 4. Test data required

| What | Where it comes from |
| --- | --- |
| The seven baseline domains | Already there. **You do not create these** |
| **One custom domain you invent** | You choose the name and code. Something real, or something obviously disposable like *Test Domain* — see the warning below before deciding |
| Two owners | **Existing genuine users.** This unit creates nobody, and you should not create anybody for it |

---

## 5. ⚠ PERMANENCE WARNING — read before you type anything

**Assigning an owner is the step that makes a domain permanent.**

| Action | Reversible? |
| --- | --- |
| Create a custom domain | **Yes — while it has never had an owner.** Then it can be removed permanently |
| **Assign an owner to it** | **This is the point of no return.** From then on the domain can only be *disabled*, never removed |
| Enable / disable a domain | Yes, freely, both ways |
| Rename a domain | Yes. Its identity code never changes either way |
| Change an owner | Yes — but **both periods are kept forever**. History is not editable and not deletable |
| Remove a custom domain permanently | **No. It is gone** |

**So: if you intend to try the permanent-removal step, create a domain for it
and do NOT give that one an owner.** Steps 14 and 15 below are written to let you
see both outcomes with two different domains.

**The seven baseline domains can never be removed**, by you or by anybody. That
is deliberate — they are SemantIQ's vocabulary, not your data. Disabling is how
an unused one is put away.

---

## 6–7. The steps, and what should happen

Work down the list. Record PASS or FAIL for each.

### Finding it

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 1 | Look at the **System Administration** menu | **Business Domains** is a link, not greyed out with *Soon* | |
| 2 | Click it | The list opens. **Seven domains**, all **Disabled**, all **Not assigned**, all *Not yet determined* | |
| 3 | Read the sentence under the heading | It says nothing here grants access to any of it | |

### One domain

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 4 | Open **Finance** | Its record. Name, Identity code, Description, Access expectation | |
| 5 | Look at **Identity code** | It reads `finance`, is **visibly not editable**, and says *"This never changes, even if the name does."* | |
| 6 | Read the **Accountability** section | *"The owner is accountable for this domain. They do not get access to it."* | |

### The rule that holds the unit together

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 7 | Press **Enable** without assigning anybody | **Refused:** *"Assign an owner before enabling this domain. Someone has to be accountable for it."* | |
| 8 | Choose **yourself** as owner and press **Set owner** | *"Owner assigned."* You appear as currently accountable, with one period **Current** | |
| 9 | Press **Enable** | *"Domain enabled."* Status **Enabled** | |
| 10 | Press **Clear owner** | **Refused:** *"This domain is enabled. Assign a replacement owner, or disable it first."* | |
| 11 | Choose the **other user** and press **Change owner** | *"Owner assigned."* **Two periods now** — yours ended, theirs current. The domain is **still Enabled** | |

### Names, codes and duplicates

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 12 | Open **Sales**, change its Name to **Commercial**, Save | *"Domain updated."* The name changes; **the code is still `sales`** | |
| 13 | Go back to the list | It shows **Commercial** with code **`sales`** | |
| 14 | **Add Domain** — a name and code you invent. **Give it no owner** | *"Domain created."* It appears as **Custom**, Disabled, Not assigned | |
| 15 | **Add Domain** again with the **same name** | **Refused in a sentence** — *"A domain called that already exists…"* **NOT a database error.** If you see the word *SQLSTATE*, *constraint* or *unique*, that is a FAIL | |
| 16 | **Add Domain** with the code **`finance`** | **Refused:** *"That code is reserved for a standard domain."* | |

### Permanent removal

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 17 | Open the custom domain from step 14. Scroll to the bottom | A **Permanent removal** section, saying it can still be removed because nobody has ever been accountable for it | |
| 18 | Open **Finance** and scroll to the bottom | **There is no Permanent removal section at all** on a standard domain | |
| 19 | Create a **second** custom domain and **assign it an owner** | *"Owner assigned."* | |
| 20 | Scroll to the bottom of that one | It now says it has ownership history and to **disable it instead**. There is no removal button | |
| 21 | Go back to the domain from step 14 and press **Remove permanently** | A dialog **naming the domain**, with **Cancel focused**. Confirm → *"Domain removed."* It is gone from the list | |

### The offboarding case — the one this design argued about most

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 22 | Go to **Users & Groups** and **deactivate** the person who owns Finance | **It works. You are not blocked.** Owning a domain is not a reason somebody cannot be offboarded | |
| 23 | Return to **Business Domains** | Finance shows an **Owner inactive** marker, and is **still Enabled** | |
| 24 | Open Finance | *"Needs attention — owner inactive. The domain remains enabled. Assign an active owner when you can. This ownership status does not change anyone's access."* | |
| 25 | Try to set that same inactive person as owner of another domain | **Refused:** *"That person's account is not active. Choose someone who can sign in."* | |
| 26 | Assign an **active** owner to Finance | The attention state clears | |
| 27 | **Reactivate** the person from step 22 in Users & Groups | They are active again. *(This undoes step 22 — do it.)* | |

### Availability, and finishing

| # | Do this | Expect | P/F |
| --- | --- | --- | --- |
| 28 | Press **Disable** on Finance | *"Domain disabled."* **Never refused.** The owner and the full history are **still there** | |
| 29 | Press **Clear owner** now | It is **permitted** — the domain is disabled | |
| 30 | Set an **access expectation** on any domain and Save | *"Domain updated."* The sentence beneath says it **does not grant or restrict anything today** | |
| 31 | Use **Search**, then **Kind**, then **Status**, then **Owner** | Each narrows the list. **Clear filters** brings it back | |
| 32 | Set a filter that matches nothing | *"No domains match these filters."* — **not** *"No business domains yet."* | |

---

## 8. Negative, refusal and security cases

Already exercised above; listed here so nothing is missed.

| # | Case | Step |
| --- | --- | --- |
| E1 | Enable with no owner | 7 |
| E2 | Clear the owner of an enabled domain | 10 |
| E3 | Duplicate name — **in a sentence, not a database error** | 15 |
| E4 | A reserved baseline code | 16 |
| E5 | Remove a standard domain — **no control exists** | 18 |
| E6 | Remove a domain that has ownership history | 20 |
| E7 | **Deactivating a domain owner is not blocked** | 22 |
| E8 | Assigning an inactive person as owner | 25 |

**One more, if you are willing:** sign in as `semantiq@educlaas.com` — a real
non-administrator — and try to reach `/console/domains` directly. **Expect: the
same refusal you saw in P1-03.** You should not be able to reach it.

---

## 9. Visual and UX checks

| # | Check | P/F |
| --- | --- | --- |
| V1 | Every sentence reads as English. **Nothing is in ALL CAPS that should be a sentence** | |
| V2 | No lock icon, no shield, and not the word *policy*, anywhere near the access expectation | |
| V3 | The **Identity code** field visibly cannot be typed in | |
| V4 | The **Owner inactive** marker is noticeable but **not alarming** — nothing is broken | |
| V5 | Switch to **dark theme**. Every pill, sentence and table is readable | |
| V6 | Narrow the window to phone width. The table scrolls **inside itself**; the page does not scroll sideways | |
| V7 | No raw code, column name, enum value or table name appears anywhere | |
| V8 | Buttons say what they do; hover and focus are visible | |

---

## 10. Evidence to capture

- A screenshot of each **refusal** (E1–E8).
- A screenshot of the domain showing **two ownership periods**.
- A screenshot of the **Needs attention — owner inactive** state.
- A screenshot of the list in **dark theme**.
- **The exact wording of any refusal that surprises you.**

> The P1-03 verbatim refusal wording was not retained and could not be quoted
> afterwards. If you can paste the sentences this time, they go into the record.

---

## 11. PASS / FAIL

A **PASS/FAIL column is on every step above.** Please also give an overall
result, and say plainly if anything felt wrong even where the step technically
passed.

---

## 12. What cannot be tested here, and why

Stated in advance so that a passing test is never mistaken for a claim it does
not make.

| # | Not observable | Why |
| --- | --- | --- |
| U1 | **That disabling a domain does not broaden access** | **There is no access yet.** Nothing in SemantIQ reads a domain to decide what anybody may see. This is carried to **P1-05** and must be tested there |
| U2 | **That the owner cannot see the domain's data** | Same reason — there is no domain data. What *is* testable is that owning a domain changes nothing about the person, and that is asserted behaviourally |
| U3 | **Two administrators acting at the same instant** | The row locking is proven against MySQL in CI. You would need two people clicking simultaneously to see it, and the outcome would look identical to one person clicking twice |
| U4 | **Search and filtering at real volume** | You will *exercise* them against eight or nine domains. They were *stressed* against 60 in the test suite. Different claims |
| U5 | **Font loading** | The verification environment could not reach the font CDN, so that was **not** verified. **Your browser console reading is the real check** — press F12 and look for red errors |

---

## Before you finish

Please note anything that made you hesitate, even briefly, even if you cannot
say why. On P1-03 that instinct produced the *"Super Admin"* observation, which
turned into a carried hazard for P1-05 — and it was worth more than several of
the numbered steps.
