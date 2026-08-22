# SemantIQ v1.3 Conflict Resolution

This file records conflicts found between the existing repository README/CLAUDE baseline and the v1.2 phased implementation kit.

| Topic | Existing repository | v1.2 package | v1.3 resolution |
| --- | --- | --- | --- |
| Documentation root | `doc/` | `docs/` | Use `doc/` everywhere. Preserve the existing design-system path. |
| Implementation status location | Root guidance implied | Several package references used `docs/IMPLEMENTATION_STATUS.md` | Keep `IMPLEMENTATION_STATUS.md` at repository root. |
| App stack | Laravel 13/PHP 8.5, React 19, MySQL/cPanel, modular monolith | Stack-neutral, sometimes mentions .NET/Python for AI | Confirm Laravel/React/MySQL as primary runtime. Other runtimes require explicit architecture approval. |
| Current tenancy | Single-tenant | SRS said multi-tenant SaaS default | Current deployment is one customer organisation/Entra tenant per instance. Architecture is multi-tenant-ready and organisation-scoped; shared multi-customer SaaS is future/approved scope. |
| SSO default | Single-tenant context implied | Multi-tenant SemantIQ SSO default | Current release uses customer-tenant SSO. Multi-tenant SSO is a future/enterprise option requiring approval. |
| Git branches | `main` only, live deploy trigger | Prior guidance could be read as phase/DEV/TEST/PROD branches | `main` remains only long-lived deploy branch. Short-lived PR/phase branches are allowed; Fabric DEV/TEST/PROD refers to Fabric workspaces, not Git branches. |
| Deployment environment | GitHub environment named `development`, but deploy is live | Not modelled | Treat as a naming/control mismatch. Do not silently rename; harden/rename through an approved deployment change. |
| Secrets | Deploy secrets in GitHub; DB values server `.env`; README also incorrectly said no credential can be in GitHub secrets | Vault/secret-manager guidance | Deployment credentials required by CI belong in GitHub Environment/Actions secrets. Runtime DB/app secrets belong server-side/secret manager. Never commit either. |
| Data retention | Seven years | Configurable customer/regulatory retention | Seven years is current project-policy baseline, implemented as configurable policy, not a universal legal constant. |
| Privacy regime | Not determined | Strong privacy/sovereignty controls | Legal regime remains undecided. Privacy-by-design and sovereignty controls are mandatory engineering baseline; legal applicability is confirmed before production acceptance. |
| UI authority | Existing design-system template is sole authority | Generic screen requirements | Existing `doc/design-system/ui-and-ux-layout-template-shared.md` wins on layout/theme/brand. SRS controls feature behaviour, not conflicting visual styling. |
| AI runtime | Primary app PHP/React | Microsoft Agent Framework .NET/Python and open-source runtimes proposed | Evaluate them, but any new runtime is a separately approved sidecar/service. Fabric Data Agent/Copilot Studio are preferred when they satisfy the use case without extra runtime. |
| Application state | No app scaffold yet | Phase 00 foundation | Phase 00 starts with repository assessment and scaffolding; do not claim build/tests can pass before `composer.json`/`package.json` exist. |

If future repository facts differ from this file, verify the actual branch/files and update this decision record through the phase process.
