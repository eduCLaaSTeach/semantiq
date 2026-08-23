# DEC-002 - The Singapore PDPA applies to this deployment

**Date:** 25 August 2026
**Status:** Confirmed by the product owner, 25 August 2026, on the advice of whoever owns compliance
**Closes:** SEC-DEC-041, and the open item carried since Phase 00 that no legal privacy regime had been determined
**Affects:** retention policy, personal data handling, breach response, and several features not yet built

---

## What was determined

The **Singapore Personal Data Protection Act** applies to this deployment.

That follows the geography confirmation of the same day - server, backups and
replication all in Singapore (SEC-DEC-036) - but it is a separate determination.
Where data sits is a fact; which law governs it is a decision about the
customer, their sector and their own contracts, and `CLAUDE.md` requires it to
be confirmed rather than assumed by code.

## Why this matters more than a line in a register

Until now the repository said no regime had been determined, and engineering
applied privacy-by-design regardless. That was the right posture for an unknown,
but it meant several things were carried as "confirm before go-live". They are
now confirmable, and three of them turn out to be **gaps rather than
confirmations**.

## What this application already does that the PDPA asks for

| Obligation | Where it is already met |
|---|---|
| Protection (s.24) - reasonable security | Two-dimension access model, permissions enforced at three layers, tenancy boundary that fails closed, append-only audit trail enforced at the database |
| Transfer limitation (s.26) - no cross-border transfer without comparable protection | Everything is in Singapore, and cross-geo processing and storage default to OFF and stay off until an approved profile says otherwise |
| Accountability (s.11) - a designated Data Protection Officer whose business contact is public | `organisations.privacy_contact` exists on the Organisation Profile screen. **See gap 4 below** |
| Purpose limitation - collect only what is needed | The control plane deliberately stores resource IDs and redacted metadata rather than business payload copies. Audit summaries are redacted and hashed, never payload |

## What it does NOT yet do

These are recorded as gaps, not as things to argue about later.

### Gap 1. Access and correction (s.21, s.22) is not buildable today

An individual may request the personal data an organisation holds about them,
and information about how it has been used or disclosed in the past year.

Personal data this application holds about a person:

```text
users                  name, work email, Entra object id, employee reference,
                       last sign-in
audit_events           actor identity, actor label (email), IP address
domain_entitlements    which business domains they may read
user_roles             which roles they hold
access_review_items    what was decided about their access, and by whom
```

There is no way to assemble that for one person and hand it over. **This is a
feature, and it is not in any current gate.** It needs a home - most naturally
alongside the Audit screen in gate 4, since the audit trail is the hardest part
of the answer.

### Gap 2. Breach notification (Part VIB) has no workflow

Since February 2021 notification is mandatory: the PDPC must be notified within
**3 calendar days** of assessing that a breach is notifiable, and affected
individuals must be notified where significant harm is likely.

This application can detect nothing of the sort today and has no workflow for
recording an assessment, a decision, or a notification. Three calendar days is
short enough that discovering the absence of a process during an incident is
the wrong time.

### Gap 3. The seven-year retention baseline is now a claim that must be justified

Retention limitation (s.25) requires that personal data stop being retained once
the purpose is served and retention is no longer necessary for legal or business
purposes.

The repository carries seven years for operational data, audit logs and backups.
That may well be justifiable for audit and compliance records - it is a common
figure for exactly that reason - but under the PDPA it is now **a position that
needs a stated basis**, not a default nobody has questioned.

It applies unevenly, and that is the point:

| Data | Seven years plausible? |
|---|---|
| `audit_events` - who changed what | Likely yes: it is the compliance record |
| `access_review_items` - access decisions | Likely yes, same reason |
| `users` of people who have left | **Questionable.** What purpose does keeping a leaver's name and email for seven years serve |
| `audit_events.ip_address` | **Questionable.** An IP is personal data with a much shorter useful life than the event it sits on |

ADM-014 in gate 4 owns the Data Protection Profile, and retention must be
policy-driven there rather than a constant. This decision records that gate 4
has to produce a **per-category** answer with a stated basis, not one number.

### Gap 4. The privacy contact is optional and should probably not be

`organisations.privacy_contact` is a free-text optional field. The PDPA requires
a designated DPO whose business contact information is made available.

Making it required is a small change, but it changes an existing screen's
validation and would fail for any organisation record already saved without one.
**Not changed unilaterally.** Raised for the product owner's decision, with
gate 4 as the natural place to do it alongside the rest of the data protection
profile.

## What changes now, and what does not

**Now:** the applicability question is closed. `DATA_PROTECTION_SOVEREIGNTY_STANDARD.md`
no longer says no regime has been determined. The four gaps above are recorded
where the work will be planned.

**Not now:** no gate is re-opened and no screen is changed. Gaps 1, 2 and 3 land
in gate 4 alongside ADM-014 and ADM-015, which is where data protection policy
belongs. Gap 4 is a decision for the product owner.

**Still true regardless:** engineering continues to apply privacy-by-design,
least privilege and the sovereignty controls. None of that was waiting on this
answer.

## What was rejected

**Treating the PDPA answer as sufficient to close the data protection work.** It
is the opposite: knowing the regime is what makes the gaps visible. An
applicability determination with no obligations traced to it is a line in a
register, not compliance.
