# V1.4 Migration From V1.3

V1.4 keeps all V1.3 human approval, schema verification, single-task execution, time tracking and UAT controls, while simplifying delivery to at most five macro phases and adding persistent core-repo knowledge. In a gateway project these files arrive with the gateway's `.claude/`, `doc/`, and `scripts/` folders.

## Add Or Merge
- `.claude/agents/infra-readiness-agent.md`
- `.claude/agents/core-repo-maintainer.md`
- updated kit agents, skills and rule files from V1.4
- `/doc/corerepo/`
- updated developer guides

## Preserve
- current root `CLAUDE.md`
- organisation and project `.claude` rules, skills, agents and context
- active `/doc/solutiondocument/`
- current `/doc/tasktracking/` history
- the existing accepted Phase Plan and tasks, unless a V1.4 drift check says a refresh is required

## New Controls
- at most five macro phases, which replace the per-phase `PLAN -> DESIGN -> IMPLEMENT -> TEST -> VERIFY -> ACCEPT` lifecycle;
- explicit Frontend, Backend, Database, Integration/API, AI, Security, Tests, UAT, Indexing and Idempotency workstream fields per feature task;
- feature and requirement coverage proof, with unresolved conflicts blocking;
- indexing and idempotency design and testing where applicable;
- Phase 1 infrastructure and master database readiness;
- `/doc/corerepo/` as living, vendor-neutral project knowledge;
- progressive context loading and event-driven documentation to keep AI usage low.
