# Document Code

Use to add or improve structured documentation comments ("docstrings") and logic explanations for a file, module, or scope.

Input: `[FILE_OR_MODULE_OR_SCOPE]`

Process:

1. Read `.claude/PROJECT-CONTEXT.md`, `.claude/rules/code-documentation.md`, `.claude/rules/production-readiness.md`, and `.claude/rules/secret-handling.md`.
2. Confirm the project language and any existing documentation convention from the codebase. Ask if the stack or convention is unknown.
3. Inspect the target scope; identify undocumented or under-documented modules, classes, abstract classes, interfaces, enums, functions, methods, constructors, significant variables/constants, and non-obvious logic.
4. Add documentation comments in the project's idiomatic docstring style: summary, parameters, return value, raised/thrown errors, side effects, and invariants where applicable.
5. Explain non-obvious logic with intent-focused inline comments; do not add redundant comments on self-evident code.
6. Do not change runtime behavior. Documentation-only edits must not alter logic.
7. Keep secrets and sensitive data out of comments and examples; use placeholders.
8. Run available documentation, lint, type, or build checks. State exact results, or document why validation could not run and the exact follow-up command.

Return:

- Files changed
- Declarations documented and convention used
- Logic explanations added
- Intentionally undocumented areas and why
- Validation commands and results
- Confirmation no secrets were placed in comments or examples
