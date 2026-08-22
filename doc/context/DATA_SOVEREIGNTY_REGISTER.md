# Data Sovereignty Register

This is the authoritative living map of customer data storage and processing boundaries. Populate per organisation/environment. Do not put secrets or raw customer data in this file.

## Organisation policy summary

| Field | Value |
|---|---|
| Organisation / Tenant ID | TBD |
| Tenant home geography | TBD |
| Approved storage geographies | TBD |
| Approved processing geographies | TBD |
| Cross-geo processing allowed | No (default) |
| Cross-geo storage allowed | No (default) |
| Conversation history outside approved geo allowed | No (default) |
| Policy approver | TBD |
| Last reviewed | TBD |

## Data-flow register

| Flow ID | From | To | Data/classification | Storage geo | Processing geo | Network path | AI/model/runtime | Cross-boundary? | Exception ID | Result | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| SOV-000 | _Example_ | _Example_ | Internal metadata | TBD | TBD | TLS/private as policy requires | N/A | TBD | None | Draft | TBD |

## High-impact Fabric/AI settings

| Setting | Expected policy | Actual | Evidence | Result |
|---|---|---|---|---|
| Azure Private Link / workspace inbound protection | Policy driven | TBD | TBD | Not verified |
| Block Public Internet Access | Policy driven | TBD | TBD | Not verified |
| Workspace CMK | Policy driven | TBD | TBD | Not verified |
| AI data processed outside capacity geo/boundary | OFF unless approved | TBD | TBD | Not verified |
| AI data stored outside capacity geo/boundary | OFF unless approved | TBD | TBD | Not verified |
| Conversation history outside capacity geo/boundary | OFF unless approved | TBD | TBD | Not verified |
