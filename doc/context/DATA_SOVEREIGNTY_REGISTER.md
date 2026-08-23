# Data Sovereignty Register

The authoritative map of storage and processing boundaries. No secrets, no customer data.

**State at the end of Release 1 gate 2 (R1.2).** No Fabric resource is provisioned and no customer business data has entered the system, so most of this register is necessarily empty. The two things that are true today are recorded honestly rather than left blank.

## Organisation policy summary

| Field | Value |
|---|---|
| Deployment | One organisation, one Microsoft Entra tenant |
| Tenant home geography | Not yet confirmed with the customer |
| Approved storage geographies | Unset. Must be stated before Phase 02 provisioning |
| Approved processing geographies | Unset. Must be stated before Phase 02 provisioning |
| Cross-geo processing / storage / conversation history | No, all three. Default and not yet overridable |
| Last reviewed | 2026-08-25 |

## Data-flow register

| Flow ID | From | To | Data / classification | Storage geo | Cross-boundary | Result |
|---|---|---|---|---|---|---|
| SOV-001 | Browser | Control plane (cPanel) | Session, account identity, entitlements / Internal to Confidential | cPanel MySQL, **geography unconfirmed** | Undetermined until the hosting geography is recorded | **Not verified.** Must be confirmed before go-live |
| SOV-002 | Control plane | `login.microsoftonline.com` | Authorization code, client credential, ID token / Confidential | Not stored by Microsoft on our behalf | Yes, inherent to federated identity | Accepted. No customer business data is sent |
| SOV-003 | Microsoft Entra / Graph | `users` | Display name, work email, directory object id / Confidential, personal | Control plane | Same question as SOV-001 | Minimal by design: no group membership, no photo, no directory graph copy |
| SOV-004 | Customer Fabric estate | Nothing | Business metrics and insights | Customer OneLake only | Not applicable | No data source exists in this phase |
| SOV-005 | Administrator actions | `audit_events` | Actor identity, IP address, redacted change summaries / Internal, personal | cPanel MySQL, **geography unconfirmed** | Same question as SOV-001 | **Not verified.** Added in Release 1 gate 1. The rows are minimised by design - metadata and redacted summaries only, never a payload copy - but the IP address is personal data and inherits SOV-001's open question |
| SOV-006 | Administrator configuration | `system_settings`, `feature_flags` | Non-secret configuration values / Internal | cPanel MySQL, **geography unconfirmed** | Same question as SOV-001 | **Not verified.** No credential and no customer data may be stored in either table, and the writer refuses a secret-bearing key |
| SOV-007 | Organisation structure and access | `business_units`, `teams`, `roles`, `role_permissions`, `user_roles`, `users` identity context | Organisational structure, authority and entitlements / Internal to Confidential, personal | cPanel MySQL, **geography unconfirmed** | Same question as SOV-001 | **Not verified.** Added in Release 1 gate 2. No business payload is stored - these describe who may reach information, never the information itself |
| SOV-008 | Access review evidence | `access_reviews`, `access_review_items` | Who held what access and what was decided / Internal, personal | cPanel MySQL, **geography unconfirmed** | Same question as SOV-001 | **Not verified.** A review is a list of who can read what about a customer, so it inherits the same open geography question and the same seven-year retention baseline |

## High-impact settings

| Setting | Expected | Actual | Result |
|---|---|---|---|
| Cross-geo processing | Off unless approved | Off | Correct by default; no workload exists |
| Cross-geo storage | Off unless approved | Off | Correct by default; no workload exists |
| Conversation history outside geography | Off unless approved | Off | Correct by default; Ask SemantIQ is not built |
| Control-plane hosting geography | Confirmed before go-live | **Unknown** | **Open item.** Confirm and record; do not assume |
