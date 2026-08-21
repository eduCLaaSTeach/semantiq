# CLaaS2SaaS SemantIQ — User Interface Specification

**Document ID:** 04-UI-Specification
**Derived from:** 02-Functional-Specification, 03-Workflow-Process-Specification
**Design system:** Approved CLaaS2SaaS design system, applied unmodified
**Status:** Draft for review

---

## 1. The standard is not a per-screen decision

The look and feel is already approved. This document records **which approved pattern each
screen uses**. No screen defines a palette, a font, a radius, an elevation or a layout of
its own, and none is proposed here.

Every archetype below is selected from the closed list. Where a requirement implied a
non-compliant shape, the conflict is named in section 7 and the compliant shape is
specified instead.

## 2. Application values obtained from the technical lead

These are the only values that vary per application. They must be confirmed, not invented.

| Value | Status |
|---|---|
| Application name (top bar) | **`CLaaS2SaaS SemantIQ`** — confirmed by the user in this engagement |
| Browser title-bar name | **To confirm.** Recommended: `SemantIQ` |
| Sidebar navigation tree | Specified in section 4, **for confirmation** |
| Brand asset pack and its installed location | **Outstanding — please supply.** The approved per-theme wide wordmark, the C2S short mark and the favicons are required. Until they arrive, mockups carry a clearly marked placeholder and the gap is flagged in delivery |
| Role labels | Specified in section 5, **for confirmation** |
| Entity names | Project, Blueprint, Step, Environment, Source, Connection, Lakehouse, Pipeline, Transformation, Semantic Model, Measure, Data Agent, Conversational App, Question, Definition |
| UI technology stack | React 19 with TypeScript, server-rendered page props, mobile-first CSS — confirmed by the user |

**One screen cannot be finalised without the asset pack:** the sidebar brand block and the
authentication screen both display identity assets directly.

## 3. Common standards, cited not restated

Every row in sections 6 cites these. Only screen-specific additions are written per row.

### Standard Branding (cited as "Standard branding")

The approved CLaaS2SaaS system applied as-is: Midnight Blue `#193E6B` primary accent; Green
Gold `#B3A125` for the active-nav treatment only; Cadmium Violet `#7F3F98` secondary
buttons; Jelly Bean Blue `#448E9D` for non-interactive and informational accents; Sunray
`#E9AC53` warning with dark ink; Avocado Green `#5F8025` success; Violet-Red `#991547`
danger. Status colour always goes through the approved badge pairs on a surface, never the
raw brand hex. Montserrat headings, Source Sans 3 body at 13px and line-height 1.5, sizes
only from the approved scale. Spacing on the 4px scale; cards 12px radius and flat at rest;
controls 8px. Both themes fully defined, with the System / Dark / Light segmented control in
the profile menu's fixed Appearance section, System by default and persisted. The approved
per-theme wordmark sits in the expanded sidebar brand block, the C2S short mark in the
collapsed rail, both swapping with the effective theme, used exactly as supplied.

### Standard Shell (cited as "Standard shell")

Full-height left sidebar owning the top-left corner at 240px expanded and 56px collapsed;
slim 52px top bar over the main column only; visible dividers between all three regions
with the rail brand block matched to the top-bar height so their bottom dividers form one
continuous line; the brand block pinned as the only collapse control with only the nav list
scrolling; the top bar carrying the application name on the left and notifications, then
theme switcher, then profile menu on the right — no navigation tabs, no global search, no
action buttons.

### Standard UI States (cited as "Standard states")

Every data-driven region ships four states. Loading is a **skeleton** mirroring the incoming
shape, with page chrome, toolbars and table column headers kept live; the only permitted
spinner is a bounded wait inside a single control. Empty has two flavours chosen by cause:
no-data-yet gives icon, title, one line and one primary Add; no-results echoes the query and
offers Clear filters, never Add. Error is the third sibling, not an empty state: a human
message, a danger icon and Retry, with the toolbar and search left live. Empty cells render
a muted em dash. Region height is reserved so nothing shifts when data lands.

### Standard Accessibility (cited as "Standard accessibility")

WCAG AA. Full keyboard operation with an always-visible 2px focus ring at 2px offset on
every interactive element. Never colour alone for status, validation, sort direction, active
tab or filter state. Real buttons and links, semantic tables with scoped column headers,
breadcrumbs as an ordered list in a labelled navigation landmark, labelled field groups.
Sort state announced on sortable headers; the active nav item and active tab marked as
current; accordion headers announcing expanded state; polite live regions on result counts
and loading; assertive for errors; a busy state during async work; accessible names on every
icon-only control and decorative icons hidden. Forms carry visible associated labels —
placeholders are not labels — with required state marked accessibly, errors below the field
in a reserved slot, validation on blur and re-validation on input once errored, and submit
staying enabled and focusing the first invalid field. Modals are labelled and described,
focus-trapped, returning focus to the trigger, with the background inert and scroll-locked,
`Esc` and backdrop both closing as Cancel, and initial focus on the safe action in a
destructive dialog. Reduced motion respected; touch targets at least 44 by 44px. User-facing
strings externalised, dates and currency formatted per locale, layouts tolerant of longer
translated labels. Contrast uses the approved theme-aware pairs, which are AA-verified.

### Standard Device Support (cited as "Standard device support")

Mobile-first: base styles target small screens, layered up with width breakpoints, with no
JavaScript-driven responsive logic and no separate mobile codebase. Small screens stack to a
single column and multi-column form rows collapse to one. The sidebar keeps the same
collapsible rail at every breakpoint so the logo stays visible. Tables scroll horizontally
inside their container with the checkbox and actions columns pinned, never transformed into
stacked cards. Pagination collapses to Prev / Next plus "Page X of Y" with the page-size
selector hidden. Toasts reflow full-width across the top; modals become near-full-width,
dock to the bottom and stack their actions full-width. Touch targets at least 44px, with
form controls raised to 44px on touch.

## 4. Navigation tree — for confirmation

Four fixed clusters in their fixed order. An accordion group is a level; a cluster heading
and a leaf are not. Maximum depth reached is three, at Data Platform.

```
WORKSPACE
  Dashboard                                  /dashboard
  Projects                                   /projects
  Readiness                                  /readiness
  Environments                               /environments
  Sources                          (L1 group)
    Source Register                          /sources
    Connections                              /sources/connections
    Connectivity & Gateways                  /sources/connectivity
  Data Platform                    (L1 group)
    Lakehouse                                /data-platform/lakehouse
    Medallion Layers                         /data-platform/layers
    Warehouse                                /data-platform/warehouse
    Ingestion                      (L2 group)
      Pipelines                              /ingestion/pipelines
      Schedules                              /ingestion/schedules
      Run History                            /ingestion/runs
    Transformation                 (L2 group)
      Transformations                        /transformation
      Entities & Keys                        /transformation/entities
      Quality Rules                          /transformation/quality
  Semantic Layer                   (L1 group)
    Semantic Models                          /semantic/models
    Relationships                            /semantic/relationships
    Measures                                 /semantic/measures
    Enrichment                               /semantic/enrichment
    Security                       (L2 group)
      Row-Level Security                     /semantic/security/rls
      Column-Level Security                  /semantic/security/cls
    AI Preparation                 (L2 group)
      AI Surface                             /semantic/ai/surface
      AI Instructions                        /semantic/ai/instructions
      Verified Answers                       /semantic/ai/verified-answers
  Data Agents                      (L1 group)
    Agents                                   /agents
    Test Console                             /agents/test-console
    Ground Truth                             /agents/ground-truth
  Conversational Apps              (L1 group)
    Applications                             /conversational-apps
    Validation                               /conversational-apps/validation
    Channels & Sharing                       /conversational-apps/channels
  Monitoring                       (L1 group)
    Capacity                                 /monitoring/capacity
    Pipeline Operations                      /monitoring/pipelines
    Conversation Quality                     /monitoring/conversation-quality
  Business Glossary                          /glossary
  Assistant                                  /assistant

COMPLIANCE
  Audit Trail                                /compliance/audit
  Exceptions                                 /compliance/exceptions
  Change Control                             /compliance/change-control
  Governance                       (L1 group)
    Access Review                            /compliance/governance/access
    Sensitivity Labels                       /compliance/governance/labels
    Lineage                                  /compliance/governance/lineage
  Go-Live Readiness                          /compliance/go-live

APPLICATION ADMINISTRATION
  Users                                      /admin/users
  Roles                                      /admin/roles
  Notifications                              /admin/notifications
  Recycle Bin                                /admin/recycle-bin
  Application Settings                       /admin/settings

SYSTEM ADMINISTRATION
  Tenant Connections                         /system/tenants
  Entra Application                          /system/entra
  Fabric Capacities                          /system/capacities
  Integration Settings                       /system/integrations
  Capability Probe                           /system/capabilities
  Step Catalogue                             /system/steps
```

**Depth confirmation:** the deepest path is `Data Platform` (L1) → `Ingestion` (L2) →
`Pipelines` (leaf). No group is named the same as its cluster. No fourth accordion level
exists. All standing navigation is in the sidebar; the top bar carries none.

**Project workspace tabs.** A project is a record with facets, so `/projects/{project}`
is a routable detail page whose facets use the **in-canvas tab strip** — `Overview |
Blueprint | Evidence | Team | Activity`. This is depth-overflow handling and record facets,
not a fifth navigation level, and the strip is terminal.

## 5. Role labels — for confirmation

| Tier | Label | Persona | Clusters reached |
|---|---|---|---|
| system admin | Platform Administrator | Administrator | Workspace, Compliance, Application Administration, System Administration |
| admin | Tenant Administrator | Administrator | Workspace, Compliance, Application Administration |
| team / collaborator | Lead Data Engineer | Data Engineer | Workspace, Compliance |
| self / contributor | Data Engineer | Data Engineer | Workspace |
| self-view / read-only | Business User | Business User | Workspace, narrowed to Assistant, Business Glossary and read-only project status |

Tiers are cumulative. Default cluster grants are narrowed for Business User, never widened.
Unauthorized items are **absent**, not dimmed. Navigation destinations not yet built stay
visible, disabled, with a "Soon" indicator; unbuilt non-navigation controls are omitted.

---
## 6. Screen specifications

Coverage approach: the screens below carry a distinctive layout, gate, state or access rule
and are specified in full. Screens that conform exactly to an archetype's standard contract
with no screen-specific addition are listed in section 6.9, which is a complete inventory
rather than an omission — a list/index or form that adds nothing beyond its archetype is
fully specified by naming the archetype.

### 6.1 Pre-authentication and onboarding

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Sign In | Auth | **No shell.** Route `/login`; no cluster, no breadcrumb | Standalone centered card: brand mark → application name → "Sign in with Microsoft" as the single primary action → flash and error display → trust footer. No local username and password field, since authentication is delegated entirely to Microsoft Entra ID | Standard branding, applied on the auth surface without the shell. The approved per-theme wordmark is the brand mark. **Blocked pending the brand asset pack** | Success: redirect to Entra ID. Loading: the sign-in control shows its own bounded in-control wait, the only permitted spinner. Error: a persistent human message for consent declined, tenant not permitted, or the callback failing, each distinguished. No empty state — the screen has no data region | Unauthenticated. No role applies. The screen reveals nothing about whether a tenant or account exists | Standard accessibility. The single action is a real button with an accessible name; the error region is an assertive live region; the theme control is not present here, so the effective theme follows the system preference | Standard device support. The card is near-full-width on small screens with the action at the 48px large size |
| Tenant Onboarding | Step-by-step form (wizard) | `Workspace` cluster, reached on first sign-in rather than from the nav; route `/onboarding`; breadcrumb suppressed | Standard shell. Step indicator → one step at a time: **1** Confirm tenant → **2** Grant consent → **3** Connectivity probe → **4** Capability probe → **5** Review. Footer carries exactly **Back** and **Continue**; Continue becomes **Finish** on step 5 | Standard branding | Standard states. The two probe steps show a skeleton per endpoint row while probing, then a per-endpoint result. A blocked endpoint renders as a warning row with its reason, not as an error state for the whole page — the page succeeded, the endpoint did not | Platform Administrator and Tenant Administrator only. Absent for every other tier. The server-side handler gates identically to the sidebar | Standard accessibility. The step indicator marks the current step as current; each probe result region carries a polite live region; a blocked endpoint is signalled by icon and text, not colour | Standard device support. The step indicator collapses to "Step 3 of 5" with the current step title on small screens |

### 6.2 Project and blueprint execution

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Dashboard | Dashboard | `Workspace` → `Dashboard` leaf; route `/dashboard`; no breadcrumb | Standard shell. Canvas: page header → metric-tile grid (Active projects · Steps awaiting input · Failed operations · Agent accuracy · Capacity utilisation) → "Needs attention" list of blocked and failed steps across projects → recent activity | Standard branding. Metric tiles use approved badge pairs for status; capacity utilisation uses the warning pair on approach to threshold, never Green Gold | Standard states. Each tile independently renders a muted placeholder on its own data failure rather than a zero, so one failed metric never makes the page look healthy or broken as a whole | Every tier. Query scope narrows rows by tier: Data Engineer sees own projects, Lead sees their team's, administrators see all. Business User sees a narrowed variant with agent availability and data freshness only | Standard accessibility. Each tile is a labelled region; the attention list is a semantic list; trend direction pairs an icon with text | Standard device support. Tiles reflow to a single column; the attention list keeps its full-width rows |
| Projects | List / index | `Workspace` → `Projects` leaf; route `/projects`; no breadcrumb | Standard shell. Page header with **New project** primary CTA → search and filter bar → card-wrapped table (Project, Stage, Progress, Blocked steps, Owner, Updated, Actions) → pagination footer. **Default sort: Updated, newest first.** Facets: Stage, Owner, Status, Updated range. Sort, filters and page live in the URL query | Standard branding. Progress renders as a labelled value with a bar, never a bar alone; stage as a neutral pill | Standard states. No-data: "No projects yet" with primary **New project**. No-results: the query echoed with **Clear filters**. Error: "Couldn't load projects" with **Retry**, toolbar left live | Visible to every tier except Business User. Query scope narrows by tier. Row actions gated by per-record policy, not merely hidden. Delete routes to the recycle bin; permanent delete is Tenant Administrator only | Standard accessibility. Sortable headers are real buttons announcing sort state; the result count carries a polite live region; progress values are read as text | Standard device support. Table scrolls horizontally with the actions column pinned |
| New Project | Form (create / edit) | `Workspace` → `Projects`; route `/projects/create`; breadcrumb `Projects › New project` | Standard shell. Back link → section cards: **Project** (name, sponsor, business objective) → **Blueprint** (template selection, or the full 80-step blueprint) → **Environment intent** (pilot or enterprise) → optional error-summary card → footer with ghost **Cancel** and primary **Create project**. **Page-hosted on its own route, never a modal** | Standard branding. Exactly one solid button in the footer group; Cancel is ghost | Standard states. Loading applies to the blueprint-template region only, as a skeleton. Error on submit renders in the error-summary card and inline below each field | Data Engineer and above. Business User absent | Standard accessibility. Every field has a visible associated label; errors sit below the field in a reserved slot; submit stays enabled and focuses the first invalid field | Standard device support. Single column throughout; the footer actions stack full-width on small screens |
| Project Overview | Detail / show | `Workspace` → `Projects`; route `/projects/{project}`; breadcrumb `Projects › {project}`. Facets via the **in-canvas tab strip**: `Overview \| Blueprint \| Evidence \| Team \| Activity` | Standard shell. Back link → header card (project name + stage badge + meta + right-aligned actions) → 2/3 + 1/3 body grid: main column carries stage progress and the current stage's steps; side column carries owner, capacity, environments and open exceptions | Standard branding. Stage badge uses the approved badge pairs; the exceptions panel uses the warning pair, never danger, since an approved exception is not an error | Standard states. Each panel loads independently with its own skeleton, so a slow evidence panel never blocks the header | Data Engineer and above; Business User sees a read-only narrowed variant without the Team and Evidence tabs. Per-record policy gates the header actions | Standard accessibility. The tab strip is route-backed with the active tab marked current and distinguished by fill, not colour alone; the strip is terminal | Standard device support. The 2/3 + 1/3 grid stacks to one column with the side column below; the tab strip scrolls horizontally |
| Blueprint Runner | Builder (hub-and-spoke) | `Workspace` → `Projects`; route `/projects/{project}/blueprint`; breadcrumb `Projects › {project} › Blueprint` | Standard shell. Central hub: the 14 stages as rows, each showing its step count by state and its lifecycle state inline. Each row's actions spoke to single-purpose sub-pages — the stage's step list, and each step's own detail page. One confirm-guarded advance control per stage. A validation gate blocks advance while a required step is unverified | Standard branding. Step state uses the approved badge pairs — verified success, awaiting warning, failed danger, blocked neutral, exception-approved violet — each pairing colour with its word | Standard states. Stage rows skeleton on load. A stage whose state cannot be read renders an error row with **Retry** rather than appearing complete. Empty does not occur: a blueprint always has 14 stages | Data Engineer and above. Advancing a stage is gated by per-record policy on the project, and by tier for stages containing administrator-only steps | Standard accessibility. Stage rows are semantic list items with real links; the advance control names its stage in its accessible name; the validation gate's blocking reason is announced in a polite live region | Standard device support. Stage rows become full-width stacked cards on small screens with their state counts wrapping |
| Step Detail | Detail / show | `Workspace` → `Projects`; route `/projects/{project}/steps/{step}`; breadcrumb `Projects › {project} › Blueprint › {step}` | Standard shell. Back link → header card (step number and name + state badge + automation tier badge + right-aligned actions) → 2/3 + 1/3 grid. Main column varies by tier: **Tier A** shows the operation list with each operation's state and its Microsoft response summary; **Tier B** shows the required action, the deep link, the verification criteria and a **Verify now** control; **Tier C** shows the decision or artifact capture and its approval state. Side column carries dependencies, evidence and audit links | Standard branding. The tier badge uses the info pair for A, the warning pair for B and the violet pair for C, each carrying the tier letter and word so the distinction never rests on colour | Standard states. Operation rows skeleton individually. A running long-running operation shows a bounded in-control wait on its own row, not a page spinner. Verification failure renders as an error region naming the exact difference, with the action and deep link left live so it can be retried | Data Engineer and above. A step whose tier requires an administrator shows its action disabled with the named required role, since the destination is legitimate but the actor is wrong. Business User absent | Standard accessibility. The verification result is an assertive live region; the deep link states that it opens a Microsoft service; the operation list announces state changes politely | Standard device support. Single column stack; the deep link and Verify control are 48px on touch |
| Step Verification Result | Status / result | `Workspace` → `Projects`; route `/projects/{project}/steps/{step}/verification/{run}`; breadcrumb inherits Step Detail | Standard shell. Single full-width card tinted by outcome: circular icon medallion → bold headline (Verified / Not yet verified / Cannot verify) → muted explanation naming the exact expected and actual state → the evidence → one forward action | Standard branding. Card tint uses the approved success, warning or danger surface treatment for its outcome; the medallion icon comes from the one icon registry | Standard states. This screen is itself an outcome, so it has no loading or empty state of its own; it is reached only once a result exists | Same as Step Detail | Standard accessibility. The headline is the page's first heading; the outcome is stated in words, never by tint alone | Standard device support. The card is full-width with the action at 48px |

### 6.3 Readiness, environments and sources

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Readiness | Dashboard | `Workspace` → `Readiness` leaf; route `/readiness`; no breadcrumb | Standard shell. Page header → metric-tile grid (Capacity eligible · Administrator recorded · AI settings verified · Cross-geo resolved) → a checklist card per readiness item showing its state, its required actor and its verification evidence → quick action to re-run the assessment | Standard branding. An unmet item uses the warning pair, a failed verification the danger pair, and a met item the success pair, each with its word | Standard states. Each tile placeholders independently on its own failure. Re-running shows skeletons on the affected rows only | Tenant Administrator and Platform Administrator author; Lead Data Engineer reads. Data Engineer sees state without the ability to re-run. Business User absent | Standard accessibility. The checklist is a semantic list; each item's required actor is text, not an icon alone; re-run reports completion in a polite live region | Standard device support. Tiles reflow to one column; checklist rows stack |
| Environment Provisioning | Step-by-step form (wizard) | `Workspace` → `Environments` leaf; route `/environments/provision`; breadcrumb `Environments › Provision` | Standard shell. Step indicator → **1** Create or attach → **2** Name and convention → **3** Capacity assignment → **4** Administrators → **5** Environment set (pilot or enterprise) → **6** Review. Footer carries exactly **Back** and **Continue**; Continue validates the step, **saves a resumable server-side draft**, then advances, and becomes **Provision** on the final step | Standard branding | Standard states. The attach step's workspace list has all four states; capacity selection skeletons while eligible capacities load; an ineligible capacity is shown disabled with its reason rather than hidden, since the reason is the useful information | Lead Data Engineer and above. Capacity assignment additionally requires Tenant Administrator, so a Lead sees the step with its control disabled and the required role named | Standard accessibility. The step indicator marks the current step; a disabled option's reason is part of its accessible description, not a tooltip alone | Standard device support. Step indicator collapses to "Step 3 of 6"; the footer actions stack full-width |
| Connection Configuration | Settings / config | `Workspace` → `Sources` (L1) → `Connections`; route `/sources/connections/{connection}`; breadcrumb `Sources › Connections › {connection}` | Standard shell. Back link → section cards: **Source** (read-only reference) → **Connection** (type, endpoint, gateway selection) → **Credential** (handled by Microsoft; SemantIQ stores no secret) → **Test result**. Footer carries **Reset** and **Test Configuration** only. **`Save` appears only after a live test passes on the current values**, and editing any tested field withdraws it again | Standard branding. Any stored reference to a secret shows only a masked "encrypted at rest" badge. Test Configuration is the single solid button until a passing test promotes Save into the group | Standard states. Test in flight uses a bounded in-control wait on the Test button with a stable width. A failed test renders the platform error verbatim in an error region, keeping every field live. No empty state | Data Engineer and above. The per-record policy gates editing; the query scope limits which connections are listed | Standard accessibility. The test result is an assertive live region; the withdrawal of Save on edit is announced politely so a keyboard user is not left searching for it | Standard device support. Single column; Test Configuration is 48px on touch |
| Connectivity & Gateways | List / index | `Workspace` → `Sources` (L1) → `Connectivity & Gateways`; route `/sources/connectivity`; breadcrumb `Sources › Connectivity & Gateways` | Standard shell. Page header → search and filter bar → card-wrapped table (Requirement, Source, Type, Owner, Target date, State, Actions) → pagination when needed. **Default sort: Target date, earliest first**, since the overdue items are the ones that block ingestion. Facets: State, Type, Owner | Standard branding. An overdue target date uses the danger pair with the word "Overdue"; pending uses the warning pair | Standard states. No-data: "No connectivity requirements" with primary **Add requirement**. No-results echoes the query with **Clear filters**. Error offers **Retry** | Data Engineer and above; Platform Administrator owns the network rows. Query scope limits to the user's projects | Standard accessibility. Dates are rendered in the tenant locale with an accessible absolute value; overdue state pairs colour with the word | Standard device support. Table scrolls horizontally with actions pinned |

### 6.4 Semantic layer

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Relationships | Builder (hub-and-spoke) | `Workspace` → `Semantic Layer` (L1) → `Relationships`; route `/semantic/relationships`; breadcrumb `Semantic Layer › Relationships` | Standard shell. Central hub: a table of relationships (From, To, Cardinality, Filter direction, State, Actions), each row spoking to its own edit page. Above it, two validation cards: **Ambiguous paths** and **Unreachable dimensions**, each listing the specific problem. One confirm-guarded **Apply to model** control, gated by both validation cards being clear | Standard branding. An ambiguous path uses the danger pair, an unreachable dimension the warning pair, a validated relationship the success pair | Standard states. Validation cards skeleton while validation runs. A validation that cannot complete renders an error with **Retry** rather than an empty card, because an empty validation card reads as "no problems" | Data Engineer and above. Applying to the model is gated by per-record policy on the semantic model | Standard accessibility. Validation findings are a semantic list announced in a polite live region on completion; the Apply control names what it will change | Standard device support. The relationship table scrolls horizontally; validation cards stack |
| Enrichment Queue | List / index | `Workspace` → `Semantic Layer` (L1) → `Enrichment`; route `/semantic/enrichment`; breadcrumb `Semantic Layer › Enrichment` | Standard shell. Page header carrying the coverage figure for the AI surface → search and filter bar → card-wrapped table (Object, Type, In AI surface, Description, Synonyms, Reviewed, Actions) → pagination. **Default sort: in-AI-surface first, then objects with no description**, because that is the work that matters most. Facets: Type, In AI surface, Reviewed state, Has description | Standard branding. Coverage renders as a labelled percentage with a bar, never a bar alone. Reviewed state uses the success pair; unreviewed the neutral pair | Standard states. No-data: "No model objects loaded" with primary **Sync model**. No-results echoes the query with **Clear filters**. Error offers **Retry**. The coverage figure placeholders on its own failure rather than showing zero | Data Engineer and above author; **Business User reads and may propose synonyms**, which is the one place a read-only tier contributes — their proposal enters the review queue rather than the model | Standard accessibility. The coverage figure is in a polite live region; the sort default is declared in the table's accessible description | Standard device support. Table scrolls horizontally with the actions column pinned; the coverage figure moves above the toolbar |
| Object Enrichment | Form (create / edit) | `Workspace` → `Semantic Layer` (L1) → `Enrichment`; route `/semantic/enrichment/{object}`; breadcrumb `Semantic Layer › Enrichment › {object}` | Standard shell. Back link → section cards: **Object** (read-only structure and lineage) → **Description** (with the AI-drafted suggestion shown as a distinct, clearly labelled proposal carrying **Use draft** and **Dismiss**) → **Synonyms** (chips with add and remove, plus candidates extracted from failed questions each showing the question that produced it) → **Review** (reviewer and date) → footer with ghost **Cancel** and primary **Save**. **Page-hosted, never a modal** | Standard branding. An AI-drafted suggestion is visually marked as a proposal using the info pair and the word "Suggested", never presented as an existing value | Standard states. The suggestion region skeletons while drafting and renders an error with **Retry** on failure — the form stays fully usable without it, since a suggestion is never required to save | Data Engineer and above save. Business User's submission is recorded as a proposal and does not write to the model | Standard accessibility. Synonym chips are removable by keyboard with an accessible name each; the suggestion's proposal status is in its accessible name, not conveyed by styling alone | Standard device support. Single column; synonym chips wrap; footer actions stack full-width |
| AI Surface | Form (create / edit) | `Workspace` → `Semantic Layer` (L1) → `AI Preparation` (L2) → `AI Surface`; route `/semantic/ai/surface`; breadcrumb `Semantic Layer › AI Preparation › AI Surface` | Standard shell. Back link → **Intended surface** section card with a selectable tree of tables, columns and measures → **Breadth assessment** card stating the selected count against the recommended range → **Configured surface** card showing what was read back from Power BI and the difference against the intended surface → footer with ghost **Cancel** and primary **Save intended surface**. Applying in Power BI is a guided action with a deep link, followed by **Verify** | Standard branding. A difference between intended and configured uses the warning pair with the word "Differs" | Standard states. The configured-surface card skeletons while reading back; on failure it renders an error with **Retry**, and explicitly does not render as "no differences" | Lead Data Engineer and above. Data Engineer reads only | Standard accessibility. The tree is keyboard-navigable with expanded state announced; the difference list is a semantic list in a polite live region | Standard device support. The tree becomes full-width with 44px rows; cards stack |
| Verified Answers | List / index | `Workspace` → `Semantic Layer` (L1) → `AI Preparation` (L2) → `Verified Answers`; route `/semantic/ai/verified-answers`; breadcrumb `Semantic Layer › AI Preparation › Verified Answers` | Standard shell. Page header with **New verified answer** primary CTA → search and filter bar → card-wrapped table (Question, Measure, Approver, Approved, Review due, State, Actions) → pagination. **Default sort: Review due, earliest first.** Facets: State, Approver, Measure, Review due range | Standard branding. "Needs re-approval" uses the warning pair; an expired review the danger pair; approved the success pair | Standard states. No-data: "No verified answers yet" with primary **New verified answer**. No-results echoes the query. Error offers **Retry** | Lead Data Engineer and Business User author; approval requires the named approver recorded on the item. **Business User reads all and approves those assigned to them** — this is the one authoring surface where the business tier leads | Standard accessibility. Review-due dates carry an accessible absolute value; state pairs colour with its word | Standard device support. Table scrolls horizontally with actions pinned |

### 6.5 Agents and evaluation

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Agent Builder | Builder (hub-and-spoke) | `Workspace` → `Data Agents` (L1) → `Agents`; route `/agents/{agent}/build`; breadcrumb `Data Agents › {agent} › Build` | Standard shell. Central hub with a row per configuration part — Sources, Scope, Instructions, Source descriptions, Examples — each showing its completeness inline and spoking to its own sub-page. A prerequisite card at the top lists the readiness items the agent depends on. One confirm-guarded **Submit for evaluation** control, gated by the validation card | Standard branding. Source-count usage against the five-source limit renders as a labelled value with its limit stated in text | Standard states. The prerequisite card skeletons while checking, and on failure renders an error with **Retry** rather than appearing satisfied — a silently-passed prerequisite is the worst failure mode on this screen | Lead Data Engineer and above. Data Engineer reads. Business User absent | Standard accessibility. Each hub row's completeness is text plus icon; the advance control's blocking reason is announced politely | Standard device support. Hub rows become stacked full-width cards |
| Test Console | Detail / show | `Workspace` → `Data Agents` (L1) → `Test Console`; route `/agents/test-console`; breadcrumb `Data Agents › Test Console`. **See section 7, conflict 1** | Standard shell. Back link → header card (agent under test + version badge + right-aligned actions) → 2/3 + 1/3 grid. Main column: a **page-hosted form region** carrying the question, the expected value and its tolerance, with a primary **Run test**, above a results region showing the answer, the **generated SQL or DAX**, the comparison outcome and the transcript. Side column: recent test runs, and **Add to ground truth**. The composer is a form region on the page, not a modal or drawer | Standard branding. The generated query renders in a bordered code region that scrolls horizontally inside its own container. Pass uses the success pair, fail the danger pair, unverified the neutral pair with the word "Unverified" | Standard states. Run in flight uses a bounded in-control wait on Run test with a stable width, while the result region shows a skeleton. Error renders the platform error with the question preserved in the composer. No-data: "No tests run yet" with the composer ready | Data Engineer and above. The agent list is scoped by project. Business User absent | Standard accessibility. The result region is a polite live region and the comparison outcome an assertive one; the generated query is in a labelled region reachable by keyboard; the transcript is a semantic list | Standard device support. The grid stacks with the composer first; the code region scrolls horizontally without the page doing so |
| Ground Truth | List / index | `Workspace` → `Data Agents` (L1) → `Ground Truth`; route `/agents/ground-truth`; breadcrumb `Data Agents › Ground Truth` | Standard shell. Page header carrying the current accuracy score and its trend, with **New question** primary CTA → search and filter bar → card-wrapped table (Question, Expected, Tolerance, Approver, Last result, Trend, Actions) → pagination. **Default sort: last result failing first.** Facets: Last result, Approver, Tolerance breach, Question age | Standard branding. The accuracy score is a display-size labelled figure with its trend in words and an icon. Pass and fail use the approved pairs | Standard states. No-data: "No ground-truth questions yet" with primary **New question** and a note that publication is blocked until the bank exists. No-results echoes the query. Error offers **Retry**. The accuracy figure placeholders independently | Data Engineer and above author; **Business User authors questions and approves expected answers assigned to them**. Approval cannot be self-granted by the person who entered the expected value | Standard accessibility. The accuracy figure and trend are in a polite live region; the trend is stated in words; a failing row pairs its colour with the word "Failed" | Standard device support. Table scrolls horizontally; the accuracy figure moves above the toolbar |
| Evaluation Run Result | Status / result | `Workspace` → `Data Agents` (L1) → `Ground Truth`; route `/agents/ground-truth/runs/{run}`; breadcrumb `Data Agents › Ground Truth › Run {run}` | Standard shell. Single full-width card tinted by outcome: medallion → headline (Passed threshold / Below threshold) → muted explanation carrying the score, the threshold and the delta against the previous run → a per-question breakdown below → one forward action to remediation | Standard branding. Card tint follows the outcome; the score is a display-size figure with the threshold stated beside it in text | Standard states. Reached only once a run has completed, so no loading or empty state of its own. A partially completed run renders as its own outcome with the incomplete count stated | Data Engineer and above; Business User reads the summary only | Standard accessibility. The headline is the first heading; the score and threshold are both text; the breakdown is a semantic table | Standard device support. Full-width card; the breakdown table scrolls horizontally |
| Security Validation | List / index | `Workspace` → `Data Agents` (L1) → `Agents`; route `/agents/{agent}/security-validation`; breadcrumb `Data Agents › {agent} › Security validation` | Standard shell. Page header stating whether publication is currently blocked → search and filter bar → card-wrapped table (Role, Test user, Questions run, Out-of-scope disclosures, Result, Evidence, Actions) → no pagination until more than one page. **Default sort: result failing first.** Facets: Result, Role | Standard branding. A disclosure finding uses the danger pair with the word "Breach"; a role with no test user uses the warning pair with "Not tested" — deliberately distinct, because untested and passed must never look alike | Standard states. No-data: "No roles configured" with a link to Row-Level Security rather than an Add action, since roles are authored elsewhere. Error offers **Retry** | Tenant Administrator authors; Lead Data Engineer reads. The blocking state is visible to every tier that can reach the agent, since it explains why publication is unavailable | Standard accessibility. The blocking statement is the page's first content and lives in a polite live region; "Not tested" and "Passed" are distinguished in text | Standard device support. Table scrolls horizontally with actions pinned |
| Agent Publication Gate | Detail / show | `Workspace` → `Data Agents` (L1) → `Agents`; route `/agents/{agent}/publish`; breadcrumb `Data Agents › {agent} › Publish` | Standard shell. Back link → header card (agent + current state + right-aligned actions) → 2/3 + 1/3 grid. Main column: an evidence card per gate — accuracy, security validation, open findings — each stating met or unmet with its figure and a link to the evidence; then the **published description** field as a page-hosted form region, with its AI-drafted proposal clearly marked. Side column: bound sources, scope summary, previous published versions. The primary **Publish** control is present but **disabled with its blocking reason named in text** while any gate is unmet | Standard branding. Met gates use the success pair, unmet the warning pair, a breach the danger pair. Publish is the single solid button in its group | Standard states. Each evidence card skeletons independently; an evidence card that fails to load renders an error with **Retry** and explicitly counts as unmet, so a loading failure can never open the gate | Lead Data Engineer and above. Publishing is gated by per-record policy in addition to tier | Standard accessibility. The disabled Publish control's blocking reason is part of its accessible description, not a tooltip alone; evidence states are text plus icon | Standard device support. Grid stacks; Publish is 48px on touch |

### 6.6 Conversational application and monitoring

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Conversational App Assembly | Builder (hub-and-spoke) | `Workspace` → `Conversational Apps` (L1) → `Applications`; route `/conversational-apps/{app}/assemble`; breadcrumb `Conversational Apps › {app} › Assemble` | Standard shell. Central hub with a row per part — Environment, Agent, Orchestration, Fabric connection, Agent binding, Routing description, Authentication, Knowledge, Actions, Conversation instructions — each showing whether it is recorded and verified, and spoking to its own sub-page. Most rows are guided actions carrying a Copilot Studio deep link and a verification control. One confirm-guarded **Submit for validation** control | Standard branding. A guided row carries the warning-pair tier badge; a verified row the success pair. An agent-binding mismatch uses the danger pair with the word "Mismatch" | Standard states. Rows skeleton individually. A row whose verification cannot run renders an error and counts as unverified. No empty state — the hub always has its full row set | Lead Data Engineer and above; the Authentication row additionally requires Tenant Administrator and shows the required role when the actor is wrong | Standard accessibility. Each row's verified state is text plus icon; deep links state that they open a Microsoft service; the advance control's blocking reason is announced | Standard device support. Hub rows stack as full-width cards; deep links are 48px on touch |
| Channels & Sharing | Settings / config | `Workspace` → `Conversational Apps` (L1) → `Channels & Sharing`; route `/conversational-apps/channels`; breadcrumb `Conversational Apps › Channels & Sharing` | Standard shell. Section cards: **Teams channel** (enabled toggle, audience, reachability verification) → **Web or custom channel** (channel type, authentication behaviour, reachability verification) → **Sharing** (Entra users and security groups, with groups presented first and preferred). Footer carries **Reset** and **Test Configuration**; **`Save` appears only after a reachability test passes**, and editing any tested field withdraws it | Standard branding. Booleans render as toggles. An unverified channel uses the warning pair; a verified one the success pair | Standard states. The reachability test uses a bounded in-control wait. A failed test renders the platform error with fields left live. The sharing list has all four states | Tenant Administrator authors; Lead Data Engineer reads. Granting to an individual where a group exists shows an inline note rather than blocking | Standard accessibility. Toggles are real switches with labelled state; the test result is an assertive live region; the group-preference note is text | Standard device support. Single column; toggles and test controls at 44px minimum |
| Capacity Monitoring | Dashboard | `Workspace` → `Monitoring` (L1) → `Capacity`; route `/monitoring/capacity`; breadcrumb `Monitoring › Capacity` | Standard shell. Page header with a date-range control built from two linked native date fields → metric-tile grid (Current utilisation · Peak in range · Throttling events · Headroom) → consumption attributed by workload and by project → a recent-alerts list | Standard branding. Utilisation approaching threshold uses the warning pair, exceeding it the danger pair. Charts follow the approved palette with their category order fixed so a series does not change colour between loads | Standard states. Each tile and chart region placeholders independently on its own failure. **A collection gap renders as its own explicit warning region**, never as a flat line, since an absence of data must not read as an absence of load | Platform Administrator and Tenant Administrator; Lead Data Engineer reads. Data Engineer and Business User absent | Standard accessibility. Charts carry an accessible text summary and a data table alternative; the range control is two labelled native date fields; alerts are a semantic list | Standard device support. Tiles reflow to one column; charts scroll horizontally inside their own container; the date fields stack |
| Conversation Quality | Dashboard | `Workspace` → `Monitoring` (L1) → `Conversation Quality`; route `/monitoring/conversation-quality`; breadcrumb `Monitoring › Conversation Quality` | Standard shell. Page header with date range → metric-tile grid (Accuracy trend · Failed questions · Low-confidence questions · Negative ratings) → a grouped-failures list where each group shows its pattern, its count and the terminology used, with a **Raise change** row action → a candidate-synonym list awaiting review | Standard branding. Accuracy trend states its direction in words with an icon. Candidate synonyms use the info pair and the word "Suggested" | Standard states. No-data: "No conversations yet" with an explanatory line and no Add action, since conversations arrive from users rather than being created here. Error offers **Retry** | Lead Data Engineer and Business User author; Tenant Administrator reads. **Business User leads the terminology review**, which is the point of the screen | Standard accessibility. Grouped failures are a semantic list; the trend is text; each Raise change action names its group | Standard device support. Tiles reflow to one column; failure groups stack as full-width cards |
| Assistant | Detail / show | `Workspace` → `Assistant` leaf; route `/assistant`; no breadcrumb. **See section 7, conflict 1** | Standard shell. Header card (available agent + data-freshness badge + right-aligned actions) → 2/3 + 1/3 grid. Main column: the conversation transcript region above a **page-hosted composer form region** with a primary **Send**. Side column: suggested questions drawn from verified answers, the definitions behind the last answer, and a link to the glossary. Every answer carries its data-freshness position and a rating control | Standard branding. The freshness badge uses the success pair when current and the warning pair when the underlying pipeline last failed — a stale answer is signalled before it is read, not after | Standard states. Send in flight uses a bounded in-control wait with a stable width while the answer region skeletons. Error renders a human message with the question preserved in the composer. No-data: an opening state offering the suggested questions | **Every tier including Business User** — this is the Business User's primary screen. The answer respects the user's own Fabric permissions through user authentication, so the query scope is enforced by Microsoft rather than by this screen | Standard accessibility. The transcript is a semantic list with each turn labelled by speaker; new answers arrive in a polite live region; errors are assertive; the composer has a visible label; the rating control has an accessible name stating what it rates | Standard device support. The grid stacks with the transcript above the composer and the side column below; the composer is 48px on touch and stays reachable without the page scrolling horizontally |

### 6.7 Compliance

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Audit Trail | List / index | `Compliance` → `Audit Trail` leaf; route `/compliance/audit`; no breadcrumb | Standard shell. Page header with an **Export** ghost action → search and filter bar → card-wrapped table (When, Actor, Project, Step, Target, Outcome, Correlation ID) → pagination. **Default sort: When, newest first.** Facets: Actor, Project, Outcome, Date range. Filters and sort run server-side over the whole result set and live in the URL query | Standard branding. Outcome uses the approved pairs with its word. The correlation ID renders as a neutral code pill | Standard states. No-data: "No audit events yet" with no Add action, since events are recorded rather than created. No-results echoes the query with **Clear filters**. Error offers **Retry** | Platform Administrator, Tenant Administrator and Lead Data Engineer. Records cannot be edited or deleted from the application by any tier, and no row action offers it | Standard accessibility. Timestamps carry an accessible absolute value in the tenant locale; the code pill has an accessible name; export reports completion in a polite live region | Standard device support. Table scrolls horizontally; the export action moves into the page header on small screens |
| Go-Live Readiness | Detail / show | `Compliance` → `Go-Live Readiness` leaf; route `/compliance/go-live`; no breadcrumb | Standard shell. Header card (project + readiness state + right-aligned actions) → 2/3 + 1/3 grid. Main column: an evidence card per gate — data quality, accuracy, security validation, routing, multi-turn, authorization, capacity headroom, access grants — each stating met, unmet or **stale**, with its figure, its date and a link; then a sign-off card per approval area with its named approver and state. Side column: open exceptions, each requiring explicit acknowledgement. The primary **Release** control is present but **disabled with its blocking reasons named in text** while any gate is unmet or any sign-off is outstanding | Standard branding. Met uses the success pair, unmet the warning pair, stale the warning pair with the word "Stale", a breach the danger pair. Release is the single solid button in its group | Standard states. Each evidence card skeletons independently. A card that fails to load renders an error with **Retry** and counts as **unmet**, never as met. Evidence older than the most recent change renders as stale rather than met | Tenant Administrator and Platform Administrator; Lead Data Engineer reads. A sign-off can be recorded only by the named approver for that area, and never by the person who produced the evidence | Standard accessibility. The blocking reasons list is the page's first content in a polite live region; the disabled Release control's reasons are in its accessible description; every state is text plus icon | Standard device support. Grid stacks with sign-offs following evidence; Release is 48px on touch |
| Recycle Bin | Recycle bin / soft-delete | `Application Administration` → `Recycle Bin` leaf; route `/admin/recycle-bin`; no breadcrumb | Standard shell. Page header → search and filter bar → for a non-administrator, their own deleted records with **Restore** per row; for an administrator, records bucketed by entity with **Restore** and a confirm-gated **Delete permanently** per row, plus an **Empty everything** action requiring a **typed confirmation word** | Standard branding. Delete permanently and Empty everything are the Danger variant; Restore is ghost. No other solid button appears in either group | Standard states. No-data: "Nothing deleted" with no Add action. No-results echoes the query. Error offers **Retry** | Every tier restores their own records. **Only Tenant Administrator permanently deletes**, and the control is absent rather than disabled for other tiers. Emptying everything is Tenant Administrator only | Standard accessibility. Each destructive confirmation names its exact target and states recoverability; the Danger button is verb-labelled, never "OK"; initial focus rests on the safe action; the typed-confirmation field has a visible label | Standard device support. Buckets stack; row actions grow to 44px; the confirmation dialog becomes near-full-width, docks to the bottom and stacks its actions |

### 6.8 System administration

| Screen Name | Page Archetype | Shell Placement | Wireframe/Mock-up | Branding | UI States | Role & Access | Accessibility | Device Support |
|---|---|---|---|---|---|---|---|---|
| Integration Settings | Settings / config | `System Administration` → `Integration Settings` leaf; route `/system/integrations`; no breadcrumb | Standard shell. Section cards per integration — Entra application, Fabric platform, Power BI service, Azure resource management, Copilot Studio — each showing its endpoint, its identity type and its last successful call. Footer carries **Reset** and **Test Configuration** only; **`Save` appears only after a live test passes**, and editing any tested field withdraws it. Secrets appear only as a masked "encrypted at rest" badge and are never rendered | Standard branding. A never-tested integration uses the neutral pair with the word "Untested"; a failing one the danger pair — deliberately distinct, since untested and failed are different problems | Standard states. Test in flight uses a bounded in-control wait per integration. A failure renders the platform error verbatim, which is the only useful content on this screen when something is wrong. No empty state | **Platform Administrator only.** Absent for every other tier in both the sidebar and the server-side handler | Standard accessibility. Each test result is an assertive live region; the masked secret badge states that the value is encrypted at rest and is not focusable as an editable field | Standard device support. Cards stack; test controls at 44px minimum |
| Capability Probe | List / index | `System Administration` → `Capability Probe` leaf; route `/system/capabilities`; no breadcrumb | Standard shell. Page header with a **Re-run probe** primary CTA → search and filter bar → card-wrapped table (Operation, Steps affected, Identity type that worked, Available, Effective tier, Last probed, Observed error) → pagination. **Default sort: unavailable operations first.** Facets: Available, Effective tier, Identity type | Standard branding. Available uses the success pair, unavailable the danger pair, delegated-only the warning pair with the word "Delegated only" | Standard states. No-data: "Probe has not run" with primary **Re-run probe**. Error offers **Retry**. A partially completed probe shows completed rows with the remaining count stated, rather than discarding the partial result | Platform Administrator only | Standard accessibility. The observed error is in a labelled region reachable by keyboard; availability pairs colour with its word; re-run completion is announced politely | Standard device support. Table scrolls horizontally with the actions column pinned |

### 6.9 Pattern-conforming screens — complete inventory

These carry no screen-specific addition beyond their archetype's standard contract. Each
uses Standard shell, Standard branding, Standard states, Standard accessibility and Standard
device support, and its archetype's non-negotiables in full — for a list that means sortable
columns with a declared default sort, a search and filter bar running server-side over the
whole result set, state in the URL query and numbered pagination at 25 per page; for a form
it means page-hosted on its own route, error-only validation inline on blur, and a footer
with ghost Cancel and one primary action.

| Screen Name | Archetype | Route | Default sort / notes |
|---|---|---|---|
| Environments | List / index | `/environments` | Environment name, DEV first |
| Source Register | List / index | `/sources` | Updated, newest first |
| New / Edit Source | Form | `/sources/create`, `/sources/{source}/edit` | One shared template for both modes |
| Source Detail | Detail / show | `/sources/{source}` | Facets: `Overview \| Connections \| Ingestion` |
| Connections | List / index | `/sources/connections` | State, failing first |
| Lakehouse | Detail / show | `/data-platform/lakehouse` | Facets: `Overview \| Tables \| Endpoints` |
| Medallion Layers | Builder | `/data-platform/layers` | Hub of three layer rows spoking to layer sub-pages |
| Warehouse Decision | Form | `/data-platform/warehouse` | Records the decision either way |
| Pipelines | List / index | `/ingestion/pipelines` | Last run result, failing first |
| Pipeline Builder | Step-by-step form | `/ingestion/pipelines/create` | Continue saves a resumable draft; drafts hold no credential |
| Schedules | List / index | `/ingestion/schedules` | Next run, soonest first |
| Run History | List / index | `/ingestion/runs` | Started, newest first |
| Transformations | List / index | `/transformation` | Updated, newest first |
| Transformation Editor | Form | `/transformation/{id}/edit` | Business purpose is required before saving |
| Entities & Keys | Builder | `/transformation/entities` | Hub of entities spoking to mapping sub-pages |
| Quality Rules | List / index | `/transformation/quality` | Severity, blocking first |
| Semantic Models | List / index | `/semantic/models` | Updated, newest first |
| Semantic Model Detail | Detail / show | `/semantic/models/{model}` | Facets: `Overview \| Tables \| Storage modes \| Activity` |
| Measures | List / index | `/semantic/measures` | Name, ascending |
| Measure Editor | Form | `/semantic/measures/{id}/edit` | Plain-language definition and owner required alongside the expression |
| Row-Level Security | List / index | `/semantic/security/rls` | Test result, untested first |
| RLS Role Editor | Form | `/semantic/security/rls/{role}/edit` | |
| Column-Level Security | List / index | `/semantic/security/cls` | Verification result, unverified first |
| AI Instructions | List / index | `/semantic/ai/instructions` | Effective date, newest first |
| AI Instruction Editor | Form | `/semantic/ai/instructions/{id}/edit` | Owner and effective date required |
| Agents | List / index | `/agents` | Updated, newest first |
| Access Grants | List / index | `/agents/{agent}/access` | Granted date, newest first |
| Conversational Apps | List / index | `/conversational-apps` | Updated, newest first |
| Validation | List / index | `/conversational-apps/validation` | Result, failing first |
| Pipeline Operations | Dashboard | `/monitoring/pipelines` | Tiles placeholder independently; a collection gap renders explicitly |
| Business Glossary | List / index | `/glossary` | Term, ascending. Read-only for Business User |
| Glossary Term | Detail / show | `/glossary/{term}` | Links to the implementing model object |
| Exceptions | List / index | `/compliance/exceptions` | Raised date, newest first |
| Exception Request | Form | `/compliance/exceptions/create` | Compensating control required before submission |
| Change Control | List / index | `/compliance/change-control` | Detected date, newest first |
| Access Review | List / index | `/compliance/governance/access` | Least-privilege exceptions first |
| Sensitivity Labels | Settings / config | `/compliance/governance/labels` | |
| Lineage | Detail / show | `/compliance/governance/lineage` | Source system through to published agent |
| Users | List / index | `/admin/users` | Name, ascending |
| User Editor | Form | `/admin/users/{user}/edit` | |
| Roles | Settings / config | `/admin/roles` | Tier grants shown as read-only defaults with narrowing only |
| Notifications | Settings / config | `/admin/notifications` | |
| Application Settings | Settings / config | `/admin/settings` | |
| Tenant Connections | List / index | `/system/tenants` | Connected date, newest first |
| Entra Application | Settings / config | `/system/entra` | Test-before-save; secrets masked only |
| Fabric Capacities | List / index | `/system/capacities` | SKU, then state |
| Step Catalogue | List / index | `/system/steps` | Step number, ascending. Tier is configuration, editable here |

---

## 7. Conflicts named, and the compliant shape specified

Two requirements implied shapes the approved standard does not carry. Neither has been
silently invented around.

**Conflict 1 — a conversational surface is not in the closed archetype list.** The Test
Console and the Assistant are both fundamentally a composer plus a transcript, and no
archetype names that shape. Rather than invent an eleventh archetype, both are specified as
**Detail / show** — a record (the agent under test, or the available agent) with a
page-hosted **form region** for the composer and a transcript region beside it. This keeps
every enforced contract intact: data entry stays page-hosted, the async control follows the
loading and stable-width rules, and the transcript is a semantic list. **This needs the
technical lead's confirmation**, since a conversational surface will recur across the
product and is worth settling once rather than per screen.

**Conflict 2 — "quick create" would have been a modal.** Several list screens would
conventionally offer an inline create dialog. Data entry is page-hosted without exception,
so every create is specified as its own route with a shared create-and-edit template. No
create, edit, multi-step or settings form appears in a modal, drawer or off-canvas panel
anywhere in this specification, however few fields it carries. Modals appear only for
decisions that must be answered now — a destructive confirmation, a typed confirm word, a
rejection reason — each carrying only the fields that *are* the decision.

## 8. Final reporting

**Archetypes selected.** Auth for sign-in. Step-by-step form for tenant onboarding,
environment provisioning and the pipeline builder, because each is long and
order-dependent. Builder for the blueprint runner, relationships, medallion layers, entities
and keys, the agent builder and conversational app assembly, because each composes parts
across sub-pages behind a gated advance. Dashboard for scanning surfaces where the user is
looking for exceptions. List / index wherever the user is finding a record. Detail / show for
one record and for the two conversational surfaces per conflict 1. Form for every create and
edit. Settings / config for connections and integrations, each with the test-before-save
contract. Status / result for step verification and evaluation run outcomes. Recycle bin for
soft-delete recovery.

**Shell placement.** All four clusters are used, in their fixed order, none added, renamed or
reordered. The deepest path is `Data Platform` → `Ingestion` → `Pipelines`, which is three
levels; there is no fourth accordion level anywhere. Project facets use an in-canvas tab
strip, which is record-facet handling, and the strip is terminal. All standing navigation is
in the sidebar; the top bar carries no navigation, no global search and no action buttons.

**UI states covered.** Success, loading as a skeleton, empty in both flavours chosen by
cause, and error with Retry, for every data-driven region. Three screens carry a deliberate
strengthening worth noting: a failed evidence load on the publication and go-live gates
counts as **unmet** rather than met; a monitoring collection gap renders as an **explicit
warning** rather than a flat line; and "untested" is visually distinct from "passed"
throughout, because on this product those two states failing to look different would be a
security problem rather than a design one.

**Role and access layers.** All four layers are named per screen: the cluster and feature
gate on both the sidebar and the server-side handler; the per-record policy; the list and
query scope so out-of-scope records never load; and the hard gate on permanent delete.
Unauthorized items are absent, not dimmed. Unbuilt navigation destinations stay visible,
disabled, with a "Soon" indicator. Business User is narrowed to the Assistant, the Business
Glossary, read-only project status, and the two surfaces where the business tier genuinely
leads — verified-answer approval and terminology review.

**Accessibility and touch.** WCAG AA throughout, with the standard applied as observable
behaviour per screen. Touch targets at 44px minimum, primary actions at 48px on touch. No
status, validation, sort state or filter state rests on colour alone anywhere.

**Branding.** The approved palette, type, shape, spacing and elevation are applied unmodified
in both themes, with no per-screen colour or font anywhere in this document. The application
name was confirmed by the user; the navigation tree and role labels are specified here for
the technical lead's confirmation. **The brand asset pack and its installed location remain
outstanding and are required** — the sidebar brand block and the sign-in screen both display
identity assets directly, and no substitute has been chosen for them.

**Authorized deviations.** None. Two conflicts were named in section 7 and resolved into
compliant shapes rather than taken as exceptions.

---

*04-UI-Specification*
