# SemantIQ Phase 1 — Functional Test Script with Valid Data

**Every field value below was checked against the application's own validation
rules**, not written from memory. Where an earlier document guessed, this one
corrects it — see §2.

> ## ⚠️ NON-PRODUCTION INSTANCES ONLY
>
> This script **creates data**, and **SemantIQ has no hard delete**. Users,
> organisations, domains and role assignments can be deactivated but never
> erased. Running it on production would put fictional companies and people
> into your real audit trail permanently.
>
> **For production, use the Gate D script instead** — it is read-only.

> ### What in this data is real, and what is not
>
> | Real | Invented |
> | --- | --- |
> | **CLaaS2SaaS Pte Ltd** — the organisation | Business units, departments and teams |
> | **Lithan Academy Pte Ltd / Sdn Bhd** — the legal entities | The five test people and their `@demo.example` addresses |
> | **Sowjanya Chukka** — the System Administrator who signs in | The business domains and every role assignment |
>
> **This mixture is exactly why the script must not run on production.** Real
> company names attached to an invented org chart is not harmless test data —
> it is **false organisational history**, and with no hard delete it cannot be
> taken back out. On a non-production instance it is ideal, because the demo
> reads as your own business.
>
> The five people stay fictional on purpose. `@demo.example` is a reserved
> domain that can never receive mail, so no real colleague is ever named or
> contacted.


| | |
| --- | --- |
| Build | `03f238ee52d95645b91a41d6271069b75f6b2320` |
| Covers | T1–T12 — organisation, people, domains, access, evidence |
| Time | 45–60 minutes |
| Sign in as | **Sowjanya Chukka** — System Administrator |

---

## 1. Before you start

- Identity (Entra SSO) must already be configured, or nobody can sign in.
- Work **top to bottom**. A department needs its business unit to exist first;
  a team needs its department; a role assignment needs the person and the
  domain.
- Record **PASS / FAIL** per step. On a FAIL, capture the exact on-screen
  message — it is usually the validation rule telling you something true.

## 2. Corrections to the earlier demo data pack

The demo pack was written before these rules were checked. **Four things in it
were wrong.** They are corrected throughout this script.

| Field | Earlier pack said | **Actually** |
| --- | --- | --- |
| Legal entity jurisdiction | "Country" | **`Jurisdiction`, and it must be an approved territory NAME** — `Singapore` ✅, `SG` ❌ |
| Business unit / department / team / group `Code` | required | **Optional**, free text, max 32 — no slug format enforced |
| **Business domain `Access expectation`** | *not mentioned at all* | **REQUIRED** — one of *Undecided, Broad, Limited, Exceptional* |
| Company profile `Country` | `Singapore` | **Exactly 2 characters** — `SG` |

**Business domain `code` is the one real slug:** required, max 32, lower-case
letters, digits and single hyphens (`learning-delivery` ✅,
`Learning Delivery` ❌).

---

## T1 — Company profile

**Screen:** System Administration → Organisation → Company Profile

| Field | Value |
| --- | --- |
| Name | `CLaaS2SaaS Pte Ltd` |
| Country | `SG` *(exactly two characters)* |
| Time zone | `Asia/Singapore` |

**Expected:** Saves. Administration Home stops reporting the organisation as
missing.

**Negative test — do this one, it proves the rule:** set Country to
`Singapore` and save.
**Expected:** **Rejected.** The field is exactly two characters.
Restore `SG`.

**PASS / FAIL:** ______

---

## T2 — Legal entities

**Screen:** Organisation → Legal Entities → **Add a legal entity**

| Name | Jurisdiction | Registration |
| --- | --- | --- |
| `Lithan Academy Pte Ltd` | `Singapore` | **leave blank** |
| `Lithan Academy Sdn Bhd` | `Malaysia` | **leave blank** |

**Registration is deliberately left blank.** It is nullable, and a legal entity
whose registration number is not yet recorded is a real state the application
supports. These are **real companies**: inventing a registration number for one
would be entering false business data, and a wrong number is worse than no
number. If you want the field populated, use the genuine ones.

*Registered address is optional — leave blank.*

**Expected:** Two entities, both **Active**.

**Negative test:** try jurisdiction `SG`.
**Expected:** **"Select a jurisdiction from the list."** The server enforces the
approved territory list, not just the dropdown.

**PASS / FAIL:** ______

---

## T3 — Business units

**Screen:** Organisation → Business Units → **Add a business unit**

| Name | Code *(optional)* | Legal entity |
| --- | --- | --- |
| `Corporate Training` | `CT` | Lithan Academy Pte Ltd |
| `Professional Certification` | `PC` | Lithan Academy Pte Ltd |
| `Digital Campus` | `DC` | Lithan Academy Sdn Bhd |

**Expected:** Three units, each showing **0 departments**.

**PASS / FAIL:** ______

---

## T4 — Departments

**Screen:** Organisation → Departments → **Add a department**

| Name | Code | Business unit |
| --- | --- | --- |
| `Finance` | `FIN` | Corporate Training |
| `People & Culture` | `PC-HR` | Corporate Training |
| `Sales` | `SAL` | Corporate Training |
| `Delivery` | `DEL` | Professional Certification |
| `Technology` | `TEC` | Digital Campus |

**Expected:** Five departments. Business Units now reads 3 / 1 / 1.

**Also test:** reopen `Sales` and try to change its business unit by editing.
**Expected:** You cannot — the screen says **"Use Move to change this."**
Moving a department is a structural change, not a typo fix.

**PASS / FAIL:** ______

---

## T5 — Teams

**Screen:** Organisation → Teams → **Add a team**

| Name | Code | Department |
| --- | --- | --- |
| `Enterprise Sales` | `ENT` | Sales |
| `Certification Delivery` | `CERT` | Delivery |
| `Data & Platform` | `DATA` | Technology |

**Expected:** Three teams, each **0 members**.

**PASS / FAIL:** ______

---

## T6 — People

**Screen:** System Administration → Users & Groups → **Add a user**

**Object ID is REQUIRED and must be a GUID** (`8-4-4-4-12` hexadecimal). The
application checks only the *shape* — it has no directory permission to confirm
the person exists, and the screen says so rather than implying otherwise.

| Name | Email | Object ID |
| --- | --- | --- |
| `Arjun Mehta` | `arjun.mehta@demo.example` | `11111111-1111-4111-8111-111111111111` |
| `Priya Raman` | `priya.raman@demo.example` | `22222222-2222-4222-8222-222222222222` |
| `Daniel Okafor` | `daniel.okafor@demo.example` | `33333333-3333-4333-8333-333333333333` |
| `Mei Lin Tan` | `meilin.tan@demo.example` | `44444444-4444-4444-8444-444444444444` |
| `Sofia Alvarez` | `sofia.alvarez@demo.example` | `55555555-5555-4555-8555-555555555555` |

**Expected:** Five users — **Active**, **Not signed in yet**, **Not assigned**.

**Negative test:** try Object ID `12345`.
**Expected:** **Rejected** on format.

> **T6 is the first half of the product's central claim.** You have created
> five people and **not one of them can see anything.** Creating a user grants
> nothing.

**PASS / FAIL:** ______

---

## T7 — Groups

**Screen:** Users & Groups → Groups → **Add a group**

| Name | Code | Description |
| --- | --- | --- |
| `Finance Leadership` | `FINLEAD` | `Finance decision makers` |
| `Enterprise Sales Team` | `ENTSALES` | `Named enterprise accounts` |

Then add members: **Finance Leadership** → Arjun Mehta, Priya Raman.
**Enterprise Sales Team** → Daniel Okafor.

**Expected:** 2 members and 1 member.

> **Group membership grants nothing either.** It is structure, not access.

**PASS / FAIL:** ______

---

## T8 — Team membership

**Screen:** Organisation → Teams → open each team → add members

| Team | Member |
| --- | --- |
| Enterprise Sales | Daniel Okafor |
| Certification Delivery | Mei Lin Tan |
| Data & Platform | Sofia Alvarez |

**Expected:** 1 member each. *(Needed for the scope test in T11.)*

**PASS / FAIL:** ______

---

## T9 — Business domains

**Screen:** System Administration → Business Domains → **Add a domain**

**`Identity code` is a strict slug.** **`Access expectation` is required.**

| Name | Identity code | Access expectation |
| --- | --- | --- |
| `Finance` | `finance` | `Limited` |
| `People` | `people` | `Limited` |
| `Sales` | `sales` | `Broad` |
| `Learning Delivery` | `learning-delivery` | `Broad` |

Then open each and set the owner under **Accountability → Choose a person**:

| Domain | Owner |
| --- | --- |
| **Finance** | **Arjun Mehta** |
| People | Priya Raman |
| Sales | Daniel Okafor |
| Learning Delivery | Mei Lin Tan |

Confirm each reads **Enabled** under **Availability**.

**Negative test:** try identity code `Learning Delivery`.
**Expected:** **Rejected** — lower-case, digits and single hyphens only.

> **Arjun now owns Finance and will be given no Finance entitlement.**
> Deliberate: it is the T11 proof that accountability ≠ access.

**PASS / FAIL:** ______

---

## T10 — Role assignments: the four-part path

**Screen:** System Administration → Roles & Access

Granting access is **four separate actions**:

```
1. ROLE         →  Grant a role
2. ENTITLEMENT  →  open the assignment, choose a business domain
3. SCOPE        →  on that entitlement: how much of the domain
4. CEILING      →  on that entitlement: how sensitive
```

**Three of the five people below STOP EARLY. That is the test.**

| Person | Role | Entitlement | Scope | Ceiling | Stop after |
| --- | --- | --- | --- | --- | --- |
| Arjun Mehta | Domain Owner | — | — | — | **step 1** |
| Daniel Okafor | Manager | — | — | — | **step 1** |
| Mei Lin Tan | Business User | Learning Delivery | **Own records** | Standard | step 4 |
| Priya Raman | Executive | Finance | Organisation | **Confidential** | step 4 |
| Sofia Alvarez | Auditor | — | — | — | **step 1** |

**Expected after all five:** Roles & Access matches the table exactly.
**If Arjun or Daniel has an entitlement, remove it** — T11 depends on it.

**Also observe (CL-12):** as an **Organisation Administrator**, the role
dropdown offers **six** roles and **not** System Administrator. As a **System
Administrator** it offers all seven.

**PASS / FAIL:** ______

---

## T11 — The Access Simulator: five answers

**Screen:** Roles & Access → **Access Simulator**

Run **in this order**. Four denials then one approval — leading with the
approval wastes it.

| # | Choose somebody | Choose a domain | Sensitivity | Expected |
| --- | --- | --- | --- | --- |
| 1 | Arjun Mehta | Finance | Standard | **Denied — no entitlement** *(he owns it)* |
| 2 | Daniel Okafor | Sales | Standard | **Denied — no entitlement** *(he has the role)* |
| 3 | Mei Lin Tan | Learning Delivery | Standard, a record **not her own** | **Denied — outside scope** |
| 4 | Priya Raman | Finance | **Restricted** | **Denied — above ceiling** |
| 5 | Priya Raman | Finance | **Confidential** | **ALLOWED** |

**Expected:** each denial names **which of the four** was missing.

> This is the whole product in five rows. If any answer differs, **stop and
> record it** — do not improvise an explanation.

**PASS / FAIL:** ______

---

## T12 — The evidence screens

**Screens:** Security Status · Access Reviews · Audit

| Step | Expected |
| --- | --- |
| Open **Security Status** — all four tabs | Load. **Read-only — no control can be switched off here** |
| Open **Access Reviews** | Loads. A review may **confirm** or **remove** access, never create or widen it |
| Open **Audit** — all four tabs | Load. The work you did in T1–T10 appears. **Nothing can edit or delete an audit record** |

**PASS / FAIL:** ______

---

## 3. What this script does NOT cover, and why

| Not covered | Why |
| --- | --- |
| **Business data** — reports, dashboards, numbers | **Phase 3.** Phase 1 decides who *may* see information; the information is not connected. The simulator answers the access question without it. |
| **Connecting a data source** | **Phase 2 — Fabric Configuration.** All 14 entries are locked roadmap items with no route. |
| **Deleting anything** | **No hard delete anywhere.** Records deactivate. This is why the script is non-production. |
| **Seeding with a command** | `php artisan db:seed` **does not work** — the stock seeder refers to a model this application does not have. Enter the data through the screens. |
| **Signing in as the test people** | Their Object IDs are synthetic, not real Microsoft accounts. You test *about* them, signed in as an administrator. |
| **Identity / Integrations writes** | Live credentials. Read-only. |

## 4. Result summary

| Test | Covers | PASS / FAIL |
| --- | --- | --- |
| T1 | Company profile | |
| T2 | Legal entities + jurisdiction rule | |
| T3 | Business units | |
| T4 | Departments + Move-to rule | |
| T5 | Teams | |
| T6 | Users + GUID rule | |
| T7 | Groups | |
| T8 | Team membership | |
| T9 | Domains + slug and expectation rules | |
| T10 | Four-part grant path + CL-12 picker | |
| T11 | Access Simulator — five answers | |
| T12 | Security Status, Access Reviews, Audit | |
