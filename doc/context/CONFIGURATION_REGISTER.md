# Configuration Register

Behaviour-changing configuration is typed, validated, scoped and documented. Secrets live only in the server environment or an approved secret manager; this register holds metadata, never values.

| Config ID | Key | Scope | Type | Default | Required | Secret | Security / residency impact | Source of truth | Status |
|---|---|---|---|---|---|---|---|---|---|
| CFG-APP-001 | `APP_NAME` | Application | string | SemantIQ | Yes | No | Shown in the top bar and the browser title | Server `.env` | Implemented |
| CFG-BRAND-001 | `brand.assets_path` | Application | string | `/brand` | Yes | No | Where the approved logo and favicon pack is served from | `config/brand.php` | Implemented |
| CFG-AUTH-001 | `services.microsoft.tenant` / `client_id` / `redirect` | Application | string | none | For SSO | No | Must match the Entra app registration exactly | Server `.env` | Implemented, unset on the server |
| CFG-AUTH-002 | `services.microsoft.client_secret` | Application | string | none | For SSO | **Yes** | Compromise permits impersonation of the application. Never committed, never sent to the browser, never logged | Server `.env` | Implemented, unset on the server |
| CFG-NAV-001 | `navigation.policies` | Application | map | see file | Yes | No | Defines the minimum tier, Auditor acceptance and domain requirement for every gated node and route | `config/navigation.php` | Implemented |
| CFG-NAV-002 | `navigation.clusters` | Application | tree | see file | Yes | No | The rail, generated from `doc/MENU_STRUCTURE.md` and mapped onto the four fixed clusters | `config/navigation.php` | Implemented |
| CFG-SOV-001 | Approved storage and processing geographies | Organisation | list | unset | Before production | No | Unset blocks production activation | Not yet built | Planned, Phase 02 |
