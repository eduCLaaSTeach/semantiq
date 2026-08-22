# 06 - App Definition

**Status: PROPOSAL. Not confirmed. Nothing is generated from this file until it is.**

The shared UI and UX layout template requires an App Definition block before any screen,
shell or navigation is generated, and it is explicit that the navigation tree, entities,
roles, app name, brand assets path, UI stack and draft shape are **asked, never invented**.

This document proposes values for every field, derived from the specifications already in
this repository, so that confirming them is a review rather than a blank page. Each entry
is tagged:

| Tag | Meaning |
|---|---|
| **SETTLED** | Already decided and in use. Shown for completeness; not a question. |
| **DERIVED** | Proposed from `doc/00` and `doc/01`. Read it and correct anything wrong. |
| **OPEN** | The template forbids choosing this without you. Listed in section 8. |

---

## 1. Stack and identity

```yaml
stack:
  ui_stack:   Laravel 13 on PHP 8.5, React 19, Vite, plain CSS custom properties   # SETTLED
  charting:   <none yet>                                                           # OPEN (Q6)

app:
  name:               SemantIQ                    # SETTLED - shown in the top bar
  title_bar:          SemantIQ                    # SETTLED - already live, from APP_NAME
  tagline:            <SHORT_TAGLINE>             # OPEN (Q1)
  brand_assets_path:  public/brand                # SETTLED - confirmed, in use since PR #5
```

The rail shows the CLaaS2SaaS wordmark; the top bar shows the app name. Those are two
different things and both are already correct in the sign-in screen.

## 2. Feature toggles

```yaml
features:
  theme_switcher:         true     # SETTLED - mandatory, and the tokens already support it
  sidebar_nav_filter:     true     # SETTLED - the tree below is large enough to need it
  sidebar_collapsible:    true     # SETTLED
  customizable_dashboard: true     # SETTLED
  notifications:          true     # SETTLED - long-running Fabric operations need to report back
  sso:                    true     # SETTLED - Microsoft Entra ID, live since PR #5
  recycle_bin:            true     # SETTLED - doc/00 carries a deleted_records table
  audit_log:              true     # SETTLED - doc/00 makes an audit trail a cross-cutting concern
  ai_model_catalog:       false    # DERIVED - see below
```

`ai_model_catalog` is proposed **false**. The catalog exists for an application that calls
an AI model at runtime from its own code. SemantIQ does not: it configures a Fabric Data
Agent and Copilot Studio, and the model calls happen inside Fabric against the customer's
own capacity. The provider configuration SemantIQ holds is an Entra and Fabric connection,
not a model endpoint. Turn it on only if SemantIQ itself will call a model directly.

## 3. Roles

The five-tier baseline with the labels proposed in `doc/00` section 7, which records them
as awaiting confirmation. The tier codes are already implemented in `app/Enums/Role.php`
and must not change; only the labels are in question.

```yaml
roles:                                                                          # DERIVED (Q4)
  - { key: platform_administrator, label: Platform Administrator, tier: system_admin,
      persona: Administrator,
      clusters: [Workspace, Compliance, Application Administration, System Administration] }
  - { key: tenant_administrator,   label: Tenant Administrator,   tier: admin,
      persona: Administrator,
      clusters: [Workspace, Compliance, Application Administration] }
  - { key: lead_data_engineer,     label: Lead Data Engineer,     tier: team,
      persona: Data Engineer,
      clusters: [Workspace, Compliance] }
  - { key: data_engineer,          label: Data Engineer,          tier: self,
      persona: Data Engineer,
      clusters: [Workspace] }
  - { key: business_user,          label: Business User,          tier: self_view,
      persona: Business User,
      clusters: [Workspace] }
```

Cluster grants follow the baseline and are narrowed, never widened.

## 4. Navigation tree

**This is the section to read hardest.** It is the largest derived artifact here and the
one most likely to be wrong, because it turns 14 delivery clusters and 16 modules into a
sidebar a person has to live in daily.

The four clusters are a closed set and are never renamed or reordered. Groups nest at most
three accordion levels; a leaf is not a level. The proposal below uses two.

```yaml
navigation:                                                                     # DERIVED (Q3)

  - cluster: Workspace
    nodes:
      - { label: Dashboard, route: dashboard, icon: i-grid, access: workspace }

      - group: Projects
        icon: i-folder
        access: workspace
        children:
          - { label: All Projects,  route: projects.index,  icon: i-list,  access: workspace }
          - { label: New Project,   route: projects.create, icon: i-plus,  access: workspace }
          - { label: Blueprints,    route: blueprints.index, icon: i-map,  access: workspace }

      - group: Data Platform                       # clusters 2-6, steps 5-26
        icon: i-database
        access: workspace
        children:
          - group: Environments
            icon: i-server
            children:
              - { label: Workspaces,  route: workspaces.index, icon: i-layers, access: workspace }
              - { label: Capacities,  route: capacities.index, icon: i-gauge,  access: workspace }
          - group: Sources
            icon: i-plug
            children:
              - { label: Source Register, route: sources.index,     icon: i-list,   access: workspace }
              - { label: Connections,     route: connections.index, icon: i-link,   access: workspace }
              - { label: Gateways,        route: gateways.index,    icon: i-shield, access: workspace }
          - group: Lakehouse
            icon: i-cube
            children:
              - { label: Lakehouses,      route: lakehouses.index, icon: i-cube,  access: workspace }
              - { label: Medallion Layers, route: layers.index,    icon: i-stack, access: workspace }
              - { label: Warehouses,      route: warehouses.index, icon: i-box,   access: workspace }
          - group: Ingestion
            icon: i-download
            children:
              - { label: Pipelines,    route: pipelines.index,     icon: i-flow,    access: workspace }
              - { label: Pipeline Runs, route: pipeline-runs.index, icon: i-history, access: workspace }
          - group: Transformation
            icon: i-wand
            children:
              - { label: Transformations, route: transformations.index, icon: i-wand,   access: workspace }
              - { label: Quality Rules,   route: quality-rules.index,   icon: i-check,  access: workspace }
              - { label: Quality Results, route: quality-results.index, icon: i-report, access: workspace }

      - group: Semantics and AI                    # clusters 7-10, steps 27-51
        icon: i-sparkles
        access: workspace
        children:
          - group: Modelling
            icon: i-share
            children:
              - { label: Semantic Models, route: semantic-models.index, icon: i-share, access: workspace }
              - { label: Measures,        route: measures.index,        icon: i-sigma, access: workspace }
              - { label: Row and Column Security, route: security-rules.index, icon: i-lock, access: workspace }
          - group: Glossary
            icon: i-book
            children:
              - { label: Terms,             route: glossary.index,         icon: i-book,  access: workspace }
              - { label: AI Instructions,   route: ai-instructions.index,  icon: i-note,  access: workspace }
              - { label: Verified Answers,  route: verified-answers.index, icon: i-check, access: workspace }
          - group: Agents
            icon: i-robot
            children:
              - { label: Data Agents, route: agents.index,          icon: i-robot, access: workspace }
              - { label: Examples,    route: agent-examples.index,  icon: i-quote, access: workspace }
          - group: Evaluation
            icon: i-target
            children:
              - { label: Ground Truth,    route: ground-truth.index, icon: i-target,  access: workspace }
              - { label: Test Runs,       route: evaluations.index,  icon: i-play,    access: workspace }
              - { label: Accuracy Results, route: eval-results.index, icon: i-report, access: workspace }

      - group: Delivery                            # clusters 11-12, steps 52-71
        icon: i-rocket
        access: workspace
        children:
          - { label: Access Grants,       route: access-grants.index, icon: i-key,     access: workspace }
          - { label: Conversational Apps, route: apps.index,          icon: i-chat,    access: workspace }
          - { label: Channels,            route: channels.index,      icon: i-signal,  access: workspace }

      - group: Observability                       # cluster 13, steps 72-74
        icon: i-activity
        access: workspace
        children:
          - { label: Capacity Metrics,     route: metrics.index,       icon: i-gauge,  access: workspace }
          - { label: Conversation Quality, route: conversation-quality.index, icon: i-chat, access: workspace }

  - cluster: Compliance                            # cluster 14 governance, steps 75-80
    nodes:
      - { label: Audit Log,          route: audit.index,      icon: i-clipboard, access: compliance }
      - { label: Sensitivity Labels, route: labels.index,     icon: i-tag,       access: compliance }
      - { label: Lineage Register,   route: lineage.index,    icon: i-share,     access: compliance }
      - { label: Exceptions,         route: exceptions.index, icon: i-alert,     access: compliance }
      - group: Change Control
        icon: i-git-branch
        access: compliance
        children:
          - { label: Change Requests, route: change-requests.index, icon: i-git-branch, access: compliance }
          - { label: Sign-offs,       route: signoffs.index,        icon: i-check,      access: compliance }
          - { label: Go-Live Gate,    route: go-live.index,         icon: i-flag,       access: compliance }

  - cluster: Application Administration
    nodes:
      - group: Access Control
        icon: i-shield-check
        access: app_admin
        children:
          - { label: Users, route: users.index, icon: i-users,        access: app_admin }
          - { label: Roles, route: roles.index, icon: i-shield-check, access: app_admin }
      - { label: Recycle Bin,   route: recycle-bin.index,   icon: i-trash, access: app_admin }
      - { label: Notifications, route: notifications.index, icon: i-bell,  access: app_admin }

  - cluster: System Administration
    nodes:
      - group: Integrations
        icon: i-plug
        access: sys_admin
        children:
          - { label: Entra Connections, route: entra.index,   icon: i-key,    access: sys_admin }
          - { label: Fabric Capacities, route: fabric.index,  icon: i-gauge,  access: sys_admin }
          - { label: Git Bindings,      route: git.index,     icon: i-git-branch, access: sys_admin }
      - group: Settings
        icon: i-cog
        access: sys_admin
        children:
          - { label: General, route: settings.index, icon: i-adjustments, access: sys_admin }
```

### What this deliberately does not do

- **It does not give the 80 steps 80 screens.** `doc/00` is explicit that the steps are
  data driven through the Blueprint engine, not one screen each. A step run is reached
  through its project and stage, not through the sidebar.
- **It does not put Blueprints in their own cluster.** A blueprint belongs to a project,
  so it sits inside `Projects`.
- **It does not name a group after its cluster**, which the template forbids.
- **It never exceeds two accordion levels**, one below the limit, leaving room to grow.

## 5. Entities

Each becomes a CRUD set built from the archetypes. Taken from the `doc/00` data model,
limited to those a person actually manages through a screen. The remainder
(`operations`, `audit_events`, `capacity_metrics` and similar) are records the system
writes and a person only ever reads, so they get a list and a detail view but no create
or edit.

```yaml
entities:                                                                       # DERIVED (Q5)
  managed:      # full CRUD, soft delete, restore
    - Project, Blueprint, Environment, Workspace, DataSource, Connection, Gateway
    - Lakehouse, Warehouse, Pipeline, Transformation, QualityRule
    - SemanticModel, Measure, RlsRule, ClsRule
    - GlossaryTerm, AiInstruction, VerifiedAnswer
    - DataAgent, AgentSource, AgentExample
    - GroundTruthQuestion, ConversationalApp, Channel, AccessGrant
    - ChangeRequest, Exception, User, Role, EntraConnection, FabricCapacity, GitBinding
  read_only:    # list and detail only; written by the system
    - StepRun, Operation, PipelineRun, QualityResult, EvaluationRun, EvaluationResult
    - CapacityMetric, ConversationReview, AuditEvent, Signoff
```

## 6. List behaviour

```yaml
lists:                                                                          # DERIVED
  query_params:  { q, sort, dir, page, size }   # plus one per facet
  server_side:   true
```

Server-side because several of these lists are tenant-scoped and unbounded: step runs,
audit events and pipeline runs all grow without limit, and paginating them in the browser
would mean shipping the whole table to it.

## 7. Step-by-step form drafts

Every field here is **OPEN**. The template states plainly that the draft storage shape and
retention are asked and never decided, and it is right to insist: this is a schema
decision, and `doc/00` does not cover it.

```yaml
drafts:
  storage:          <draft_table | same_table_draft_state>   # OPEN (Q2a)
  state_source:     <existing_status_dimension | new_one>    # OPEN (Q2b)
  in_flight:        one                                       # DERIVED - the template default
  excluded_fields:  every credential, key, token, and password - always, never persisted
  autosave:         <none | interval | on_blur | on_close>   # OPEN (Q2c)
  retention:        <ABANDONED_DRAFT_RETENTION>              # OPEN (Q2d)
```

This matters more here than in most applications. Onboarding a tenant and standing up a
blueprint is a long multi-step flow, so an abandoned draft is likely, and a draft that
silently persisted a client secret would be a credential leak. The exclusion rule above is
not negotiable whatever the storage answer.

## 8. The open questions

Nothing below can be answered from the repository. The template forbids guessing any of
them, and generating a shell without them would bake in a decision nobody made.

| # | Question | Why it cannot be derived |
|---|---|---|
| Q1 | The tagline, if any, for the sign-in screen and empty states | Marketing copy; optional, and inventing a strapline for a product is not a technical call |
| Q2a | Draft storage: a separate `drafts` table, or a draft state on the record's own table | A schema decision with different migration and query consequences |
| Q2b | Where draft state comes from: an existing status dimension or a new one | Depends on Q2a and on whether a status column already means something |
| Q2c | Autosave on top of the per-step save: none, interval, on blur, or on close | A product decision about how much silent writing is wanted |
| Q2d | How long an abandoned draft lives before it is cleared | A retention policy, which is a governance decision, not a technical default |
| Q3 | Is the navigation tree in section 4 right? | Derived, not confirmed. It is a proposal to correct, not an answer |
| Q4 | Are the five role labels in section 3 right? | `doc/00` records them as awaiting the technical lead |
| Q5 | Is the managed and read-only entity split in section 5 right? | Derived from a first-cut data model |
| Q6 | Will any screen chart anything, and if so with what library | Adding a charting library is a dependency decision |

Q3, Q4 and Q5 are proposals: silence on them is not confirmation, but a short "yes, that
tree is right" is enough. Q1 and Q6 can be deferred without blocking the shell. **Q2a
through Q2d block the first multi-step form, not the shell**, so the shell can be built
once Q3 and Q4 are settled.

## 9. What unblocks what

| To build | Needs |
|---|---|
| The application shell: rail, top bar, four clusters, theme switcher, nav filter | Q3, Q4 |
| The first list or index screen | Q3, Q4, Q5 |
| The first multi-step form (tenant onboarding, blueprint authoring) | Q2a-Q2d |
| Any screen that charts | Q6 |
| The sign-in tagline | Q1 (cosmetic; the screen is already live without it) |
