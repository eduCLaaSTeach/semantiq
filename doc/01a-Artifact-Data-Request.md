# CLaaS2SaaS SemantIQ — Data and Artifact Request

**Document ID:** 01a-Artifact-Data-Request
**To:** Customer project sponsor and technical lead
**From:** CLaaS2SaaS SemantIQ implementation team
**Purpose:** Collect the existing artifacts needed to understand the current state before configuration begins

---

## Why we are asking

SemantIQ configures your Microsoft Fabric environment from source data through to a
governed conversational assistant. Several steps in that procedure cannot be performed
without decisions that already exist inside your organisation — an agreed definition of
revenue, a known list of source systems, an existing security model. Collecting these up
front prevents us from inventing an answer that later has to be unwound.

Please supply what exists today. **Do not create anything new for this request.** Where an
artifact does not exist, marking it "not available" is a genuinely useful answer: it tells
us the definition has to be established during the project, and we will schedule that work
rather than discover it late.

**Please do not send credentials, passwords, connection strings, API keys or secrets in
response to this request.** Access is granted through Microsoft Entra ID during onboarding,
never by sharing a secret.

---

## A — Microsoft tenant and licensing

| # | Artifact requested | Why it is needed | Related step | Priority |
|---|---|---|---|---|
| A1 | Fabric capacity details: SKU, region, billing state | Confirms the F2-or-higher paid capacity that Fabric Data Agent requires | 1 | Essential |
| A2 | Name and contact of the Fabric Administrator or Global Administrator | Tenant-level AI settings cannot be changed without this role | 2 | Essential |
| A3 | Current Fabric tenant settings export or screenshots for the Copilot and AI settings | Establishes the starting state before any change is requested | 3, 4 | Essential |
| A4 | Entra ID tenant ID and the admin-consent approver | Required to connect SemantIQ to your tenant | — | Essential |
| A5 | Microsoft licensing summary covering Fabric, Power BI and Copilot Studio | Confirms the conversational layer is licensed before it is designed | 55 | Essential |
| A6 | Data-residency or sovereignty policy, if one exists | Determines whether cross-geo AI processing is permissible | 4 | Important |

## B — Existing Fabric and Power BI estate

| # | Artifact requested | Why it is needed | Related step | Priority |
|---|---|---|---|---|
| B1 | List of existing Fabric workspaces with their capacity assignment | Determines whether we attach to existing workspaces or create new ones | 5, 6, 8 | Essential |
| B2 | List of existing lakehouses, warehouses and semantic models | Avoids duplicating an asset you already trust | 14, 26, 29 | Essential |
| B3 | Existing Power BI reports and semantic models in active business use | These contain your real, already-agreed business definitions | 29, 32, 78 | Essential |
| B4 | Existing DAX measure definitions, if extractable | The fastest route to a correct measure library | 32 | Important |
| B5 | Current workspace role assignments | Establishes the existing access model before we narrow it | 7, 77 | Important |
| B6 | Existing deployment pipelines and Git connections | Determines what lifecycle tooling already exists | 75, 76 | Useful |

## C — Source systems

| # | Artifact requested | Why it is needed | Related step | Priority |
|---|---|---|---|---|
| C1 | Inventory of every source system: name, vendor, version, hosting, owner | The foundation of the entire ingestion design | 9 | Essential |
| C2 | For each source, whether it is cloud-hosted or on-premises | Determines gateway and private-connectivity requirements | 11, 12 | Essential |
| C3 | Data dictionaries, ERDs or schema exports per source | Enables entity standardisation without guesswork | 21, 22 | Essential |
| C4 | Approximate table sizes and daily change volumes | Drives ingestion method and schedule selection | 10, 17, 18 | Important |
| C5 | Available change-tracking mechanism per source: timestamp, key, CDC, none | Determines whether incremental loading is possible | 17 | Important |
| C6 | Business freshness requirement per source, stated in business terms | Sets the schedule; "hourly" and "daily" are very different costs | 18 | Important |
| C7 | Existing integration or ETL documentation, including any current tooling | Shows what already works and what is being replaced | 10, 16, 23 | Important |
| C8 | Network topology or connectivity constraints for non-public sources | Needed before a connection can be attempted | 12 | Important |
| C9 | Known data-quality issues, in whatever form they are currently tracked | Seeds the quality rule set with real, already-known problems | 24 | Useful |

## D — Business definitions and semantics

| # | Artifact requested | Why it is needed | Related step | Priority |
|---|---|---|---|---|
| D1 | KPI definitions currently in use, with owners | These become the measure library and the glossary | 32, 78 | Essential |
| D2 | Existing business glossary or data dictionary | Avoids re-litigating terms your business has already settled | 78 | Essential |
| D3 | Definitions of contested terms — for example active learner, revenue, completion, pipeline | These are the terms most likely to produce a disputed AI answer | 38 | Essential |
| D4 | Master lists for core entities: Customer, Learner, Product, Course, Employee, Department | Establishes the canonical entity shapes | 21 | Essential |
| D5 | Existing management reports or board packs, redacted as needed | The most reliable source of the questions the assistant must answer | 39, 49 | Essential |
| D6 | The vocabulary and abbreviations your business users actually use | Becomes the synonym set, which is the strongest driver of answer quality | 34 | Important |
| D7 | Financial calendar and period definitions | Date logic is the most common cause of a wrong answer | 28 | Important |

## E — Security and governance

| # | Artifact requested | Why it is needed | Related step | Priority |
|---|---|---|---|---|
| E1 | Current data-access policy: who may see which companies, departments, regions, learners or customers | Becomes the row-level security design | 35 | Essential |
| E2 | List of sensitive or restricted columns and fields | Becomes the column-level security design | 36 | Essential |
| E3 | Entra ID security groups intended for data access | Grants are made to groups, not individuals, wherever possible | 53, 71 | Essential |
| E4 | Sensitivity-label taxonomy, if one is in use | Applied during governance configuration | 77 | Important |
| E5 | Applicable regulatory or internal compliance obligations | Shapes governance, residency and audit requirements | 77 | Important |
| E6 | Existing change-management or approval process for data and reporting | The AI change process should extend this, not compete with it | 79 | Important |
| E7 | Named approvers for go-live sign-off across data, security and business | Step 80 cannot complete without named approvers | 80 | Important |

## F — Users and consumption

| # | Artifact requested | Why it is needed | Related step | Priority |
|---|---|---|---|---|
| F1 | Intended user population with role and approximate count | Sizes the access model and the capacity requirement | 53, 71 | Essential |
| F2 | Intended consumption channels: Teams, web portal, other | Determines channel configuration | 69, 70 | Essential |
| F3 | 20 to 30 real questions the business wants to ask, in the users' own words | The single most valuable artifact in this request — it becomes the ground-truth bank | 46, 49 | Essential |
| F4 | Named test users, one per security role, available for validation | Steps 51 and 67 require testing as real users, not administrators | 51, 67 | Essential |
| F5 | Any existing chatbot or assistant already in use | Avoids overlap and clarifies routing boundaries | 60, 62 | Useful |

## G — Operations

| # | Artifact requested | Why it is needed | Related step | Priority |
|---|---|---|---|---|
| G1 | Existing monitoring and alerting arrangements, and who receives alerts | Pipeline and capacity alerts should reach the people already on call | 19, 72, 73 | Important |
| G2 | Support and escalation model for data issues | Determines who owns a failed load after go-live | 19, 73 | Important |
| G3 | Maintenance windows and change-freeze periods | Constrains scheduling and promotion | 18, 76 | Useful |

---

## How to return this

1. Reply with the artifacts you can supply now, referenced by the item number above.
2. For anything unavailable, mark it "not available" or "does not exist" — this is a
   valid and useful answer, and we will plan the work to establish it.
3. Flag anything that requires an internal approval before release, and we will schedule
   it rather than block on it.
4. **Send no credentials, secrets, connection strings or API keys.** Access is arranged
   through Microsoft Entra ID during technical onboarding.

## What happens next

| Sequence | Activity | Depends on |
|---|---|---|
| 1 | Readiness assessment against the tenant and capacity | A1–A6, B1 |
| 2 | Source and connectivity plan | C1–C9 |
| 3 | Semantic and definition workshop | D1–D7 |
| 4 | Security model design | E1–E3 |
| 5 | Ground-truth question bank baseline | F3, F4 |
| 6 | Configuration begins in the DEV workspace | All Essential items |

**Essential items are genuinely blocking.** We can begin readiness and provisioning work
with section A and B1 alone, and we would rather start there than wait for a complete
return.

---

*01-Requirement-Scoping*
