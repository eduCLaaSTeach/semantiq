# Code Documentation Rules

Claude must document code with structured documentation comments ("docstrings") wherever applicable, in the confirmed project language's idiomatic convention, and explain non-obvious logic, without redundant noise.

Applies to every task that writes or edits code. It does not override `.claude/rules/secret-handling.md`, `.claude/rules/schema-mcp.md`, or the DDL restrictions.

## Convention First, Assume Nothing

- Do not assume the language or documentation style. Use the confirmed stack from `.claude/PROJECT-CONTEXT.md`; ask if unknown.
- Follow the repository's existing documentation convention when one exists (its docstring style, tag set, ordering, tone). When none exists, use the language's standard format (see table) and stay consistent.
- Preserve existing accurate documentation. Do not reformat unrelated docs unless documentation is the task.

## What Must Be Documented

Add a docstring for each of these when public, exported, or non-trivial:

- Modules, files, packages, namespaces: purpose and responsibility.
- Classes, abstract classes, interfaces, traits, structs, records, enums: responsibility, invariants, intended usage.
- Functions, methods, constructors, property accessors: behavior, parameters, return value, raised errors, side effects.
- Significant variables, constants, fields, config values: purpose, units, valid range, invariants.
- Non-obvious or complex logic: explain the WHY (intent, trade-off, edge case, workaround), not a restatement of the code.

Where applicable also cover: parameter meaning/types, return semantics, errors raised, side effects, nullability, generic/type parameters, async/concurrency/thread-safety, performance or security caveats, examples for non-trivial public APIs, and deprecation notes.

## Thorough, No Noise

- Document every declaration above and explain non-obvious logic. Do not leave public/exported or complex code undocumented.
- Do not restate self-evident code (`i = 0  // set i to zero`). Prefer clear names over comments that compensate for unclear names. Inline comments explain intent and reasoning, not mechanics.
- Keep documentation accurate and in sync; update or remove stale, misleading, or contradictory comments as part of the change.

## Language Convention Reference

| Language | Documentation comment style |
| --- | --- |
| Python | Triple-quoted docstrings (`"""..."""`) in the project's chosen style (Google, NumPy, reST); `#` for inline logic |
| JavaScript / TypeScript | JSDoc / TSDoc `/** ... */` with `@param`, `@returns`, `@throws` |
| PHP | PHPDoc `/** ... */` with `@param`, `@return`, `@throws`, `@var` |
| Java | Javadoc `/** ... */` with `@param`, `@return`, `@throws` |
| C# | XML doc comments `/// <summary>`, `<param>`, `<returns>`, `<exception>` |
| Go | Doc comment sentences immediately preceding the declaration, starting with the identifier name |
| Rust | `///` outer and `//!` inner doc comments in Markdown, with `# Examples`, `# Errors`, `# Panics` |
| Ruby | YARD `# @param`, `# @return` |
| Kotlin | KDoc `/** ... */` |
| Swift | `///` Markdown documentation comments |

For a language not listed, use its established documentation-comment standard; if it has none, use idiomatic block comments in the same structured spirit.

## Secrets And Sensitive Data

- Never place secrets, tokens, credentials, full connection strings, private keys, PII, or production data in comments, docstrings, or examples.
- Use placeholders such as `<TOKEN>`, `<DATABASE_NAME>`, and `<APP_URL>`, per `.claude/rules/secret-handling.md`.

## Final Reporting

For code work, report: which declarations and files received or updated documentation; the convention/style used; any intentionally undocumented areas and why; confirmation that no secrets or sensitive data were placed in comments or examples.
