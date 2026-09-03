# Day-end handover — 3 September 2026

**The single canonical daily note for 3 September.** No other 3 September
daily-update file exists or should be created.

**Work stopped here at the Product Owner's direction.** P1-05 DESIGN is **open
for review and NOT merged**. No implementation has started, and no schema,
migration, authorization, bootstrap, step-up-authentication or production
privilege change was made.

---

## 1. Where the day started

| | |
| --- | --- |
| **P1-03 — Users & Groups** | **ACCEPTED** (2–3 September). Merge SHA `5ec9327e56e0403fc4acf52437d6c4ad287b0613`, PR #87 |
| **P1-04 — Business Domains** | Implemented, deployed, and **awaiting Product Owner test** |
| **UI / Brand / Navigation foundation** | **Accepted and FROZEN.** Unchanged today, and unchanged by anything below |

---

## 2. P1-04 — completed and accepted today

### 2.1 Acceptance

**P1-04 — BUSINESS DOMAINS — PRODUCT OWNER ACCEPTED, 3 September 2026.**

**Product Owner test completed — ALL PASS. No failures observed.**

| Stage | Merge SHA | PR |
| --- | --- | --- |
| PLAN | `b083b30f261820a00f8fdfc37addcd1a6e063789` | #88 |
| DESIGN | `bffa100032cf9fe2d18869961b1659f4003c7aae` | #89 |
| **Implementation** | **`920600279561224d2955debc78c71892eecd5f73`** | #90 |
| Production evidence | `1fde89bb5acaae3a956f70fa5950be8aaf15aac0` | #91 |
| **Acceptance** | **`61a302a13c02ce9765b88ca85982d2b0b86f3f4e`** | #92 |

Recorded in the **three existing documents only** — the test script, the
verification record and `PHASE-1-PLAN.md`. **No duplicate acceptance or test
document was created.**

### 2.2 Delivered

| | |
| --- | --- |
| Schema | `business_domains`, `business_domain_owners` |
| Module | `App\Modules\Domains` — 3 services, 1 controller, 2 models, 3 enums, 1 console command |
| Routes | 9 under `/console/domains` |
| Events | 7, **with no new security-event context key** |
| Tests | **538 passing, 8,393 assertions** (2 skipped on SQLite by design) |
| Mutations | **63 run — 55 caught first time, 4 survived and were resolved, 1 recorded as a genuine no-op** |

### 2.3 Final read-only production verification — successful

[Run 33751962653](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33751962653),
**success. No write anywhere in it.**

| | |
| --- | --- |
| **Domains** | **8** — **7 baseline** + **1 custom, `software`** |
| **Enabled** | **0** |
| **Ownership periods** | **1** |
| Current owners | 1 |
| `platform_roles_total` | **1** — the P1-00 seam is exactly where P1-04 left it |

**`software` has ownership history**, so under **D-43** it **cannot be
permanently purged — disable only.** That is the design working as intended.
**No action is required and none should be taken.**

### 2.4 Evidence limitations — recorded honestly

**Three things the final snapshot does NOT establish**, stated so a later reader
does not read a result into data that does not contain one:

| # | Limitation |
| --- | --- |
| 1 | **The enabled-domain / active-owner production invariant was NOT exercised.** Zero domains were enabled, so the check had nothing to iterate. It passed **vacuously** — *"no violation found because there was nothing to check"*, not *"the invariant was verified in production"* |
| 2 | **`renamed: false` cannot establish whether a rename-and-restore occurred.** Renaming *Sales* to *Commercial* and back produces exactly this reading, and so does never renaming it |
| 3 | **The verbatim wording of refusals E1–E8 was not captured**, and has **not** been reconstructed from the source. The optional live non-administrator check was not specifically captured either, so it is **not recorded as observed** |

> **The Product Owner's PASS remains the source for the observed UI test steps.**
> The snapshot corroborates the stored state; it does not corroborate the steps,
> and it is not treated as though it does.

---

## 3. P1-04 carried gate → P1-05

> **A DISABLED DOMAIN MUST NEVER BROADEN EFFECTIVE ACCESS.**

**Open and mandatory.** P1-04 intentionally contains **no access engine**, so
the failure is unreachable and untestable there. Recorded in
`PHASE-1-PLAN.md` §10 as **five cases, all preserved**:

| # | Case | Required outcome |
| --- | --- | --- |
| 1 | **One** domain disabled | No access through it. The others unchanged |
| 2 | **All** domains disabled | No domain access at all |
| 3 | **NO ENABLED DOMAINS** | **NO DOMAIN ACCESS — NEVER UNRESTRICTED ACCESS** |
| 4 | An entitlement whose domain is later disabled | Grants nothing. **Not deleted** |
| 5 | **Re-enable** | Access returns **exactly** as before, and no further |

**Case 3 is not a rewording of case 2.** *All disabled* and *none exist* reach
the same empty set by different paths, and a filter that guards one may not
guard the other.

---

## 4. P1-05 PLAN — completed and merged today

**Merge SHA `c313a39652681bc198045fa1c175e2e70964c9be`** (PR #93).
**D-49 through D-73 approved.**

### The decisions of record

| Area | Decision |
| --- | --- |
| **The `platform_role` seam** | **Remove `users.platform_role`.** Historical **role assignments become the single authorization source.** No compatibility column left behind |
| **Bootstrap** | **System Administrator is PLATFORM-SCOPED and may exist before an Organisation.** A `system_administrator` assignment may carry no organisation; every other role requires one |
| **Roles** | **Seven immutable Release-1 definitions.** No custom roles. **No editable role capabilities.** No renaming. No deactivation |
| **Assignments** | **Multiple roles per person permitted.** No future dating |
| **Groups** | **USERS-ONLY assignment.** P1-03 groups continue granting nothing, and **the engine must not read group membership** |
| **Entitlements** | **Explicit domain entitlements only.** No role carries an implicit one |
| **Scope** | **Per entitlement**, with explicit structural targets |
| **Sensitivity** | **Per entitlement**, one ceiling. Not also per person |
| **Resolution** | **Independent complete grant paths.** At least one complete active path authorises |
| **Deny records** | **None.** And a revoked row must never become an invisible one |
| **Caching** | **No permission cache** |
| **Revocation** | **Immediate — the next authorization decision after commit.** Session expiry is not the mechanism |
| **The engine** | **One effective-access engine**, usable outside an HTTP request |
| **Phase 2** | **Must DERIVE from that engine**, never restate it |
| **D-73 — NEW** | **Privileged step-up / re-authentication**, added from **SYS-018** |

> **D-73 was a gap the review caught.** SYS-018 is in the Ground-Zero source,
> P1-05 is the first unit that can actually grant privilege, and the first PLAN
> draft did not mention it once.

---

## 5. P1-05 DESIGN — written, NOT merged

| | |
| --- | --- |
| **Where** | **PR #94 — OPEN** |
| **Merged?** | **NO** |
| **Implementation started?** | **NO** |
| Guards proposed | **60** |

### Areas already completed in the DESIGN

| # | |
| --- | --- |
| 1 | **Immutable Role / Action catalogue** — seven roles × five action classes |
| 2 | **D-49 bootstrap migration and rollback sequence** — five migrations, in order |
| 3 | **The replacement role assignment created INSIDE the existing atomic bootstrap transaction** |
| 4 | **Platform-scoped System Administrator model** |
| 5 | **Historical role → entitlement → scope/ceiling parent–child lifecycle** |
| 6 | **A single Access Engine** |
| 7 | **Independent grant-path evaluation** |
| 8 | **Explicit Team and Business Unit scope targets** |
| 9 | **Per-entitlement sensitivity** |
| 10 | **Bounded P1-00 step-up extension using Microsoft authentication** |
| 11 | **Escalation guard separating `PLATFORM_ADMIN` from `ACCESS_ADMIN`** |
| 12 | **The P1-02 carried SSO gate handled only if a genuine second System Administrator exists** |

---

## 6. OPEN PRODUCT OWNER DECISION — D-74

> ### D-74 — Domain versus Organisation scope
>
> **UNRESOLVED. The Product Owner will decide this next session.**

**The DESIGN's finding:** under Release 1, because

- there is **one Organisation**; and
- every entitlement **already names one Business Domain**,

**`Domain` and `Organisation` scope currently resolve to the SAME record set.**

**The DESIGN presents the options and does not silently choose one.**

> **Do not modify the DESIGN to resolve D-74 until that decision is given.**

---

## 7. Restart point — next session

**Restart exactly at:**

> ## P1-05 DESIGN — PRODUCT OWNER REVIEW

**First item: resolve D-74.**

Then review, in this order:

| # | |
| --- | --- |
| 1 | Role / action catalogue |
| 2 | Bootstrap migration and rollback |
| 3 | **Last-System-Administrator concurrency protection** |
| 4 | Parent–child access history |
| 5 | Effective-access engine |
| 6 | Exact scope semantics |
| 7 | Step-up authentication |
| 8 | Existing-route authorization transition |
| 9 | Access Simulator |
| 10 | **The P1-04 carried access gate** |

**Do NOT repeat the P1-05 PLAN.** It is merged and approved.

**Do NOT reopen P1-00 to P1-04** unless the DESIGN reveals a genuine
compatibility or security defect.

---

## 8. Still-open operational items — recorded, unchanged

**None of these was changed today, and none requires action tonight.**

| # | Item | State |
| --- | --- | --- |
| 1 | **`srikanth@lithan.com`** | Existing **P1-03** operational item. The record carries an incorrect Object ID and can never sign in; it has never signed in, so the D-39 guarded purge still applies. **Left exactly as P1-03 left it.** Full context: `P1-03-USERS-GROUPS-VERIFICATION.md` §12.3 |
| 2 | **Custom domain `software`** | **Retained because ownership history exists.** Under D-43 it can only ever be disabled, never purged. Context: `P1-04-BUSINESS-DOMAINS-VERIFICATION.md` §9.5 |
| 3 | **P1-02 provider-wide SSO Re-check lock** | **REMAINS OPEN.** It needs a second **privileged** account. **A fake one must not be created to close it.** If P1-05 legitimately establishes a genuine second System Administrator, the live observation is taken then; otherwise it is carried forward and said so |

---

## 9. What was NOT done tonight, deliberately

- **PR #94 was not merged.** It stays open for review.
- **No implementation started.**
- **No schema, migration, authorization, bootstrap, step-up-authentication or production privilege change.**
- **D-74 was not resolved.**
- **No second daily-update file for 3 September.** This is the canonical one.
