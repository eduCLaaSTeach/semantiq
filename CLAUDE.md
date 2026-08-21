# Claude Code Notes

Project-specific guidance for this repository. Read it before making changes.

## What This Is

SemantIQ, a control plane for Microsoft Fabric. Laravel 13 on PHP 8.5, React 19,
MySQL. Modular monolith. See [README.md](README.md) for the full picture and
[doc/](doc/) for the design and specification set.

Application code has not landed yet. There is no `composer.json` or
`package.json`, so the deployment pipeline's build steps cannot currently pass.

## Ask, Do Not Assume

Confirm before acting when the stack, hosting, schema shape, validation commands,
or intent is unclear. Many rounds of questions are fine. Verify a claim about this
repository by reading the file, running the command, or checking the branch, and
cite what you read. Label anything unverified as unverified in the same sentence.
"I have not verified that" is a complete answer.

## Never Commit A Secret

No token, key, password, connection string, `.env` value or production row data
goes into any file, commit, log, or chat summary. Use placeholders such as
`<DATABASE_NAME>`, `<TOKEN>`, `<APP_BASE_URL>`. If you find a real credential in
the repository, replace it with a placeholder and say it needs rotating.

Database and deploy credentials live in `.env` on the server and in GitHub
secrets. Never in the repository.

## Branching And Deployment

One branch, `main`, which is the GitHub default and the deploy trigger.

A push to `main` deploys to the live site. Prefer a pull request over a direct
push. Running an actual deployment or a database migration is a separate action
that needs explicit approval every time; promoting code through Git does not.

Git and GitHub commands need no per-action confirmation, but state the source
branch, target branch, files and command before any write or remote action.

Commit identity is governed by the global commit-identity policy resolved through
`git config --global --get ai-commit-identity.policy-path`. Read and follow it
before generating a commit message, configuring identity, committing, or pushing.
Never attribute a commit to an AI, a bot, or a `noreply` address, and strip every
`Co-authored-by:` trailer.

## Schema

Laravel migrations in `database/migrations/` are the source of truth. Pair every
forward migration with a working `down()`. Never edit a migration already merged
or applied; add a new one. Do not invent a table or column name; read the
migrations.

Design a persisted structure so the feature's own reporting is answerable from it:
one clear meaning per row, atomic values rather than stored totals, codified
reference lists rather than free text, and state changes captured as rows rather
than overwritten.

## User Interface

Use only `doc/design-system/ui-and-ux-layout-template-shared.md`. It is the single
authority for
layout, structure and theme for the entire application, and there is no second
source. Read it before generating or changing any screen, component, layout or
stylesheet.

Do not introduce another design system, theme, component-library skin or ad hoc
styling alongside it. Do not invent a token value, a colour, a font, a spacing step,
a shell dimension or an icon style; take them from the template. Never modify,
recolour, regenerate or substitute a logo or favicon, and never source one from
outside the pack in `doc/design-system/assets/`.

Never ask about or offer alternatives for the theme, colours, fonts, logos or
favicon. Those are settled. The only things ever asked are the app-specific values
in the template's App Definition, and `BRAND_ASSETS_PATH`, which is the developer's
choice and must never be chosen for them.

Every rule is tagged ENFORCED or PRINCIPLED. ENFORCED, which covers every token
value, brand asset and theme decision, is not deviable. To deviate from a
PRINCIPLED default, state the standard pattern, the proposed deviation, the
rationale, the domain context and the trade-offs, then get sign-off before
generating. Record an authorized deviation as a documented exception; never apply
one silently.

Generate in the template's order: navigation and access config, then the role and
policy layer, then the shell, then tokens in both themes, then one page per screen
from the matching archetype. Cover the success, empty, loading, error and small
screen states of every screen, not only success.

Where `doc/04-UI-Specification.md` or the mockups under `doc/mockups/` disagree with
the template, the template wins.

## Code

Match the repository's existing conventions, formatting and naming. Keep changes
small and tied to the request. Do not add a dependency, service, queue, cache or
runtime without approval. Document declarations with PHPDoc and TSDoc, explaining
intent rather than restating the code.

Validate external input at boundaries. Use parameterised queries and the
framework's safe APIs. Handle the empty, loading and error states of any
data-driven view, not only success. Give every outbound call a bounded timeout.

## Done Means Verified

Do not claim complete without running the available validation and reporting the
real result. If validation cannot run, say why and give the exact follow-up
command. Never report that tests passed unless they were run.

## Writing Style

Plain ASCII in every file, commit message, pull request and comment. No em dash,
no en dash, no curly quotes, no ellipsis character. Use a plain hyphen, a comma, a
colon, or reword.
