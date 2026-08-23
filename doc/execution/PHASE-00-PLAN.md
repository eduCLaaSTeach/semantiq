# Phase 00 Plan - Business Shell, Navigation Framework and Sign-In

**Phase:** P00 - Engineering Foundation and Business Experience Shell
**References:** `doc/phases/PHASE-00-UI-SHELL.md`, `doc/MENU_STRUCTURE.md` v1.1, `doc/ROLE_MODEL.md` v1.0, `.claude/reference-template/ui-and-ux-layout-template-shared.md`
**Status:** AWAITING APPROVAL. The four blocking decisions below are answered; no work in section 6 has begun.

---

## 1. Verified repository observations

Observed by reading the repository at merge commit `0f5f9f0`, not assumed.

| Item | Observed |
|---|---|
| Backend | Laravel 13.26.1, PHP 8.4 in this container, 8.5 on the deploy runner |
| Frontend | Vite 8, no CSS framework. Tailwind was removed: the design template forbids a second design system |
| React | NOT installed. Screens so far are server-rendered Blade |
| Database | SQLite locally, MySQL on cPanel |
| CI | `.github/workflows/ci.yml` runs Pint and PHPUnit on every pull request |
| Deploy | `.github/workflows/deploy.yml`, push to `main`, live |
| Tests | 50 passing, 185 assertions |

### Already built and on this branch

| Area | State |
|---|---|
| Design tokens | `resources/css/app.css`, section 4 transcribed, both themes, self-hosted fonts |
| Sign-in screen | Archetype 5.7, credential path, throttling, session regeneration |
| Microsoft Entra SSO | OIDC authorization code with PKCE, single-use state, nonce check. Unconfigured until server values are set |
| Application shell | Rail, top bar, profile menu, theme switcher, breadcrumb, toast host, collapse |
| Role tiers | Five template tiers on `users.role` |
| Navigation gate | `app/Support/Navigation.php`, filter-not-fork, unknown policy denies |

### Discrepancies found while reading

1. **`CLAUDE.md` points at `doc/design-system/ui-and-ux-layout-template-shared.md`, which does not exist.** The template is at `.claude/reference-template/ui-and-ux-layout-template-shared.md`. Same file, wrong path, referenced in several places. Correcting the path is proposed in section 6.
2. **`CLAUDE.md` states there is no `composer.json` or `package.json`.** That was true of `main`; both exist on this branch. It instructs the reader to re-verify, which is what this row records.
3. **React 19 is named as the confirmed frontend baseline and is not installed.** Nothing built so far needs it. Raised as decision D5 below rather than resolved silently.

---

## 2. Conflicts between the authorities, and how they were settled

`CLAUDE.md` requires that a material conflict stops implementation and is put to the user rather than decided quietly. Four were found. All four were answered on 23 August 2026.

### D1. Navigation shape - ANSWERED: map into the four clusters

The design template makes the four clusters an ENFORCED closed set in fixed order. `MENU_STRUCTURE.md` gives eight business top-level items plus an Administration entry holding fifteen groups. Different shapes for the same rail.

Settled by mapping rather than by replacing either:

| Cluster | Holds |
|---|---|
| Workspace | Home, My Intelligence, Ask SemantIQ, Explore, Decisions & Alerts, Reports & Insights, My Workspace, Help |
| Compliance | Governance, Data Protection, Data Sovereignty, Audit |
| Application Administration | Organisation & Users, Data Sources, Data Engineering, Data Quality, Business Model, Semantic Intelligence, AI & Agents, Deployment, Monitoring |
| System Administration | Platform Overview, Fabric Environment, System Configuration |

Both documents are satisfied. The split also keeps the template's stated reason for a separate System Administration cluster intact: an administrator who can invite a colleague should not thereby hold every provider credential.

### D2. Role model - ANSWERED: six tiers, Auditor as a flag, entitlements separate

`ROLE_MODEL.md` names seven roles; the template defines five cumulative tiers. Auditor does not sit on a ladder at all - it is read-only yet sees compliance evidence, which cuts across tiers.

| Tier | Rank | ROLE_MODEL role |
|---|---|---|
| `system_admin` | 6 | System Administrator |
| `admin` | 5 | Administrator |
| `domain_owner` | 4 | Domain Owner |
| `analyst` | 3 | Analyst / Collaborator |
| `contributor` | 2 | Contributor |
| `viewer` | 1 | Viewer |

Auditor becomes a capability flag on an account, not a rung. Business-domain entitlement stays the second dimension `ROLE_MODEL.md` requires: a tier alone never grants business data.

This extends the template's five tiers to six, which section 7 permits with a documented per-app reason. This plan is that record.

### D3. Tree scope - ANSWERED: the full tree, with Soon pills on everything unbuilt

`MENU_STRUCTURE.md` section 15 puts the role-aware menu framework and the Administration boundary in Phase 00. Rendering the whole tree makes the product shape legible and genuinely exercises role filtering. Most of the rail will read `Soon`, which is the template's own treatment for a destination that exists but is not built.

### D4. Application name - ANSWERED: SemantIQ

The specification set uses SemantIQ throughout. `APP_NAME` changes from "CLaaS SemantiQ" to "SemantIQ".

### Settled by the precedence rule rather than by asking

- **`PHASE-00-UI-SHELL.md` section 2 places Organisation context, a Global Ask entry and Help in the global header.** The template's top bar is ENFORCED as app name, notifications, theme switcher and profile menu, with no action buttons and no global search. The template wins, and `PHASE-00-UI-SHELL.md` section 11 says so itself. Ask SemantIQ and Help become navigation nodes. Organisation context is moot while one deployment serves one organisation; it belongs in the profile menu when it stops being.
- **Depth.** Workspace to My Intelligence to Sales Intelligence to its leaves is two accordion levels, inside the three-level limit. No tab-strip overflow is required anywhere in the tree.

### D5. React - OPEN, does not block this phase

`CLAUDE.md` names React 19 as the confirmed frontend baseline. It is not installed, and nothing in this phase needs it: an auth screen and a navigation shell are better server-rendered, and every page here is placeholder content. Explore and Ask SemantIQ will want client state when they become real.

Proposal: leave it out until a screen needs it, and add it then rather than carrying an unused framework. Raised for a decision, not assumed.

---

## 3. Scope of this phase

In: the sign-in screen (built), the business Home landing page, the full role-aware navigation tree, the Administration boundary, and route-level policy enforcement.

Out: every domain page, Ask SemantIQ behaviour, Explore behaviour, any Microsoft Fabric call, any AI model or provider, and any real metric. `PHASE-00-UI-SHELL.md` section 3 is explicit that Phase 00 must not fake production insights.

---

## 4. Security and authorization

`ROLE_MODEL.md` section 5 is unambiguous that menu visibility is convenience only. Four layers, all of which must agree:

1. **Cluster and feature access** gates both the sidebar and the route.
2. **Route policy.** Every route carries its policy. A business-only user typing an admin URL is denied, which is a named Phase 00 acceptance criterion.
3. **Query scope** filters lists to the viewer's scope. Nothing queryable exists yet; the scope helper ships with the tier model.
4. **Permanent-delete guard.** Nothing deletable exists yet.

Domain entitlement is enforced alongside the tier from the start, because retrofitting a second dimension after screens assume one is the change this design exists to avoid.

---

## 5. Data protection and sovereignty

- **Classification:** Internal only. Account identity, role, entitlement and navigation state. No customer business data enters this phase.
- **Storage and processing geography:** the control-plane database on cPanel. Its hosting geography is unconfirmed and is recorded as an open item rather than assumed.
- **Cross-geo:** not applicable. Nothing is provisioned.
- **Logging:** no credential, token or personal data beyond the account identity already stored.

---

## 6. Work items

| # | Item |
|---|---|
| W1 | Extend `Role` to six tiers, add the Auditor capability flag and the domain-entitlement dimension. Migration plus a reversible `down()` |
| W2 | Rewrite `config/navigation.php` as the full tree from `MENU_STRUCTURE.md`, mapped per D1, every node carrying an icon and a policy |
| W3 | Extend the icon registry to cover the tree, in the one fixed style |
| W4 | Route-policy middleware, so a policy gates the route and not only the sidebar |
| W5 | Business Home per the `PHASE-00-UI-SHELL.md` section 3 contract, in placeholder and empty states only |
| W6 | Administration landing as the ten-step setup journey from section 8, statuses shown as not started |
| W7 | Rename the application to SemantIQ |
| W8 | Correct the `doc/design-system/` path references in `CLAUDE.md` |
| W9 | Populate the six context registers for everything built |
| W10 | `doc/execution/PHASE-00-VERIFICATION.md` with real evidence |

---

## 7. Test strategy

| Layer | Coverage |
|---|---|
| Role tiers | Cumulative ordering; Auditor reaching compliance without gaining a tier; entitlement gating a domain independently of tier |
| Navigation | Each tier sees its clusters and no others; inaccessible nodes ABSENT from the markup, not dimmed; empty groups and clusters dropped |
| Route policy | A business-only account is denied an admin route by URL, not merely un-linked |
| Home | Renders in every state the section 9 list names |
| Regression | The existing 50 tests keep passing |

Rendered in a browser in both themes and at 390px, as with the work so far.

---

## 8. Rollback

Every migration reversible and exercised. All changes additive; no table is dropped or renamed. Deployment rollback is a revert commit on `main`. The live database is untouched until a migration run is separately approved.

---

## 9. Open items carried forward

1. **The cPanel hosting geography is unconfirmed** and must be recorded before go-live rather than assumed.
2. **No account is an administrator in production.** New accounts default to the lowest tier; the first System Administrator must be promoted directly in the database.
3. **D5, React**, above.
4. **Microsoft Entra is unconfigured** on the server, so the SSO button explains itself rather than working.

---

## 10. Approval

Nothing in section 6 has been implemented. On approval I will build W1 to W10 and produce the verification report.
