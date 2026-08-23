# Semantiq Engineering Context Index

**Document ID:** CTX-INDEX-001  
**Status:** Mandatory living engineering context

Claude Code and human contributors must read the relevant context files before planning/changing a feature and must update them when behavior changes.

| File | Purpose | Update trigger |
|---|---|---|
| `CODE_CONTEXT_REGISTER.md` | Why each material component exists, what it touches and how it is tested | New/changed component, service, job, handler, adapter |
| `DATA_CONTEXT_REGISTER.md` | Data owner/source/classification/residency/retention/lineage/access | New/changed dataset/entity/field/data flow |
| `VALIDATION_RULES_REGISTER.md` | Canonical validation IDs and server/client enforcement | New/changed validation/business rule |
| `CONFIGURATION_REGISTER.md` | Typed configuration, scope, secret flag, defaults and validation | New/changed config/feature flag/tenant setting |
| `DATA_SOVEREIGNTY_REGISTER.md` | Storage/processing geographies and cross-boundary decisions | New/changed data source, capacity, AI/model, network or region |
| `SECURITY_PRIVACY_DECISIONS.md` | Security/privacy/sovereignty decisions and exceptions | Non-trivial control choice or approved exception |

## No-context-drift rule

A pull request/phase is not verification-complete until the code, tests and these context registers describe the same behavior. If a register is not applicable, the phase verification report must say why.
