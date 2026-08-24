# DEC-004 - ADM-013 uses one Audit Logs screen with four functional views

**Date:** 24 August 2026
**Status:** Approved by the product owner, 24 August 2026
**Supersedes:** Nothing. It closes gap M2 recorded in `doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md` section 21.3
**Affects:** `doc/MENU_STRUCTURE.md`, `config/navigation.php`, Release 1 gate 4 batch R1.4b

---

## Context

ADM-013 asks for four audit views: User Activity, Administrative Changes,
Security Changes and Configuration Changes. `doc/MENU_STRUCTURE.md` has a single
`Audit Logs` leaf.

Recorded as gap M2 in the Release 1 plan and left unresolved through gates 1 to
3, because nothing needed it until the screen was built.

## Decision

**One screen. Four views are FILTER PRESETS over the same `audit_events` table,
not four routes and not four features.**

```text
Audit Logs

View:
  All Events
  User Activity
  Administrative Changes
  Security Changes
  Configuration Changes
```

Each preset applies the appropriate `module`, `action` and `outcome` filters. A
reader can then refine the selected view with the ordinary filters on the same
page:

```text
date/time range
actor
action
module
outcome
resource type
correlation id
reason
```

**One table, one screen, one route.** The audit table is not duplicated and no
separate data store is created for any view.

## Why

**Four leaves would be navigation noise without four capabilities.** The views
differ only in which `module` and `action` values they show. Four navigation
nodes onto one table is four names for one thing, and the breadcrumb could not
tell which one the reader came in through.

**A preset is refinable; a separate screen is not.** Somebody who opens Security
Changes and then wants to narrow by actor and date can do it without leaving the
view. With four screens each would need its own copy of every filter.

**It makes the future Governance Overview drill-through cleaner.** The overview
can link to `/audit-logs?view=security` rather than the product maintaining four
parallel audit screens to link into.

## The navigation placement, which is not where the document says

**`MENU_STRUCTURE.md` 12.14 lists `Audit Logs` under Monitoring.
`config/navigation.php` places it as a top-level leaf in the Compliance
cluster.** Found while wiring R1.4b, and it is not a mistake in either: DEC-001
mapped the fifteen administration groups into the template's four fixed
clusters, and that mapping put audit evidence in Compliance.

**The rail's placement is the one that works, and the reason is decision D2.**
Monitoring sits in Application Administration at the `app-admin` policy, which
is Administrator tier. An Auditor is frequently a Viewer. Placing the audit log
under Monitoring would put it behind a tier that locks out the one role
`ROLE_MODEL.md` says exists to read it.

`MENU_STRUCTURE.md` is updated to match, so the navigation authority and the
rail agree rather than quietly differing.

## What this does NOT do

- It does not create a second audit table, a materialised view, or a per-view
  cache. One query, one table, filters applied.
- It does not make the views configurable by a user. They are system presets
  declared in code. A user-saved view is a later requirement if anybody asks
  for one.
- It does not change who may read the trail. That is decision D2, recorded
  separately as SEC-DEC-062, and the network identifier stays behind its own
  permission per D8 and SEC-DEC-063.
- It does not touch the `Audit` leaf under Governance (12.10), which is business
  governance of catalogue objects and remains unbuilt.

## Related

- `doc/execution/decisions/DEC-001-security-group-and-permissions-navigation.md`
  - the mapping that put audit evidence in Compliance
- `doc/execution/decisions/DEC-003-pdpa-navigation-homes.md` - the same class of
  navigation gap, resolved the same way
- SEC-DEC-062 - who may read the audit trail
- SEC-DEC-063 - who may see the network identifier
