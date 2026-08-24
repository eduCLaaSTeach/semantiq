# DEC-003 - Privacy Requests and Breach Register get homes under Data Protection

**Date:** 24 August 2026
**Status:** Approved by the product owner, 24 August 2026
**Supersedes:** Nothing. It closes the navigation gap raised as decision D1 in `doc/execution/R1.4-GATE-4-DATA-PROTECTION-PDPA-PLAN.md`
**Affects:** `doc/MENU_STRUCTURE.md` section 12.11, `config/navigation.php`, Release 1 gate 4

---

## Context

`doc/execution/decisions/DEC-002-pdpa-applies.md` determined that the Singapore
PDPA applies and traced obligations this application does not yet meet. Two of
them became gate 4 features:

- **PDPA-01** Personal Data Access and Correction. A data subject may ask what
  personal data is held about them, and may ask for a correction.
- **PDPA-02** Data Breach Assessment and Notification, against a notification
  deadline.

`doc/MENU_STRUCTURE.md` is the functional navigation authority. Section 12.11
Data Protection had homes for the profile, the personal and sensitive data
register and retention. **It had no home for either PDPA-01 or PDPA-02.**

This is the same situation DEC-001 recorded as gap M1 in gate 3, where four
security policy screens had nowhere to live. The pattern is worth naming: a
requirement traced from a legal determination arrives after the navigation
authority was written, so the authority has no node for it. Building the screen
and reaching it by a route nobody can navigate to would be the "unwanted parts
left hanging" failure, and inventing a home silently would put the navigation
authority and the code out of step.

## Decision

Two new leaves under Data Protection, in the position shown:

```text
Compliance
`-- Data Protection
    |-- Data Protection Profile      ADM-014
    |-- Personal / Sensitive Data    ADM-014, the category register
    |-- Privacy Requests             PDPA-01   <- NEW
    |-- Breach Register              PDPA-02   <- NEW
    |-- Sensitivity Labels           unbuilt
    |-- DLP Policies                 unbuilt
    |-- Retention                    PDPA-03
    |-- Minimisation                 unbuilt
    |-- Export Policy                unbuilt
    `-- Exceptions                   unbuilt
```

`doc/MENU_STRUCTURE.md` section 12.11 is updated in the same change, so the
authority and the rail cannot drift.

## Why here and not elsewhere

**Not under Governance (12.10).** Governance is business governance - catalogue,
ownership, lineage, certifications. A subject access request is not a business
governance artefact.

**Not a new top-level group.** DEC-001 created a Security group because four
screens had nowhere plausible to go and no existing group described them. That
is not the case here: Data Protection describes both of these exactly, and a
fifth cluster would fragment a subject that already has one.

**Not folded into the Data Protection Profile screen.** A profile is a standing
position. A privacy request and a breach are events with their own lifecycle,
their own deadlines and their own audit trail. Folding an event register into a
settings screen is how the register becomes invisible.

## Amendment, same date: ADM-015 also had no leaf

Found while wiring R1.4a, after this decision was written.

`doc/MENU_STRUCTURE.md` section 12.12 Data Sovereignty lists eight aspects -
Approved Geographies, Storage Geography, Processing Geography, AI Processing
Geography, Cross-Geo Controls, Network Route, Exceptions and Evidence - and has
**no node for ADM-015 Data Sovereignty Profile**, the screen that answers four
of those eight at once.

This is the same class of gap as the two above, and it is resolved the same way:

```text
Compliance
`-- Data Sovereignty
    |-- Sovereignty Profile        ADM-015   <- NEW
    |-- Approved Geographies       unbuilt
    |-- Storage Geography          unbuilt
    |-- ...unchanged leaves stay unbuilt
```

The alternative considered and rejected was pointing the four aspect leaves at
one screen. Four navigation nodes landing on the same page is not navigation, it
is four names for one thing, and the breadcrumb could not tell which one the
reader came in through.

The eight aspect leaves are unchanged and stay unbuilt. This adds one node; it
does not promise the rest of the group.

**This amendment was not in the approved D1 text.** It is recorded here rather
than applied silently, and it can be reversed: the screen would then need a
different home, because a screen with a route and no navigation node is the
"unwanted parts left hanging" failure.

## Consequences

- Two new navigation leaves, each disabled with a Soon pill until gate 4c
  delivers the screen behind it. The filter-not-fork rule holds: a user without
  the permission does not see the node at all.
- `NavigationIntegrityTest` covers both leaves from the moment they are authored.
- The remaining unbuilt leaves in 12.11 are unchanged and stay unbuilt. This
  decision adds nodes; it does not promise the rest of the group.
- Both leaves are gate 4c, the last batch. They will render as unbuilt through
  4a and 4b, which is the same sequence gate 3 used and is deliberate: the node
  exists and is honest about not being ready.

## Related

- `doc/execution/decisions/DEC-001-security-group-and-permissions-navigation.md`
  - the same class of gap, resolved the same way
- `doc/execution/decisions/DEC-002-pdpa-applies.md` - the determination that
  created the requirement
- `doc/execution/R1.4-GATE-4-DATA-PROTECTION-PDPA-PLAN.md` section 7, decision D1
- SEC-DEC-062 - the authorization change that decides who may reach these leaves
