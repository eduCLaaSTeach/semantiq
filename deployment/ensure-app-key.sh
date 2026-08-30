#!/bin/sh
#
# Bootstrap APP_KEY on the INITIAL deployment only.
#
# The problem this solves is a deadlock. The server .env is created by hand
# during the D-05 provisioning, and a Laravel application key cannot reasonably
# be produced by hand. But before the first deployment there is no remote
# artisan to generate one either. A deployment that refuses to proceed on an
# empty APP_KEY therefore never reaches the point where it could fix that.
#
# So the key is generated exactly once, here, after the application has been
# transferred and only on the INITIAL path.
#
# On an UPDATE deployment an existing key is mandatory and is NEVER regenerated.
# Rotating APP_KEY on a running system invalidates every encrypted cookie and
# session, and any other data encrypted with it - an outage that looks like data
# loss, caused by a deployment that thought it was being helpful.
#
# The key value is never printed. It is not echoed, not logged, and not returned
# to GitHub; only the fact that a key is present is ever reported.
#
# Piped to the server over stdin rather than deployed: deployment/ is excluded
# from rsync and this script has no business living on the host.
#
# usage: sh -s -- <deploy_path> <INITIAL|EXISTING>

set -eu

deploy_path="${1:?deploy path required}"
state="${2:?deployment state required}"

cd "$deploy_path"

if [ ! -f .env ]; then
  echo "No .env on the server. Complete the one-time provisioning (D-05) first." >&2
  exit 1
fi

# A Laravel key is base64: followed by the encoded 32 bytes. Matching the shape
# rather than mere presence means APP_KEY=base64: on its own counts as missing,
# which is what it is.
if grep -qE '^APP_KEY=base64:[A-Za-z0-9+/]{40,}={0,2}$' .env; then
  echo "APP_KEY is present. Leaving it untouched."
  exit 0
fi

# Missing, empty, or malformed from here on.

if [ "$state" != "INITIAL" ]; then
  echo "APP_KEY is missing or malformed on an UPDATE deployment." >&2
  echo "It is never generated here: doing so would invalidate every existing" >&2
  echo "session and any data encrypted with the previous key. Restore the" >&2
  echo "correct key in the server .env and deploy again." >&2
  exit 1
fi

if [ ! -f artisan ] || [ ! -f vendor/autoload.php ]; then
  echo "Laravel is not present in $deploy_path, so no key can be generated." >&2
  exit 1
fi

php_bin="${PHP_BIN:-$(command -v php || true)}"
if [ -z "$php_bin" ]; then
  echo "No php CLI found on the server; cannot generate APP_KEY." >&2
  exit 1
fi

echo "APP_KEY is empty on the INITIAL deployment. Generating one."

# Output is discarded so that nothing carrying the key can reach the workflow
# log, whatever a future Laravel version decides to print.
if ! "$php_bin" artisan key:generate --force --no-interaction >/dev/null 2>&1; then
  echo "key:generate failed." >&2
  exit 1
fi

if ! grep -qE '^APP_KEY=base64:[A-Za-z0-9+/]{40,}={0,2}$' .env; then
  echo "key:generate reported success but .env still has no valid APP_KEY." >&2
  exit 1
fi

echo "APP_KEY generated and verified."
