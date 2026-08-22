# Configuration Register

All behavior-changing configuration must be typed, validated, scoped and documented. Secrets are stored only in the approved secret manager; this register stores metadata, never secret values.

| Config ID | Key / setting | Scope | Type | Default | Required | Secret? | Allowed values / validation | Security / residency impact | Source of truth | Change approval | Test IDs | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| CFG-SOV-001 | `DataProtection:ApprovedStorageGeographies` | Organisation | list | none | Production | No | Non-empty approved geo IDs | Blocks unsupported storage placement | Semantiq config DB | Data Protection Admin | TBD | Planned |
| CFG-SOV-002 | `DataProtection:CrossGeoProcessingAllowed` | Organisation | bool | false | Yes | No | true only with active exception/approval | Controls cross-geo processing | Semantiq config DB | Data Protection Admin | TBD | Planned |
| CFG-SOV-003 | `DataProtection:CrossGeoStorageAllowed` | Organisation | bool | false | Yes | No | true only with active exception/approval | Controls cross-geo storage | Semantiq config DB | Data Protection Admin | TBD | Planned |
| CFG-LOG-001 | `Observability:CaptureProductionPayloads` | Environment | bool | false | Yes | No | false unless time-bound support approval | Prevents sensitive payload leakage | Deployment config | Security Admin | TBD | Planned |
