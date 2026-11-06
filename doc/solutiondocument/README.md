# Solution Document Intake

This folder is the authoritative intake location for approved solution requirements.

## Current Recommended Category Structure
A normal project may contain:

1. `1_Solution Scope Document-Final/`
   - approved solution scope, objectives, modules, personas, in-scope/out-of-scope boundaries, business outcomes.
2. `2_Engagement App Workflow Specs/`
   - end-to-end workflows, subprocesses, decisions, conditions, approvals, handoffs, exception flows.
3. `3_Engagement App Data Points Specs/`
   - screen/process data points, fields, source, type, required/optional status, validation, usage.
4. `4_Analytics Specifications/`
   - dashboards, reports, KPIs, metrics, dimensions, filters, calculations, drill-down and analytics questions.
5. `5_Agent Specs/`
   - AI/agent roles, goals, triggers, inputs, outputs, tools, guardrails, knowledge sources, human approval points.
6. `6a_Engagement App UI Specs/`
   - navigation/menu structure, page/screen specification, controls, actions, behaviour, role visibility.
7. `6b_Engagement App Mockup Screens/`
   - HTML mockups, images, screenshots, wireframes or other screen references.
8. `7_Data Modelling Specs/`
   - entity/table concepts, attributes, relationships, grain, keys, history, dimensions/measures and semantic-model expectations.
9. `Archive/`
   - superseded or historical material. Ignored by planning by default.

## Dynamic Structure Rule
This category structure is a recommendation, not a hard-coded contract. New versions may add, remove, split, merge, or rename folders.

Planning agents must:
- recursively discover the actual active files and folders at run time;
- read all relevant active content before finalizing requirements;
- not require every recommended folder to exist;
- report missing information as a GAP rather than inventing it;
- ignore `Archive` by default;
- preserve source folder/file references in traceability.

## Source Precedence
Do not silently decide that one document overrides another. If active documents disagree, create a `CONFLICT` in `02_Gap_Conflict_Report.md`. A document marked Final/Approved may be noted as such, but a material contradiction still requires explicit resolution unless the repository's standards define a formal precedence rule.

Run `/generate-phase-plan` when the active source pack is ready.
