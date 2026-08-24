#!/usr/bin/env bash
# DeadCenter production deploy — always run this INSTEAD of raw
# `docker compose` commands.
#
# Background: Docker Compose interpolates `${VAR}` in docker-compose.yml
# from the shell env before falling back to `.env`. If the operator
# `cd`s from /opt/saprf (or any other project) with `DB_USERNAME` /
# `DB_PASSWORD` / `DB_DATABASE` exported, those values silently win over
# DeadCenter's `.env` and the containers are created with the *other*
# project's credentials. We hit this on 2026-08-24 — the app container
# tried to log in to MySQL as `saprf` even though .env said `deadcenter`.
#
# This script:
#   1. Unsets every DB_/MYSQL_ shell var and refuses to run if any
#      variable is still exported afterwards (readonly / declared via
#      systemd, etc.).
#   2. Sanity-checks `docker compose config` before doing anything
#      destructive — confirms the resolved DB_USERNAME/DB_DATABASE are
#      literally `deadcenter`, not something inherited from a rogue
#      env.
#   3. Runs the canonical pull → rebuild → force-recreate deploy.
#
# Usage (on the production server, as the deploy user):
#   cd /opt/deadcenter
#   ./scripts/deploy.sh
#
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_DIR"

# ── 1. Scrub shell env ────────────────────────────────────────────────────
# All the shell vars that could leak into `${VAR}` interpolation inside
# docker-compose.yml. Add to this list if you add more `${...}` refs.
SCRUBBED_VARS=(
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
    DB_ROOT_PASSWORD
    MYSQL_ROOT_PASSWORD MYSQL_DATABASE MYSQL_USER MYSQL_PASSWORD
)

for v in "${SCRUBBED_VARS[@]}"; do
    unset "$v" 2>/dev/null || true
done

# Anything still exported at this point is either readonly or being
# re-exported by a shell profile — bail loudly instead of deploying
# with the wrong values.
LEAKED=""
for v in "${SCRUBBED_VARS[@]}"; do
    if [[ -n "${!v-}" ]]; then
        LEAKED+="  ${v}=${!v}\n"
    fi
done
if [[ -n "$LEAKED" ]]; then
    echo "ERROR: DB_/MYSQL_ shell env vars are still set after unset:" >&2
    printf "%b" "$LEAKED" >&2
    echo "" >&2
    echo "Fix your shell (check ~/.bashrc, ~/.profile, /etc/environment," >&2
    echo "systemd service EnvironmentFile, tmux/screen leftovers) then re-run." >&2
    exit 1
fi

# ── 2. Sanity-check compose interpolation ─────────────────────────────────
# `docker compose config` renders the fully-interpolated compose file. We
# grep for the resolved DB creds and fail if they're not `deadcenter`.
# This catches the exact bug that started this script existing.
CONFIG_OUT="$(docker compose config 2>&1)"
if ! grep -qE '^\s+DB_USERNAME:\s*deadcenter\s*$' <<<"$CONFIG_OUT"; then
    echo "ERROR: docker compose config resolved DB_USERNAME to something other than 'deadcenter'." >&2
    echo "$CONFIG_OUT" | grep -E 'DB_(USERNAME|DATABASE|PASSWORD)' >&2 || true
    exit 1
fi
if ! grep -qE '^\s+DB_DATABASE:\s*deadcenter\s*$' <<<"$CONFIG_OUT"; then
    echo "ERROR: docker compose config resolved DB_DATABASE to something other than 'deadcenter'." >&2
    echo "$CONFIG_OUT" | grep -E 'DB_(USERNAME|DATABASE|PASSWORD)' >&2 || true
    exit 1
fi

echo "✓ Shell env clean, compose config resolves to deadcenter/deadcenter."

# ── 3. Deploy ─────────────────────────────────────────────────────────────
echo "→ git pull"
git pull origin master

echo "→ docker compose build --no-cache app"
docker compose build --no-cache app

echo "→ docker compose up -d --force-recreate app scheduler queue"
docker compose up -d --force-recreate app scheduler queue

echo ""
echo "✓ Deploy complete. Verifying container env:"
docker exec deadcenter-app printenv | grep -E '^(DB_|APP_ENV|APP_URL)'
