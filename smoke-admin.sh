#!/usr/bin/env bash
set -euo pipefail

APP_URL="${APP_URL:-http://localhost}"

DEMO_EMAIL="${DEMO_EMAIL:-demo@example.com}"
DEMO_PASSWORD="${DEMO_PASSWORD:-password}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@example.com}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin12345}"
CATEGORY="${CATEGORY:-triggers}"

demo_cookie="$(mktemp)"
admin_cookie="$(mktemp)"
trap 'rm -f "$demo_cookie" "$admin_cookie"' EXIT

fail() {
  echo "[smoke-admin] FAIL: $1" >&2
  exit 1
}

request() {
  local method="$1"
  local url="$2"
  local cookie_in="${3:-}"
  local cookie_out="${4:-}"
  local body="${5:-}"

  local args=(-sS -X "$method" "$url" -H 'Accept: application/json')
  if [[ -n "$cookie_in" ]]; then args+=(-b "$cookie_in"); fi
  if [[ -n "$cookie_out" ]]; then args+=(-c "$cookie_out"); fi
  if [[ -n "$body" ]]; then args+=(-H 'Content-Type: application/json' -d "$body"); fi

  local response
  response="$(curl "${args[@]}" -w $'\n%{http_code}')" || fail "curl error for $method $url"
  RESP_STATUS="${response##*$'\n'}"
  RESP_BODY="${response%$'\n'*}"
}

json_field() {
  local payload="$1"
  local field="$2"
  sed -n "s/.*\"$field\"[[:space:]]*:[[:space:]]*\"\\{0,1\\}\\([^\",}]*\\)\"\\{0,1\\}.*/\\1/p" <<<"$payload" | head -n1
}

assert_contains() {
  local haystack="$1"
  local needle="$2"
  grep -q "$needle" <<<"$haystack" || fail "expected to find '$needle' in response"
}

echo "[smoke-admin] Demo login..."
request "POST" "$APP_URL/api/v1/auth/login" "" "$demo_cookie" "{\"email\":\"$DEMO_EMAIL\",\"password\":\"$DEMO_PASSWORD\"}"
[[ "$RESP_STATUS" == "200" ]] || fail "demo login status $RESP_STATUS"

echo "[smoke-admin] Create custom option..."
suffix="$(date +%s)"
name="Smoke Admin ${suffix}"
request "POST" "$APP_URL/api/v1/custom-options" "$demo_cookie" "" "{\"category\":\"$CATEGORY\",\"name\":\"$name\"}"
[[ "$RESP_STATUS" == "201" ]] || fail "create custom option status $RESP_STATUS"
option_id="$(json_field "$RESP_BODY" "id")"
[[ -n "$option_id" ]] || fail "custom option id missing"

echo "[smoke-admin] Admin login..."
request "POST" "$APP_URL/api/v1/auth/login" "" "$admin_cookie" "{\"email\":\"$ADMIN_EMAIL\",\"password\":\"$ADMIN_PASSWORD\"}"
[[ "$RESP_STATUS" == "200" ]] || fail "admin login status $RESP_STATUS"

echo "[smoke-admin] Approve custom option..."
request "POST" "$APP_URL/api/v1/admin/custom-triggers/$option_id/approve" "$admin_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "approve status $RESP_STATUS"
status_value="$(json_field "$RESP_BODY" "status")"
[[ "$status_value" == "approved" ]] || fail "expected approved, got '$status_value'"

echo "[smoke-admin] Verify approved in list..."
request "GET" "$APP_URL/api/v1/admin/custom-triggers?status=approved&category=$CATEGORY" "$admin_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "approved list status $RESP_STATUS"
assert_contains "$RESP_BODY" "\"id\":$option_id"

echo "[smoke-admin] Reject custom option..."
request "POST" "$APP_URL/api/v1/admin/custom-triggers/$option_id/reject" "$admin_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "reject status $RESP_STATUS"
status_value="$(json_field "$RESP_BODY" "status")"
[[ "$status_value" == "rejected" ]] || fail "expected rejected, got '$status_value'"

echo "[smoke-admin] Verify rejected in list..."
request "GET" "$APP_URL/api/v1/admin/custom-triggers?status=rejected&category=$CATEGORY" "$admin_cookie"
[[ "$RESP_STATUS" == "200" ]] || fail "rejected list status $RESP_STATUS"
assert_contains "$RESP_BODY" "\"id\":$option_id"

echo "[smoke-admin] OK"
