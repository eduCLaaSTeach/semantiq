# Fresh Project Start

Use as the first command in a new project: the repository the infra team provides with the environment branches and the root `CLAUDE.md` plus the `.claude/`, `doc/`, and `scripts/` folders already in it, or any other blank or unknown repository that holds them. It updates the gateway for this project; nobody needs to write an intake prompt.

Input: `[INITIAL_USER_GOAL]`

Process:

1. Read `.claude/README.md`, `.claude/PROJECT-CONTEXT.md`, `.claude/rules/fresh-project-gateway.md`, `.claude/rules/project-intake.md`, and `.claude/rules/deployment.md`.
2. Inspect repository structure only enough to identify likely questions. Note whether `doc/solutiondocument/` holds active requirements, any `Archive` folder excluded; when it does, they answer what to build, and `/generate-phase-plan` plans them per `.claude/rules/solution-development-kit.md`.
3. Ask what the developer wants to build or change, and how, including the approved stack, versions, framework, package manager, and validation commands.
4. Ask source control, CI/CD, and hosting/deployment details: provider/target, domain/base URL, runtime, build system, artifact/output path, startup command or entry point, deployment method, required environment placeholders, rollback, and manual/provider steps.
5. Ask database details: engine and version, the schema source of truth, the migration tool, and whether database-backed work is in scope.
6. Ask security, authentication, data sensitivity, and user-role details.
7. Record open questions in `.claude/PROJECT-CONTEXT.md`; after developer confirmation, update it with non-secret confirmed facts.
8. Keep asking follow-up questions until the task is clear enough to implement, validate, explain, and deploy safely. If any required answer is still unclear, stop and ask again. Do not implement, edit, run commands, update project context, or proceed with deployment/schema work from guesses.

Return:

- Confirmed project facts
- Whether active requirements exist under `doc/solutiondocument/`, and the approval status of the two planning documents
- Missing facts still blocking work
- Follow-up questions
- Hard-stop blockers from unclear answers
- Project context updated or needing update
- Whether database details are complete
- Whether deployment details are complete
- Validation plan status
