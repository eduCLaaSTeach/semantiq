# Data Sovereignty Register

This is the authoritative living map of customer data storage and processing boundaries. Populate per organisation/environment. Do not put secrets or raw customer data in this file.

**State at the end of Phase 00 work items W1 to W3.** The mechanism exists and the values do not. Decision D5 in `doc/execution/PHASE-00-PLAN.md` defers the customer's approved geographies to Phase 02, where a Fabric resource is first provisioned. Until they are stated, `VAL-SOV-GEO-001` must return BLOCKED for any production activation: an unset geography is a refusal, never a pass. Setting these values is a recorded Phase 02 precondition.

## Organisation policy summary

| Field | Value |
|---|---|
| Organisation / Tenant ID | One organisation, one Microsoft Entra tenant. The Entra tenant GUID is server configuration and is not recorded here |
| Tenant home geography | Not yet confirmed with the customer |
| Approved storage geographies | Unset. Blocks production activation (decision D5) |
| Approved processing geographies | Unset. Blocks production activation (decision D5) |
| Cross-geo processing allowed | No (default, enforced by column default) |
| Cross-geo storage allowed | No (default, enforced by column default) |
| Conversation history outside approved geo allowed | No (default, enforced by column default) |
| Policy approver | To be named by the customer before Phase 02 |
| Last reviewed | 2026-08-22 |

## Data-flow register

| Flow ID | From | To | Data/classification | Storage geo | Processing geo | Network path | AI/model/runtime | Cross-boundary? | Exception ID | Result | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| SOV-001 | Browser | SemantIQ control plane (cPanel) | Session, organisation configuration / Internal | cPanel MySQL, hosting geography to be confirmed | Same host | HTTPS | None | To be determined once the hosting geography is confirmed | None | Not verified: hosting geography must be confirmed and recorded before go-live | `doc/execution/PHASE-00-VERIFICATION.md` (pending) |
| SOV-002 | SemantIQ control plane | Microsoft identity platform (`login.microsoftonline.com`) | Authorization code, client credential, ID token / Confidential | Not stored by Microsoft on SemantIQ's behalf | Microsoft global identity service | HTTPS, direct TLS, client authentication on the token call | None | Yes, by the nature of federated identity. Unavoidable and accepted for authentication | None required: no customer business data is sent | Accepted | `tests/Feature/Auth/SignInTest.php` |
| SOV-003 | Microsoft Entra ID | `users` table | Display name, work email, directory object ID / Confidential, personal data | Control plane | Control plane | HTTPS | None | Same question as SOV-001 | None | Minimal by design: no group membership, no photo, no directory graph copy | `app/Http/Controllers/Auth/MicrosoftSignInController.php` |
| SOV-004 | SemantIQ control plane | `audit_events` | Actor label, action, target, before/after hash / Internal | Control plane | Control plane | Local | None | No | None | Hashes rather than payload copies, per NFR-COMP-01 | `tests/Feature/Data/ConfigurationDataModelTest.php` |
| SOV-005 | Customer Fabric tenant | `fabric_items` | Fabric resource identifiers only / Internal | Control plane holds identifiers; item data never leaves the customer's OneLake | Customer capacity geography for the data; control plane for the metadata | HTTPS (from Phase 02) | None in Phase 00 | Metadata only | None | Planned. No Microsoft call is made in Phase 00 | Phase 02 |

## High-impact Fabric/AI settings

| Setting | Expected policy | Actual | Evidence | Result |
|---|---|---|---|---|
| Azure Private Link / workspace inbound protection | Policy driven | Not applicable in Phase 00; no Fabric resource provisioned | Profile field `public_internet_access_allowed` exists, default false | Not verified (Phase 02) |
| Block Public Internet Access | Policy driven | Not applicable in Phase 00 | Profile field exists, default false | Not verified (Phase 02) |
| Workspace CMK | Policy driven | Not applicable in Phase 00 | Profile field `customer_managed_key_required` exists, default false | Not verified (Phase 02) |
| AI data processed outside capacity geo/boundary | OFF unless approved | OFF | `cross_geo_processing_allowed` default false, asserted in test | Verified as a default; no AI workload exists yet |
| AI data stored outside capacity geo/boundary | OFF unless approved | OFF | `cross_geo_storage_allowed` default false, asserted in test | Verified as a default; no AI workload exists yet |
| Conversation history outside capacity geo/boundary | OFF unless approved | OFF | `conversation_history_outside_geo_allowed` default false, asserted in test | Verified as a default; no conversational feature exists yet |
| Control-plane database hosting geography | Must be confirmed before go-live | Unknown | cPanel hosting details not yet recorded | **Open item.** Must be confirmed and recorded, not assumed |
