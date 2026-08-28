#!/usr/bin/env bash
# DeadCenter production deploy — always run this INSTEAD of raw
# `docker compose` commands.
#
# Background: Docker Compose interpolates `${VAR}` in docker-compose.yml
# from the shell env before falling back to `.env`. If the operator
# `cd`s from /opt/saprf (or any other project) with `DB_USERNAME` /
# `DB_PASSWORD` / `APP_KEY` exported, those values silently win over
# DeadCenter's `.env` and the containers are created with the *other*
# project's credentials. We hit this on 2026-08-24 — the operator's login
# shell had all of /opt/saprf/.env sourced into it, and the app container
# tried to log in to MySQL as `saprf` even though .env said `deadcenter`.
#
# A leaked APP_KEY is just as damaging as a leaked DB password: it logs
# every user out and makes anything encrypted at rest undecryptable.
#
# This script:
#   1. Pulls first, so the scrub list is derived from the compose file we
#      are about to deploy rather than a stale copy.
#   2. Unsets every variable the compose file interpolates — read straight
#      out of docker-compose.yml so the list can't drift — and refuses to
#      run if any survives the unset (readonly, systemd EnvironmentFile,
#      shell profile re-export, etc.).
#   3. Sanity-checks `docker compose config` before doing anything
#      destructive: DB creds must resolve to `deadcenter`, APP_URL must be
#      a deadcenter host, APP_KEY must be present.
#   4. Runs the canonical rebuild → force-recreate deploy.
#
# Usage (on the production server, as the deploy user):
#   cd /opt/deadcenter
#   ./scripts/deploy.sh
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_DIR"

COMPOSE_FILE_PATH="docker-compose.yml"

# ── 1. Pull ───────────────────────────────────────────────────────────────
echo "→ git pull"
git pull origin master

# ── 2. Scrub shell env ────────────────────────────────────────────────────
# Every ${VAR} referenced by the compose file. Derived from the file itself
# so adding a new `${...}` reference is automatically covered.
mapfile -t INTERPOLATED < <(
    grep -oE '\$\{[A-Za-z_][A-Za-z0-9_]*' "$COMPOSE_FILE_PATH" \
        | sed 's/^\${//' \
        | sort -u
)

if [[ ${#INTERPOLATED[@]} -eq 0 ]]; then
    echo "ERROR: found no \${VAR} references in $COMPOSE_FILE_PATH — refusing to" >&2
    echo "deploy, because that means the scrub below would be a no-op." >&2
    exit 1
fi

# Not interpolated today — the DB_ ones are hardcoded in the compose file and
# MYSQL_ ones are derived — but they're the first things a copy-paste from
# another project drags in, and COMPOSE_FILE/COMPOSE_PROJECT_NAME redirect
# Compose at an entirely different stack. Listing them explicitly also means
# the scrub doesn't weaken if someone edits the comments the grep above reads.
EXTRA_VARS=(
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    DB_ROOT_PASSWORD
    MYSQL_ROOT_PASSWORD MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD
    COMPOSE_FILE COMPOSE_PROJECT_NAME
)

SCRUBBED_VARS=("${INTERPOLATED[@]}" "${EXTRA_VARS[@]}")

for v in "${SCRUBBED_VARS[@]}"; do
    unset "$v" 2>/dev/null || true
done

# Anything still exported at this point is either readonly or being
# re-exported by a shell profile — bail loudly instead of deploying
# with the wrong values. Secret-looking names are redacted so a failed
# deploy doesn't spray credentials into the terminal scrollback.
LEAKED=""
for v in "${SCRUBBED_VARS[@]}"; do
    if [[ -n "${!v-}" ]]; then
        if [[ "$v" =~ (PASSWORD|KEY|SECRET|TOKEN) ]]; then
            LEAKED+="  ${v}=<redacted>\n"
        else
            LEAKED+="  ${v}=${!v}\n"
        fi
    fi
done
if [[ -n "$LEAKED" ]]; then
    echo "ERROR: shell env vars are still set after unset:" >&2
    printf "%b" "$LEAKED" >&2
    echo "" >&2
    echo "Fix your shell (check ~/.bashrc, ~/.profile, /etc/environment," >&2
    echo "systemd service EnvironmentFile, tmux/screen leftovers) then re-run." >&2
    exit 1
fi

# ── 3. Sanity-check compose interpolation ─────────────────────────────────
# `docker compose config` renders the fully-interpolated compose file. If any
# of these resolve to another project's values, stop before touching
# containers. Never echo the rendered config — it contains secrets.
CONFIG_OUT="$(docker compose config 2>&1)"

fail() {
    echo "ERROR: $1" >&2
    echo "Resolved (secrets omitted):" >&2
    grep -E '^\s+(DB_USERNAME|DB_DATABASE|APP_URL|APP_ENV):' <<<"$CONFIG_OUT" >&2 || true
    exit 1
}

grep -qE '^\s+DB_USERNAME:\s*deadcenter\s*$' <<<"$CONFIG_OUT" \
    || fail "DB_USERNAME resolved to something other than 'deadcenter'."
grep -qE '^\s+DB_DATABASE:\s*deadcenter\s*$' <<<"$CONFIG_OUT" \
    || fail "DB_DATABASE resolved to something other than 'deadcenter'."
grep -qE '^\s+APP_URL:\s*"?https?://[^[:space:]"]*deadcenter' <<<"$CONFIG_OUT" \
    || fail "APP_URL does not point at a deadcenter host."
grep -qE '^\s+APP_KEY:\s*"?base64:.+$' <<<"$CONFIG_OUT" \
    || fail "APP_KEY did not resolve to a base64: value — check .env."

echo "✓ Shell env clean; compose resolves to deadcenter DB, URL and APP_KEY."

# ── 4. Deploy ─────────────────────────────────────────────────────────────
echo "→ docker compose build --no-cache app"
docker compose build --no-cache app

echo "→ docker compose up -d --force-recreate app scheduler queue"
docker compose up -d --force-recreate app scheduler queue

echo ""
echo "✓ Deploy complete. Verifying container env:"
docker exec deadcenter-app printenv \
    | grep -E '^(APP_NAME|APP_ENV|APP_URL|DB_HOST|DB_DATABASE|DB_USERNAME)='

# ── 5. APP_KEY fingerprint ────────────────────────────────────────────────
# Livewire v4 derives its /livewire-<hash>/update endpoint from APP_KEY.
# If APP_KEY silently changes between deploys (e.g. env leaks from another
# project the way SAPRF did on 2026-08-24), every open browser tab starts
# getting 404s on Livewire XHRs and looks like a broken app to end users.
# Print a fingerprint so any unexpected key drift is loud in the deploy log
# without ever exposing the key itself.
echo ""
echo "→ APP_KEY fingerprint (should stay stable across deploys):"
docker exec deadcenter-app php -r '
    $k = getenv("APP_KEY") ?: "";
    echo "  key      : ", substr(hash("sha256", $k), 0, 12), "\n";
    echo "  livewire : /livewire-", substr(hash("sha256", $k."livewire-endpoint"), 0, 8), "/update\n";
'
