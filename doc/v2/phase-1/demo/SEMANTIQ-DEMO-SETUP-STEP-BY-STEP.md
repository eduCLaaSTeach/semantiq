# SemantIQ Demo — Step-by-Step Setup Guide

**Every click, every field, in order.** Companion to
`SEMANTIQ-BUSINESS-DEMO-SCRIPT.md`.

> ## ⚠️ NON-PRODUCTION INSTANCES ONLY
>
> This guide **creates data**. SemantIQ has **no hard delete** — users,
> organisations, domains and role assignments can be deactivated but never
> erased. Running this on production would put fictional companies and people
> into your real audit trail permanently.
>
> **For a production demo, use Part A of the demo script instead.** It is
> read-only and creates nothing.

**Time: 35–45 minutes.** Do it the day before the demo, not the morning of.

---

## Part 0 — Before you begin

| | |
| --- | --- |
| Sign in as | **System Administrator** or **Organisation Administrator** |
| Sign-in method | **Microsoft** — there is no SemantIQ password |
| Prerequisite | Identity (Entra SSO) already configured, or nobody can sign in at all |

**Three things that will trip you up, so they are said up front:**

1. **Adding a person needs a GUID.** The *Object ID* field is **required** and
   must look like `11111111-1111-4111-8111-111111111111`. SemantIQ cannot
   check whether it names a real person — it only checks the shape. Synthetic
   GUIDs are supplied below.
2. **Everything needs a Code** — business units, departments, teams, groups
   and domains. Codes are supplied below. Use lower-case with hyphens.
3. **Order matters.** A department needs its business unit to exist first; a
   team needs its department; a role assignment needs the person and the
   domain. Work top to bottom.

---

## Part 1 — Organisation structure

### Step 1.1 — Company profile

1. Open **System Administration → Organisation**.
2. You land on **Company Profile**. If it is not yet set, complete it.

| Field | Value |
| --- | --- |
| Name | `Northwind Learning Group` |
| Country | `SG` |
| Time zone | `Asia/Singapore` |

3. **Save.**

**Expected:** the profile saves and Administration Home stops reporting the
organisation as missing.

---

### Step 1.2 — Legal entities (2)

1. **Organisation → Legal Entities** tab.
2. **Add a legal entity.** Repeat for both rows.

| Name | Jurisdiction | Registration |
| --- | --- | --- |
| `Northwind Learning Group Pte Ltd` | `Singapore` | `NW-SG-2019-0441` |
| `Northwind Academy Sdn Bhd` | `Malaysia` | `NW-MY-2021-7732` |

*(Registered address is optional — leave it blank.)*

**Expected:** two entities listed, both **Active**.

---

### Step 1.3 — Business units (3)

1. **Organisation → Business Units** tab.
2. **Add a business unit.** Three times.

| Name | Code |
| --- | --- |
| `Corporate Training` | `corporate-training` |
| `Professional Certification` | `professional-certification` |
| `Digital Campus` | `digital-campus` |

**Expected:** three units, each showing **0 departments** for now.

---

### Step 1.4 — Departments (5)

1. **Organisation → Departments** tab.
2. **Add a department.** Five times. **The business unit must already exist.**

| Name | Code | Business unit |
| --- | --- | --- |
| `Finance` | `finance` | Corporate Training |
| `People & Culture` | `people-culture` | Corporate Training |
| `Sales` | `sales` | Corporate Training |
| `Delivery` | `delivery` | Professional Certification |
| `Technology` | `technology` | Digital Campus |

**Expected:** five departments. Business Units now shows 3 / 1 / 1.

> **Note:** once created, a department's business unit is changed with **Move
> to**, not by editing the field. That is deliberate — moving a department is
> a structural change, not a typo fix.

---

### Step 1.5 — Teams (3)

1. **Organisation → Teams** tab.
2. **Add a team.** Three times.

| Name | Code | Department |
| --- | --- | --- |
| `Enterprise Sales` | `enterprise-sales` | Sales |
| `Certification Delivery` | `certification-delivery` | Delivery |
| `Data & Platform` | `data-platform` | Technology |

**Expected:** three teams, each with **0 members** for now.

---

## Part 2 — People and groups

### Step 2.1 — Add the five people

1. Open **System Administration → Users & Groups**.
2. **Add a user.** Five times, using these **exact** Object IDs.

| Name | Email | Object ID *(required)* |
| --- | --- | --- |
| `Arjun Mehta` | `arjun.mehta@northwind.example` | `11111111-1111-4111-8111-111111111111` |
| `Priya Raman` | `priya.raman@northwind.example` | `22222222-2222-4222-8222-222222222222` |
| `Daniel Okafor` | `daniel.okafor@northwind.example` | `33333333-3333-4333-8333-333333333333` |
| `Mei Lin Tan` | `meilin.tan@northwind.example` | `44444444-4444-4444-8444-444444444444` |
| `Sofia Alvarez` | `sofia.alvarez@northwind.example` | `55555555-5555-4555-8555-555555555555` |

**Expected:** five users, all **Active**, all **Not signed in yet**, all
showing **Not assigned** — they hold no roles.

> **This is demo point one and it is worth pausing on now:** you have just
> created five people and **not one of them can see anything.** Creating a
> user grants nothing.

---

### Step 2.2 — Add the two groups

1. **Users & Groups → Groups** tab.
2. **Add a group.** Twice.

| Name | Code | Description |
| --- | --- | --- |
| `Finance Leadership` | `finance-leadership` | `Finance decision makers` |
| `Enterprise Sales Team` | `enterprise-sales-team` | `Named enterprise accounts` |

3. Open each group and **add members**:
   - **Finance Leadership** → Arjun Mehta, Priya Raman
   - **Enterprise Sales Team** → Daniel Okafor

**Expected:** 2 members and 1 member respectively.

---

### Step 2.3 — Put people in teams *(optional but recommended)*

Scenario 3 in the demo is sharper if Mei Lin visibly belongs to a team.

1. **Organisation → Teams** → open **Certification Delivery** → add **Mei Lin
   Tan**.
2. Open **Enterprise Sales** → add **Daniel Okafor**.
3. Open **Data & Platform** → add **Sofia Alvarez**.

---

## Part 3 — Business domains

1. Open **System Administration → Business Domains**.
2. **Add a domain.** Four times.

| Name | Identity code |
| --- | --- |
| `Finance` | `finance` |
| `People` | `people` |
| `Sales` | `sales` |
| `Learning Delivery` | `learning-delivery` |

3. Open each domain and set the owner under **Accountability** → **Choose a
   person**:

| Domain | Owner |
| --- | --- |
| **Finance** | **Arjun Mehta** |
| People | Priya Raman |
| Sales | Daniel Okafor |
| Learning Delivery | Mei Lin Tan |

4. Confirm each shows **Enabled** under **Availability**.

**Expected:** four domains, each with an owner, all enabled.

> **Demo point two, already set up:** Arjun now owns Finance. He will be given
> **no Finance entitlement**. He is accountable for it and cannot read it.

---

## Part 4 — Role assignments and the four-part path

This is the fiddly part. **Read the shape first, then do it.**

Granting access is **four separate actions**, and that is the product's whole
argument:

```
1. Assign a ROLE          →  on Roles & Access
2. Grant an ENTITLEMENT   →  open the assignment, pick a domain
3. Assign a SCOPE         →  on that entitlement: how much of the domain
4. Set a CEILING          →  on that entitlement: how sensitive
```

**Stop after the step each person needs.** Three of the five are deliberately
left incomplete — that is what makes the demo work.

---

### Step 4.1 — Arjun Mehta: role only, stop there

1. **Roles & Access → Assign a role.**
2. Person **Arjun Mehta**, role **Domain Owner**. Save.
3. **Do nothing else.** No entitlement, no scope, no ceiling.

**Expected:** Arjun holds Domain Owner and owns the Finance domain, with no
entitlement anywhere.

---

### Step 4.2 — Daniel Okafor: role only, stop there

1. **Assign a role** → **Daniel Okafor**, role **Manager**. Save.
2. **Do nothing else.**

**Expected:** Daniel holds Manager and no entitlement.

---

### Step 4.3 — Mei Lin Tan: all four, but scope limited

1. **Assign a role** → **Mei Lin Tan**, role **Business User**. Save.
2. **Open the assignment** (click the row).
3. **Grant an entitlement** → domain **Learning Delivery**.
4. On that entitlement, **assign a scope** → **Own records**.
5. On that entitlement, **set the sensitivity ceiling** → **Standard**.

**Expected:** a complete path — but scope **Own records** and ceiling
**Standard**.

---

### Step 4.4 — Priya Raman: all four, the one that works

1. **Assign a role** → **Priya Raman**, role **Executive**. Save.
2. **Open the assignment.**
3. **Grant an entitlement** → domain **Finance**.
4. **Assign a scope** → **Organisation**.
5. **Set the sensitivity ceiling** → **Confidential**.

**Expected:** a complete path. Priya is the only person in the demo who will
be allowed.

---

### Step 4.5 — Sofia Alvarez: Auditor

1. **Assign a role** → **Sofia Alvarez**, role **Auditor**. Save.
2. **Do nothing else.**

**Expected:** Sofia holds Auditor, no entitlement, no business data.

---

### Step 4.6 — Check the finished state

**Roles & Access** should now read:

| Person | Role | Entitlement | Scope | Ceiling |
| --- | --- | --- | --- | --- |
| Arjun Mehta | Domain Owner | — | — | — |
| Daniel Okafor | Manager | — | — | — |
| Mei Lin Tan | Business User | Learning Delivery | Own records | Standard |
| Priya Raman | Executive | Finance | Organisation | Confidential |
| Sofia Alvarez | Auditor | — | — | — |

**If Arjun or Daniel has an entitlement, remove it.** The demo depends on them
not having one.

---

## Part 5 — Rehearse the five simulator answers

**Do this before the demo. Do not discover a wrong answer live.**

1. Open **Roles & Access → Access Simulator**.
2. For each row: **Choose somebody**, **Choose a domain**, set sensitivity,
   run it, and read **What is in the way**.

| # | Choose somebody | Choose a domain | Sensitivity | Expected answer |
| --- | --- | --- | --- | --- |
| 1 | Arjun Mehta | Finance | Standard | **Denied — no entitlement** |
| 2 | Daniel Okafor | Sales | Standard | **Denied — no entitlement** |
| 3 | Mei Lin Tan | Learning Delivery | Standard, a record that is **not** her own | **Denied — outside scope** |
| 4 | Priya Raman | Finance | **Restricted** | **Denied — above ceiling** |
| 5 | Priya Raman | Finance | **Confidential** | **ALLOWED** |

**Run them in this order during the demo.** Four denials then one approval is
what lands the point; leading with the approval wastes it.

**If an answer differs from the table, fix the data before the demo** — do not
improvise an explanation in front of the audience.

---

## Part 6 — Final pre-demo checklist

- [ ] Administration Home loads and the Action Queue reads sensibly
- [ ] Organisation shows 2 legal entities, 3 business units, 5 departments, 3 teams
- [ ] Users & Groups shows 5 people and 2 groups
- [ ] Business Domains shows 4 domains, all enabled, all owned
- [ ] Roles & Access matches the table in Step 4.6 **exactly**
- [ ] All five simulator scenarios return the expected answer
- [ ] You are signed in as an administrator, on a clean browser window
- [ ] The demo script is open on a second screen or printed

---

## Part 7 — Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| "Add a user" rejects the form | **Object ID** missing or wrong shape | Must be `8-4-4-4-12` hex. Use the GUIDs in Step 2.1 exactly. |
| Cannot pick a business unit for a department | The business unit does not exist yet | Do Step 1.3 first. |
| Cannot change a department's business unit | Editing does not move it | Use **Move to**. |
| Code is rejected | Codes are lower-case letters, digits and hyphens | `people-culture`, not `People & Culture`. |
| Simulator says "no entitlement" for Priya | Step 4.4 stopped early | Open her assignment and finish steps 3–5. |
| Simulator allows Arjun | He was given an entitlement | Remove it. He must have none. |
| Nobody can sign in at all | Identity/SSO not configured on this instance | Must be set up before any of this. |
| You entered something wrong | **There is no hard delete** | Deactivate it and create the corrected record. Say so plainly if asked during the demo — it is deliberate design, not a gap. |

---

## Part 8 — What you cannot set up, and why

| Not possible | Why |
| --- | --- |
| **Real business data** — reports, dashboards, numbers | **Phase 3.** Phase 1 decides who *may* see information; the information is not connected yet. The simulator answers the access question without needing it. |
| **Connecting a data source** | **Phase 2 — Fabric Configuration.** Visible in the sidebar, locked on purpose. |
| **Deleting the demo data afterwards** | **No hard delete, anywhere.** This is why the guide is non-production only. |
| **Seeding the data with a command** | `php artisan db:seed` **does not work** on this application — the stock seeder refers to a model that does not exist and there are no factories. Enter the data through the screens, which is also a rehearsal. |
| **Signing in as the demo people** | They are synthetic GUIDs, not real Microsoft accounts. You demo *about* them, signed in as an administrator. The Auditor closer in §21 of the script needs a genuine account. |
