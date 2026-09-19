# P1-08 — Audit: Product Owner Test Script

Written for you, in your words, about your screens.

## 1. Feature being tested

**System Administration → Audit.** Four tabs — User Access, Admin Changes,
Security Events, Configuration Changes — over a permanent record of who did
what, and what the platform refused.

## 2. Deployed build

Recorded at deployment. **Not yet deployed at the time this script was
written** — Gate C review comes first.

## 3. Preconditions

- You are signed in as the **System Administrator**.
- The build under test has been deployed.
- You have used SemantIQ at least once **since it was deployed** — see the
  warning below about why that matters.

## 4. Test data required

**None.** Nothing is created and nothing is typed in. The evidence you will
read is produced by ordinary use.

## 5. ⚠️ WARNING — read before you start

**EVERYTHING HERE IS PERMANENT AND CANNOT BE DELETED.**

There is no delete button, no archive, no retention job and no export. That is
deliberate: an audit trail an administrator can tidy is not an audit trail.
Anything you do while testing — **including an action the platform refuses** —
is recorded for good, under your name.

**AUDIT BEGINS THE DAY IT WAS DEPLOYED.** Everything that happened before then
went to the server's log files and was never stored anywhere searchable. The
screen states the exact start date on every tab. An empty first day is
**correct**, not a fault.

**"Cannot be tampered with" means something specific.** Anybody with direct
access to the database or the server can still delete a record, and no
application can prevent that on this hosting. What SemantIQ guarantees is that
doing so **cannot go unnoticed**: the screen shows a warning if the record has
been altered or is missing anything.

## 6–7. Numbered steps, and what must happen

### A — Reaching the screen

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| A1 | Open **System Administration** in the sidebar | **Audit** is listed and is no longer greyed out or marked "Soon" | |
| A2 | Click **Audit** | The **User Access** tab opens. Four tabs across the top, each with a count | |
| A3 | Read the sentence above the list | *"Evidence begins \<date, time\>. Activity before that time was not recorded and is not available here."* | |
| A4 | Confirm no warning banner is showing | No red **"This record may have been tampered with"** message | |

### B — Your own sign-in is evidenced

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| B1 | Stay on **User Access** | An entry reading **Signed in** with **Who: your name** and today's date and time | |
| B2 | Check what it does NOT say | No codes, no identifiers, no words like "user_id". Everything reads as plain English | |

### C — A change you make is evidenced

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| C1 | In another tab, make an **ordinary change you were going to make anyway** — rename a team, or update the organisation profile | The change works as it always has | |
| C2 | Return to **Audit → Admin Changes** and refresh | The change is listed, naming **you** as who did it, what was changed, and when | |

**Do not invent a change to test this.** Anything you do is permanent, and
false organisational history is worse than an untested step. If you have no
real change to make, mark C **NOT TESTED** and say so.

### D — A refusal is evidenced

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| D1 | Open **Access Reviews** and try to remove your own System Administrator access (the P1-07 retest you have already run) | The familiar refusal: *"This is the only active System Administrator…"* | |
| D2 | Open **Audit → Security Events** | The refusal is listed, naming **you**, with **Outcome: Refused** and a reason | |

**This is the important one.** A refused action is recorded exactly because it
was refused.

### E — Searching

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| E1 | On any tab, choose an **Action** from the dropdown and press **Apply** | Only that kind of entry is listed. Every option reads as business English | |
| E2 | Type part of your own name into **Person** and **Apply** | Only entries involving you | |
| E3 | Set **From** to tomorrow's date and **Apply** | *"Nothing here matches what you are looking for."* — an empty result, not an error | |
| E4 | Press **Clear** | The full list returns | |

### F — The four tabs hold different things

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| F1 | Open each of the four tabs | Each shows its own kind of activity; the counts differ | |
| F2 | Confirm nothing appears **twice** | A refused sign-in is in **Security Events only**, never also in User Access | |
| F3 | Open **Configuration Changes** | Sign-in configuration activity, or an empty message. Empty here is normal | |

## 8. Negative, refusal and security cases

| # | Step | Expected result | PASS / FAIL |
| --- | --- | --- | --- |
| G1 | With Audit open, try to edit or delete an entry | **There is nothing to click.** No edit control, no delete control, anywhere | |
| G2 | Open **Security Status → Security Events** | It still describes **what is recorded**, and now points you at Audit for what happened | |

## 9. Visual and UX checks

| # | Check | PASS / FAIL |
| --- | --- | --- |
| V1 | Every entry reads as a sentence a person would say — no codes, no identifiers, no field names | |
| V2 | The evidence start date is visible on **every** tab without scrolling to find it | |
| V3 | Nothing is cut off or runs past the edge, on your normal window **and** a narrow one | |
| V4 | The four tabs are readable and clickable at a narrow width | |
| V5 | Light and dark both look deliberate | |
| V6 | Empty results say something useful rather than showing a blank area | |
| V7 | Would a professional SaaS product team show this screen to a customer? | |

## 10. Evidence to capture

1. Each of the four tabs, **including the address bar**.
2. The evidence start sentence.
3. The entry for your own sign-in (B1).
4. The refusal entry (D2).
5. Audit at a narrow window width.

## 11. NOT CURRENTLY OBSERVABLE WITH REAL PRODUCTION DATA

**Each of these would require creating false or permanent history, or
manufacturing access that should not exist.** Each keeps its automated
evidence. **None is an implementation defect.**

| What cannot be observed | Why not | Automated evidence |
| --- | --- | --- |
| **An Auditor reading Audit** | D-19 shows the sidebar to System Administrators only, so an Auditor cannot reach any console screen. **Carried**, unchanged from P1-07 | `AuditRoutesTest::test_evidence_readers_reach_audit` |
| **An Organisation Administrator seeing less than you** | Needs a genuine second privileged person. **One will not be created** | `AuditVisibilityTest` — rows and fields |
| **Technical identifiers being withheld** | Same reason | `test_platform_sensitive_identifiers_are_withheld` |
| **Fail-closed behaviour** | Would mean breaking the evidence store in production on purpose. **It will not be broken** | `AuditFailClosedTest` — four cases |
| **The tamper warning** | Would mean editing the production database to trigger it. **It will not be edited** | `AuditChainTest` — altered, removed, end-removed and forged |
| **Two people acting at the same instant** | Not performable by one person in a browser | `AuditChainConcurrencyTest`, **MySQL in CI** |
| **Evidence older than the deployment** | It does not exist. D-109 | — |

### 11.1 Anything else that cannot be tested

- **Visibility itself cannot be asserted by any automated test in this
  project.** There is no JavaScript test runner, so every check in section 9 is
  a **human** check — which is why they are here rather than in the suite.
- **P1-02's provider-wide SSO re-check remains OPEN / CARRIED / UNVERIFIED**,
  and is untouched by this unit.
- **Everything P1-07 carried remains carried.**
