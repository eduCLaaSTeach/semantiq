# P1-00 — Application Entry, Login & First-Run Bootstrap — VERIFICATION

## P1-00 ACCEPTED — 31 August 2026

**Status:** **ACCEPTED** by the Product Owner. **P1-00 is closed.**
**Unit:** P1-00 (Phase 1 delivery order 2)
**PLAN:** `P1-00-LOGIN-BOOTSTRAP-PLAN.md` — approved, D-03, D-04, D-09, D-10, D-11, D-12
**DESIGN:** `P1-00-LOGIN-BOOTSTRAP-DESIGN.md` — approved, D-03.1, D-13
**Predecessor:** P1-BASE — ACCEPTED at `3d075bf`
**Successor:** P1-01 — Organisation

> This document exists because the Phase 1 execution contract requires one per
> unit: *"Each unit is complete only after its verification document contains
> real evidence and the product owner accepts it."* It was missing when P1-00
> was accepted — the evidence lived only in the daily record — and is created
> here so the per-unit record is complete rather than implied.

Nothing below is marked PASS unless it was executed and its output observed.

---

## 1. Delivery, including what failed

| Event | Outcome |
| --- | --- |
| PR [#45](https://github.com/eduCLaaSTeach/semantiq/pull/45) — implementation | merge `6aa4ab0`; deployment [33365197625](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33365197625) success |
| **First live sign-in** | **FAILED** — `auth.login.refused.protocol reason=token_signature_invalid` |
| **First bootstrap grant** | **Unusable.** Issued before the defect was found; left to expire; never reused; no database record edited |
| PR [#46](https://github.com/eduCLaaSTeach/semantiq/pull/46) — JWKS correction | merge `08d7bd2`; deployment [33370195089](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33370195089) success |
| **First verification-workflow run** | **FAILED** — `Permission denied (publickey)`; a defect in my workflow, not in production |
| PR [#47](https://github.com/eduCLaaSTeach/semantiq/pull/47) — workflow correction | merge `72b3f4d` |
| Fresh grant, redeemed through Entra | **SUCCESS** — first System Administrator created |

### 1.1 The production defect

`JWK::parseKeySet($jwks)` was called **without a default algorithm**. Microsoft's
real Entra JWKS omits the per-key `alg` field, and php-jwt throws
`UnexpectedValueException: JWK must contain an "alg" parameter` for such a key
when no default is given. **The entire key set failed to parse, so no signature
was ever checked.** The rotation-retry path did not engage — it only refetches
when the error message mentions `kid` — so the failure surfaced as
`token_signature_invalid`, naming the wrong cause.

**Why CI stayed green.** The test JWKS included `alg`. The fixture was more
helpful than the real thing, so it tested the fixture rather than the system.
That is the lesson worth carrying out of this unit.

**Correction.** Keys are filtered before parsing — RSA only, `use: sig` when
present, `alg: RS256` when present, `kid`/`n`/`e` required — then parsed with
RS256 as the explicit default. The fixture now mirrors Entra, and the pre-fix
validator **fails seven tests against it**.

---

## 2. Live verification — 31 August 2026

### 2.1 Bootstrap state, read from the server

Read-only workflow
[33371191488](https://github.com/eduCLaaSTeach/semantiq/actions/runs/33371191488).
Counts only; no identity, grant or secret read.

```json
{"bootstrap_state":"CONFIGURED","active_system_admins":1,"users_total":1,
 "grants_total":2,"grants_consumed":1,"grants_still_open":0}
```

| Claim | Evidence | Result |
| --- | --- | --- |
| Bootstrap closed | `bootstrap_state = CONFIGURED` | **PASS** |
| Exactly one administrator | `active_system_admins = 1` | **PASS** |
| The successful grant was consumed | `grants_consumed = 1` | **PASS** |
| No usable grant remains | `grants_still_open = 0` | **PASS** |
| No self-registration | `users_total = 1` | **PASS** |

> **What could not be proven from outside.** `/first-run/<grant>` answers
> identically whether bootstrap is closed, the grant is unknown, or it has
> expired. That indistinguishability is deliberate anti-probing, and it is
> exactly why closure is proven from server state rather than inferred from an
> HTTP response.

### 2.2 Authentication

| Check | Result |
| --- | --- |
| Redirect scope | `openid profile email` only — no Graph, Mail, Files, Groups or Directory | **PASS** |
| PKCE | `code_challenge_method=S256`, `state` and `nonce` present | **PASS** |
| Entra accepts the request | sign-in page returned, **no `AADSTS` error** | **PASS** |
| Real sign-in | completed by the nominated administrator, reached `/console` | **PASS** |
| System Administrator recognised | `platform_role = system_administrator` | **PASS** |
| No automatic business-domain access | none granted; no entitlement surface exists | **PASS** |

### 2.3 Session, refusal and security

| Check | Observed | Result |
| --- | --- | --- |
| `/console` anonymous | 302 to login | **PASS** |
| `/console` anonymous, JSON client | 401 | **PASS** |
| `GET /auth/logout` | 405 — POST only | **PASS** |
| `POST /auth/logout` without session | 419 — CSRF enforced | **PASS** |
| Invented 64-character grants (×2) | 302 to closed; no administrator creatable | **PASS** |
| Six refusal states | 200; no trace, framework internals, token, nonce or role mapping | **PASS** |
| Unknown vs inactive | indistinguishable in status and wording | **PASS** |
| 17 protected paths | **all 403** | **PASS** |
| ACME challenge path | 404 (resolves, file absent); `.well-known/` 403 | **PASS** |
| `semantiq:health` | green | **PASS** |
| Secret hygiene | no token, grant, code, nonce, verifier or administrator identity in any public body or workflow output | **PASS** |

---

## 3. Test coverage

**121 tests, 464 assertions.** Every guard was deliberately broken and observed
to fail, per the P1-BASE convention.

Four tests were **vacuous before they were right**, each caught by mutation
rather than review:

1. A `setUp` `Http::fake` defeated every per-test key override — Laravel appends
   stubs and the first match wins.
2. A mixed key set proved only that the correct key is findable by `kid`, not
   that the others were filtered.
3. Asserting the exception **type** passed whichever guard fired; only the
   specific reason identifies which one.
4. The bootstrap concurrency case consumed the grant *before* the request began,
   exercising only the initial lookup — removing the atomic guard did not make
   it fail. It now consumes the grant after the user row is written, in the
   exact window a competing request occupies.

### 3.1 An honest coverage limit

`signing_key_format_invalid` is **not reachable by any input a test can
supply** — php-jwt accepts malformed moduli and defers failure to verification.
It is retained as a guard against a stricter future parser and is documented in
the code as untested, not presented as covered. The distinction that *is*
reachable and tested is `no_rs256_signing_key`.

---

## 4. Scope

Delivered exactly the approved P1-00 scope. **Not built:** roles, permissions,
business domains, scopes, sensitivity, organisations, teams, entitlements, user
or group administration, Fabric, Workplace. `P1BoundaryTest` fails if a
migration creates any of them, and asserts the `system_administrator` seam
admits no second value — P1-05 owns replacing it.

## 5. Unresolved issues

**None.**
