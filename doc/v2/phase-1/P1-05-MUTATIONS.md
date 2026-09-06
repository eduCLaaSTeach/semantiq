# P1-05 — MUTATIONS

**Every guard in the P1-05 DESIGN, broken deliberately and observed to fail.**
`CLAUDE.md` §2: a test that cannot fail is worse than no test, because it
reports safety that does not exist.

| | |
| --- | --- |
| Mutations run | **34** |
| Caught | **34** |
| Survived | **0** |

Each mutation is the one **a person who misunderstood the rule would plausibly
write** — not an arbitrary edit. Several are a single `&& false`, because the
defect being modelled is somebody deleting a guard they thought was redundant.

---

## The run

| # | Mutation | Result |
| --- | --- | :---: |
| **N-B1** | System Administrator short-circuits to allow | **Caught** |
| **N-B3** | domain_owner gets an action class of its own | **Caught** |
| **N-B9** | Organisation Administrator may grant system_administrator | **Caught** |
| **N-B10** | ORG_ADMIN satisfies a business path | **Caught** |
| **N-D1** | default to allow when no path matched | **Caught** |
| **N-D2** | engine failure returns allow | **Caught** |
| **N-D3** | skip the domain-enabled check | **Caught** |
| **N-D5** | no enabled domains returns allow (the gate, as one line) | **Caught** |
| **N-D8** | missing scope falls back to organisation (REAL mutation) | **Caught** |
| **N-D10** | inactive user filtered rather than denied | **Caught** |
| **N-C7** | missing ceiling assumed to be standard | **Caught** |
| **N-D8b** | clearing a ceiling clears to restricted | **Caught** |
| **N-SC1** | scopes intersect instead of union | **Caught** |
| **N-SC3** | return on the first scope row instead of testing all | **Caught** |
| **N-SC5** | organisation scope escapes the entitled domain | **Caught** |
| **N-SC8** | duplicate current scope permitted | **Caught** |
| **N-SC9** | ended scopes participate | **Caught** |
| **N-SC11** | scope target no longer required | **Caught** |
| **N-SC12** | scope target accepted where it does not apply | **Caught** |
| **N-P3** | a revoked assignment vetoes an active path (REAL mutation) | **Caught** |
| **N-EN2** | primary path taken in database order | **Caught** |
| **N-EN3** | engine failure raises no operational signal | **Caught** |
| **N-M8/9** | the last-administrator guard is dropped | **Caught** |
| **N-M13** | the effective count drops its active-user filter | **Caught** |
| **N-M15** | the SET lock becomes a subject lock | **Caught** |
| **N-M15b** | the deterministic order is removed | **Caught** |
| **N-L9** | revoking a role leaves its children orphaned | **Caught** |
| **N-L2** | replace becomes two separate transactions | **Caught** |
| **N-S2** | the reference is not consumed | **Caught** |
| **N-S7** | a cancellation leaves the reference reusable | **Caught** |
| **N-S9a** | a missing auth_time is accepted | **Caught** |
| **N-S9b** | an auth_time from the original sign-in is accepted | **Caught** |
| **N-E1** | an unrecognised action class falls open | **Caught** |
| **N-E5** | the route gate stops asking the engine | **Caught** |

---

## The seven that survived the first run, and what each one meant

**None of them meant the code was wrong. Every one meant a TEST was wrong**, and
that is the whole reason for running mutations rather than trusting a green
suite. They are recorded here rather than quietly fixed, because the failure
mode they share — an assertion that passes for a reason unrelated to what it
claims to check — is the one this project keeps producing.

### 1. N-B9 — the escalation guard was never reached

Removing *"an Organisation Administrator may never grant `system_administrator`"*
changed nothing, because the controller diverted the request to **step-up before
it ever reached the service holding the guard.** The assertion counted zero
assignments and passed for the wrong reason.

**This was also a real product defect.** An Organisation Administrator asking
for the System Administrator role was sent to Microsoft, re-authenticated, and
only then refused. **A confirmation somebody can never complete is a trap.** The
grantability check now runs *before* step-up is offered, the service still
refuses independently, and both layers are tested separately.

### 2. N-D8 — the mutation was a no-op

`if (false && $scopes->isEmpty())` only skipped a `continue`; the loop below
iterates an empty collection either way, so nothing changed. **Replaced with the
real defect** — falling back to an organisation scope — which is caught.

### 3. N-P3 — the mutation could not express the defect

Dropping the `ended_at` filter let revoked assignments be *evaluated*, but their
children were ended too, so no path completed and the answer was unchanged. The
test now asserts, from the emitted SQL, that revoked rows **never enter the
evaluation at all** — which is the stronger property, and the one that makes a
veto impossible rather than merely unobserved.

### 4. N-EN3 — `Log::spy()` cannot be trusted for this

`Log::shouldHaveReceived('info')->with(...)` reports its expectation as
`info(<Any Arguments>)`. **The argument constraint is not applied**, so the
assertion passed whenever *any* line was logged — which is true in every case
the engine denies. It looked like it was checking which event fired.

Replaced with `RecordingSecurityEventLogger`, a subclass that records what it
was asked to log **and still runs the real validation**, so a test using it
cannot pass against an event nobody declared.

### 5. N-M15b — one assertion, two call sites

`assertStringContainsString("->orderBy('id')", ...)` was satisfied by the
*users* query after the ordering was removed from the *assignments* query. Now
counted: **both** steps must be ordered, because a common serialisation boundary
needs one order on both halves or it is not common.

### 6. N-S9b — two conditions overlapped

*"auth_time predates the request"* was tested with `now()->subHour()`, which
also falls outside the freshness tolerance — so the other condition caught it
and the check under test could be deleted freely. The case now uses an
`auth_time` comfortably **within** tolerance and still older than the request,
which only that one check can reject.

### 7. N-E1 — nothing exercised the branch

No delivered route names an invalid action class, so the fail-closed branch was
never executed by any test. Now exercised by calling the middleware directly —
a route registered mid-test does not resolve, and that version asserted nothing
either.

---

## What the run does not cover

| # | Not covered | Why |
| --- | --- | --- |
| 1 | **True simultaneity** | Two people clicking at the same instant look identical to one person clicking twice. What is proven instead: the administrator set is genuinely held against a second connection, both reducing operations take the same boundary in the same order, and the loser re-evaluates against committed state — `AdministratorConcurrencyTest`, MySQL only |
| 2 | **Row-level filtering of real records** | There is no business data in Phase 1. Scope is tested against the structural targets it resolves |
| 3 | **A live Entra step-up round trip** | The provider's behaviour is not this suite's to assert. What is tested is every branch on this side of it: binding, expiry, replay, cancellation and all three freshness failures |
