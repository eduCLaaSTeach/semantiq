# Validation Rules Register

Validation rules are canonical server-side rules. Client-side validation may improve UX but must not be the only enforcement for security, data-integrity, tenant, permission or sovereignty rules.

| Validation ID | Entity/field/action | Rule | Severity | Server enforcement | Client UX | Error/help message | Source requirement | Test IDs | Data/security/sovereignty relevance | Status |
|---|---|---|---|---|---|---|---|---|---|---|
| VAL-CORE-TENANT-001 | Organisation tenant ID | Authenticated tenant must match onboarded tenant for privileged actions | Block | Required | Show mismatch | Tenant does not match this organisation | FR-AUTH-002 | TBD | Tenant isolation | Planned |
| VAL-SOV-GEO-001 | Capacity/workspace/AI region | Storage/processing geography must be allowed by DataProtectionProfile or have active exception | Block | Required | Preflight warning/block | Selected region is outside the approved data boundary | REF-DPS-001 | TBD | Data sovereignty | Planned |
