# Owner Standing Instructions

Five standing instructions issued by the project owner. They are **always in force**, in
every session and every task in this repository, and they are summarised inline in the
Non-Negotiable Rules section of `CLAUDE.md` so they bind without this file being read.

Read this file when a task reaches gate **OSI** — any Git/PR action, any feature delivery,
any completion report, or any knowledge-base update — for the operational detail below.

Each instruction is recorded verbatim, then operationalised, then reconciled against the
existing rule it touches. Where a standing instruction changes an existing rule's default,
that change is stated explicitly here rather than left as a silent contradiction.

---

## OSI-1 — Git tasks: always deliver the PR link, and automate it

> *"Every task which is involved with git - Give me PR link and automate it."*

**What this means operationally.** On any task that commits or pushes, do not stop at the
push. Create the Pull Request, and return its URL in the final summary. The owner should
never have to open a PR by hand for work Claude pushed.

| Action | Automated by Claude | Requires the owner |
|---|---|---|
| Branch, commit, push | Yes | No |
| **Create the Pull Request** | **Yes — by this instruction** | No |
| Report the PR URL | Yes, in the final summary | No |
| Subscribe to the PR and act on CI failures and review comments | Yes | No |
| **Merge the PR** | **No** | **Yes — approval and merge stay with the owner** |
| Promote between environment branches | No | Yes |
| Production deploy or database migration | No | Yes, explicitly, every time |

**PR target** follows the promotion flow in `.claude/rules/git-branching-release.md` without
exception: working branches into `DEV`; `release/*` promoted through `QA`, `STAG`, `PROD`;
`hotfix/*` into `PROD` then back to `DEV`. Never propose a target that skips a stage. If the
correct target is unclear, ask — that rule's final line still governs.

**Every PR body states**, per that rule: what changed; why; how it was tested; source and
target branch; and whether it affects release, deployment, database, API, configuration or
operations. A PR that merges into a branch with a deployment workflow attached must say so
explicitly, because merging then triggers a deploy.

### Reconciliation with `git-branching-release.md`

That rule's *"After Pushing A Branch (PR Hand-Off)"* section makes handing the PR to the
developer the **default**, and permits Claude to create one *"when the developer explicitly
asks for that exact action."*

OSI-1 **is** that explicit ask, given once as a standing instruction. So:

- The hand-off default is **replaced by automatic PR creation** for this repository.
- Everything else in that section is **retained**: the PR URL is still shown, the
  recommended target is still stated from the promotion flow, and the ordered copy-ready
  next-step list is still given to the owner — now for review, approval and merge rather
  than for creating the PR.
- The **merge** step is *not* covered by OSI-1 and is not automated. "Automate it" was asked
  of the PR, not of the merge, and merging into a protected environment branch with a
  deployment workflow attached is an outcome the owner must choose. Claude merges only when
  asked for that specific merge.

---

## OSI-2 — Security is considered in every feature that is built

> *"Always make sure security is consider when every feature is built."*

**What this means operationally.** Security is not only a review at the end; it is a design
input at the start and a gate before "done".

**At design time**, for every feature, state in the design or plan:

1. **Trust boundary** — what crosses a process, network or tenant boundary here.
2. **Identity and authorisation** — who may perform this, enforced *server-side*, not only
   hidden in the UI. Name the authorisation layers: feature gate, per-record policy,
   query/list scope, and any destructive-action gate.
3. **Data classification** — what the feature reads, writes or logs, and whether any of it
   is a secret, a credential, personal data or production row data.
4. **Secret handling** — where credentials live, how they are stored, and confirmation that
   none reaches a log, a draft, a URL, a comment, a docstring, an error message or the UI.
   `.claude/rules/secret-handling.md` governs and is never relaxed.
5. **Input handling** — validation and output encoding at the boundary, parameterised
   queries, and no user input reaching a shell, a query or a file path unmediated.
6. **Failure mode** — what a failed check does. It must **fail closed**: an unreadable
   permission, an unverifiable state, or a failed evidence load denies rather than allows.
7. **Auditability** — what record is left of who did what, and to which record.

**Before "done"**, gate REV applies unchanged and in full: the `code-reviewer` and
`security-reviewer` subagents run over the **actual diff**, not as a self-assessment.
`Critical` and `High` findings block the done claim until fixed and re-reviewed, or
explicitly signed off by the owner and recorded. Both passes are reported every time,
including when one ran clean, and a pass that did not run is never reported as one.

**Multi-tenant note for this project.** SemantIQ holds delegated Microsoft credentials and
operates across customer tenants, so two checks are mandatory on every feature that touches
tenant data: a **negative cross-tenant test** proving the feature cannot read another
tenant's rows, and confirmation that **no Microsoft access or refresh token reaches the
browser**. Neither is optional and neither is satisfied by inspection alone.

---

## OSI-3 — Completion detail, PR monitoring, and the owner's step-by-step guide

> *"Every task you complete, provide me details on completion follow by monitoring PR and
> giving me step by step guide which i need to perform on my side."*

Every completed task ends with three things, in this order. This composes with the
per-rule Final Reporting sections; it does not replace them.

**1. Completion detail — what was actually done.**
Changes made and the files they touched; what was validated and by which command, with the
real result; which reviews ran (gate REV) and their findings; what was **not** done and why;
and every assumption that remains unverified, named as such.

**2. PR monitoring — the PR is watched, not handed over and forgotten.**
Subscribe to the PR's activity. On a CI failure, diagnose the root cause and push a fix, or
state plainly what is failing and why it is not this change's to fix. On a review comment,
implement small in-scope asks and push; put larger ones to the owner with a proposal. Keep
the PR watched until it is merged or closed. Report the PR's live state — mergeable, CI
status, open review threads — in the completion detail.

**3. The owner's step-by-step guide — only what Claude cannot do.**
An ordered, copy-ready list of the actions that require the owner: things needing a human
identity, a portal Claude cannot reach, an approval, a merge, a deploy, or a secret. Each
step states **where** to go, **what** to do, and **how to confirm it worked**. Never pad the
list with steps Claude already performed, and never include a step Claude could have done.
If nothing is required, say "nothing required on your side" rather than inventing filler.

---

## OSI-4 — Verify, never assume; no volume in place of fact

> *"Always verify never assume and hallucinate with lots of information."*

This sharpens the existing **Ask, don't assume** hard stop with an evidence discipline.

**Verify before stating.** Read the file, run the command, query the API, check the branch.
A claim about this repository, its history, its configuration or its dependencies is made
from something that was actually read this session — never from memory of a previous
session, and never from a plausible default.

**Cite the source.** Reference a fact by file path and symbol, branch and commit, or the
command whose output was read, so the owner can check it. Prefer a citation over a
re-pasted blob.

**Label every unverified claim** as unverified, in the same sentence, and say what would
verify it. Terms to use precisely and never interchangeably:

| Term | Means |
|---|---|
| **Verified** | Read, executed or queried this session; the source is citable |
| **Unverified** | Believed but not checked; the check is named |
| **Assumption** | A deliberate choice made in the absence of a fact; the owner can overturn it |
| **Not applicable** | Genuinely does not apply — stated, not silently omitted |

**Schema and external APIs are never asserted from memory.** Database names come from the
`schema` MCP server, live, every session (gate D). Microsoft Fabric, Power BI, Power Platform
and Copilot Studio API surfaces change and their service-principal support varies per
endpoint: probe them, and record availability as a capability result, not as documentation.

**No padding.** Length is not evidence. Do not fill a gap with adjacent plausible detail,
do not restate the question back as an answer, and do not produce a long response where a
short verified one is available. **"I have not verified that" is a complete and acceptable
answer.** A confident wrong answer costs the owner more than an admitted gap.

---

## OSI-5 — On confirmed working features, update the three living documents

> *"Always once feature is confirm working - Update Feature list Doc, Data Dictionary doc,
> Code dictionary doc."*

**Trigger.** The feature is confirmed working — implementation complete, validation actually
run (or the limitation documented), gate REV passed, and the owner has confirmed it works.
Not on merge, and not on Claude's own confidence.

**The three documents.**

| Owner's term | Home | Holds |
|---|---|---|
| **Feature list Doc** | `docs/knowledge-base/` — feature write-ups under the solution → module → feature hierarchy, plus a flat feature index in the knowledge-base README | Per feature: purpose, status, owner, the module it belongs to, and a link to its write-up. The write-up carries the six-part contents required by `knowledge-base.md` |
| **Data Dictionary doc** | `docs/knowledge-base/table-dictionary.md` | Metadata only: each table's one-sentence grain, each column's analytical role with unit and additivity, relationships with cardinality, indexes. Never row data, customer records or production values |
| **Code dictionary doc** | **`docs/knowledge-base/code-dictionary.md` — proposed, needs the owner's confirmation** | The map from feature to code: modules and their responsibility, key classes and services with their file path, routes and their handler, jobs and commands, and the public surface of each module. Cited by path and symbol, never pasted code |

**The Code dictionary is a new artifact.** `knowledge-base.md` defines the knowledge base and
the table dictionary but has no code dictionary, and `code-documentation.md` governs
docstrings *in* the code rather than a document *about* it. Its name, location and contents
above are therefore a **proposal awaiting the owner's confirmation**, not an established
convention.

**The approval gate in `knowledge-base.md` still stands and is not weakened by OSI-5.**
That rule requires the owner to have verified, validated and explicitly approved the update,
and forbids treating silence or Claude's confidence as approval. OSI-5 sets the **trigger and
the scope** — when a feature is confirmed working, all three documents are offered together,
none skipped. It does not authorise writing them unprompted. So on a confirmed feature:
prompt for all three, name the classification question, write only what the owner approves,
refresh the README index in the same change, and record any deferral in the final report.

**One exception is inherited unchanged:** when a change actually alters the schema, the
table-dictionary update travels in the **same commit or PR** as the schema change, so
documented schema never drifts from real schema.

---

## Open items these instructions raise

Recorded here rather than assumed. Each needs one answer from the owner.

1. **Code dictionary** — confirm the name, the location (`docs/knowledge-base/code-dictionary.md`
   proposed) and the contents above.
2. **`doc/` versus `docs/`** — the design set was placed in `doc/` as instructed, while
   `knowledge-base.md` specifies `docs/knowledge-base/`. Confirm whether the design set moves
   to `docs/` so there is one documentation root, or whether `doc/` stays as a separate home
   for pre-implementation design.
3. **Feature list shape** — confirm whether the flat feature index lives in the
   knowledge-base README or as its own `docs/knowledge-base/feature-list.md`.
4. **Working branch naming** — this platform mandates the session branch name
   `claude/<slug>`, while `git-branching-release.md` specifies `feature/*` from `DEV`.
   Confirm whether `claude/*` is accepted as an equivalent working-branch prefix, or whether
   work should be re-pushed under `feature/*` before the PR.

## Final Reporting

For any task reaching gate OSI, report: the PR URL and its live state (mergeable, CI, open
review threads) whenever a branch was pushed; the security considerations named at design
time and the outcome of both gate REV passes; the three-part completion report required by
OSI-3, ending with the owner's step-by-step guide or an explicit "nothing required on your
side"; every unverified claim labelled as such with the check that would settle it; and, on
a confirmed working feature, whether the three OSI-5 documents were offered and the owner's
decision on each.
