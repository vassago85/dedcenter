#!/usr/bin/env bash
#
# deploy.sh — pull latest master and rebuild the DeadCenter stack on the server.
#
# Intended for Ubuntu at 41.72.157.26:/opt/deadcenter.
#
# Usage (on server):
#   ./deploy.sh                 # full rebuild (no-cache) + recreate
#   ./deploy.sh --fast          # cached build + recreate (faster, use when deps unchanged)
#   ./deploy.sh --no-recreate   # just pull + build, don't touch running containers
#   ./deploy.sh --branch=foo    # pull a different branch
#   ./deploy.sh --dry-run       # print commands only
#
# The Docker entrypoint already handles: migrations, cache clearing, Livewire
# assets, storage links, permissions. This script only drives git + docker.

set -euo pipefail

PROJECT_DIR="${DEADCENTER_DIR:-/opt/deadcenter}"
BRANCH="master"
NO_CACHE="--no-cache"
RECREATE=1
DRY_RUN=0

for arg in "$@"; do
  case "$arg" in
    --fast)         NO_CACHE="" ;;
    --no-recreate)  RECREATE=0 ;;
    --branch=*)     BRANCH="${arg#*=}" ;;
    --dry-run)      DRY_RUN=1 ;;
    -h|--help)
      sed -n '1,20p' "$0"
      exit 0
      ;;
    *)
      echo "unknown option: $arg" >&2
      exit 1
      ;;
  esac
done

run() {
  echo "+ $*"
  if [[ "$DRY_RUN" -eq 0 ]]; then
    "$@"
  fi
}

if [[ ! -d "$PROJECT_DIR" ]]; then
  echo "Project dir not found: $PROJECT_DIR" >&2
  exit 1
fi

cd "$PROJECT_DIR"

echo ">> Pulling $BRANCH in $PROJECT_DIR"
run git fetch --prune origin
run git checkout "$BRANCH"
run git pull --ff-only origin "$BRANCH"

BEFORE_SHA=$(git rev-parse --short HEAD)
echo ">> HEAD is now $BEFORE_SHA"

echo ">> Pulling external images (gotenberg etc.)"
run docker compose pull gotenberg || true

echo ">> Building app image (no_cache=${NO_CACHE:-cached})"
if [[ -n "$NO_CACHE" ]]; then
  run docker compose build --no-cache app
else
  run docker compose build app
fi

if [[ "$RECREATE" -eq 1 ]]; then
  echo ">> Recreating app, scheduler, queue, gotenberg"
  run docker compose up -d --force-recreate app scheduler queue gotenberg
else
  echo ">> Skipping recreate (--no-recreate); starting gotenberg if new"
  run docker compose up -d gotenberg
fi

echo ">> Done. HEAD=$BEFORE_SHA"
