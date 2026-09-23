#!/bin/sh
#
# Bring the server's SESSION_DRIVER to an approved value - CL-10, WS-1.
#
# WHAT THIS IS FOR. Phase 1's baseline target is a database-backed session
# store; production has been running the file driver. This is the only
# mechanism in the repository that can change that, and it is deliberately the
# narrowest one that can: one key, two permitted values, two arguments.
#
# THIS IS NOT AN .env EDITOR AND MUST NEVER BECOME ONE. It takes no key name.
# The key is SESSION_DRIVER, written here and nowhere else. It takes no
# arbitrary value: the target must be exactly `file` or `database`. Everything
# else in that file - APP_KEY, the Microsoft client secret, the database
# credentials - is preserved byte for byte, and the script proves that before
# it replaces anything rather than promising it in a comment.
#
# FORWARD AND ROLLBACK ARE THE SAME SCRIPT. `database` is the change; `file` is
# the rollback. A rollback path that is a different piece of code is a path
# nobody has ever run, exercised for the first time under pressure.
#
# WHAT WAS LEARNED FROM ensure-session-lifetime.sh, and what is done
# differently:
#
#   Reused - the atomic rename swaps the inode, so the replacement carries the
#   TEMPORARY file's mode and owner rather than the original's; umask 077
#   before the temporary file exists rather than chmod after; a trap on every
#   exit path; a sweep of stale temporaries, because SIGKILL is untrappable and
#   a killed run was observed leaving a complete copy of .env on the host; a
#   same-directory rename, because a cross-filesystem rename is not atomic; no
#   backup, because a backup of a secrets file is a second secrets file; and no
#   value from .env ever printed.
#
#   CHANGED - that script restores mode and ownership and then checks them
#   AFTER the rename, so a failed restoration is detected once .env has already
#   been replaced. That is recovery, not prevention. Here mode and ownership
#   are verified on the temporary file BEFORE the rename, and a mismatch aborts
#   with the original untouched. They are verified again afterwards.
#
#   ADDED - that script's comment promises a line-count check its code never
#   performs, and its only pre-rename check is that the target line exists,
#   which a rewrite that also mangled an unrelated line would pass. Here the
#   line count is compared for real, and a hash of the two files with the
#   target line normalised out proves that nothing except the target line
#   moved. Computed by streaming, so no second copy of .env is written to disk
#   and no content is printed.
#
# ensure-session-lifetime.sh is NOT modified by this workstream. Absorbing an
# unrelated hardening into a controlled production alignment would widen it
# into something else; the weakness is recorded separately.
#
# REFUSAL IS THE DEFAULT. A missing .env, a missing SESSION_DRIVER entry, more
# than one of them, a current value this script does not recognise, a target it
# does not recognise, an unreadable mode or owner - each is a hard stop with
# .env untouched. In particular a MISSING key is refused rather than appended:
# inventing deployment configuration is worse than declining to act.
#
# Piped to the server over stdin rather than deployed: deployment/ is excluded
# from rsync and this script has no business living on the host.
#
# usage: sh -s -- <deploy_path> <file|database>

set -eu

# EXACTLY TWO ARGUMENTS. A third would mean the caller thinks this script takes
# something it does not - a key name, a flag, a second value - and guessing
# which is how an .env editor gets built by accident.
if [ "$#" -ne 2 ]; then
  echo "usage: ensure-session-driver.sh <deploy_path> <file|database>" >&2
  exit 1
fi

deploy_path="$1"
target="$2"

# R-6. The target is one of two names. Not a key=value pair, not a driver this
# deployment has never run, not free text.
case "$target" in
  file|database) ;;
  *)
    echo "The requested session driver is not one this script will write. Only file and database are permitted." >&2
    exit 1
    ;;
esac

cd "$deploy_path" 2>/dev/null || {
  echo "The deployment directory does not exist." >&2
  exit 1
}

# R-1. A missing .env is a stop. This script never creates one.
if [ ! -f .env ]; then
  echo "No .env on the server. Complete the one-time provisioning (D-05) first." >&2
  exit 1
fi

# R-2 and R-3. EXACTLY ONE SESSION_DRIVER entry.
#
# Zero is refused rather than appended, because a deployment whose session
# configuration is absent is not a deployment this script understands, and
# writing one would be inventing production configuration from a script
# argument.
#
# More than one is refused because Laravel's .env reader takes one of them and
# which one is not a thing to be confident about from here. Rewriting both, or
# picking one, would leave the file self-contradictory either way.
entries="$(grep -cE '^SESSION_DRIVER=' .env || true)"

if [ "$entries" -eq 0 ]; then
  echo "The server .env has no SESSION_DRIVER entry. Refusing to invent deployment configuration." >&2
  exit 1
fi

if [ "$entries" -gt 1 ]; then
  echo "The server .env has more than one SESSION_DRIVER entry. Refusing to guess which one is in effect." >&2
  exit 1
fi

# R-4. The current value must be one this script recognises.
#
# Read into a variable and never printed. An unexpected value means the server
# is in a state nobody described, and overwriting it would destroy the evidence
# of how it got there.
current="$(sed -n 's|^SESSION_DRIVER=||p' .env)"

case "$current" in
  file|database) ;;
  *)
    echo "The current SESSION_DRIVER is not one of the two values this script recognises. Refusing to overwrite an unexpected state." >&2
    exit 1
    ;;
esac

# R-7. Already correct: succeed without rewriting.
#
# Not "rewrite it to the same thing" - the safest edit to a file holding every
# secret the application has is the one that does not happen.
if [ "$current" = "$target" ]; then
  echo "SESSION_DRIVER is already ${target}. Leaving .env untouched."
  exit 0
fi

# Record what must survive the rename. stat's BSD and GNU forms differ, so both
# are tried; failing to read either is a hard stop rather than a guess, because
# guessing here is how a permission gets widened silently.
mode="$(stat -c '%a' .env 2>/dev/null || stat -f '%Lp' .env 2>/dev/null || true)"
owner="$(stat -c '%u:%g' .env 2>/dev/null || stat -f '%u:%g' .env 2>/dev/null || true)"

if [ -z "$mode" ] || [ -z "$owner" ]; then
  echo "Could not read the existing .env mode and ownership; refusing to replace it." >&2
  exit 1
fi

# The hasher used for V-3. Chosen once, up front: a guard that silently
# degrades to no guard when a tool is missing is not a guard, so no hasher at
# all is a refusal rather than a skip.
hash_tool=''
for candidate in sha256sum sha256 shasum cksum; do
  if command -v "$candidate" >/dev/null 2>&1; then
    hash_tool="$candidate"
    break
  fi
done

if [ -z "$hash_tool" ]; then
  echo "No checksum utility is available on the server; refusing to replace .env without proving nothing else moved." >&2
  exit 1
fi

# A hash of the file with the SESSION_DRIVER line normalised away, so the one
# line that is MEANT to change cannot mask a line that is not.
#
# Streamed, never written: a second copy of .env on disk is the exposure this
# script exists to avoid, and the hash is not content.
case "$hash_tool" in
  shasum) hash_cmd='shasum -a 256' ;;
  *) hash_cmd="$hash_tool" ;;
esac

normalised_hash() {
  # $hash_cmd is deliberately unquoted: it may carry an argument.
  # shellcheck disable=SC2086
  sed 's|^SESSION_DRIVER=.*|SESSION_DRIVER=|' "$1" | $hash_cmd
}

before_hash="$(normalised_hash .env)"

# BSD wc pads its output; the comparison below is numeric either way, but
# stripping makes that independent of which wc the host has.
before_lines="$(wc -l < .env | tr -d '[:space:]')"

# Sweep anything a previous run left behind, BEFORE writing a new one.
#
# A trap cannot cover every ending: SIGKILL is untrappable, and a run killed by
# an unexpected signal was observed leaving its temporary file - a complete copy
# of .env, secrets included - sitting on the host. The trap below is still worth
# having because it handles the endings it can; this sweep is what bounds the
# exposure to a single interrupted run rather than forever.
rm -f .env.semantiq-session-driver.*

# Same directory: a rename across filesystems is not atomic.
tmp=".env.semantiq-session-driver.$$"
trap 'rm -f "$tmp"' EXIT INT TERM HUP QUIT PIPE XFSZ

# Before the file exists, not after it has content. The window between creating
# a world-readable file and chmod-ing it is short, and short is not a property
# anyone should rely on for a file holding every secret the application has.
umask 077

sed "s|^SESSION_DRIVER=.*|SESSION_DRIVER=${target}|" .env > "$tmp"

# V-1. The target line is exactly right, and there is still exactly one of it.
if [ "$(grep -cE "^SESSION_DRIVER=${target}\$" "$tmp" || true)" -ne 1 ]; then
  echo "The rewrite did not produce exactly one correct SESSION_DRIVER line; .env is unchanged." >&2
  exit 1
fi

# V-2. The line count is unchanged. This script only ever replaces a line that
# already exists, so any movement here means the rewrite did something it was
# not asked to.
after_lines="$(wc -l < "$tmp" | tr -d '[:space:]')"

if [ "$after_lines" -ne "$before_lines" ]; then
  echo "The rewrite changed the number of lines in .env; .env is unchanged." >&2
  exit 1
fi

# V-3. NOTHING EXCEPT THE TARGET LINE MOVED.
#
# This is the check the existing lifetime script's comment promises and its code
# does not perform. Its only pre-rename test is that the target line is present,
# which a rewrite that also mangled the client secret would pass.
if [ "$(normalised_hash "$tmp")" != "$before_hash" ]; then
  echo "The rewrite changed content other than SESSION_DRIVER; .env is unchanged." >&2
  exit 1
fi

# V-4. MODE AND OWNERSHIP PROVEN BEFORE THE RENAME, NOT AFTER.
#
# The lifetime script restores these and then checks them once .env has already
# been replaced, so a chown that silently failed is discovered too late to
# prevent anything. Here a mismatch aborts with the original file untouched.
chmod "$mode" "$tmp"
chown "$owner" "$tmp" 2>/dev/null || true

tmp_mode="$(stat -c '%a' "$tmp" 2>/dev/null || stat -f '%Lp' "$tmp" 2>/dev/null || true)"
tmp_owner="$(stat -c '%u:%g' "$tmp" 2>/dev/null || stat -f '%u:%g' "$tmp" 2>/dev/null || true)"

if [ -z "$tmp_mode" ] || [ -z "$tmp_owner" ]; then
  echo "Could not read the replacement file's mode and ownership; .env is unchanged." >&2
  exit 1
fi

if [ "$tmp_mode" != "$mode" ] || [ "$tmp_owner" != "$owner" ]; then
  echo "The replacement file does not carry the original .env mode and ownership; .env is unchanged." >&2
  exit 1
fi

mv -f "$tmp" .env
trap - EXIT INT TERM HUP QUIT PIPE XFSZ

# Verify again, rather than assume. A silent permission change is exactly the
# kind of thing nobody notices until it matters.
new_mode="$(stat -c '%a' .env 2>/dev/null || stat -f '%Lp' .env 2>/dev/null || true)"
new_owner="$(stat -c '%u:%g' .env 2>/dev/null || stat -f '%u:%g' .env 2>/dev/null || true)"

if [ "$new_mode" != "$mode" ] || [ "$new_owner" != "$owner" ]; then
  echo "The .env mode or ownership changed during the update. Restore them before serving traffic." >&2
  exit 1
fi

echo "SESSION_DRIVER updated to ${target}."
