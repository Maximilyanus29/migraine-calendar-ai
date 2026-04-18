#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/opt/migraine-calendar-ai"
APP_URL="${APP_URL:-http://localhost}"
RUN_SEED="${RUN_SEED:-1}"
RUN_SMOKE="${RUN_SMOKE:-1}"
RUN_SMOKE_ADMIN="${RUN_SMOKE_ADMIN:-0}"
SKIP_PULL="${SKIP_PULL:-0}"
DEPLOY_REF="${DEPLOY_REF:-}"

DEPLOY_PREV_FILE=".deploy_previous"
DEPLOY_CURR_FILE=".deploy_current"

cd "$APP_DIR"

PRE_DEPLOY_SHA="$(git rev-parse HEAD)"

if [[ -n "$DEPLOY_REF" ]]; then
  echo "[deploy] Deploying ref: $DEPLOY_REF"
  git fetch --all --tags
  git checkout --detach "$DEPLOY_REF"
elif [[ "$SKIP_PULL" == "1" ]]; then
  echo "[deploy] SKIP_PULL=1, using current checkout: $(git rev-parse --short HEAD)"
else
  echo "[deploy] Pulling latest changes..."
  git pull --ff-only
fi

POST_DEPLOY_SHA="$(git rev-parse HEAD)"

echo "[deploy] Building frontend assets..."
docker run --rm -u "$(id -u):$(id -g)" \
  -v "$APP_DIR/backend:/app" -w /app \
  public.ecr.aws/docker/library/node:22-alpine sh -lc "npm ci && npm run build"

echo "[deploy] Restarting services..."
docker compose up --build -d

echo "[deploy] Running migrations..."
docker compose exec -T php php artisan migrate --force

if [[ "$RUN_SEED" == "1" ]]; then
  echo "[deploy] Running seeders..."
  docker compose exec -T php php artisan db:seed --force
fi

echo "[deploy] Clearing caches..."
docker compose exec -T php php artisan optimize:clear

if [[ "$RUN_SMOKE" == "1" ]]; then
  echo "[deploy] Running smoke checks via smoke.sh..."
  APP_URL="$APP_URL" "$APP_DIR/smoke.sh"
fi

if [[ "$RUN_SMOKE_ADMIN" == "1" ]]; then
  echo "[deploy] Running admin smoke checks via smoke-admin.sh..."
  APP_URL="$APP_URL" "$APP_DIR/smoke-admin.sh"
fi

echo "$PRE_DEPLOY_SHA" > "$DEPLOY_PREV_FILE"
echo "$POST_DEPLOY_SHA" > "$DEPLOY_CURR_FILE"

echo "[deploy] Done."
