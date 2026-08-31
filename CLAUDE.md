# SemantIQ — working instructions

Durable instructions for anyone, human or agent, delivering SemantIQ.

This file holds the **delivery protocol**. It does not restate the product,
architecture or UI rules — those live in their own documents and are
authoritative there:

| Subject | Authoritative document |
| --- | --- |
| Blueprint, three-phase architecture | `doc/SemantIQ_v2.2_Ground_Zero_Architecture_Reset_Three_Phase_Blueprint.md` |
| Phase 1 scope, delivery order, decisions, carried gates | `doc/v2/phase-1/PHASE-1-PLAN.md` |
| UI and UX standard | `doc/design-system/ui-and-ux-layout-template-shared.md` |
| Per-unit plan, design, verification | `doc/v2/phase-1/P1-*.md` |

---

## 1. The lifecycle is a gate, not a formality

**PLAN → APPROVE → DESIGN → APPROVE → EXECUTE → TEST → VERIFY → ACCEPT.**

One delivery unit at a time. **A green CI run does not unlock the next unit.**
Only explicit Product Owner acceptance does.

---

## 2. Every guard must be proven non-vacuous

A test that cannot fail is worse than no test: it reports safety that does not
exist. For every guard, **break it deliberately and observe the test fail.**
Record the mutation alongside the case.

Watch for the specific failure this project keeps producing: a test that passes
for a reason unrelated to what it claims to check. A fixture more helpful than
reality, an assertion satisfied by any refusal, a column that happens to hold
the right value today. Prefer the mutation that would plausibly be written by
someone who misunderstood the rule.

---

## 3. MANDATORY — the Product Owner Test Script

**Every completed feature, corrective task or delivery unit must ship with a
Product Owner Test Script before acceptance is requested. No exceptions.**

It is written **for the Product Owner**, not as a developer test plan: their
words, their screens, their decisions. It must contain all twelve:

1. **Feature or task being tested**
2. **Deployed build / merge SHA**
3. **Preconditions** — what must already be true before they start
4. **Test data required**
5. **Warning where test data is permanent or cannot be deleted** — SemantIQ has
   no hard delete in several units; say so before they type anything
6. **Numbered user steps**
7. **Expected result for every step**
8. **Negative, refusal and security cases** where relevant
9. **Visual and UX checks** wherever UI is involved
10. **Evidence to capture**
11. **PASS / FAIL field per step**
12. **Anything that cannot currently be tested, and why** — never inferred from
    a passing test, and never silently omitted

Never ask the Product Owner to enter inaccurate business data, or to falsify
real organisational structure, to satisfy a test. Where a check cannot be
exercised without creating false or misleading permanent history, mark it
**NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA**, keep its automated
evidence, and carry the live observation forward as a gate on a later unit
(see `PHASE-1-PLAN.md` §10). That is not an implementation defect.

---

## 4. MANDATORY — the professional-polish gate

**Green CI is not sufficient to call anything ready for Product Owner testing.**

Before every handover, do a final human-style review of every changed UI screen
and look explicitly for:

- raw icon names, enum values, database or internal keys, route names
- debug text, placeholder copy, developer terminology on a user-facing surface
- meaningless or awkward labels; spelling and grammar
- inconsistent Title Case / sentence case / uppercase
- strange font sizing, poor spacing, poor alignment
- missing icons, broken images, truncation, overflow
- unusable button labels
- empty, error, refusal and success states
- hover, focus and disabled states
- responsive layout; light and dark theme consistency
- accessibility basics
- browser console errors
- **navigation that exists technically but the user cannot actually discover**

Then ask, honestly:

> **Would a professional SaaS product team be comfortable showing this exact
> screen to a customer?**

If not, fix it before asking for testing.

This is a **quality gate, not licence to expand scope.** It does not authorise
redesigning an approved product decision. Raise those separately.

---

## 5. MANDATORY — visual verification for UI work

Unit and feature tests are not enough for anything a person looks at. Before
requesting verification:

- open the actual rendered screen in a real browser;
- inspect the complete screen, not just the changed element;
- walk the navigation and the user journey end to end;
- check at normal desktop width **and** a small-screen width;
- check both themes where the feature supports both;
- confirm no implementation terms are exposed.

**Record what you actually observed**, not what you expect to be true. Chromium
is available at `/opt/pw-browsers/`; `playwright` drives it.

---

## 6. Report honestly

State what was executed and observed. A passing automated test is not the same
claim as an observed production result, and must never be presented as one. If
something is unverified, blocked, or was skipped, say so plainly and say why.
