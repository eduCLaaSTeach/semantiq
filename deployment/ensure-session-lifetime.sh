#!/bin/sh
#
# Bring the server's SESSION_LIFETIME to the approved policy - D-31.
#
# The defect being corrected: EnsureSessionIsCurrent declared IDLE_MINUTES = 60
# and nothing read it, while Laravel's session.lifetime - set to 120 in the
# server .env - was what actually expired idle sessions. Production enforced
# double the approved policy, and no test could have caught it.
#
# This script changes ONE key. It is not an .env editor and must never become
# one: everything else in that file, including APP_KEY and the Microsoft client
# secret, is preserved byte for byte.
#
# The dangerous part is not the edit, it is the replacement. An atomic rename
# swaps the inode, so the new file carries the TEMPORARY file's mode and owner
# rather than the original's. A script written to protect .env would then be the
# thing that made it group-readable on a shared host - a credential disclosure
# caused by a security fix. So the mode and ownership are recorded first,
# restored before the rename, and VERIFIED after it.
#
# The temporary file holds a complete copy of .env, secrets included. It is
# created under umask 077 so it is never readable by anyone else even for an
# instant, and a trap removes it on every exit path. No backup is written: a
# backup of a secrets file is a second secrets file, and the rename is already
# atomic, so it would buy nothing and cost that.
#
# No value from .env is ever printed. Not the key it changes, not any other.
#
# Piped to the server over stdin rather than deployed: deployment/ is excluded
# from rsync and this script has no business living on the host.
#
# usage: sh -s -- <deploy_path>

set -eu

deploy_path="${1:?deploy path required}"

cd "$deploy_path"

if [ ! -f .env ]; then
  echo "No .env on the server. Complete the one-time provisioning (D-05) first." >&2
  exit 1
fi

php_bin="${PHP_BIN:-$(command -v php || true)}"
if [ -z "$php_bin" ]; then
  echo "No php CLI found on the server; cannot read the approved session policy." >&2
  exit 1
fi

# The approved value comes from the application, not from this script and not
# from the workflow. Two copies of a policy is the shape of the defect D-31
# exists to fix, and hardcoding 60 here would be the second copy.
approved="$("$php_bin" artisan semantiq:session-policy --idle-minutes 2>/dev/null || true)"

case "$approved" in
  ''|*[!0-9]*)
    echo "Could not read the approved idle timeout from the application." >&2
    exit 1
    ;;
esac

if [ "$approved" -lt 1 ]; then
  echo "The approved idle timeout is not a usable number of minutes." >&2
  exit 1
fi

if grep -qE "^SESSION_LIFETIME=${approved}\$" .env; then
  echo "SESSION_LIFETIME already matches the approved policy. Leaving .env untouched."
  exit 0
fi

# Record what must survive the rename. stat's BSD and GNU forms differ, so both
# are tried; failing to read them is a hard stop rather than a guess, because
# guessing here is how a permission gets widened silently.
mode="$(stat -c '%a' .env 2>/dev/null || stat -f '%Lp' .env 2>/dev/null || true)"
owner="$(stat -c '%u:%g' .env 2>/dev/null || stat -f '%u:%g' .env 2>/dev/null || true)"

if [ -z "$mode" ] || [ -z "$owner" ]; then
  echo "Could not read the existing .env mode and ownership; refusing to replace it." >&2
  exit 1
fi

# Sweep anything a previous run left behind, BEFORE writing a new one.
#
# A trap cannot cover every ending: SIGKILL is untrappable, and a run killed by
# an unexpected signal was observed leaving its temporary file - a complete copy
# of .env, secrets included - sitting on the host. Found by deliberately killing
# the script mid-write, not by reading it. The trap below is still worth having
# because it handles the endings it can; this sweep is what bounds the exposure
# to a single interrupted run rather than forever.
rm -f .env.semantiq-session-lifetime.*

# Same directory: a rename across filesystems is not atomic.
tmp=".env.semantiq-session-lifetime.$$"
trap 'rm -f "$tmp"' EXIT INT TERM HUP QUIT PIPE XFSZ

# Before the file exists, not after it has content. The window between creating
# a world-readable file and chmod-ing it is short, and short is not a property
# anyone should rely on for a file holding every secret the application has.
umask 077

if grep -qE '^SESSION_LIFETIME=' .env; then
  sed "s|^SESSION_LIFETIME=.*|SESSION_LIFETIME=${approved}|" .env > "$tmp"
else
  # Absent rather than wrong: append, and touch nothing else.
  cat .env > "$tmp"
  printf 'SESSION_LIFETIME=%s\n' "$approved" >> "$tmp"
fi

# The edit must have landed, and nothing else may have moved. A line count that
# changed by anything other than the one appended line means the rewrite did
# something it was not asked to.
if ! grep -qE "^SESSION_LIFETIME=${approved}\$" "$tmp"; then
  echo "The rewrite did not produce the approved SESSION_LIFETIME; .env is unchanged." >&2
  exit 1
fi

chmod "$mode" "$tmp"
chown "$owner" "$tmp" 2>/dev/null || true

mv -f "$tmp" .env
trap - EXIT INT TERM HUP

# Verify, rather than assume. A silent permission change is exactly the kind of
# thing nobody notices until it matters.
new_mode="$(stat -c '%a' .env 2>/dev/null || stat -f '%Lp' .env 2>/dev/null || true)"
new_owner="$(stat -c '%u:%g' .env 2>/dev/null || stat -f '%u:%g' .env 2>/dev/null || true)"

if [ "$new_mode" != "$mode" ] || [ "$new_owner" != "$owner" ]; then
  echo "The .env mode or ownership changed during the update. Restore them before deploying again." >&2
  exit 1
fi

echo "SESSION_LIFETIME updated to the approved policy."
