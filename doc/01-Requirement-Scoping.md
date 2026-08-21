# CLaaS2SaaS SemantIQ — Requirement Scoping

**Document ID:** 01-Requirement-Scoping
**Source:** End-to-end Microsoft Fabric to conversational AI procedure (80 steps)
**Status:** Draft for review

---

## How to read this document

Each row is one step of the source procedure, in its original sequence, scoped as an
implementable SemantIQ feature. The **Automation Tier** column records how SemantIQ
participates:

- **A — Automated:** SemantIQ performs the change through a Microsoft API.
- **B — Guided and verified:** SemantIQ instructs, deep-links, then verifies by reading
  the resulting state back. Progress is blocked until verification passes or a written
  exception is approved.
- **C — Governed record:** SemantIQ is the system of record and the review workflow for a
  decision or knowledge artifact.

Roles used in the **Who** column are the SemantIQ application roles: **Platform
Administrator**, **Tenant Administrator**, **Lead Data Engineer**, **Data Engineer**,
**Business User**. Where the action must be taken by a Microsoft tenant role that SemantIQ
cannot hold, the row names it explicitly (for example *Fabric Administrator (customer)*).

---

## Cluster 1 — Tenant Readiness and Governance Preflight

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 1 | Verify Fabric capacity | Tenant Administrator | Per project onboarding, before any provisioning | - Read the tenant's Fabric capacities and their SKUs<br>- Confirm at least one paid capacity is F2 or higher<br>- Confirm the capacity is active, not paused<br>- Record the capacity region<br>- Block the project if no eligible capacity exists | Capacity Readiness Check | Reads the customer's Fabric capacities and verifies that a paid F2-or-higher capacity is active, because Fabric Data Agent will not run below F2. Records SKU, region and state against the project and blocks downstream stages until an eligible capacity is confirmed. | A |
| 2 | Confirm Fabric Administrator | Tenant Administrator | Per project onboarding | - Identify who holds Fabric Administrator or Global Administrator in the tenant<br>- Confirm Capacity Admin alone is insufficient for tenant settings<br>- Record the named administrator and contact<br>- Request their participation for the tenant-setting steps | Administrator Role Register | Records which named person holds the Microsoft tenant-level administrator role required for Fabric tenant settings, and makes clear that Capacity Admin cannot complete those steps. Gives the project a named owner for every action SemantIQ cannot perform itself. | B |
| 3 | Enable Fabric AI tenant settings | Fabric Administrator (customer) | Per tenant, once, before agent work | - Open the Fabric Admin Portal tenant settings<br>- Enable the Copilot and Fabric Data Agent AI settings<br>- Return to SemantIQ and run verification<br>- SemantIQ reads tenant settings back and confirms the enabled state | Tenant AI Settings Verifier | Presents the exact tenant settings that must be enabled, deep-links to the Fabric Admin Portal, then reads the tenant settings back to verify the change rather than trusting a checkbox. Blocks the agent stages until verification passes. | B |
| 4 | Enable cross-geo AI settings if required | Fabric Administrator (customer) | Per tenant, when the capacity region requires it | - Compare capacity region against the regions where Azure OpenAI processing is available<br>- Determine whether cross-geo processing or storage is required<br>- Enable the cross-geo tenant settings if so<br>- Record the data-residency implication for governance review | Cross-Geo AI Requirement Advisor | Evaluates the capacity region against AI processing availability, tells the customer whether cross-geo processing must be enabled, and records the residency consequence as a governance decision so it is visible at go-live review rather than discovered later. | B |

## Cluster 2 — Workspace and Environment Provisioning

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 5 | Create Fabric workspace | Lead Data Engineer | Per project setup | - Choose create-new or attach-existing<br>- Supply the workspace name, for example `Data-AI-DEV`<br>- Create the workspace through the Fabric API, or select an existing one to attach<br>- Register the workspace identifier against the project | Workspace Provisioner | Creates a dedicated Fabric workspace for the solution, or attaches one the customer already owns, and registers its identity in the project so every later artifact is created in the right place. Naming follows a project convention to keep DEV, TEST and PROD unambiguous. | A |
| 6 | Assign workspace to Fabric capacity | Lead Data Engineer | Immediately after workspace creation | - Select the verified F2+ capacity<br>- Assign the workspace to that capacity through the API<br>- Re-read the workspace to confirm the assignment<br>- Fail the step if the workspace is still on a trial or shared capacity | Capacity Assignment | Assigns each workspace to the verified Fabric capacity and confirms the assignment by read-back, because a workspace left on trial or shared capacity will fail later at the Data Agent stage in a way that is hard to diagnose. | A |
| 7 | Assign workspace administrators | Tenant Administrator | Per workspace, at creation and on team change | - Select the technical administrators<br>- Grant Workspace Admin role through the API<br>- Confirm the required capacity permissions are also held<br>- Record the resulting role assignments | Workspace Role Manager | Grants Workspace Admin rights to the named technical administrators and confirms the capacity permissions that must accompany them, then keeps the resulting assignment list as an auditable record of who can change what. | A |
| 8 | Create TEST and PROD workspaces | Lead Data Engineer | Per project, when the solution is intended for production | - Decide whether the project is a pilot or an enterprise deployment<br>- Create or attach TEST and PROD workspaces<br>- Assign each to capacity and to administrators<br>- Link the three as one promotion set | Environment Set Manager | Creates or attaches the DEV, TEST and PROD workspaces as a single linked promotion set, so that deployment pipelines and change control have somewhere to promote to. A pilot may run DEV only, and the decision is recorded rather than assumed. | A |

## Cluster 3 — Source and Connectivity Management

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 9 | Identify source systems | Data Engineer | Per project discovery, revisited when a source is added | - Capture every source: SQL Server, Business Central, Dataverse, SharePoint, Excel, APIs, ERP, CRM, external databases<br>- Record owner, environment, location, refresh expectation and sensitivity<br>- Mark whether the source is on-premises or cloud<br>- Confirm the register with the business owner | Source System Register | Maintains the authoritative inventory of every system feeding the solution, with owner, hosting location, sensitivity and refresh expectation. This register drives ingestion-method selection, connectivity requirements and the data-quality plan, and it is the first artifact reviewed at go-live. | C |
| 10 | Decide ingestion method per source | Data Engineer | Per source, at registration | - Assess source type, volume, change rate and latency need<br>- Choose Fabric Pipeline, Dataflow Gen2, Mirroring, Shortcut, Eventstream or direct upload<br>- Record the rationale for the choice<br>- Flag sources where no method is viable without additional connectivity | Ingestion Method Advisor | Recommends an ingestion method per source from its type, volume, change rate and latency requirement, and records why the method was chosen. The rationale matters because revisiting ingestion later is expensive, and reviewers need to see the reasoning. | C |
| 11 | Install gateway for on-premises sources | Tenant Administrator | Event-driven, when a source is on-premises | - Identify all sources marked on-premises<br>- Install the Microsoft On-premises Data Gateway on a suitable host<br>- Register the gateway in the tenant<br>- SemantIQ reads the gateway list back and confirms it is online | Gateway Readiness Tracker | Detects which registered sources require an on-premises data gateway, guides the installation, and then verifies through the API that a gateway is registered and online before dependent connections are attempted. | B |
| 12 | Configure private connectivity if required | Platform Administrator | Event-driven, when a source is not publicly reachable | - Determine reachability for each source<br>- Select VNet Gateway, Private Link or the appropriate network control<br>- Record the network design and its owner<br>- Verify connectivity by a test connection before proceeding | Private Connectivity Planner | Records the network path required for sources that are not publicly reachable, names the owner of that network change, and holds the source in a pending state until a live test connection succeeds. Prevents ingestion being built against a source that cannot actually be reached. | B |
| 13 | Create Fabric connections | Data Engineer | Per source, after connectivity is in place | - Create an authenticated connection per source in Fabric<br>- Select the credential type and gateway where applicable<br>- Run a live connection test<br>- Save only after the test passes and link the connection to the source register entry | Connection Manager | Creates and tests an authenticated Fabric connection for every registered source, and follows the test-before-save rule so a connection is never stored in an untested state. Credentials are held by Microsoft; SemantIQ stores only the connection reference. | A |

## Cluster 4 — Lakehouse and Medallion Architecture

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 14 | Create a Lakehouse | Data Engineer | Per environment, after workspace setup | - Create a Fabric Lakehouse in the target workspace<br>- Confirm OneLake storage and Delta table format<br>- Register the lakehouse and its endpoints against the project | Lakehouse Provisioner | Creates the Fabric Lakehouse that will hold enterprise data in OneLake as Delta tables, and registers its identity and endpoints so ingestion, transformation and modelling all target the same store. | A |
| 15 | Create Bronze/raw area | Data Engineer | Per lakehouse, at creation | - Define the Bronze schema or folder convention<br>- Configure landing with minimal transformation<br>- Establish retention for original source data<br>- Document that Bronze is the immutable record of what the source sent | Medallion Layer Designer — Bronze | Establishes the Bronze layer as the immutable landing zone holding source data with minimal transformation, so the original business data is always recoverable when a transformation or interpretation later proves wrong. | A |
| 20 | Create Silver/clean layer | Data Engineer | Per lakehouse, after Bronze is landing | - Define Silver schema conventions<br>- Configure cleansing for duplicates, invalid records, missing values, inconsistent dates, codes and data types<br>- Establish the Bronze-to-Silver lineage record | Medallion Layer Designer — Silver | Establishes the Silver layer and its cleansing contract, covering duplicates, invalid records, missing values and inconsistent dates, codes and types, with lineage recorded back to Bronze so any cleaned value can be traced to its origin. | A |
| 25 | Create Gold/business layer | Data Engineer | Per lakehouse, after Silver is validated | - Define Gold schema conventions<br>- Build analytics-ready tables of trusted entities, facts, dimensions and calculated fields<br>- Register each Gold table with its business owner | Medallion Layer Designer — Gold | Establishes the Gold layer of analytics-ready, business-trusted tables, each with a named owner, forming the only surface the semantic model is permitted to read from. | A |
| 26 | Create Fabric Warehouse if required | Lead Data Engineer | Per project, when the Gold layer needs a SQL star schema | - Assess whether the Gold layer benefits from a traditional SQL analytical structure<br>- Create the Fabric Warehouse if so<br>- Record the decision and rationale where a Warehouse is not used | Warehouse Provisioner | Creates a Fabric Warehouse where the Gold layer benefits from a traditional SQL star-schema structure, and records the rationale either way, so the choice between lakehouse-only and warehouse is a documented architectural decision rather than an accident. | A |

## Cluster 5 — Ingestion Orchestration

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 16 | Create Data Factory pipelines | Data Engineer | Per source, after connection is tested | - Generate a Fabric pipeline or Copy Job per source<br>- Map source objects to Bronze targets<br>- Parameterise the pipeline for environment promotion<br>- Run once and confirm data lands in Bronze | Pipeline Builder | Builds the Fabric pipelines and copy jobs that move source data into the Bronze layer, parameterised so the same definition promotes cleanly from DEV to TEST to PROD, and verified by a first successful run. | A |
| 17 | Configure incremental loading | Data Engineer | Per pipeline, at build time | - Identify the change-tracking mechanism available in the source: timestamp, key, CDC or source-specific<br>- Configure watermark storage and the incremental predicate<br>- Fall back to full load only where no mechanism exists, and record why<br>- Validate that a second run moves only changed records | Incremental Load Configurator | Configures each pipeline to move only new or modified records using the best mechanism the source offers, storing the watermark and validating on a second run that the load is genuinely incremental. Where full reload is unavoidable, the reason is recorded. | A |
| 18 | Schedule ingestion | Data Engineer | Per pipeline, at build time; revised on business need | - Capture the business freshness requirement per source<br>- Set the schedule: hourly, daily or near-real-time<br>- Check the combined schedule against capacity headroom<br>- Activate the schedule | Ingestion Scheduler | Sets each pipeline's schedule from the stated business freshness requirement and checks the combined load against capacity headroom before activating, so a new schedule cannot silently exhaust the capacity that the Data Agent also depends on. | A |
| 19 | Add pipeline error handling | Data Engineer | Per pipeline, at build time | - Configure retries with backoff<br>- Define failure paths and on-failure activities<br>- Enable run logging<br>- Configure notification recipients for failed loads<br>- Test by forcing a failure | Pipeline Resilience Configurator | Adds retries, failure paths, logging and notification to every pipeline and proves the configuration by forcing a failure, so a broken load is detected and owned rather than silently producing stale answers in the conversational app. | A |

## Cluster 6 — Transformation and Data Quality

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 21 | Standardize business entities | Data Engineer | Per entity, during Silver build | - List the business entities present across sources: Customer, Employee, Product, Course, Learner, Supplier, Transaction<br>- Define one canonical shape per entity<br>- Map each source's fields onto the canonical entity<br>- Review the canonical definition with the business owner | Entity Standardisation Workbench | Defines one canonical shape per business entity and maps every source's fields onto it, with business-owner review. This is where the conversational app's vocabulary is actually decided, because the agent can only speak about entities that exist consistently. | A |
| 22 | Create common business keys | Data Engineer | Per entity, during Silver build | - Identify candidate natural keys per source<br>- Define the surrogate or composite key strategy<br>- Build the cross-system mapping table<br>- Test join integrity and measure unmatched rates | Business Key Builder | Creates reliable keys that let records from different systems be joined correctly, with a cross-system mapping table and a measured unmatched rate, because an unmatched key becomes a wrong number in a management answer. | A |
| 23 | Apply transformation logic | Data Engineer | Per transformation, during Silver and Gold build | - Select the tool: Dataflow Gen2, Fabric Notebook or Spark, SQL, or pipeline<br>- Implement the transformation<br>- Version the logic and record its purpose in business terms<br>- Run and reconcile output against source counts | Transformation Studio | Registers and versions every transformation with its business purpose alongside its implementation, whichever Fabric tool performs it, so a later question about how a figure was derived has an answer that does not require reading code. | A |
| 24 | Add data-quality validation | Data Engineer | Per layer promotion, on every run | - Define rules for uniqueness, nulls, ranges, referential integrity, record counts and business rules<br>- Set severity and the blocking threshold per rule<br>- Execute rules as a gate before promotion<br>- Publish a quality result per run | Data Quality Gate | Runs uniqueness, null, range, referential-integrity, record-count and business-rule checks as a gate before data is promoted, blocking promotion on a breach of a rule marked blocking, and publishing a per-run quality result that the go-live review depends on. | A |

## Cluster 7 — Dimensional Modelling

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 27 | Create fact tables | Data Engineer | Per subject area, during Gold build | - Identify the business processes to measure: Sales, Enrolments, Attendance, Payments, Leads, Orders<br>- Define grain explicitly for each fact<br>- Define measures, degenerate dimensions and foreign keys<br>- Validate row counts against the source process | Fact Table Designer | Designs the transaction-oriented fact tables with an explicitly declared grain, because an undeclared or mixed grain is the most common cause of a conversational agent returning a plausible but wrong total. | A |
| 28 | Create dimension tables | Data Engineer | Per dimension, during Gold build | - Define descriptive dimensions: Customer, Learner, Product, Course, Employee, Department, Calendar<br>- Set the key, attributes and hierarchy<br>- Decide and record history handling per dimension<br>- Generate and populate the Calendar dimension | Dimension Table Designer | Designs the descriptive dimension tables including a generated Calendar dimension, with attributes, hierarchies and an explicit decision on history handling, giving the agent the vocabulary it needs to filter, group and compare over time. | A |

---
## Cluster 8 — Semantic Model and AI Preparation

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 29 | Create Power BI Semantic Model | Lead Data Engineer | Per project, after the Gold layer is validated | - Create the semantic model over Gold data<br>- Select the tables to include<br>- Register the model against the project<br>- Confirm it reads only from Gold | Semantic Model Provisioner | Creates the Power BI semantic model over the Gold layer so business definitions are controlled in one place, and enforces that the model reads only from Gold rather than reaching back into Silver or Bronze. | A |
| 30 | Prefer Direct Lake where appropriate | Lead Data Engineer | At model creation; reviewed on performance change | - Assess table size, refresh pattern and transformation need<br>- Select Direct Lake where the data supports it<br>- Record the storage mode per table and the reason for any exception<br>- Verify no unnecessary duplication of OneLake data | Storage Mode Advisor | Recommends and records the storage mode per table, defaulting to Direct Lake so the model queries OneLake without duplicating data, and requiring a recorded reason wherever import or DirectQuery is used instead. | A |
| 31 | Define relationships | Data Engineer | At model build; revised when tables change | - Define relationships between facts and dimensions<br>- Set cardinality and cross-filter direction<br>- Detect ambiguous or missing paths<br>- Validate that every fact can be filtered by every intended dimension | Relationship Designer | Configures and validates the fact-to-dimension relationships that let the AI understand how business entities connect, and actively reports missing or ambiguous join paths, since an unreachable dimension makes a whole class of questions unanswerable. | A |
| 32 | Create explicit measures | Data Engineer | Per measure, at model build; revised on definition change | - List the business measures required: Revenue, Margin, Attendance %, Completion Rate, Pipeline Value<br>- Author explicit DAX for each<br>- Record the business definition and owner alongside the DAX<br>- Remove reliance on implicit aggregation | Measure Library | Holds every business measure as explicit DAX paired with its plain-language definition and named owner, and discourages implicit aggregation, because an implicit sum gives the agent a number with no agreed meaning behind it. | A |
| 33 | Use business-friendly names | Data Engineer | At model build; enforced on every model change | - Scan the model for technical names such as `cust_id` or `rev_amt`<br>- Propose business-friendly replacements<br>- Apply approved renames to tables, columns and measures<br>- Re-scan and report any remaining technical names | Business Naming Assistant | Detects technical field names in the model, proposes business-friendly replacements, applies the approved ones and re-scans until none remain, so the agent answers in the language the business actually uses. | A |
| 34 | Add descriptions and synonyms | Data Engineer with Business User | Per object, at model build; extended from real questions | - Write a description for every table, column and measure<br>- Add the synonyms and abbreviations business users actually say<br>- Source additional synonyms from real failed questions in monitoring<br>- Report coverage as a percentage of objects described | Semantic Enrichment Workspace | Captures descriptions and business synonyms for every model object, measures the coverage, and feeds new synonyms in from questions the agent failed to understand. This is the highest-leverage feature in the product, because agent quality tracks this metadata more than any prompt. | A |
| 35 | Configure Row-Level Security | Lead Data Engineer | Per project, before publication | - Identify the dimensions that must restrict visibility: company, department, region, learner, customer<br>- Define RLS roles and their DAX filters<br>- Map Entra users and security groups to roles<br>- Test each role with a representative user | Row-Level Security Manager | Defines the RLS roles and filters that keep users to their own companies, departments, regions, learners or customers, maps Entra identities to those roles, and requires a per-role test before publication is permitted. | A |
| 36 | Configure Column-Level Security if required | Lead Data Engineer | Per project, when sensitive columns exist | - Identify sensitive columns<br>- Apply column restrictions per role<br>- Record the restriction and its justification<br>- Verify a restricted user cannot retrieve the column through the agent | Column-Level Security Manager | Restricts sensitive columns per role, records why each restriction exists, and verifies through the agent itself that a restricted user cannot retrieve the value by asking a question, which is the path most likely to be overlooked. | A |
| 37 | Configure semantic-model Prep for AI | Lead Data Engineer | Per model, before agent creation | - Open Prep data for AI for the semantic model<br>- Select the subset of tables, columns and measures the AI should understand<br>- Exclude technical, duplicated and irrelevant objects<br>- Verify the prepared surface matches the intended use case | AI Surface Curator | Defines and records the deliberately narrow subset of the model the AI is allowed to reason over, excluding technical and irrelevant objects, because a wide surface reliably degrades answer accuracy. SemantIQ holds the intended surface and verifies the configured one against it. | B |
| 38 | Configure AI Instructions | Business User with Lead Data Engineer | Per model, at preparation; revised on definition change | - Capture the terminology and business rules the AI must apply<br>- Define contested terms explicitly, for example active learner, revenue, completion, pipeline<br>- Version each instruction with an owner and effective date<br>- Publish the approved instruction set to the model | AI Instruction Manager | Holds the business terminology and rules the AI must apply as versioned, owned and dated instructions, with contested terms defined explicitly, so a change of business definition is a reviewable event rather than an unattributed edit. | B |
| 39 | Configure Verified Answers | Business User with Lead Data Engineer | Per question, ongoing | - Collect the important recurring management questions<br>- Attach the validated answer or visual to each<br>- Record the approver and the review date<br>- Publish verified answers to the model | Verified Answer Register | Maintains the recurring management questions with their validated answers, each with a named approver and review date, so the questions leadership asks most often return a governed answer instead of a freshly generated one. | B |

## Cluster 9 — Data Agent Studio

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 40 | Create Fabric Data Agent | Lead Data Engineer | Per use case, after the model is AI-prepared | - Create a Fabric Data Agent in the workspace<br>- Give it a clear business name reflecting the questions it answers<br>- Register the agent against the project<br>- Confirm the workspace capacity supports it | Data Agent Builder | Creates the Fabric Data Agent with a business-meaningful name and registers it against the project, confirming first that the readiness checks and capacity requirements it depends on have all passed. | A |
| 41 | Add the semantic model to Data Agent | Lead Data Engineer | At agent creation | - Attach the prepared semantic model as a data source<br>- Designate it the primary governed source<br>- Confirm the model's AI preparation is in place | Agent Source Binder — Semantic Model | Attaches the prepared Power BI semantic model as the agent's primary governed business-data source, and refuses the binding if the model's AI preparation has not been completed and verified. | A |
| 42 | Add other Fabric sources if required | Lead Data Engineer | Per agent, when additional sources are needed | - Identify additional required sources: Lakehouse, Warehouse, KQL, Graph, Ontology<br>- Attach each as an agent source<br>- Enforce the current five-source limit<br>- Record why each additional source is needed | Agent Source Binder — Additional Sources | Attaches supported additional Fabric sources to the agent, enforces the current limit of five sources per agent, and requires a recorded justification per source so the agent's source list stays deliberate. | A |
| 43 | Select only required schemas/tables | Lead Data Engineer | Per source, at binding | - Review the objects each source exposes<br>- Select only what the conversational use case needs<br>- Exclude everything else<br>- Report the selected surface for review | Agent Scope Selector | Restricts each agent source to only the schemas and tables the conversational use case requires, and reports the resulting surface for review, since unnecessary tables measurably reduce answer accuracy. | A |
| 44 | Write Data Agent instructions | Lead Data Engineer | At agent creation; revised on test findings | - State the agent's role and audience<br>- State which source to use for which kind of question<br>- Define expected answer style and format<br>- State business restrictions and refusal conditions<br>- Version the instruction set | Agent Instruction Editor | Authors and versions the agent's own instructions covering role, source selection, answer style and business restrictions, keeping every revision so a change in behaviour can be traced to the instruction change that caused it. | A |
| 45 | Add source descriptions | Lead Data Engineer | Per source, at binding | - Describe what each Lakehouse, Warehouse or KQL source represents in business terms<br>- State what kinds of question it should answer<br>- State what it must not be used for | Source Description Editor | Describes each attached source in business terms with guidance on which questions it should and should not serve, which is how the agent chooses correctly between sources rather than guessing. | A |
| 46 | Add example questions and queries | Lead Data Engineer | Per source, at binding; extended from testing | - Collect representative natural-language questions<br>- Provide SQL or KQL examples where the source supports them<br>- Add examples drawn from real failures<br>- Keep examples aligned to current measures and tables | Example Question Library | Holds representative natural-language questions with matching query examples where the source supports them, and grows from real failures found in testing and monitoring, giving the agent concrete patterns instead of only abstract instructions. | A |

## Cluster 10 — Testing, Accuracy and Security Validation

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 47 | Test simple questions | Data Engineer | Per agent version, before publication | - Ask baseline questions such as total sales last month<br>- Inspect the generated SQL or DAX<br>- Compare the returned result against an independently calculated figure<br>- Record pass or fail with the evidence | Agent Test Console — Baseline | Runs baseline questions against the agent, captures the generated query alongside the returned result, and requires comparison against an independently calculated figure, so a passing test means the number was verified and not merely returned. | A |
| 48 | Test complex questions | Data Engineer | Per agent version, before publication | - Test comparisons, trends, rankings, multi-dimension filters and date logic<br>- Test conversational follow-up questions<br>- Capture the query and result for each<br>- Classify each failure by cause | Agent Test Console — Advanced | Exercises comparisons, trends, rankings, filters, date logic, multiple dimensions and multi-turn follow-ups, classifying every failure by cause so remediation targets the model, the metadata or the instructions rather than being guessed at. | A |
| 49 | Create ground-truth test questions | Business User with Data Engineer | Per project; maintained continuously | - Collect real business questions from the intended users<br>- Establish the correct answer for each with a named approver<br>- Store question, expected answer, tolerance and approver<br>- Re-run the whole bank on every agent or model change | Ground Truth Question Bank | Maintains business questions with independently approved correct answers and tolerances, and re-runs the full bank on every change, turning agent accuracy from an impression into a measured, trackable figure. | C |
| 50 | Correct semantic definitions instead of prompts alone | Lead Data Engineer | Event-driven, on every failed test | - Diagnose whether the failure is in measures, relationships, metadata or AI preparation<br>- Fix the underlying semantic definition<br>- Use instruction changes only where the definition is already correct<br>- Re-run the affected tests and record the resolution | Root Cause Remediation Tracker | Routes every failed question to a diagnosis of its true cause and pushes the fix into the semantic layer, permitting an instruction-only fix solely where the definition is already correct. This is what stops the project accumulating prompt patches over a broken model. | C |
| 51 | Validate security | Tenant Administrator | Per agent version, before publication | - Select representative users from each RLS role<br>- Ask questions that would expose out-of-scope data<br>- Confirm RLS, CLS and source permissions all hold through the agent<br>- Record the evidence per role and block publication on any breach | Security Validation Suite | Tests the published agent as real users from each security role, confirming that row-level security, column-level security and source permissions are all still enforced when data is reached through conversation, and blocks publication on any breach. | A |

## Cluster 11 — Publication and Access

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 52 | Publish Fabric Data Agent | Lead Data Engineer | Per agent version, after tests and security pass | - Confirm accuracy threshold and security validation are both met<br>- Write the agent's published description stating exactly what it can answer<br>- Publish the agent<br>- Record the published version and its evidence | Agent Publication Gate | Publishes the validated agent only after accuracy and security evidence are both present, and requires a published description stating precisely what the agent can and cannot answer, which is what sets correct user expectations from first contact. | A |
| 53 | Grant Data Agent access | Tenant Administrator | Per user or group, after publication | - Select the Entra users and security groups<br>- Grant permission to the published agent<br>- Confirm permission on the required underlying data<br>- Record every grant with its approver | Access Grant Manager | Grants access to the published agent and its underlying data to named Entra users and security groups, and records each grant with its approver so the audit question of who was given access to what has a direct answer. | A |
| 54 | Grant semantic-model Read permission | Tenant Administrator | Per user or group, alongside agent access | - Grant Read on the semantic model to querying users<br>- Confirm that Build permission is not granted merely to enable querying<br>- Verify effective permissions per user | Model Permission Manager | Grants the Read permission that users querying through the Data Agent require, and deliberately avoids granting Build, which is not needed to query and which would widen access beyond intent. | A |

## Cluster 12 — Conversational Application

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 55 | Create Copilot Studio environment | Platform Administrator | Once per tenant, before agent authoring | - Confirm the Copilot Studio environment is in the same tenant as the Fabric Data Agent<br>- Confirm licensing and environment availability<br>- Record the environment identity against the project | Copilot Environment Register | Confirms and records the Copilot Studio environment, checking it is in the same tenant as the Fabric Data Agent, which is a common and costly early misconfiguration. | B |
| 56 | Create conversational agent | Lead Data Engineer | Per conversational application | - Create the agent in Copilot Studio<br>- Set a user-friendly name, description and instructions<br>- Record the agent identity against the project | Conversational Agent Register | Guides the creation of the Copilot Studio agent and holds its name, description and instructions in SemantIQ as the reviewable source of record, since the authoring itself is performed in the Microsoft portal. | B |
| 57 | Enable generative orchestration | Lead Data Engineer | At agent creation | - Enable generative orchestration in Copilot Studio<br>- Confirm the agent may decide dynamically when to invoke the Fabric Data Agent<br>- Verify by a routing test | Orchestration Configuration Guide | Records and verifies that generative orchestration is enabled so the assistant can decide dynamically when to call the Fabric Data Agent, and proves it with a routing test rather than a setting screenshot. | B |
| 58 | Add Fabric Data Agent | Lead Data Engineer | At agent creation | - Add the Microsoft Fabric agent connection in Copilot Studio<br>- Create or select the Fabric connection<br>- Confirm the connection authenticates | Fabric Connection Guide | Guides the creation of the Copilot Studio to Fabric connection and records which connection the conversational agent uses, so a later authentication failure can be traced to a known connection. | B |
| 59 | Select published Fabric Data Agent | Lead Data Engineer | At agent creation | - Select the published Data Agent from the workspace<br>- Confirm the selected version matches the version SemantIQ published<br>- Record the binding | Agent Binding Verifier | Confirms that the Data Agent selected in Copilot Studio is the same published version SemantIQ validated, catching the case where the conversational app is pointed at an older or untested agent. | B |
| 60 | Write a clear connected-agent description | Lead Data Engineer | At agent creation; revised on routing failures | - Describe exactly when Copilot Studio should invoke Fabric<br>- Distinguish it from other knowledge sources and tools<br>- Revise from observed routing failures | Routing Description Editor | Holds the connected-agent description that tells Copilot Studio when to invoke Fabric rather than another source, and revises it from observed routing failures, since most wrong answers at this layer are wrong routing rather than wrong data. | B |
| 61 | Choose authentication model | Tenant Administrator | Per conversational application | - Decide between user authentication and author authentication<br>- Default to user authentication so each user's own Fabric permissions apply<br>- Record the decision, its owner and its security consequence<br>- Require explicit approval for author authentication | Authentication Model Decision | Records the authentication choice with its security consequence, defaulting to user authentication so answers respect each user's own data permissions, and requiring explicit named approval before author authentication is used. | C |
| 62 | Add additional knowledge if required | Lead Data Engineer | Per application, when non-analytical knowledge is needed | - Identify required non-analytical knowledge: SharePoint, websites, documents<br>- Add each knowledge source<br>- Record its purpose and its boundary against Fabric data | Knowledge Source Register | Records any non-analytical knowledge added to the conversational app and the boundary between it and governed Fabric data, so a policy answer is never mistaken for a measured one. | B |
| 63 | Add actions if required | Lead Data Engineer | Per application, when the assistant must act | - Identify required actions: Power Automate flows, APIs, connectors<br>- Add each action<br>- Define its permission and confirmation requirement<br>- Record the action and its owner | Action Register | Records every action the assistant can perform beyond answering, together with its permission and confirmation requirement, because an action that changes data needs governance an answer does not. | B |
| 64 | Create conversation instructions | Business User with Lead Data Engineer | Per application; revised from monitoring | - Define response format, tone and follow-up behaviour<br>- Define restrictions and the wording used when information is unavailable<br>- Version the instruction set with an owner | Conversation Instruction Manager | Holds the versioned conversation instructions covering format, tone, follow-up behaviour, restrictions and how the assistant declines when information is unavailable, which is what stops a confident answer being given to an unanswerable question. | B |
| 65 | Test Copilot Studio routing | Data Engineer | Per version, before publication | - Ask analytical questions and confirm routing to the Fabric Data Agent<br>- Ask non-analytical questions and confirm they are not routed there<br>- Record each routing outcome<br>- Fail the gate on incorrect routing | Routing Test Suite | Tests that analytical questions reach the Fabric Data Agent and that non-analytical ones do not, recording each outcome, since a question answered by the wrong source is a wrong answer even when it sounds reasonable. | A |
| 66 | Test multi-turn conversation | Data Engineer | Per version, before publication | - Run follow-up sequences such as show only Singapore, compare with last year, which customer caused the change<br>- Confirm context is retained across turns<br>- Record the transcript as evidence | Multi-Turn Test Suite | Runs realistic follow-up sequences and confirms context is retained across turns, keeping the transcripts as publication evidence, because single-question testing consistently overstates conversational quality. | A |
| 67 | Test authorization | Tenant Administrator | Per version, before publication | - Test with ordinary end-user accounts, not administrators<br>- Confirm each user sees only their permitted data<br>- Record evidence per role<br>- Block publication on any breach | End-User Authorization Test | Requires testing with ordinary end-user accounts rather than administrators, which is the only way the real production permission path is exercised, and blocks publication until every role's evidence is recorded. | A |
| 68 | Publish Copilot Studio agent | Lead Data Engineer | After routing, multi-turn and authorization tests pass | - Confirm all three test suites pass<br>- Publish the conversational application<br>- Record the published version and evidence | Conversational App Publication Gate | Publishes the conversational application only once routing, multi-turn and authorization evidence are all present, and records the published version so what is live is never in doubt. | B |
| 69 | Add Teams channel | Lead Data Engineer | Per application, when Teams is a consumption channel | - Enable the Microsoft Teams channel<br>- Confirm availability for the intended audience<br>- Record the channel and its audience | Channel Manager — Teams | Records and verifies the Teams channel configuration and the audience it serves, so consumption through Teams is a governed decision with a known audience. | B |
| 70 | Add Web/custom channel if needed | Lead Data Engineer | Per application, when portal access is needed | - Configure the appropriate supported channel for the portal or application<br>- Confirm authentication behaviour on that channel<br>- Record the channel and its audience | Channel Manager — Web | Records and verifies any additional supported channel, including its authentication behaviour, which frequently differs from Teams and is where permission assumptions break. | B |
| 71 | Share the conversational app | Tenant Administrator | Per audience, after publication | - Select the Entra users and security groups<br>- Grant access and make the agent available<br>- Confirm the users can reach it on their channel<br>- Record every grant with its approver | App Sharing Manager | Grants and records access to the conversational application for named Entra users and security groups, and confirms reachability on the intended channel rather than assuming it. | B |

## Cluster 13 — Monitoring and Observability

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 72 | Monitor Fabric capacity | Platform Administrator | Scheduled daily; alerted on threshold | - Collect capacity metrics for Data Factory, Lakehouse, semantic model and Data Agent<br>- Attribute consumption to project and stage<br>- Alert on throttling risk and sustained high utilisation<br>- Report trend against headroom | Capacity Monitor | Collects Fabric capacity consumption and attributes it to projects and stages, alerting before throttling affects the conversational application, which is the failure mode users notice first and understand least. | A |
| 73 | Monitor pipeline operations | Data Engineer | Scheduled per run; alerted on failure | - Collect pipeline run history, duration, volumes and refresh outcomes<br>- Alert on failure and on unusual duration or volume<br>- Show the freshness of every Gold table<br>- Link a failure to the affected downstream model and agent | Pipeline Monitor | Tracks ingestion outcomes, durations, volumes and refresh history, and links any failure to the downstream model and agent it affects, so the operational question of whether today's answers are trustworthy has a visible answer. | A |
| 74 | Monitor conversational accuracy | Business User with Lead Data Engineer | Scheduled weekly; event-driven on user feedback | - Review failed and low-confidence questions<br>- Review misinterpretations and the terminology users actually used<br>- Convert findings into synonym, instruction, measure or verified-answer changes<br>- Track accuracy trend against the ground-truth bank | Conversation Quality Review | Reviews failed questions, misinterpretations and real user terminology, and converts each finding into a concrete change to the semantic layer or the agent instructions, closing the loop that keeps accuracy improving after go-live. | C |

## Cluster 14 — Lifecycle, DevOps and Governance

| Step | Activities | Who | When | How | Feature | Feature Description | Tier |
|---|---|---|---|---|---|---|---|
| 75 | Implement Git/version control | Lead Data Engineer | Per workspace, at setup | - Connect the workspace to a Git repository where Fabric supports it<br>- Record which artifact types are and are not covered<br>- Export and version the configuration Fabric does not cover, including agent instructions and SemantIQ metadata<br>- Confirm sync status | Version Control Integration | Connects supported Fabric artifacts to Git and, crucially, records what Git does not cover and versions that configuration inside SemantIQ instead, so the solution has one complete recoverable definition rather than a partial one. | A |
| 76 | Create deployment pipelines | Lead Data Engineer | Per environment set, at setup | - Create the Fabric deployment pipeline across DEV, TEST and PROD<br>- Define the promotion rules and required approvals<br>- Configure environment-specific parameters<br>- Confirm production is not modified directly | Deployment Pipeline Manager | Sets up the DEV to TEST to PROD promotion path with its approvals and environment parameters, and detects direct production modification, which is the practice this step exists to prevent. | A |
| 77 | Apply governance | Tenant Administrator | Per project; reviewed quarterly | - Review workspace roles against least privilege<br>- Apply sensitivity labels<br>- Capture lineage from source to agent<br>- Record the applicable organisational policies and their owners | Governance Control Centre | Consolidates workspace roles, sensitivity labels, end-to-end lineage and applicable policies into one reviewable view, replacing the scattered evidence gathering that governance review otherwise requires. | B |
| 78 | Document business definitions | Business User with Lead Data Engineer | Per definition; reviewed on change | - Maintain definitions of KPIs, measures, entities, owners and source systems<br>- Link each definition to the model object that implements it<br>- Require review on change<br>- Publish the glossary to business users | Business Glossary | Maintains KPI, measure and entity definitions with owners and source systems, each linked to the model object implementing it, so a conversational answer can always be traced to an agreed definition. Published read-only to business users. | C |
| 79 | Establish an AI change process | Tenant Administrator | Event-driven, on any change to tables, measures, relationships, sources or definitions | - Detect the change and assess its impact on the model and agent<br>- Require re-testing against the ground-truth bank<br>- Require approval before promotion<br>- Record the change, its evidence and its approver | AI Change Control | Detects changes to tables, measures, relationships, sources or business definitions, requires the ground-truth bank to be re-run, and blocks promotion until the evidence and approval are recorded. This is the control that stops accuracy decaying silently after go-live. | C |
| 80 | Go live | Tenant Administrator | Once per release, at the end of the programme | - Confirm data accuracy, security, performance, user access and AI response quality are all approved<br>- Collect the evidence from every gate<br>- Obtain the named sign-offs<br>- Release the production conversational application | Go-Live Readiness Gate | Assembles the evidence from every earlier gate into one readiness view, requires named sign-off on data accuracy, security, performance, access and AI response quality, and only then releases the production conversational application. | C |

---

## Summary

### Major modules

| Cluster | Module | Steps | Predominant tier |
|---|---|---|---|
| 1 | Readiness | 1–4 | B |
| 2 | Environments | 5–8 | A |
| 3 | Sources | 9–13 | Mixed A/B/C |
| 4 | Data Platform | 14, 15, 20, 25, 26 | A |
| 5 | Ingestion | 16–19 | A |
| 6 | Transformation | 21–24 | A |
| 7 | Modelling (dimensional) | 27–28 | A |
| 8 | Semantics | 29–39 | Mixed A/B |
| 9 | Agents | 40–46 | A |
| 10 | Evaluation | 47–51 | Mixed A/C |
| 11 | Publication and Access | 52–54 | A |
| 12 | Conversation | 55–71 | Predominantly B |
| 13 | Observability | 72–74 | Mixed A/C |
| 14 | Lifecycle and Governance | 75–80 | Mixed A/B/C |

### Cross-cutting concerns

- **Verification over assertion.** Every Tier B step is completed by a read-back probe or a written exception, never by a user ticking a box.
- **Evidence accumulation.** Steps 24, 47–51, 65–67 and 72–74 all produce evidence that step 80 consumes. Evidence is a first-class record from the beginning, not assembled at the end.
- **Audit trail.** Every Microsoft mutation records actor, tenant, target, correlation ID and outcome.
- **Idempotency.** Every provisioning step is safely repeatable; a resumed run never duplicates a Fabric artifact.
- **Least privilege.** Steps 7, 35, 36, 53, 54, 71 and 77 all narrow access; none widens it by default.
- **Capacity awareness.** Steps 1, 6, 18 and 72 form one continuous thread; scheduling decisions are checked against the capacity the agent depends on.

### Key dependencies

- Steps 3, 4 and 37 gate step 40. A Data Agent created before AI preparation is complete will behave unpredictably.
- Step 24 gates step 25, and step 25 gates step 29. The semantic model reads only from validated Gold.
- Steps 47–51 gate step 52; steps 65–67 gate step 68. Publication is evidence-driven in both layers.
- Step 49 is a prerequisite for step 79. Change control is impossible without a ground-truth baseline.
- Steps 11 and 12 gate step 13, which gates step 16. Connectivity precedes ingestion.

### Sequence note

Rows are grouped into the 14 delivery clusters used throughout documents 02 to 05, because
the clusters are what become EPICs and the delivery phases in document 00. **Original step
numbers are preserved unchanged**, and Appendix A lists all 80 steps in their original
sequence so traceability to the source procedure is exact.

---

## Appendix A — All 80 steps in original sequence

| Step | Feature | Cluster | Tier |
|---|---|---|---|
| 1 | Capacity Readiness Check | 1 Readiness | A |
| 2 | Administrator Role Register | 1 Readiness | B |
| 3 | Tenant AI Settings Verifier | 1 Readiness | B |
| 4 | Cross-Geo AI Requirement Advisor | 1 Readiness | B |
| 5 | Workspace Provisioner | 2 Environments | A |
| 6 | Capacity Assignment | 2 Environments | A |
| 7 | Workspace Role Manager | 2 Environments | A |
| 8 | Environment Set Manager | 2 Environments | A |
| 9 | Source System Register | 3 Sources | C |
| 10 | Ingestion Method Advisor | 3 Sources | C |
| 11 | Gateway Readiness Tracker | 3 Sources | B |
| 12 | Private Connectivity Planner | 3 Sources | B |
| 13 | Connection Manager | 3 Sources | A |
| 14 | Lakehouse Provisioner | 4 Data Platform | A |
| 15 | Medallion Layer Designer — Bronze | 4 Data Platform | A |
| 16 | Pipeline Builder | 5 Ingestion | A |
| 17 | Incremental Load Configurator | 5 Ingestion | A |
| 18 | Ingestion Scheduler | 5 Ingestion | A |
| 19 | Pipeline Resilience Configurator | 5 Ingestion | A |
| 20 | Medallion Layer Designer — Silver | 4 Data Platform | A |
| 21 | Entity Standardisation Workbench | 6 Transformation | A |
| 22 | Business Key Builder | 6 Transformation | A |
| 23 | Transformation Studio | 6 Transformation | A |
| 24 | Data Quality Gate | 6 Transformation | A |
| 25 | Medallion Layer Designer — Gold | 4 Data Platform | A |
| 26 | Warehouse Provisioner | 4 Data Platform | A |
| 27 | Fact Table Designer | 7 Modelling | A |
| 28 | Dimension Table Designer | 7 Modelling | A |
| 29 | Semantic Model Provisioner | 8 Semantics | A |
| 30 | Storage Mode Advisor | 8 Semantics | A |
| 31 | Relationship Designer | 8 Semantics | A |
| 32 | Measure Library | 8 Semantics | A |
| 33 | Business Naming Assistant | 8 Semantics | A |
| 34 | Semantic Enrichment Workspace | 8 Semantics | A |
| 35 | Row-Level Security Manager | 8 Semantics | A |
| 36 | Column-Level Security Manager | 8 Semantics | A |
| 37 | AI Surface Curator | 8 Semantics | B |
| 38 | AI Instruction Manager | 8 Semantics | B |
| 39 | Verified Answer Register | 8 Semantics | B |
| 40 | Data Agent Builder | 9 Agents | A |
| 41 | Agent Source Binder — Semantic Model | 9 Agents | A |
| 42 | Agent Source Binder — Additional Sources | 9 Agents | A |
| 43 | Agent Scope Selector | 9 Agents | A |
| 44 | Agent Instruction Editor | 9 Agents | A |
| 45 | Source Description Editor | 9 Agents | A |
| 46 | Example Question Library | 9 Agents | A |
| 47 | Agent Test Console — Baseline | 10 Evaluation | A |
| 48 | Agent Test Console — Advanced | 10 Evaluation | A |
| 49 | Ground Truth Question Bank | 10 Evaluation | C |
| 50 | Root Cause Remediation Tracker | 10 Evaluation | C |
| 51 | Security Validation Suite | 10 Evaluation | A |
| 52 | Agent Publication Gate | 11 Publication | A |
| 53 | Access Grant Manager | 11 Publication | A |
| 54 | Model Permission Manager | 11 Publication | A |
| 55 | Copilot Environment Register | 12 Conversation | B |
| 56 | Conversational Agent Register | 12 Conversation | B |
| 57 | Orchestration Configuration Guide | 12 Conversation | B |
| 58 | Fabric Connection Guide | 12 Conversation | B |
| 59 | Agent Binding Verifier | 12 Conversation | B |
| 60 | Routing Description Editor | 12 Conversation | B |
| 61 | Authentication Model Decision | 12 Conversation | C |
| 62 | Knowledge Source Register | 12 Conversation | B |
| 63 | Action Register | 12 Conversation | B |
| 64 | Conversation Instruction Manager | 12 Conversation | B |
| 65 | Routing Test Suite | 12 Conversation | A |
| 66 | Multi-Turn Test Suite | 12 Conversation | A |
| 67 | End-User Authorization Test | 12 Conversation | A |
| 68 | Conversational App Publication Gate | 12 Conversation | B |
| 69 | Channel Manager — Teams | 12 Conversation | B |
| 70 | Channel Manager — Web | 12 Conversation | B |
| 71 | App Sharing Manager | 12 Conversation | B |
| 72 | Capacity Monitor | 13 Observability | A |
| 73 | Pipeline Monitor | 13 Observability | A |
| 74 | Conversation Quality Review | 13 Observability | C |
| 75 | Version Control Integration | 14 Lifecycle | A |
| 76 | Deployment Pipeline Manager | 14 Lifecycle | A |
| 77 | Governance Control Centre | 14 Lifecycle | B |
| 78 | Business Glossary | 14 Lifecycle | C |
| 79 | AI Change Control | 14 Lifecycle | C |
| 80 | Go-Live Readiness Gate | 14 Lifecycle | C |

---

*01-Requirement-Scoping*
