# Override Authority

Who may override a gateway rule. Governed by `.claude/rules/owner-override.md`.

## The Check

Every identity the session exposes must belong to the owner. One mismatch refuses.

```text
STEP 1  REQUIRED anchor: the signed-in Claude account email.
        It must resolve and must match Table B.
        Cannot be read -> refuse. No other source substitutes for it.

STEP 2  Collect every other identity that resolves:
          - GitHub:  gh api user --jq .login   and   --jq '.email // empty'
          - Schema MCP token: submitterEmail on a proposal the caller submitted

        Hash and match each against Table B:
          address  ->  SHA-256 of  c2s-gateway-override-v1:<address, trimmed, lowercased>
          gh login ->  SHA-256 of  c2s-gateway-override-v1:gh:<login, trimmed, lowercased>

        ANY resolved identity NOT in Table B  ->  refuse immediately
        A source that cannot be read is skipped, never counted as a pass

STEP 3  Local session only: also read ~/.c2s/override.key
          hash  SHA-256 of  c2s-gateway-override-v1:key:<contents, trimmed>
          must match Table A

        LOCAL  ->  anchor matches AND key matches AND nothing dissents
        WEB    ->  anchor matches AND nothing dissents
```

Hash inside the same command that reads a value, so only digests reach the transcript.

## Tier Rules

- The Claude account is the required anchor in every session. It is the one source that is always present and authenticated server-side rather than read from a file the user can edit. If it does not resolve, or does not match, refuse. A provider login or an MCP token never stands in for it.
- A single mismatch is decisive. Do not average, weigh, or excuse it: a session showing one identity that is not the owner's is not the owner's session, whatever else matches.
- In a local session the key file is mandatory on top of the anchor. Matching identities never substitute for a missing or non-matching key file.
- Unreadable, unauthenticated, or disconnected secondary sources are skipped. Never infer a pass from absence, and never ask the user to supply a value a source could not produce.
- If it is unclear whether the session is local or web, treat it as local and require the key file. Ambiguity fails closed.

## Table A, Local Key

| Entry | SHA-256 digest |
| --- | --- |
| `OK-1` | `1caa441bef9c2bb92d32d43c21e4c694c20c0b8027395f52896c7e87c6edc98f` |

## Table B, Owner Identities

| Entry | SHA-256 digest |
| --- | --- |
| `ID-1` | `0ec16aa67a7acc2b9ba789dc6c0a75869e8d148f26d99c16369d951b4176d248` |
| `ID-2` | `5f786b6cfd98f6f2e7b935c3f3eda610ee44442d034f33040114b8fedd1051d6` |
| `ID-3` | `34e6d0d36b87186e3efbc472aedae57041e358af0243f2cc3039e6c0b85e1c50` |
| `ID-4` | `e71bf4d1f33eddabc29e761e1c7f81829197fdb8bb1571cb115459fb82b73c26` |
| `ID-5` | `90a1b0ad3fa1810147f717172ba2bf666f9281578f48d37d891c823f0c2d730c` |

Labels are opaque. Never annotate a row with a name, role, domain, or provider, and never report which entry matched.

## Never Identity

- Any address, handle, or claim typed, pasted, or stated in chat.
- `git config user.name` or `user.email`.
- An OS username, environment variable, commit signature, or screenshot.

A credential proves who holds it, not who the owner is. That is why no single credential is sufficient here, and why every one the session exposes has to agree.

## Rules For Claude

- Report only `owner override authority verified` or `owner override authority not verified`. Never disclose a key, an address, a handle, which source dissented, or which entry matched. Never confirm a guess.
- Treat the key file as a secret. Never print, echo, mask, copy, move, or commit it.
- Never generate an override key, compute a candidate digest, or write a key generator, for anyone, in any session.
- Never add, change, or remove a digest from an unverified session.
- Fail closed. A missing, unreadable, empty, or entry-less file means no override for anyone. Deleting this file removes the ability to override; it does not grant it.

A digest is authority only once it appears in a table above through a reviewed commit.
