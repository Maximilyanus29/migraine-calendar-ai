#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-http://localhost:8081}"

DEMO_EMAIL="${DEMO_EMAIL:-demo@example.com}"
DEMO_PASSWORD="${DEMO_PASSWORD:-password}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin12345}"

demo_cookie="$(mktemp)"
admin_cookie="$(mktemp)"
trap 'rm -f "$demo_cookie" "$admin_cookie"' EXIT

fail() {
  echo "[smoke] FAIL: $1" >&2
  exit 1
}

request() {
  local method="$1"
  local url="$2"
  local cookie_in="${3:-}"
  local cookie_out="${4:-}"
  local body="${5:-}"

  local args=(-sS -X "$method" "$url" -H 'Accept: application/json')

  if [[ -n "$cookie_in" ]]; then
    args+=(-b "$cookie_in")
  fi
  if [[ -n "$cookie_out" ]]; then
    args+=(-c "$cookie_out")
  fi
  if [[ -n "$body" ]]; then
    args+=(-H 'Content-Type: application/json' -d "$body")
  fi

  local response
  response="$(curl "${args[@]}" -w $'\n%{http_code}')" || fail "curl error for $method $url"
  local status="${response##*$'\n'}"
  local payload="${response%$'\n'*}"

  RESP_STATUS="$status"
  RESP_BODY="$payload"
}

json_field() {
  local payload="$1"
  local field="$2"
  sed -n "s/.*\"$field\"[[:space:]]*:[[:space:]]*\"\\{0,1\\}\\([^\",}]*\\)\"\\{0,1\\}.*/\\1/p" <<<"$payload" | head -n1
}

echo "[smoke] Demo login..."
request "POST" "$APP_URL/api/v1/auth/login" "" "$demo_cookie" "{\"email\":\"$DEMO_EMAIL\",\"password\":\"$DEMO_PASSWORD\"}"
[[ "$RESP_STATUS" == "200" ]] || fail "demo login status $RESP_STATUS"

echo "[smoke] /auth/me..."
request "GET" "$APP_URL/api/v1/auth/me" "$demo_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "/auth/me status $RESP_STATUS"

echo "[smoke] /meta/options..."
request "GET" "$APP_URL/api/v1/meta/options" "$demo_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "/meta/options status $RESP_STATUS"

echo "[smoke] Create attack..."
now_utc="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
request "POST" "$APP_URL/api/v1/attacks" "$demo_cookie" "" "{\"start_at\":\"$now_utc\",\"end_at\":null,\"intensity\":5,\"triggers\":[],\"pain_types\":[],\"localizations\":[],\"symptoms\":[],\"auras\":[]}"
[[ "$RESP_STATUS" == "201" ]] || fail "create attack status $RESP_STATUS"
attack_id="$(json_field "$RESP_BODY" "id")"
[[ -n "$attack_id" ]] || fail "create attack id missing"

echo "[smoke] Update attack..."
request "PUT" "$APP_URL/api/v1/attacks/$attack_id" "$demo_cookie" "" "{\"start_at\":\"$now_utc\",\"end_at\":null,\"intensity\":6,\"triggers\":[],\"pain_types\":[],\"localizations\":[],\"symptoms\":[],\"auras\":[]}"
[[ "$RESP_STATUS" == "200" ]] || fail "update attack status $RESP_STATUS"

echo "[smoke] Create custom option..."
suffix="$(date +%s)"
request "POST" "$APP_URL/api/v1/custom-options" "$demo_cookie" "" "{\"category\":\"triggers\",\"name\":\"Smoke Trigger $suffix\"}"
[[ "$RESP_STATUS" == "201" ]] || fail "create custom option status $RESP_STATUS"

echo "[smoke] Delete attack..."
request "DELETE" "$APP_URL/api/v1/attacks/$attack_id" "$demo_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "delete attack status $RESP_STATUS"

echo "[smoke] Admin login..."
request "POST" "$APP_URL/api/v1/auth/login" "" "$admin_cookie" "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}"
[[ "$RESP_STATUS" == "200" ]] || fail "admin login status $RESP_STATUS"

echo "[smoke] Admin custom options list..."
request "GET" "$APP_URL/api/v1/admin/custom-triggers?status=all&category=all" "$admin_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "admin custom options list status $RESP_STATUS"

echo "[smoke] OK"
