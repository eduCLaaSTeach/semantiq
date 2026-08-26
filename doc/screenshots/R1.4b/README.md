# R1.4b screen captures

Taken 24 August 2026, at the point the product owner accepted R1.4b.

## How these were produced, and what they are not

These are captured from a LOCAL server running the accepted code
(`c5974ab`) against a local database with the same three migrations applied.
They are **not** photographs of the production server: this session cannot sign
in to production and holds no production credential.

Production was verified separately, by the product owner, on the live
deployment. Their observations are recorded in
`doc/execution/ADMIN-FOUNDATION-RELEASE-1-VERIFICATION.md` section 15.5 and
match these captures.

## Personal data is masked

Audit rows legitimately show the actor's email address, and `CLAUDE.md` forbids
committing personal data in a screenshot. Every address is replaced with
`person@example.test` in the browser DOM immediately before the shutter, so the
committed image carries none. Nothing in any database was changed to produce
that: only what the camera saw.

The `From` column shows `127.0.0.1` because this is a local capture. On a real
deployment it holds a genuine network identifier, which is exactly why it sits
behind its own permission.

## The captures

| File | Screen | What it evidences |
| --- | --- | --- |
| `retention.png` | PDPA-03 Retention | 7 categories, every compliance-owned value `Not Configured`, state `Nothing recorded`. The blue notice states SemantIQ deletes nothing as a result of the screen; the amber notice states plainly that 7 of 7 categories have no retention period, rather than letting an empty table read as coverage |
| `exceptions.png` | ADM-016 Sovereignty Exceptions | The approved sovereignty position, and an empty exception list whose wording says the approved position applies without exception. The request form states that a request permits nothing and that the requester can never approve their own |
| `audit-logs.png` | ADM-013 Audit Logs, System Administrator | Five view presets, eight filters, and the **`From` column present** because this reader holds `admin.audit.view_network` |
| `audit-logs-auditor.png` | ADM-013 Audit Logs, Auditor | The same trail with **no `From` column at all** - absent, not blanked. The rail also shows only Workspace and Compliance: no Application Administration, no System Administration |

## The pair that matters - SEC-DEC-063 evidence

`audit-logs.png` and `audit-logs-auditor.png` show the **same Audit Log feature
and substantially the same audit history, viewed under two authorization
contexts.**

Compare the table headers:

- System Administrator: `WHEN  WHAT  WHO  OUTCOME  FROM`
- Auditor: `WHEN  WHAT  WHO  OUTCOME`

**The FROM / network-origin column is structurally absent for the Auditor.** Not
blanked, not redacted, not hidden with CSS. The column is never selected, so it
never reaches the page. That is SEC-DEC-063 working, and it is the reason an
Auditor can read the whole trail without receiving network identifiers as a side
effect.

### They are not identical snapshots, and the wording matters

The two captures were taken moments apart and **the event count is different**:
the System Administrator capture reads `All Events 27` and the Auditor capture
reads `All Events 28`.

The difference is explained and benign. The extra row is the newest one in the
Auditor capture, an `auth.login.succeeded` at 14:41:31 UTC - the Auditor's own
sign-in, recorded between the two shutters. Everything at 14:41:27 and earlier
appears in both.

This is stated rather than glossed because an earlier version of this file
described the pair as "the same screen, the same data, two readers". **The
history was substantially the same but the snapshots were not identical**, and a
claim of identity that a reader can disprove by counting the badge weakens
evidence that is otherwise sound. The column comparison is what carries
SEC-DEC-063, and it does not depend on the two rows sets matching.

### Navigation is separate, supporting evidence

The Auditor rail shows only `WORKSPACE` and `COMPLIANCE`. The System
Administrator rail additionally shows `APPLICATION ADMINISTRATION` and
`SYSTEM ADMINISTRATION`.

That is real authorization evidence and worth keeping, but it is **filter-not-fork
navigation, a different control from the column-level rule.** It is recorded
here as supporting evidence and is deliberately not folded into the SEC-DEC-063
conclusion above.

### Denials are visible

The Auditor capture also shows two `privileged.action.denied` rows against
`admin.retention.manage`, at 12:42:11 and 12:41:01 UTC. A refusal is evidence
and is recorded, which is how an incident review finds attempts rather than only
successes.
