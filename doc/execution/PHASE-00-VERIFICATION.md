# Phase 00 Verification - Sign-In, Landing Page and Navigation Framework

**Scope verified:** work items W1 to W10 of `doc/execution/PHASE-00-PLAN.md`.
**Date:** 23 August 2026

---

## 1. Automated tests

`php artisan test`

```
Tests:    67 passed (229 assertions)
```

`vendor/bin/pint --test` - passed.

| Suite | Covers |
|---|---|
| `Auth/SignInTest` | Credential path, error shapes, throttling, session regeneration |
| `Auth/MicrosoftSignInTest` | PKCE, single-use state, nonce mismatch, declined consent, refused exchange, unconfigured deployment |
| `Shell/ShellTest` | Rendered shell, absent-not-dimmed gating, theme switcher order, toast host |
| `Shell/NavigationTest` | Cluster order, empty-group collapse, breadcrumb, unknown policy denies |
| `Shell/AccessModelTest` | Six tiers, Auditor flag, domain entitlement, route enforcement |

## 2. Migrations

Verified `up`, then `down`, then `up` again on SQLite. Both new migrations reverse cleanly.

The role remap is data as well as schema: existing rows carrying the old five-tier codes are rewritten, because an unmapped `self_view` matches no case in the new enum and that account would be locked out rather than downgraded. The reverse direction places Analyst, which has no old equivalent, on the nearest tier below rather than silently promoting it.

## 3. Browser verification

Chromium, both themes, 1400px and 390px.

| Check | Result |
|---|---|
| Rail head and top bar heights | 52px and 52px, dividers form one line |
| Rail owns the top-left corner | Yes, rail top level with the top bar |
| Rail width | 240px expanded, 56px collapsed, persists across navigation |
| Collapsed brand mark | Short mark at rest, `i-panel` expander on hover, cross-fading |
| Theme switcher | Swaps theme, logos and favicon; System resolves from the OS |
| Nav filter | Narrows the rail and drops emptied clusters |
| Console errors | None |

### Role-shaped rendering

| Account | Clusters | Rail nodes | Notes |
|---|---|---|---|
| System Administrator, entitled to Executive, Sales, Finance | Workspace, Compliance, Application Administration, System Administration | 244 (218 `Soon`) | Every cluster |
| Analyst entitled to Sales only | Workspace | 67 | Only Sales Intelligence under My Intelligence. Explore visible |
| Viewer, no domains | Workspace | 47 | No Explore. My Intelligence degrades to a leaf |

Typing `/admin` as the Analyst returned **403**, not a redirect and not a page.

## 4. Security and privacy checks

| Check | Result |
|---|---|
| Route policy and rail agree for every tier | Asserted in a loop over all six tiers |
| Tier never implies a domain | A System Administrator with no entitlement is refused `domain-finance` |
| Auditor reaches Compliance without an operational tier | Asserted, and denied `app-admin`, `system-admin` and `analyst` |
| Inaccessible nodes absent from the markup | Asserted on a Viewer's rendered page |
| Sign-in failure discloses nothing | Unknown address and wrong password return byte-identical messages |
| Microsoft failure reasons never reach the browser | A declined consent is driven to the rendered page; the AADSTS code does not appear |
| No secret in client state | No Microsoft value is passed to any view or script |

## 5. Context registers

All six updated in the same change as the behaviour: code, data, validation, configuration, sovereignty, and security decisions.

## 6. Defects found and fixed during this phase

1. **Policy keys containing a dot were unreadable.** `config('navigation.policies.domain.sales')` resolves dots as nesting, so every domain policy silently returned null and denied. Renamed to `domain-sales`. Found by a test, not by reading.
2. **`entitledDomains()` matched nothing.** `pluck()` applies the model cast, so it returned `BusinessDomain` instances compared strictly against strings. The only symptom was an empty domain grid for a properly entitled user - invisible to every test that went through the rail. Found in the browser. A regression test now covers it.
3. **A routable group vanished when its children were filtered.** My Intelligence is both a page and a group; somebody entitled to no domains lost the one screen that explains why. It now degrades to a leaf.

## 7. Known gaps and deferred items

| Item | State |
|---|---|
| Control-plane hosting geography | **Unconfirmed.** Must be recorded before go-live, not assumed |
| Microsoft Entra | Unconfigured on the server; the button explains itself rather than working |
| First administrator | New accounts default to Viewer. The first System Administrator must be promoted directly in the database |
| React | Named in `CLAUDE.md` as the frontend baseline, not installed. Nothing in this phase needs it. Decision D5, open |
| Every domain, Ask SemantIQ, Explore, Decisions | Structure only. No data source, no AI provider, no metric |

## 8. Result against the Phase 00 acceptance criteria

| Criterion | Result |
|---|---|
| A business user lands in a business-facing shell, not Fabric setup | **Pass.** `/` is Home |
| Role and domain navigation changes with entitlement | **Pass.** Verified across three role shapes |
| Administrator entry is policy-gated | **Pass** |
| Direct navigation to an admin route is denied for business-only users | **Pass.** 403 |
| All major shell states represented | **Partial.** Empty, permission-denied, validation and small-screen are built. Loading and external-service-error states arrive with the first real data source |
| The UI follows the design-system authority | **Pass** |
| No unapproved AI or model dependency | **Pass.** None introduced |

**Overall: pass, with the gaps in section 7 recorded rather than closed.**
