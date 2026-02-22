#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/migraine-calendar-ai}"
APP_URL="${APP_URL:-http://localhost:8081}"
RUN_SEED="${RUN_SEED:-0}"
RUN_SMOKE="${RUN_SMOKE:-1}"
RUN_SMOKE_ADMIN="${RUN_SMOKE_ADMIN:-0}"

cd "$APP_DIR"

target_ref="${1:-}"
if [[ -z "$target_ref" && -f .deploy_previous ]]; then
  target_ref="$(cat .deploy_previous)"
fi
if [[ -z "$target_ref" ]]; then
  target_ref="$(git rev-parse HEAD~1)"
fi

echo "[rollback] Rolling back to: $target_ref"

SKIP_PULL=1 \
DEPLOY_REF="$target_ref" \
APP_URL="$APP_URL" \
RUN_SEED="$RUN_SEED" \
RUN_SMOKE="$RUN_SMOKE" \
RUN_SMOKE_ADMIN="$RUN_SMOKE_ADMIN" \
./pull.sh

echo "[rollback] Done."
