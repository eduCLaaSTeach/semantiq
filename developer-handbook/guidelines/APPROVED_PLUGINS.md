# Approved Plugins

Claude Code plugins approved for the development team. Install only plugins listed here. A plugin can add skills, slash commands, hooks, and MCP servers, so it runs as trusted code - treat an unapproved plugin the same as an unapproved dependency.

Approval owner: Salil. To propose a new plugin, get Salil's approval, then record it here before anyone installs it.

How plugins work: a plugin is distributed through a marketplace. You add the marketplace once, then install the plugin from it. Run the commands below inside a Claude Code session.

## Superpowers

- Marketplace: `obra/superpowers-marketplace`
- Plugin: `superpowers@superpowers-marketplace`
- Author: Jesse Vincent (obra)
- What it is: a software-development methodology framework that adds composable skills for systematic workflows - test-driven development, brainstorming, planning, code review, debugging, and subagent-driven development - that activate to guide design, planning, and iterative implementation.
- Approved by: Salil

Install:

```text
/plugin marketplace add obra/superpowers-marketplace
/plugin install superpowers@superpowers-marketplace
```

Manage and verify (list marketplaces and installed plugins, enable, disable, or update):

```text
/plugin
```

Note: after installing, a session restart or reload may be needed before the plugin's skills and commands are available.

## Adding A New Approved Plugin

1. Get Salil's approval for the specific marketplace and plugin.
2. Add a section here recording the marketplace, plugin id, author, what it does, and the approver.
3. Use the same install shape (replace the placeholders):

```text
/plugin marketplace add <MARKETPLACE>
/plugin install <PLUGIN>@<MARKETPLACE>
```

## Guardrails

- Install only plugins listed above; do not add unapproved marketplaces or plugins.
- This file is the single source of truth for approved plugins; keep it current.
- Review what a plugin adds (skills, commands, hooks, MCP servers) before relying on it, and keep secrets out of any plugin configuration per `.claude/rules/secret-handling.md`.
