# P1-09 — System Health: mutation record

**Every guard below was broken deliberately and the test observed to fail.**
CLAUDE.md §2: a test that cannot fail is worse than no test, because it reports
safety that does not exist.

**51 mutations. 51 killed — but six survived first, and those six are the
useful part of this document.** A mutation that dies immediately confirms a
guard that was already sound. A mutation that survives has found a test
measuring something other than what it claims to.

**Seven of the 51 were added after the Gate C review**, against two defects the
Product Owner found by reading the code rather than the tests: a cache check
that raced with itself, and a stored sign-in state that could claim a result
with no measurement time. Neither was reachable by any mutation of the code as
it then stood, because **neither guard existed** — §4.

---

## 1. The six that survived, and what each one exposed

| # | Mutation | Why it survived | What changed |
| --- | --- | --- | --- |
| **M-4** | `storedReport()` trusts any stored value — `$state !== null` in place of the three-constant check | The absence cases asserted the **rendered row**, and the row's `match()` fell through its `default` arm to Not checked. So a garbage state came out of `storedReport()`, the screen looked right, and the guard was measuring the row's default rather than the method's contract — **the M-A5 failure from P1-08, in a new place** | Contract asserted where it lives: `storedReport()` returns one of P1-02's four constants **whatever** is in the cache, over eight shapes of rubbish. The row is not its only caller, and the next caller will not have a default arm to hide behind |
| **M-4b** | Loose comparison — `in_array(..., false)` | **None of the rubbish values distinguished strict from loose.** PHP 8 no longer compares `1` or `''` equal to `'healthy'`, so dropping the strict flag changed nothing for anything the test tried | Added `true` and `false`. `in_array(true, ['healthy', …], false)` **is** true, because `true == any non-empty string` — the one value that tells the two apart |
| **M-15** | The cache check stops comparing the value — reports Available whenever nothing threw | **Nothing anywhere exercised a cache that answers without failing.** A real cache outage rarely throws: it returns null, or a stale value, or drops the write | A new test file over a `BrokenCacheStore` — empty, lying, forgetful and throwing. **Three of the four throw nothing at all** |
| **M-28** | The inspector's own `detail` is passed through to the screen | The leak guard passed, because none of those details carries a secret — and **every one of them would have put developer terminology on a Product Owner's screen**: *"3 migration(s) pending"*, *"Build manifest present"*, *"2 configuration problem(s); see the log"*. The professional-polish gate had no test at all | Every explanation is asserted to be one **this unit declared**: not an inspector detail, no digits, no implementation words, a finished sentence. Run in the healthy **and** the broken state. It immediately caught my own copy — *"a query answered"* |
| **M-35** | The re-check hard-redirects to `identity.health` again | The test pressed twice in one case, clearing the rate limiter between. **The second press never reaches the success branch**: `EntraDiscovery` holds a provider-wide probe lock, so `ran` is false and the controller returns `back()` from a *refusal* branch whatever the success branch says | Split into two cases, one per origin, each a first press, each asserting the confirmation **and** no errors — so a refusal cannot satisfy the assertion on another line's behalf |
| **M-37** / **M-38** | The session round trip never reads back / reads back and ignores the result | The success case still reported Available, and the missing-table case still failed at the **insert**. So *"written, read back and discarded"* was a sentence on the screen with nothing holding it up | The statement guard now asserts exactly one `insert` and exactly one keyed `select`; and a separate file gives the check a connection whose `select()` returns nothing, and one that returns somebody else's row |

**M-40 is worth a line of its own.** A fixed synthetic session identifier
survived every case. It is not cosmetic: two administrators refreshing at the
same moment collide on the primary key, so one of them sees **Unavailable for a
session store that is working perfectly** — a false red on a healthy system.

---

## 2. The full table

### The network boundary — correction 1

| # | Mutation | Verdict | Killed by |
| --- | --- | --- | --- |
| M-1 | **The previous DESIGN**: `storedReport()` → `report()` | **KILLED** | cold-cache boundary, absence, stored-state and whole-page cases |
| M-2 | **The previous DESIGN**: `inspectLocal()` → `inspect()` | **KILLED** | `…contacts_nobody`, `…cold_cache…not_checked` |
| M-3 | `storedReport()` returns HEALTHY when nothing is stored | **KILLED** | three cases |
| M-4 | `storedReport()` trusts any non-null value | **survived → KILLED** | see §1 |
| M-4b | Loose `in_array` | **survived → KILLED** | see §1 |
| M-4c | `str_contains` in place of equality | **KILLED** | `…whatever_is_cached` |
| M-5 | The sign-in row collapses Degraded into Available | **KILLED** | `…all_three_directions` |
| M-7 | `inspectLocal()` includes identity after all | **KILLED** | five cases |
| M-8 | `inspect()` loses identity while "simplifying" | **KILLED** | six, including the console command and the deployment verdict |
| M-9 | `storedReport()` drops the checked-at age | **KILLED** | two |

**M-1 and M-2 are the two that matter most**, because each restores the design
this unit was corrected away from. If either had survived, the correction would
have been prose.

### The session store — correction 2

| # | Mutation | Verdict | Killed by |
| --- | --- | --- | --- |
| M-10 | Hard-code Available (never run the round trip) | **KILLED** | missing-table and DDL cases |
| M-11 | Commit instead of rolling back | **KILLED** | `…leaves_no_session_behind` |
| M-12 | Report Available for an unsupported driver | **KILLED** | `…never_available` |
| M-13 | Swallow the failure and report Available | **KILLED** | missing-table |
| M-14 | Catch `Throwable` to unwind, hiding real failures | **KILLED** | missing-table |
| M-27 | Put the caught exception in the explanation | **KILLED** | missing-table |
| M-37 | Delete the read-back | **survived → KILLED** | see §1 |
| M-38 | Read back and ignore the result | **survived → KILLED** | see §1 |
| M-38b | Check only for null, not identity | **KILLED** | `…returns_the_wrong_row…` |
| M-40 | Fixed synthetic identifier | **survived → KILLED** | `…different_synthetic_identifier` |
| M-41 | Drop the synthetic prefix | **KILLED** | same |
| M-31 | Add a `file` driver adapter that writes a session file | **KILLED** | the architecture guard |

### The cache store

| # | Mutation | Verdict | Killed by |
| --- | --- | --- | --- |
| M-15 | Stop comparing the value | **survived → KILLED** | see §1 |
| M-16 | Compare loosely, and treat null as a pass | **KILLED** | `…answers_wrongly…` |
| M-17 | Never forget the probe key | **KILLED** | `…namespaced_and_forgotten` |
| M-18 | Write a constant | **KILLED** | `…different_value` |

### Tasks and timetables — the three easiest statuses to fake

| # | Mutation | Verdict | Killed by |
| --- | --- | --- | --- |
| M-19 | Hard-code Not applicable regardless of driver | **KILLED** | the **other-direction** case |
| M-20 | Hard-code Not configured regardless of the schedule | **KILLED** | the **other-direction** case |
| M-21 | Report Unavailable where the deployment is working as intended | **KILLED** | `…not_applicable_while_work_runs_inline` |

**Each is broken in both directions** — forced on when it should not be, and
absent when it should. Only the second direction catches a literal.

### The payload, the roll-up and the route

| # | Mutation | Verdict | Killed by |
| --- | --- | --- | --- |
| M-6 | A missing local check defaults to Available | **KILLED** | `…absent_is_not_checked` |
| M-22 | Report the audit chain without verifying it | **KILLED** | `…does_not_verify…` |
| M-23 | Roll identity into Local service health | **KILLED** | `…excludes_sign_in` |
| M-24 | Add a `detail` passthrough field to `HealthRow` | **KILLED** | the four-property guard |
| M-25 | Add a POST under the prefix | **KILLED** | the route-set equality |
| M-26 | Drop the route to `EvidenceRead` | **KILLED** | the six-role refusal sweep |
| M-28 | Pass the inspector's operator detail to the screen | **survived → KILLED** | see §1 |
| M-29 | Record a security event on render | **KILLED** | runtime **and** static guard |
| M-30 | Reimplement the storage check inside this module | **KILLED** | `…reimplements_no_existing_check` |
| M-32 | Change a `HealthStatus` backing value | **KILLED** | the enum guard |
| M-33 | Name a business model in the module | **KILLED** | `…names_no_business_model` |
| M-34 | Leave the sidebar node locked | **KILLED** | two navigation cases |
| M-36 | Drop a row from an area | **KILLED** | the shape equality |
| M-39 | Render a dotted configuration key as a row name | **KILLED** | two |
| M-42 | Add a key to the rendered payload | **KILLED** | the allowlist equality **on the props** |
| M-43 | Hard-code Local service health as Available | **KILLED** | `…fails_when_a_local_check_fails` |
| M-44 | Hard-code the evidence-store row as Available | **KILLED** | `…does_not_verify…` |
| M-35 | Hard-redirect the re-check away from System Health | **survived → KILLED** | see §1 |

---

## 3. The seven added at Gate C review

### Correction A — the cache check raced with itself

The defect needed no mutation to find: one fixed key meant two overlapping
renders wrote to the same address, and the loser read the winner's value and
reported **Unavailable on a healthy cache**. The mutations below prove the fix
is real rather than incidental.

| # | Mutation | Verdict | Killed by |
| --- | --- | --- | --- |
| C1-M1 | Restore the single constant key | **KILLED** | the 25-key case, the interleaved case, the isolation case |
| C1-M2 | Unique but **predictable** — a static counter | **KILLED** | the 25-key case, which asserts 32 hex characters rather than mere uniqueness |
| C1-M3 | `forget()` the prefix rather than the generated key | **KILLED** | the 25-key case and the interleaved case, which assert nothing is left in the store |

**C1-M2 is the one worth keeping.** A counter is unique within a process and
collides across two of them, which is exactly the deployment this check runs
in. Asserting *distinct* would have passed it; asserting the **shape** does
not.

**No lock was added.** Serialising renders behind a mutex to protect a
throwaway diagnostic value would make the screen slower and could stall under
contention — a health page must not become the thing that needs diagnosing.
Unique keys remove the race rather than guarding it.

### Correction B — a stored state could claim a result with no age

| # | Mutation | Verdict | Killed by |
| --- | --- | --- | --- |
| C2-M1 | Take the instant on trust — the line as it was | **KILLED** | the eight-shapes case and the rendered-row case |
| C2-M2 | `is_string()` only; never parse | **KILLED** | the eight-shapes case — `'recently'` and `'2026-13-45T99:99:99'` are strings |
| C2-M3 | Accept an empty or whitespace instant | **KILLED** | same |
| C2-M4 | Reject the instant but report the state anyway, just without an age | **KILLED** | both |

**C2-M4 is the subtle one.** It is what a careful person would write if they
understood the rule as *"do not invent an age"* rather than *"a state without a
time is not a result"* — the state still renders as Available, and the age is
simply missing, which is the original defect wearing a tidier implementation.

---

## 4. Two things the mutation pass did NOT do

- **It did not break production, and it issued no DDL.** Every failure is
  induced at a dependency boundary: a manager that cannot hand out a
  connection, a store that answers wrongly, a configured table name that is not
  there, a storage path that does not exist. `Schema::drop()` appears nowhere —
  it commits the open transaction on MySQL and cost P1-08 four cases that were
  green on SQLite and red on the engine production runs. D-128.
- **It did not prove the absence of every defect, and the Gate C review is the
  evidence for that.** A 44-for-44 mutation score was reported against a build
  that contained **two real defects**: a cache check that raced with itself and
  a stored state that could claim a result with no age. Both were found by
  **reading the code**, not by mutating it — because a mutation can only break
  a guard that exists, and neither guard existed.

  A mutation score measures the tests that are there. It says nothing about the
  case nobody thought to write, which is why it is reported here beside what is
  NOT covered rather than in place of it.
