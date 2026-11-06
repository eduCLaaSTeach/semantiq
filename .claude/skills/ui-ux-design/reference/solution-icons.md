# Solution & Module Icons

The approved icon for every CLaaS2SaaS solution, module, sub-module, and feature in the pack. Use when showing the app icon in the top bar or on a standalone auth card, or when a screen lists solutions or modules.

These icons identify a node in the company's solution hierarchy. They are a separate set from the inline icon registry in `assets.md`, which draws actions and navigation, and they never replace the company logo.

## Contents

- The four levels
- Where the files are
- Layout
- File variants (ENFORCED)
- Color (ENFORCED)
- Placement (ENFORCED)
- Embedding (ENFORCED)
- Choosing the app icon (ENFORCED)
- Do / Don't
- Catalog

---

## The four levels

The pack follows the company's dev task sheet, so the kit, Claude, and the teams use one set of terms. There are four levels, in this order:

| Level | Meaning |
| --- | --- |
| Solution | A product line, for example Agentic CRM |
| Module | A part of a solution |
| Sub-module | A part of a module, only where applicable |
| Feature | A capability inside a module or a sub-module, only where applicable. The sheet's column is "Key Features"; the kit always writes "Feature" |

The pack holds 5 solutions, 26 modules, 23 sub-modules, and 22 features, in 94 files. Every name is the sheet's name. Marketing named the EOS solution Enterprise Intelligence Fabric; the kit uses the sheet name, Enterprise Intelligence OS (EOS). Three Integration Hub sub-modules are not in the sheet and carry a note in the catalog: Content Processor, Task Scheduler, and Process Tracker (Microsoft).

---

## Where the files are

| Property | Value |
| --- | --- |
| Official source | This skill's asset pack: `.claude/skills/ui-ux-design/assets/solution-icons/` (do not download elsewhere or redraw) |
| Index | `catalog.json` in the same folder is the source of truth. Its top-level `levels` lists the four levels, `files` explains the file variants, and `solutions` holds the tree. Each node carries `name`, `level`, `path`, and `files`, its children under `modules`, `sub_modules`, or `features`, and a `note` where one applies |
| Set | 94 files for 76 nodes: 5 solutions, 26 modules, 23 sub-modules, 22 features |
| File format | A square SVG with a square `viewBox` and `fill="currentColor"`, `aria-hidden="true"`, and `focusable="false"` on the root; no fixed color, no ids, no classes |
| In-project destination | Copy only the icons the app uses into `<BRAND_ASSETS_PATH>`, as with the logos. If the path is not recorded in `.claude/PROJECT-CONTEXT.md`, ask the developer; never choose the location yourself |
| Sizes | Any square size, since it is SVG. The standard steps are 16, 20, 24, 32, 40, 48 |

---

## Layout

The folders mirror the hierarchy, with short names to stay inside Windows path limits.

- Each solution is a folder named by the kebab-case of its sheet name, for example `agentic-crm/`. The EOS solution's folder is `eos/`.
- A node that has children is a folder inside its parent's folder. It holds its own `icon.svg` and, where marketing drew them, `outline.svg` and `in-parent.svg`. For example `eos/integration-hub/icon.svg`.
- A leaf node, one with no children, is one file named after it in its parent's folder. For example `agentic-crm/agentic-leads-generator.svg`, or `eos/intelligence-hub/enterprise-data-analytics/marketing-analytics.svg`.

```text
solution-icons/
  catalog.json
  agentic-crm/
    icon.svg                      Solution
    outline.svg
    agentic-leads-generator.svg   Module, a leaf
  eos/
    icon.svg                      Solution
    outline.svg
    integration-hub/              Module with sub-modules
      icon.svg
      outline.svg
      in-parent.svg
      enterprise-mdm/             Sub-module with features
        icon.svg
        outline.svg
        kernel-mdm.svg            Feature, a leaf
      task-scheduler.svg          Sub-module, a leaf
```

Read a node's files from its `files` in `catalog.json`, never by building a path from its name.

---

## File variants (ENFORCED)

| File | What it is | When to use it |
| --- | --- | --- |
| `icon.svg`, or a leaf's own file | The icon to use for the node. A leaf's file is the badged form, carrying a small badge of its parent, because it is the only form marketing drew | Always, by default |
| `outline.svg` | Marketing's outline drawing of the same icon | Only when the developer asks for it |
| `in-parent.svg` | The same icon carrying a small badge of its parent, as marketing drew it inside the parent's set | Only when the developer asks for it |

Use the node's `files.icon`. `outline.svg` and `in-parent.svg` are never chosen on Claude's own judgment.

---

## Color (ENFORCED)

An icon takes one color, the `--solution-icon` token, defined in `design-tokens.md`.

| Token | Light | Dark |
| --- | --- | --- |
| `--solution-icon` | `#193E6B` (Midnight Blue) | `#B3A125` (Green Gold) |

The token follows marketing's guidance for the pack: Midnight Blue on light backgrounds, gold on dark. Because every file paints with `currentColor`, setting `color: var(--solution-icon)` on the icon (or `background-color` for a mask) is the whole color treatment. No other color, tint, gradient, or status role is applied to an icon.

---

## Placement (ENFORCED)

Icons appear in these places, and only these.

| Where | Which icon | Size | Detail |
| --- | --- | --- | --- |
| Top bar | The app icon | 20px | Directly left of the application name, 8px gap. Decorative (`aria-hidden="true"`) because the name follows. Shown only when the App Definition records an app icon. See `topbar-sidenav.md` |
| A screen that lists solutions or modules: a launcher, a module switcher, dashboard tiles, a settings list of modules | Each item's own node icon | 24px in lists and table rows, 40px on cards and tiles | Every item carries its own node's `files.icon`, shown with its name |
| The three standalone auth cards: signed-out, no-access, failed sign-in | The app icon | 48px | Above the app name |

The name always appears with the icon, so the icon stays decorative and keeps `aria-hidden="true"`.

---

## Embedding (ENFORCED)

Use one of three methods:

- Inline the SVG markup from the file.
- Reference it from one SVG sprite of `<symbol>` elements.
- Use the file as a CSS `mask-image` with `background-color: var(--solution-icon)`.

Never `<img src>`. `currentColor` does not reach inside an `<img>`, so the icon renders black in both themes.

Inline, colored through `currentColor`, keeping the file's own `viewBox`:

```html
<span class="solution-icon solution-icon-20">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" fill="currentColor"
       aria-hidden="true" focusable="false"><!-- paths from agentic-crm/agentic-leads-generator.svg --></svg>
</span>
```

```css
.solution-icon { display: inline-flex; color: var(--solution-icon); }
.solution-icon svg { width: 100%; height: 100%; }
.solution-icon-20 { width: 20px; height: 20px; }   /* top bar */
.solution-icon-24 { width: 24px; height: 24px; }   /* lists and table rows */
.solution-icon-40 { width: 40px; height: 40px; }   /* cards and tiles */
.solution-icon-48 { width: 48px; height: 48px; }   /* auth cards */
```

From a sprite, keep `fill="currentColor"` and the file's own `viewBox` on each `<symbol>`:

```html
<svg hidden>
  <symbol id="eos-integration-hub" viewBox="0 0 800 800" fill="currentColor"><!-- paths from eos/integration-hub/icon.svg --></symbol>
</svg>
<span class="solution-icon solution-icon-24">
  <svg aria-hidden="true" focusable="false"><use href="#eos-integration-hub" /></svg>
</span>
```

As a mask, colored by the token:

```html
<span class="solution-icon-mask" aria-hidden="true"></span>
```

```css
.solution-icon-mask {
  display: inline-block;
  width: 40px;
  height: 40px;
  background-color: var(--solution-icon);
  -webkit-mask: url("<BRAND_ASSETS_PATH>/agentic-crm/agentic-leads-generator.svg") center / contain no-repeat;
          mask: url("<BRAND_ASSETS_PATH>/agentic-crm/agentic-leads-generator.svg") center / contain no-repeat;
}
```

Size an icon by its placement's class, at one of the standard steps, never with an inline `style`.

---

## Choosing the app icon (ENFORCED)

The app icon is the icon file of the most specific node the app is. It is an App Definition value, recorded in `.claude/PROJECT-CONTEXT.md` as `App icon`, next to the app name and the title-bar name, as the node's `files.icon` value from `catalog.json`, for example `agentic-crm/agentic-leads-generator.svg` or `eos/integration-hub/icon.svg`.

- Ask the developer where the app sits in the hierarchy. Its Solution, Module, and Sub-module are recorded in `.claude/PROJECT-CONTEXT.md`; ask for its Feature where one applies. Never guess any of them from the app name, the repository name, or the domain.
- Choose by the app's own place: its Feature, else its Sub-module, else its Module, else its Solution.
- When the app's node is not in the catalog, use its nearest ancestor's icon.
- When no node on its path is in the catalog, the app has no icon. Record it as `none`, and the top bar and the auth cards show the name alone.
- Never invent, redraw, or borrow a near match from another node.

---

## Do / Don't

**Do**
- Color every icon with `--solution-icon` and nothing else.
- Embed inline, from one `<symbol>` sprite, or as a mask colored by the token.
- Use the standard sizes: 20px in the top bar, 24px in lists and table rows, 40px on cards and tiles, 48px on auth cards.
- Use the recorded app icon, and each listed item's own node icon.
- Use the node's `files.icon`, and `outline.svg` or `in-parent.svg` only when the developer asks.
- Read paths from `catalog.json`.
- Copy only the icons the app uses into `<BRAND_ASSETS_PATH>`.

**Don't**
- Put an icon in place of the company logo in the sidebar brand block, or use one as the favicon.
- Use an icon as a navigation leaf or group icon. Those stay on the inline icon registry in `assets.md`.
- Show an icon in any color other than `--solution-icon`, or recolor it.
- Stretch, crop, redraw, plate, or pad an icon differently.
- Rasterize an icon to a fixed-color PNG for the app UI.
- Embed an icon with `<img src>`.
- Invent an icon, or borrow a near match, for a node the catalog does not cover.
- Build a path from a node's name instead of reading it from the catalog.

---

## Catalog

Every node in the pack, generated from `catalog.json`, one table per solution, rows in tree order: each node is followed by its children. Every path is relative to `.claude/skills/ui-ux-design/assets/solution-icons/`. Icon is the node's `files.icon`; Other files lists `outline` and `in-parent` where marketing drew them.

### Agentic CRM (`agentic-crm`)

| Level | Name | Icon | Other files |
| --- | --- | --- | --- |
| Solution | Agentic CRM | `agentic-crm/icon.svg` | outline `agentic-crm/outline.svg` |
| Module | Agentic Contact Manager | `agentic-crm/agentic-contact-manager.svg` | - |
| Module | Agentic Leads Generator | `agentic-crm/agentic-leads-generator.svg` | - |
| Module | Agentic Sales Manager | `agentic-crm/agentic-sales-manager.svg` | - |
| Module | Agentic Contents Builder | `agentic-crm/agentic-contents-builder.svg` | - |
| Module | Agentic Campaign Builder | `agentic-crm/agentic-campaign-builder.svg` | - |
| Module | Agentic SEO Manager | `agentic-crm/agentic-seo-manager.svg` | - |
| Module | Agentic Marketing Manager | `agentic-crm/agentic-marketing-manager.svg` | - |
| Module | Agentic Shopfront | `agentic-crm/agentic-shopfront.svg` | - |
| Module | Agentic Customer Success Manager | `agentic-crm/agentic-customer-success-manager.svg` | - |
| Module | Agentic Sales Concierge | `agentic-crm/agentic-sales-concierge.svg` | - |
| Module | CRM Intelligence | `agentic-crm/crm-intelligence.svg` | - |

### Agentic Product (`agentic-product`)

| Level | Name | Icon | Other files |
| --- | --- | --- | --- |
| Solution | Agentic Product | `agentic-product/icon.svg` | outline `agentic-product/outline.svg` |
| Module | Agentic Product Manager | `agentic-product/agentic-product-manager.svg` | - |
| Module | Agentic Product Marketer | `agentic-product/agentic-product-marketer.svg` | - |

### Adaptive CLaaS (`adaptive-claas`)

| Level | Name | Icon | Other files |
| --- | --- | --- | --- |
| Solution | Adaptive CLaaS | `adaptive-claas/icon.svg` | outline `adaptive-claas/outline.svg` |
| Module | CLaaS Talents | `adaptive-claas/claas-talents.svg` | - |
| Module | CLaaS Developer | `adaptive-claas/claas-developer.svg` | - |
| Module | CLaaS Manager | `adaptive-claas/claas-manager.svg` | - |
| Module | CLaaS Mentor | `adaptive-claas/claas-mentor.svg` | - |
| Module | CLaaS Operations | `adaptive-claas/claas-operations.svg` | - |

### Agentic ERP (`agentic-erp`)

| Level | Name | Icon | Other files |
| --- | --- | --- | --- |
| Solution | Agentic ERP | `agentic-erp/icon.svg` | outline `agentic-erp/outline.svg` |
| Module | Agentic Finance | `agentic-erp/agentic-finance.svg` | - |
| Module | Agentic Procurement | `agentic-erp/agentic-procurement.svg` | - |
| Module | Agentic Operations | `agentic-erp/agentic-operations.svg` | - |
| Module | Agentic Compliance | `agentic-erp/agentic-compliance.svg` | - |

### Enterprise Intelligence OS (EOS) (`eos`)

| Level | Name | Icon | Other files | Note |
| --- | --- | --- | --- | --- |
| Solution | Enterprise Intelligence OS (EOS) | `eos/icon.svg` | outline `eos/outline.svg` | Marketing named this solution Enterprise Intelligence Fabric. |
| Module | Integration Hub | `eos/integration-hub/icon.svg` | outline `eos/integration-hub/outline.svg`, in-parent `eos/integration-hub/in-parent.svg` | - |
| Sub-module | Enterprise MDM | `eos/integration-hub/enterprise-mdm/icon.svg` | outline `eos/integration-hub/enterprise-mdm/outline.svg` | - |
| Feature | Kernel MDM | `eos/integration-hub/enterprise-mdm/kernel-mdm.svg` | - | - |
| Feature | CLaaS MDM | `eos/integration-hub/enterprise-mdm/claas-mdm.svg` | - | - |
| Feature | CRM MDM | `eos/integration-hub/enterprise-mdm/crm-mdm.svg` | - | - |
| Feature | Operation MDM | `eos/integration-hub/enterprise-mdm/operation-mdm.svg` | - | - |
| Feature | Finance MDM | `eos/integration-hub/enterprise-mdm/finance-mdm.svg` | - | - |
| Feature | Compliance MDM | `eos/integration-hub/enterprise-mdm/compliance-mdm.svg` | - | - |
| Sub-module | Enterprise MicroServices | `eos/integration-hub/enterprise-microservices.svg` | - | - |
| Sub-module | Intelligence OS Agents | `eos/integration-hub/intelligence-os-agents.svg` | - | - |
| Sub-module | Enterprise Control Centre | `eos/integration-hub/enterprise-control-centre.svg` | - | - |
| Sub-module | Security Control Centre | `eos/integration-hub/security-control-centre.svg` | - | - |
| Sub-module | Content Processor | `eos/integration-hub/content-processor.svg` | - | Not in the dev task sheet. |
| Sub-module | Task Scheduler | `eos/integration-hub/task-scheduler.svg` | - | Not in the dev task sheet. |
| Sub-module | Process Tracker (Microsoft) | `eos/integration-hub/process-tracker-microsoft.svg` | - | Not in the dev task sheet. |
| Module | Knowledge Hub | `eos/knowledge-hub/icon.svg` | outline `eos/knowledge-hub/outline.svg`, in-parent `eos/knowledge-hub/in-parent.svg` | - |
| Sub-module | Enterprise Memory System | `eos/knowledge-hub/enterprise-memory-system.svg` | - | - |
| Sub-module | Knowledge Ingestion | `eos/knowledge-hub/knowledge-ingestion.svg` | - | - |
| Sub-module | Structured Data Management | `eos/knowledge-hub/structured-data-management.svg` | - | - |
| Sub-module | Unstructured Data Management | `eos/knowledge-hub/unstructured-data-management.svg` | - | - |
| Sub-module | Enterprise Knowledge Growth | `eos/knowledge-hub/enterprise-knowledge-growth.svg` | - | - |
| Sub-module | Semantic Search & AI Retrieval | `eos/knowledge-hub/semantic-search-and-ai-retrieval.svg` | - | - |
| Sub-module | Knowledge Governance | `eos/knowledge-hub/knowledge-governance.svg` | - | - |
| Module | Operations Hub | `eos/operations-hub/icon.svg` | outline `eos/operations-hub/outline.svg`, in-parent `eos/operations-hub/in-parent.svg` | - |
| Sub-module | Enterprise Operations Engine | `eos/operations-hub/enterprise-operations-engine.svg` | - | - |
| Sub-module | SOP Management | `eos/operations-hub/sop-management.svg` | - | - |
| Sub-module | Resource Management | `eos/operations-hub/resource-management.svg` | - | - |
| Sub-module | Fulfillment & Compliance | `eos/operations-hub/fulfillment-and-compliance.svg` | - | - |
| Module | Intelligence Hub | `eos/intelligence-hub/icon.svg` | outline `eos/intelligence-hub/outline.svg`, in-parent `eos/intelligence-hub/in-parent.svg` | - |
| Sub-module | Enterprise Data Analytics | `eos/intelligence-hub/enterprise-data-analytics/icon.svg` | outline `eos/intelligence-hub/enterprise-data-analytics/outline.svg`, in-parent `eos/intelligence-hub/enterprise-data-analytics/in-parent.svg` | - |
| Feature | Marketing Analytics | `eos/intelligence-hub/enterprise-data-analytics/marketing-analytics.svg` | - | - |
| Feature | Sales Analytics | `eos/intelligence-hub/enterprise-data-analytics/sales-analytics.svg` | - | - |
| Feature | Post Sales Analytics | `eos/intelligence-hub/enterprise-data-analytics/post-sales-analytics.svg` | - | - |
| Feature | Key Accounts Analytics | `eos/intelligence-hub/enterprise-data-analytics/key-accounts-analytics.svg` | - | - |
| Feature | Talent Analytics | `eos/intelligence-hub/enterprise-data-analytics/talent-analytics.svg` | - | - |
| Feature | Learning Analytics | `eos/intelligence-hub/enterprise-data-analytics/learning-analytics.svg` | - | - |
| Feature | Product Analytics | `eos/intelligence-hub/enterprise-data-analytics/product-analytics.svg` | - | - |
| Feature | Resourcing Analytics | `eos/intelligence-hub/enterprise-data-analytics/resourcing-analytics.svg` | - | - |
| Feature | Compliance Analytics | `eos/intelligence-hub/enterprise-data-analytics/compliance-analytics.svg` | - | - |
| Feature | Finance Analytics | `eos/intelligence-hub/enterprise-data-analytics/finance-analytics.svg` | - | - |
| Feature | Customer 360 | `eos/intelligence-hub/enterprise-data-analytics/customer-360.svg` | - | - |
| Feature | Product 360 | `eos/intelligence-hub/enterprise-data-analytics/product-360.svg` | - | - |
| Feature | Operation 360 | `eos/intelligence-hub/enterprise-data-analytics/operation-360.svg` | - | - |
| Sub-module | Enterprise Decision Automation | `eos/intelligence-hub/enterprise-decision-automation/icon.svg` | outline `eos/intelligence-hub/enterprise-decision-automation/outline.svg`, in-parent `eos/intelligence-hub/enterprise-decision-automation/in-parent.svg` | - |
| Feature | AI Models Life Cycle Management | `eos/intelligence-hub/enterprise-decision-automation/ai-models-life-cycle-management.svg` | - | - |
| Feature | AI Agents Orchestration Management | `eos/intelligence-hub/enterprise-decision-automation/ai-agents-orchestration-management.svg` | - | - |
| Feature | Decision Automation Management | `eos/intelligence-hub/enterprise-decision-automation/decision-automation-management.svg` | - | - |
| Sub-module | Enterprise Decision (AI) Tower | `eos/intelligence-hub/enterprise-decision-ai-tower.svg` | - | - |
| Sub-module | Enterprise AI Orchestration | `eos/intelligence-hub/enterprise-ai-orchestration.svg` | - | - |
