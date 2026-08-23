# Data Sovereignty Register

The authoritative map of storage and processing boundaries. No secrets, no customer data.

**State at the end of Release 1 gate 2 (R1.2).**

The control-plane geography question is CLOSED: server, backups and replication are all Singapore, confirmed 25 August 2026.

Two things remain open, and neither is a technical gap. The Fabric side - the approved geographies for customer business data - has no resource provisioned yet and must be stated before Phase 02. And the applicable privacy regime is a legal determination rather than an engineering one (SEC-DEC-041).

## Organisation policy summary

| Field | Value |
|---|---|
| Deployment | One organisation, one Microsoft Entra tenant |
| Control-plane hosting geography | **Singapore (Asia)**, confirmed by the product owner 25 August 2026 |
| Tenant home geography | Not yet confirmed with the customer. The Microsoft Entra tenant is a separate question from where this server sits |
| Approved storage geographies | **Singapore** for the control plane, confirmed. The Fabric list for customer business data is still unset and must be stated before Phase 02 provisioning |
| Approved processing geographies | **Singapore** for the control plane, confirmed. The Fabric list is still unset and must be stated before Phase 02 provisioning |
| Cross-geo processing / storage / conversation history | No, all three. Default and not yet overridable |
| Last reviewed | 2026-08-25 |
| Backup geography | **Singapore (Asia)**, confirmed by the product owner 25 August 2026 |
| Replication outside Singapore | **None**, confirmed by the product owner 25 August 2026 |

## Data-flow register

| Flow ID | From | To | Data / classification | Storage geo | Cross-boundary | Result |
|---|---|---|---|---|---|---|
| SOV-001 | Browser | Control plane (cPanel) | Session, account identity, entitlements / Internal to Confidential | cPanel MySQL, **Singapore** | No | **VERIFIED 25 August 2026.** Server, backups and replication all confirmed Singapore-only |
| SOV-002 | Control plane | `login.microsoftonline.com` | Authorization code, client credential, ID token / Confidential | Not stored by Microsoft on our behalf | Yes, inherent to federated identity | Accepted. No customer business data is sent |
| SOV-003 | Microsoft Entra / Graph | `users` | Display name, work email, directory object id / Confidential, personal | Control plane | Same question as SOV-001 | Minimal by design: no group membership, no photo, no directory graph copy |
| SOV-004 | Customer Fabric estate | Nothing | Business metrics and insights | Customer OneLake only | Not applicable | No data source exists in this phase |
| SOV-005 | Administrator actions | `audit_events` | Actor identity, IP address, redacted change summaries / Internal, personal | cPanel MySQL, **Singapore** | No | **VERIFIED.** Singapore-only. Added in Release 1 gate 1. The rows are minimised by design - metadata and redacted summaries only, never a payload copy - and the IP address is personal data held in Singapore under the seven-year retention baseline |
| SOV-006 | Administrator configuration | `system_settings`, `feature_flags` | Non-secret configuration values / Internal | cPanel MySQL, **Singapore** | No | **VERIFIED.** Singapore-only. No credential and no customer data may be stored in either table, and the writer refuses a secret-bearing key |
| SOV-007 | Organisation structure and access | `business_units`, `teams`, `roles`, `role_permissions`, `user_roles`, `users` identity context | Organisational structure, authority and entitlements / Internal to Confidential, personal | cPanel MySQL, **Singapore** | No | **VERIFIED.** Singapore-only. Added in Release 1 gate 2. No business payload is stored - these describe who may reach information, never the information itself |
| SOV-008 | Access review evidence | `access_reviews`, `access_review_items` | Who held what access and what was decided / Internal, personal | cPanel MySQL, **Singapore** | No | **VERIFIED.** Singapore-only. A review is a list of who can read what about a customer, and it carries the seven-year retention baseline |

## High-impact settings

| Setting | Expected | Actual | Result |
|---|---|---|---|
| Cross-geo processing | Off unless approved | Off | Correct by default; no workload exists |
| Cross-geo storage | Off unless approved | Off | Correct by default; no workload exists |
| Conversation history outside geography | Off unless approved | Off | Correct by default; Ask SemantIQ is not built |
| Control-plane hosting geography | Confirmed before go-live | **Singapore** | **Confirmed** 25 August 2026 |
| Backup geography | Confirmed before go-live | **Singapore** | **Confirmed** 25 August 2026 |
| Replication outside the approved geography | None unless approved | **None** | **Confirmed** 25 August 2026 |
| Applicable privacy regime | Determined before production acceptance | **Not determined** | **Open item.** Singapore hosting makes the PDPA the likely regime, but CLAUDE.md requires legal applicability to be confirmed rather than assumed by code |
