# P1-05 — TRACEABILITY

**Requirement / Decision → Code → Test → Evidence.**

The DESIGN §17.5 gate. Every important PLAN and DESIGN decision is traceable to
the code that implements it, the test that holds it and the evidence that it
ran — so that nothing disappeared because the review rounds were compressed.

| | |
| --- | --- |
| PLAN merge SHA | `c313a39652681bc198045fa1c175e2e70964c9be` |
| DESIGN merge SHA | `73f62bc5ffa3beab17975e8107698f48f4f20f1c` |
| Mutations | `P1-05-MUTATIONS.md` — **34 run, 34 caught, 0 survived** |
| Deployment | `P1-05-DEPLOYMENT-NOTE.md` |

---

## 1. Decisions D-49 to D-74

| Decision | Code | Test | Evidence |
| --- | --- | --- | --- |
| **D-49** bootstrap migration, rollback, deployment | `database/migrations/2026_09_06_0000{01..07}_*` · `GrantRedeemer` | `MigrationTest` · `BootstrapTest` | CI *migrate → rollback → migrate* on MySQL, administrator seeded first |
| **D-49a** floor of 1, warning at 1 | `AdministratorSetGuard::isSoleAdministratorWarningActive()` | `AdministratorLockoutTest` · `PresentationTest` | **N-M21** |
| **D-50** build and test order | — | — | Engine → enforcement → screens → simulator, in commit order |
| **D-51–D-54** nothing about a role is manageable | `RoleCatalogue` · `RoleCode` | `Architecture/AccessBoundaryTest` · `P1BoundaryTest` | No `roles` table asserted |
| **D-55** several roles at once | `AccessEngine::evaluateBusinessData()` | `GrantPathIndependenceTest` | Role actions union |
| **D-56** no effective dating | `RoleAssignmentService::assign()` | `LifecycleTest` | Begins on commit, ends on revoke |
| **D-57** no implicit entitlement | `EntitlementService::grant()` | `EngineBoundaryTest` · `DomainAccessBoundaryTest` | **N-B15**, **N-B11** |
| **D-58** groups grant nothing | `AccessEngine` — never reads `group_memberships` | `EngineBoundaryTest` | Asserted from emitted SQL |
| **D-59** scope belongs to an entitlement period | `entitlement_scopes` schema | `ScopeUnionTest` | Parentage |
| **D-60** one ceiling, on the entitlement | `EntitlementCeiling` | `GrantPathIndependenceTest` | **N-P2** — a ceiling caps its own path only |
| **D-61** `access_expectation` is context only | — *the engine never reads it* | `Architecture/DomainsBoundaryTest` | **N-B17**, architecture guard |
| **D-62** independent complete paths | `AccessEngine::evaluateBusinessData()` | `GrantPathIndependenceTest` | **N-P1** to **N-P4** |
| **D-63** above the ceiling → DENY | `AccessEngine`, `DecisionReason::DeniedCeiling` | `EngineBoundaryTest` | No redaction engine exists |
| **D-64** no explicit deny records | `AccessEngine` — revoked rows never evaluated | `GrantPathIndependenceTest` | **N-P3**, asserted from SQL |
| **D-65** self-assignment | `AccessController::stepUpActionForGrant()` and `AccessController::grantEntitlement()` | `StepUpTest` · `SelfEntitlementStepUpTest` | A self-grant is one of the five step-up actions — **and covers a role and an entitlement alike** |
| **D-66** no manager inference, no recursion | `AccessEngine::scopeCovers()` | `ScopeUnionTest` | Scope reads the named team only |
| **D-67** the "Own" contract | `ResourceReference::belongsTo()` | `GrantPathIndependenceTest` | Defined here, implemented by Phase 2 |
| **D-68** Auditor — evidence read, no business data | `RoleCatalogue::MATRIX` | `Architecture/AccessBoundaryTest` | **N-E7** |
| **D-69** "immediate", no permission cache | `AccessEngine` — no cache anywhere | `GrantPathIndependenceTest` · `LifecycleTest` | **N-B18**, **N-L13** |
| **D-70** the Phase 2 projection contract | `AccessEngine` usable outside HTTP | `Architecture/AccessBoundaryTest` | No session, request or auth in the engine |
| **D-71** privileged-surface denials only | `AccessEngine::denied()` | `EngineBoundaryTest` | Routine denials are not logged |
| **D-72** four context keys, `role` is a CODE | `SecurityEventLogger::ALLOWED_KEYS` | `P1BoundaryTest` | No free-text channel |
| **D-73** step-up re-authentication — **self-granting a ROLE** | `AccessController::stepUpActionForGrant` · `StepUpService` · `StepUpController::performGrant` · `EntraProvider` | `StepUpTest` | **N-S1** to **N-S11** |
| **D-73** step-up re-authentication — **self-granting a DOMAIN ENTITLEMENT** | `AccessController::grantEntitlement` · `StepUpController::performSelfEntitlementGrant` | `SelfEntitlementStepUpTest` | **N-SE1** to **N-SE9**, **N-EV3**, **M-SE1** to **M-SE13** |
| **D-74** both scopes, documented as equivalent | `ScopeType` · one resolver in `AccessEngine` | `ScopeUnionTest` · `PresentationTest` | **N-Q1**, **N-Q2** |

---

## 2. The seven §17.1 invariants

| # | Invariant | Where it is held | Where it is proven |
| --- | --- | --- | --- |
| **1** | **One authorization model** | `AccessEngine` singleton; `PlatformRole` and `users.platform_role` deleted | `Architecture/AccessBoundaryTest` · `P1BoundaryTest` · `MigrationTest` |
| **2** | **Fail closed, no permissive fallback** | `AccessEngine::globalGates()` and `evaluate()` | `EngineBoundaryTest` — all nine states |
| **3** | **No silent privilege expansion** | `RoleCatalogue`; `ActionClass::requiresGrantPath()` | `EngineBoundaryTest` · `Architecture/*` · **N-B1**, **N-B10**, **N-B11**, **N-B16**, **N-B17** |
| **4** | **Concurrency genuinely safe** | `AdministratorSetGuard::lockAndReadEffectiveSet()` | `AdministratorConcurrencyTest` — **MySQL only**, CI |
| **5** | **Historical integrity** | `RoleAssignmentService::endChildrenOf()`; nothing deletes | `LifecycleTest` |
| **6** | **Step-up remains real** | `StepUpService`; provider `auth_time` | `StepUpTest` · `SelfEntitlementStepUpTest` |
| **7** | **Enforcement before the protected fetch** | `RequireActionClass` middleware | `RouteAuthorizationMatrixTest` — no payload on denial |

---

## 3. The §17.2 test classes

| # | Required | Where |
| --- | --- | --- |
| 1 | Feature tests | `tests/Feature/Access/*` |
| 2 | **Architecture** tests | `Architecture/AccessBoundaryTest` · `Architecture/DomainsBoundaryTest` |
| 3 | Negative / security tests | `EngineBoundaryTest` · `RouteAuthorizationMatrixTest` |
| 4 | **Mutation tests** | `P1-05-MUTATIONS.md` — 34/34 caught |
| 5 | **MySQL concurrency** | `AdministratorConcurrencyTest` — CI *Access suite against MySQL* |
| 6 | Migration tests | `MigrationTest` |
| 7 | **Rollback** tests | `MigrationTest` · CI *migrate → rollback → migrate* |
| 8 | **Empty-deployment bootstrap** | `MigrationTest` · `AdministratorLockoutTest` |
| 9 | **Existing-administrator migration** | `MigrationTest` · CI rollback step |
| 10 | Last-administrator lockout | `AdministratorLockoutTest` |
| 11 | Route authorization matrix | `RouteAuthorizationMatrixTest` |
| 12 | **Disabled-domain five cases** | `EngineBoundaryTest::test_the_p1_04_domain_gate_holds_in_all_five_cases` |
| 13 | **Scope UNION** | `ScopeUnionTest` |
| 14 | Incomplete-entitlement | `ScopeUnionTest` · `PresentationTest` |
| 15 | Independent grant paths | `GrantPathIndependenceTest` |
| 16 | **Simulator / enforcement parity** | `GrantPathIndependenceTest::test_decide_and_explain_always_agree` |
| 17 | **Step-up** substitution, replay, expiry, cancel, freshness | `StepUpTest` |
| 18 | Inactive-user | `EngineBoundaryTest` |
| 19 | Immediate revocation | `GrantPathIndependenceTest` |
| 20 | **No payload on denial** | `RouteAuthorizationMatrixTest` |
| 21 | **Architecture guards against a second model** | `Architecture/AccessBoundaryTest` |

---

## 4. The carried gates

| Gate | Status | Evidence |
| --- | --- | --- |
| **P1-04 — the disabled-domain gate** | **CLOSED** in automated evidence; awaiting Product Owner observation | `EngineBoundaryTest` proves all five cases, including *no enabled domains grants nothing* — **N-D5** breaks it as one line and is caught |
| **P1-02 — provider-wide SSO Re-check** | **REMAINS OPEN, carried** | No genuine second System Administrator exists. **No account was manufactured to close it** |

---

## 5. Product Owner corrections from the DESIGN review

All thirteen recorded in `P1-05-ROLES-ACCESS-DESIGN.md` §16.2. The five with
executable consequences:

| Correction | Code | Test |
| --- | --- | --- |
| The `domain_owner` role is not the source of accountability | Nothing derives one from the other | **N-B11** to **N-B16**, architecture |
| Common administrator-set serialisation, not subject-first | `AdministratorSetGuard` | **N-M15**, **N-M15b**, `AdministratorConcurrencyTest` |
| P1-00 controlled recovery preserved | `BootstrapState` — predicate only | `AdministratorLockoutTest` |
| Revoking the last scope does not revoke the entitlement | `EntitlementService::revokeScope()` | `ScopeUnionTest`, `PresentationTest` |
| Cleared and absent ceilings are different states | `EntitlementService::setCeiling()` | **N-C7**, **N-C8** |
