# SemantIQ Implementation Status

Repository alignment: v1.3 (Laravel/PHP/React/MySQL-cPanel, `doc/` documentation root, current single-customer deployment, multi-tenant-ready boundaries).

> **Claude Code:** This file is authoritative for phase state AND for Release 1
> gate state, which are two separate tables tracking two separate things. Do not
> change either to `CONFIRMED` unless the user explicitly sent that item's exact
> confirmation phrase, and do not unlock the next one before that confirmation.

The phase table below tracks the product roadmap. The Administrator Foundation
Release 1 gates are tracked in their own section further down; a gate's state
says nothing about a phase's, or the reverse.

| Phase | Reference | State | Plan approval | User completion confirmation | Notes |
|---|---|---|---|---|---|
| 00 | P00-FND | READY_FOR_PLAN | Pending | Pending (`CONFIRM PHASE 00 COMPLETE`) | |
| 01 | P01-IDN | LOCKED | Pending | Pending (`CONFIRM PHASE 01 COMPLETE`) | |
| 02 | P02-FAB | LOCKED | Pending | Pending (`CONFIRM PHASE 02 COMPLETE`) | |
| 03 | P03-SRC | LOCKED | Pending | Pending (`CONFIRM PHASE 03 COMPLETE`) | |
| 04 | P04-ING | LOCKED | Pending | Pending (`CONFIRM PHASE 04 COMPLETE`) | |
| 05 | P05-DQM | LOCKED | Pending | Pending (`CONFIRM PHASE 05 COMPLETE`) | |
| 06 | P06-SEM | LOCKED | Pending | Pending (`CONFIRM PHASE 06 COMPLETE`) | |
| 07 | P07-AI | LOCKED | Pending | Pending (`CONFIRM PHASE 07 COMPLETE`) | |
| 08 | P08-OPS | LOCKED | Pending | Pending (`CONFIRM PHASE 08 COMPLETE`) | |
| 09 | P09-GO | LOCKED | Pending | Pending (`CONFIRM PHASE 09 COMPLETE AND BASELINE ACCEPTED`) | |

## Administrator Foundation Release 1

**A separate concept from the phase table above, and deliberately so.** A phase
is a slice of the product roadmap; a release gate is a slice of the
Administrator Foundation that ships to production on its own. They advance
independently, they have their own confirmation phrases, and neither one's
status implies anything about the other. Do not merge the two tables.

Gates are defined in `doc/execution/ADMIN-FOUNDATION-RELEASE-1-PLAN.md`.

> **Claude Code:** a gate moves to `CONFIRMED` only when the user sends that
> gate's exact confirmation phrase. Merging, a green pipeline and a working
> production deployment are evidence for the request - they are not the
> confirmation.

| Gate | Reference | Title | State | User completion confirmation | Notes |
|---|---|---|---|---|---|
| R1.1 | Gate 1 | Platform Foundation | `CONFIRMED` | Confirmed in the product owner's own words: "PR #17 / R1.1 has been reviewed and merged. Treat Gate 1 as complete." | PR #17, merged and live. Organisation scope, audit writer, system settings, feature flags, diagnostics |
| R1.2 | Gate 2 | Identity & Access | `CONFIRMED` | Confirmed in the product owner's own words, after the cross-organisation fix: "Done merge." | PR #18, merged and live. Users, roles, permissions, entitlements, access reviews, the last-System-Administrator invariant |
| R1.3 | Gate 3 | Security Foundation | `CONFIRMED` | Received 23 August 2026: `CONFIRM R1.3 GATE 3 COMPLETE` | PR #21 and PR #22, both merged and live; migrations run on production. All seven conditions met |
| R1.4 | Gate 4 | Data Protection, Sovereignty & PDPA | `PLAN_SUBMITTED` | Phrase not yet set | ADM-013 to ADM-016, plus the three PDPA obligations DEC-002 traced and the required privacy contact (SEC-DEC-043). Unlocked 23 August 2026 by the R1.3 confirmation. Plan written 24 August 2026: `doc/execution/R1.4-GATE-4-DATA-PROTECTION-PDPA-PLAN.md`. **No code written. Decisions D1 to D13 are open, and D1 to D4 change the shape of the gate** |
| R1.5 | Gate 5 | Integration Foundation | `LOCKED` | Phrase not yet set | ADM-017 to ADM-020. The gate the Fabric release depends on |
| R1.6 | Gate 6 | Operations | `LOCKED` | Phrase not yet set | ADM-022, ADM-023. Needs a real queue driver and a worker: production runs `QUEUE_CONNECTION=sync` (CFG-QUEUE-001) |
| R1.7 | Gate 7 | Verification | `LOCKED` | Phrase not yet set | ADM-025 Help framework, context registers refreshed, evidence against every gate |

**On the confirmation column.** R1.3 is the first gate with a formal phrase; the
product owner set it on 23 August 2026. R1.1 and R1.2 were confirmed before the
convention existed, so their rows record what was actually said rather than a
phrase invented afterwards - a fabricated confirmation is worse than an informal
one. R1.4 onwards will be given a phrase when each gate reaches confirmation.

### R1.3 conditions, all met

Kept rather than deleted: what a gate was held open for is part of its record.

| # | Condition | State |
|---|---|---|
| 1 | The Expiring Credentials panel must not report a healthy state when zero references are tracked | Met, PR #22 |
| 2 | The posture explanation must be derived from the same result as the badge | Met, PR #22 |
| 3 | Follow-up PR merged | Met, `2135299` |
| 4 | CI passes | Met, run 33 |
| 5 | Deployment passes | Met, run 47 |
| 6 | Live Security Overview verified | Met, by the product owner |
| 7 | Product owner sends `CONFIRM R1.3 GATE 3 COMPLETE` | **Met, 23 August 2026** |

Gate 4 moved from `LOCKED` to `READY_FOR_PLAN` on the strength of this.

### Release gate update rules

1. `LOCKED` -> `READY_FOR_PLAN` only after the previous gate is `CONFIRMED`.
2. `READY_FOR_PLAN` -> `PLAN_PENDING_APPROVAL` after Claude presents the gate plan.
3. `PLAN_PENDING_APPROVAL` -> `IN_PROGRESS` only after the user approves the plan.
4. `IN_PROGRESS` -> `VERIFYING` when implementation is ready for validation.
5. `VERIFYING` -> `AWAITING_USER_CONFIRMATION` when the verification evidence is complete and presented.
6. `AWAITING_USER_CONFIRMATION` -> `CONFIRMED` only on that gate's exact phrase. Where no phrase has been set, ask for one; never invent it and never record a phrase that was not actually sent.
7. A gate accepted in production may still be held in `AWAITING_USER_CONFIRMATION`; record what it is being held for.

## Status update rules

1. `READY_FOR_PLAN` -> `PLAN_PENDING_APPROVAL` after Claude presents the plan.
2. `PLAN_PENDING_APPROVAL` -> `IN_PROGRESS` only after user approves the plan.
3. `IN_PROGRESS` -> `VERIFYING` when implementation is ready for validation.
4. `VERIFYING` -> `AWAITING_USER_CONFIRMATION` only when the verification report is complete and evidence is presented.
5. `AWAITING_USER_CONFIRMATION` -> `CONFIRMED` only after the user sends the phase completion phrase.
6. After confirmation, change the next phase from `LOCKED` to `READY_FOR_PLAN`.
7. Any unresolved dependency/security/API conflict may set the current phase to `BLOCKED`; record the reason.
