# DEC-001 - A Security group in Administration, and Permissions as a first-class leaf

**Date:** 24 August 2026
**Status:** Approved by the product owner, 24 August 2026
**Supersedes:** Nothing. It closes gaps M1 and M3 recorded in `doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md` section 21.3
**Affects:** `doc/MENU_STRUCTURE.md`, `config/navigation.php`, Release 1 gates 2 and 3

---

## Context

`doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md` section 21.3 recorded eight
differences between the Release 1 specification's own navigation sketch and
`doc/MENU_STRUCTURE.md`, which `CLAUDE.md` names as the functional navigation
authority. Two of them blocked work rather than merely needing a note.

**M1.** ADM-009 Authentication Policy, ADM-010 Session Policy, ADM-011 API
Security and ADM-012 Secret References had no home anywhere in MENU_STRUCTURE
section 12. Governance (12.10) is business governance. System Configuration
(12.15) is application settings. Neither is a security policy surface, and
putting policy screens in either would have buried them.

**M2 note.** Secret References DID have a home, under System Configuration, but
it sat apart from the three policy screens it belongs with.

**M3.** MENU_STRUCTURE 12.2 lists Roles but not Permissions, while ADM-007
requires a Permissions screen.

## Decision

### M1 - a new top-level Security group

```text
Administration
`-- Security
    |-- Security Overview
    |-- Authentication Policy
    |-- Session Policy
    |-- API Security
    `-- Secret References
```

This is the authoritative home for ADM-009, ADM-010, ADM-011 and ADM-012.

Planned route family, for gate 3:

```text
/admin/security
/admin/security/authentication
/admin/security/sessions
/admin/security/api
/admin/security/secrets
```

**Secret References moves conceptually from System Configuration to Security.**
It is removed from System Configuration in the same change that adds it to
Security, so no duplicate node exists at any point.

**Gate 3 implementation stays deferred to R1.3.** The group and its five leaves
are added to the navigation now, all rendering as unbuilt "Soon" destinations,
so the shape of the product is legible and the decision is recorded in the one
place navigation is authored. No route, controller, table or screen is created
for them in R1.2.

### M3 - Permissions becomes a first-class leaf

```text
Administration
`-- Organisation & Users
    |-- Organisation Profile
    |-- Business Units
    |-- Teams
    |-- Users
    |-- Roles
    |-- Permissions          <- added
    |-- Domain Entitlements
    |-- Security Groups
    `-- Access Reviews
```

Built in R1.2, alongside the rest of gate 2.

### M7 - Security Groups stays deferred

No Release 1 feature defines what a Security Groups screen shows. It is
presumably Entra group mapping, but nothing says so, and inventing the
functionality would be worse than leaving the gap visible. The node stays in the
rail as an unbuilt "Soon" destination and M7 stays open.

## Consequences

- `doc/MENU_STRUCTURE.md` gains section 12.16 for the Security group, and its
  12.2 and 12.15 lists are corrected. The menu structure remains the authority;
  this decision changes it rather than working around it.
- Plan section 21.3 marks M1 and M3 resolved and records where.
- The Administration cluster grows from fifteen groups to sixteen.
- Gate 3's route family is settled in advance, so R1.3 implements against a
  recorded decision rather than re-opening the question.

## What was rejected

**Putting the policy screens under System Configuration.** It would have avoided
a new group, but it conflates "how this application is set up" with "what this
application will allow", and the second is the one an auditor comes looking for.

**Leaving Secret References where it was and adding a link from Security.** Two
paths to one screen is exactly the duplicate-entry problem the filter-not-fork
navigation rule exists to prevent.
