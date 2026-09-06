# P1-05 — Roles & Access: PRODUCT OWNER TEST SCRIPT

**Written for you, not for a developer.** Your words, your screens, your
decisions. Work through it in order — later steps depend on what earlier ones
set up.

| | |
| --- | --- |
| **1. Feature being tested** | **P1-05 — Roles & Access.** Who holds which role, which business domains that role may take part in, which records inside them, and how sensitive the information may be |
| **2. Deployed build** | *Merge SHA to be recorded at deployment. Not yet deployed — this script is provided with the implementation pull request* |
| **3. Where** | **Roles & Access** in the left-hand menu, under System Administration |

---

## ⚠️ 4. READ THIS BEFORE YOU TYPE ANYTHING

**SemantIQ does not delete access records. It ends them.**

| What you do | What happens |
| --- | --- |
| **Grant a role, entitlement, scope or sensitivity level** | Revocable — **but the history is permanent.** The record that it was granted, and when, is kept for good |
| **Revoke anything** | **Permanent.** Re-granting creates a **new** grant. The old entitlements and scopes beneath it **do not come back** |
| **Grant somebody the System Administrator role** | Assignable and revocable — **but the last one can never be removed.** That is deliberate |

**Nothing in this script asks you to enter false business information**, and
nothing asks you to create a second System Administrator you do not actually
want. Where a check cannot be done without doing that, it says so and is marked
**not testable here**.

---

## 5. Before you start

| # | Must already be true |
| --- | --- |
| 1 | The Company Profile exists |
| 2 | At least one business domain is **enabled** and at least one is **disabled** — Business Domains |
| 3 | At least two people other than yourself exist in Users & Groups |
| 4 | At least two **teams** exist — Organisation → Teams |

**Test data you will create:** role assignments, domain entitlements, scopes and
sensitivity levels for **real people in your organisation**. These are real
grants. They can be revoked, and the record of them is permanent.

---

## 6. The steps

### A — Nothing is granted, and that is the baseline

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| A1 | Open **Roles & Access** | The list opens. The header says *"A role on its own grants nothing"* and names what a complete grant needs | |
| A2 | Look at your own row | Your **System Administrator** role is listed. **Domains entitled** reads **"None — grants no access"** | |
| A3 | Read the notice above the list | If you are the only administrator, it says *"Only one active System Administrator remains…"* — **and nothing is blocked by it** | |

> **A2 is the point of the whole unit.** Being a System Administrator gives you
> the platform. It gives you **no business information at all**.

### B — Grant a role, and watch it grant nothing

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| B1 | **Grant a role** → choose a colleague → choose **Business User** | The description of what that role does appears beside your choice, **before** you grant it | |
| B2 | Select **Grant role** | Confirmed. You land on that person's record page | |
| B3 | Read the page | It says the role has **no domain entitlements, so it gives no access to business information** | |

### C — An entitlement is still not access

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| C1 | **Add entitlement** → choose your **enabled** domain | Confirmed: *"Domain entitlement granted. Assign a scope to make it effective."* | |
| C2 | Look at the entitlement | It shows **No access — scope required**, and explains that it has no active scope and grants no business-data access | |

> **C2 is a deliberate state, not a fault.** An entitlement without a scope is a
> grant that does not work, and the screen must say so rather than look normal.

### D — Scope, and scopes adding together

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| D1 | **Add scope** → choose **Team** → choose a team | Confirmed. The *No access* message is gone | |
| D2 | **Add scope** again → **Team** → a **different** team | Confirmed. **Both** teams are listed, both Current | |
| D3 | **Add scope** again → **Team** → the **same** team as D1 | **Refused:** *"That scope is already assigned… Scopes add together, so assigning it twice grants nothing further."* | |
| D4 | **Add scope** → choose **Domain** | A note appears: **Domain and Organisation scope grant the same records today.** Domain is reserved for a future split | |

> **D4 is D-74.** Two choices that do the same thing today, said plainly, so
> nobody assumes one is narrower than the other.

### E — Sensitivity

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| E1 | Set **Sensitivity level** to **Confidential** | Confirmed. The description of that level is shown beside the choice | |
| E2 | Set it to **Restricted** | **You are taken to a confirmation page**, then to **Microsoft to sign in again**. Nothing changes until you return | |
| E3 | **Cancel at Microsoft** | You return to Roles & Access. *"The action was cancelled and has not been applied."* The level is **still Confidential** | |
| E4 | Try **Restricted** again and complete the Microsoft sign-in | Confirmed. The level is now Restricted | |

> **E2–E4 are step-up.** SemantIQ never asks you for a password — Microsoft
> does. **One confirmation covers one change, once.**

### F — The Access Simulator

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| F1 | Open **Access Simulator** | The form opens. It says nothing here changes anybody's access and no business information is shown | |
| F2 | Choose your colleague, the **enabled** domain, **Standard**, and the team from D1 | **Allowed.** Under *How they have it*, the grant is named in a sentence | |
| F3 | Read the note under that grant | It says whether revoking it **would** remove their access, or whether another grant also allows it | |
| F4 | Change the team to one you did **not** assign | **Not allowed** — *"The assigned scope does not include this record."* | |
| F5 | Change sensitivity to a level above their ceiling | **Not allowed** — *"The assigned sensitivity level does not permit this information."* | |
| F6 | Look at every message on this screen | **Plain English throughout.** No codes, no field names, nothing that looks like it came from a database | |

### G — The boundaries

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| G1 | Simulate **yourself**, any domain, Standard | **Not allowed.** You are a System Administrator and hold no business entitlement | |
| G2 | In Business Domains, assign your colleague as **owner** of a domain they have **no entitlement to**. Then simulate them on it | **Not allowed.** Owning a domain grants nothing | |
| G3 | Open Roles & Access. Their role list is **unchanged** — being made an owner gave them no role | | |
| G4 | Grant somebody **Manager**, entitle them to a domain, and give them **one** team scope. Simulate a record in a **different** team | **Not allowed.** A manager reaches the teams that were assigned, and no others | |

### H — Disabled domains

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| H1 | Entitle your colleague to a **disabled** domain | Accepted, with a note that nobody reaches its information until it is enabled | |
| H2 | Give it a scope and a level, then simulate it | **Not allowed** — *"This business domain is currently disabled."* | |
| H3 | **Enable** the domain in Business Domains, then simulate again | **Allowed** — and you granted nothing new. The entitlement was kept | |
| H4 | **Disable every domain** in Business Domains, then simulate anything | **Not allowed**, for every domain | |
| H5 | Re-enable the domains you disabled | Access returns exactly as it was | |

> **H4 is the P1-04 carried gate.** With no enabled domains, nobody sees
> anything. It must never become *everybody sees everything*.

### I — Revocation, and what does not come back

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| I1 | Revoke **one** of the two team scopes from D2 | Confirmed. The other is still Current, and access through it still works in the Simulator | |
| I2 | Revoke the **remaining** scope | Confirmed: *"…This entitlement now has no active scope and grants no access."* | |
| I3 | Look at the entitlement | It is **still Current**, and shows **No access — scope required** | |
| I4 | Simulate that person again | **Not allowed** — the scope does not include the record | |
| I5 | Revoke the whole **role** | Confirmed, with a warning that everything beneath it went too | |
| I6 | Grant the **same role again** to the same person | The role is back — **with no entitlements at all.** Nothing came back with it | |
| I7 | Set **Shows** to **Everything** | Both the old revoked period **and** the new one are listed | |

> **I6 is the one to look at closely.** Re-granting a role must never quietly
> restore access somebody had months ago.

### J — The last administrator

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| J1 | Open your own role assignment and select **Revoke this role** | **You are taken to Microsoft** first, because removing a System Administrator is privileged | |
| J2 | Complete the Microsoft sign-in | **Refused:** *"This is the only active System Administrator. Add or retain another before removing this one."* | |
| J3 | Confirm you are still an administrator | You are. Roles & Access still opens | |

> **If a second System Administrator genuinely exists** — because your
> organisation actually wants one — J1 will succeed for the other person and
> then refuse for the survivor. **Do not create one just for this test.**

### K — Granting access to yourself

**This is the correction you asked for at Gate C.** Adding a business domain to
**your own** access is the same escalation as giving yourself the role, so it
now asks you to sign in with Microsoft again first.

> ⚠️ **K5 creates a real, permanent record.** Choose a domain you genuinely
> should be able to reach. If there is none, do **K1 to K4 and K7 to K9** and
> mark **K5 and K6 not done** — do not entitle yourself to a domain you should
> not have, and do not invent one to test with.

> **What "confirm your identity" means here.** Microsoft performs the
> re-authentication; SemantIQ never sees a password. Whether you are asked for a
> second factor depends on **your Entra sign-in policy**, not on SemantIQ — so
> if you are only asked for a password, that is your tenant's policy, not a
> defect.

| # | Action | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| K1 | Roles & Access → open **your own** row | Your role record opens | |
| K2 | Select **Add entitlement** | Beneath the domain list: *"This role belongs to you. Adding a domain to your own access asks you to confirm your identity with Microsoft first, and nothing is granted until you do."* | |
| K3 | Choose a domain and select **Add entitlement** | A **Confirm your identity** page. It says *"You are about to **grant access to yourself**"*, offers **Continue to Microsoft**, and says the confirmation expires in 5 minutes. **It asks you for no password and no code** | |
| K4 | Select **Cancel and go back** | You return to Roles & Access. **Nothing was granted** — reopen your record and the domain is still absent | |
| K5 | Repeat K1–K3, then **Continue to Microsoft** and complete the sign-in | You return to your role record: *"Domain entitlement granted. Assign a scope to make it effective."* The domain now appears, marked as having **no active scope** | |
| K6 | Repeat K1–K3 for a **second** domain, then **cancel at the Microsoft page** | *"The action was cancelled and has not been applied."* The second domain is **not** on your record | |
| K7 | Open a **colleague's** role record and add an entitlement | It is granted immediately. **No identity confirmation** — this is ordinary administration, not a self-grant | |
| K8 | On your own record, try to add the **same** domain from K5 again | Refused straight away, on the page, **without sending you to Microsoft** | |
| K9 | Repeat K1–K3, then leave the confirmation page open for **more than five minutes** before selecting Continue | Refused: *"That confirmation is no longer valid. Start the action again."* Nothing is granted | |

> **K8 matters.** A confirmation you could never complete is a trap: you would
> re-authenticate with Microsoft and be refused afterwards. The refusal comes
> first.

### L — Look at it as a customer would

| # | Check | PASS / FAIL |
| --- | --- | --- |
| L1 | Spelling, grammar and capitalisation on every screen | |
| L2 | Nothing in ALL CAPS that should be a sentence | |
| L3 | No codes, enum values, field names or route names anywhere a person reads | |
| L4 | Every refusal says **what to do instead**, not just that it failed | |
| L5 | Every successful save is **confirmed** — never silence | |
| L6 | Switch to **dark theme** and walk the same screens | |
| L7 | Narrow the window to phone width and walk them again | |
| L8 | **Back** and the browser's back button both behave sensibly | |
| L9 | Would you be comfortable showing these exact screens to a customer? | |

---

## 7. What cannot be tested here, and why

**Stated rather than left out, and never inferred from a passing automated
test.**

| # | Not testable | Why | Where the evidence is instead |
| --- | --- | --- | --- |
| **1** | **That AI and Fabric get exactly the requesting person's access** | There is no AI surface and no business data in Phase 1. The **contract** exists; the integration is Phase 2/3 | `Architecture/AccessBoundaryTest` — the engine is usable outside HTTP, and nothing may load data first and filter after |
| **2** | **Row-level filtering of real business records** | There are no business records yet. Scope is tested against the structural targets it resolves — teams, business units, own | `ScopeUnionTest` |
| **3** | **Two people acting at the same instant** | Two people clicking simultaneously look identical to one person clicking twice | `AdministratorConcurrencyTest` against **real MySQL** in CI: the administrator set is genuinely locked, and the loser of a race gets the business refusal rather than a database error |
| **4** | **The P1-02 SSO Re-check lock** | It needs a **genuine second System Administrator**, and one must not be manufactured to close a gate | **Carried forward, openly.** A fake privileged account would make the evidence worth less than leaving it open |
| **5** | **That Microsoft required a particular credential** | SemantIQ can prove Microsoft reports a **fresh sign-in**. It cannot prove which factor was used unless your Entra policy guarantees it | Stated in `P1-05-DEPLOYMENT-NOTE.md` §5. **"MFA verified" is never claimed anywhere** |
| **6** | **Section K, until the second redirect URI is registered in Entra** | `https://<host>/auth/microsoft/step-up` must exist in the app registration first. Until it does, **K3 onwards fails on the way to Microsoft** — nothing is granted, which is the correct failure, but it is not the test. See `P1-05-DEPLOYMENT-NOTE.md` §1 | `SelfEntitlementStepUpTest` drives all fourteen cases through the real routes: the grant, the cancellation, the stale sign-in, the expiry, the replay, and every attempt to substitute the domain or the assignment on the way back |

---

## 8. Evidence to capture

| # | |
| --- | --- |
| 1 | A screenshot of **A2** — your System Administrator role showing *"None — grants no access"* |
| 2 | A screenshot of **C2** — an entitlement showing *No access — scope required* |
| 3 | A screenshot of **D4** — the Domain / Organisation equivalence note |
| 4 | A screenshot of **F2** showing the grant path, and of **F4** or **F5** showing a refusal |
| 5 | A screenshot of **H4** — no enabled domains, and access refused |
| 6 | A screenshot of **I6** — the re-granted role with no entitlements |
| 7 | A screenshot of **J2** — the last-administrator refusal |
| 8 | A screenshot of **K3** — the Confirm your identity page for a self-grant |
| 9 | A screenshot of **K8** — the immediate refusal, with no trip to Microsoft |
| 10 | Anything under **L** that you would not show a customer |

---

## 9. Result

**To be completed by the Product Owner.**

| | |
| --- | --- |
| Tested by | |
| Date | |
| Build / merge SHA | |
| Overall | **PASS / FAIL** |
| Notes | |
