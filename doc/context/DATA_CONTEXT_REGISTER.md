# Data Context Register

Every production entity has a named owner, classification, residency and retention before go-live.

The control plane runs on cPanel MySQL. **Its hosting geography is unconfirmed** and is carried as an open item rather than assumed.

| Data ID | Entity / fields | Source & owner | Purpose | Classification / personal | Storage | Retention | Access | Validation | Status |
|---|---|---|---|---|---|---|---|---|---|
| DATA-001 | `users` (name, email, `entra_object_id`, `entra_tenant_id`) | Microsoft Entra ID / customer directory owner | Local mirror of a directory account, so authorisation and audit have something to attach to | Confidential / Yes: name, work email, directory object id | Control plane | Removed when the directory account is deprovisioned | Administrator and above | VAL-AUTH-STATE-001 | Implemented |
| DATA-002 | `users.password` | SemantIQ | Fallback for accounts the directory does not hold | Restricted / No | Control plane, hashed | With the account | Never read back | VAL-AUTH-PAIR-001 | Implemented |
| DATA-003 | `users.role` | SemantIQ / Administrator | The platform authority tier | Internal / No | Control plane | With the account | Administrator and above | VAL-NAV-POLICY-001 | Implemented |
| DATA-004 | `users.is_auditor` | SemantIQ / System Administrator | Compliance-evidence access without an operational tier | Internal / No | Control plane | With the account | System Administrator | VAL-NAV-AUDITOR-001 | Implemented |
| DATA-005 | `domain_entitlements` (domain, scope, granted_by) | SemantIQ / Administrator | Which business domains an account may see. The second access dimension | Internal / No | Control plane | Retained as a permission-change record | Administrator and above | VAL-NAV-DOMAIN-001 | Implemented |
| DATA-006 | `sessions` | Laravel | Signed-in session state | Confidential / Yes: user id, IP | Control plane | Cleared on expiry or sign-out | None, framework internal | None | Implemented |
| DATA-007 | Business metrics, KPIs, insights | Customer Fabric estate | The intelligence the product exists to deliver | Confidential to Restricted by domain | Customer OneLake, NOT the control plane | Customer policy | Domain entitlement plus row, object and field security | VAL-SOV-GEO-001 | Not implemented. No data source exists in this phase |
