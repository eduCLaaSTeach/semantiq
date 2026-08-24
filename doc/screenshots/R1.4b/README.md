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

## The pair that matters

`audit-logs.png` and `audit-logs-auditor.png` are the same screen, the same
data, two readers. Compare the table headers:

- System Administrator: `WHEN  WHAT  WHO  OUTCOME  FROM`
- Auditor: `WHEN  WHAT  WHO  OUTCOME`

The network column is not hidden with CSS and not blanked out. It is never
selected, so it never reaches the page. That is SEC-DEC-063 working, and it is
the reason an Auditor can read the whole trail without receiving network
identifiers as a side effect.

The Auditor capture also shows two `privileged.action.denied` rows against
`admin.retention.manage`. A refusal is evidence and is recorded, which is how an
incident review finds attempts rather than only successes.
