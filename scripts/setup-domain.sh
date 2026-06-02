#!/usr/bin/env bash
# Одноразовая настройка домена + Caddy на VPS (запускать на сервере от root).
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/migraine-calendar-ai}"
DOMAIN="${DOMAIN:-migraine-calendar.ru}"
APP_URL="${APP_URL:-https://${DOMAIN}}"

if [[ "$(id -u)" -ne 0 ]]; then
  echo "Запустите от root: sudo $0"
  exit 1
fi

echo "[domain] APP_DIR=$APP_DIR DOMAIN=$DOMAIN"

if ! command -v caddy >/dev/null 2>&1; then
  echo "[domain] Installing Caddy..."
  apt-get update
  apt-get install -y debian-keyring debian-archive-keyring apt-transport-https curl
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
  apt-get update
  apt-get install -y caddy
fi

echo "[domain] Docker nginx -> 127.0.0.1:8080"
compose_env="$APP_DIR/.env"
if grep -q '^NGINX_PUBLISH=' "$compose_env" 2>/dev/null; then
  sed -i 's|^NGINX_PUBLISH=.*|NGINX_PUBLISH=127.0.0.1:8080|' "$compose_env"
else
  echo 'NGINX_PUBLISH=127.0.0.1:8080' >> "$compose_env"
fi

cd "$APP_DIR"
docker compose up -d

echo "[domain] Caddyfile"
install -d /etc/caddy
cat > /etc/caddy/Caddyfile <<EOF
${DOMAIN}, www.${DOMAIN} {
    reverse_proxy 127.0.0.1:8080
}
EOF

systemctl enable caddy
systemctl restart caddy

backend_env="$APP_DIR/backend/.env"
for key_val in \
  "APP_URL=${APP_URL}" \
  "SESSION_SECURE_COOKIE=true"; do
  key="${key_val%%=*}"
  if grep -q "^${key}=" "$backend_env"; then
    sed -i "s|^${key}=.*|${key_val}|" "$backend_env"
  else
    echo "$key_val" >> "$backend_env"
  fi
done

  docker compose exec -T php php artisan optimize:clear
  docker compose exec -T php sh -c 'chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache'
  docker compose exec -T -u www-data php php artisan config:cache
  docker compose exec -T -u www-data php php artisan view:cache

echo "[domain] Done. Check: curl -sS -o /dev/null -w '%{http_code}\n' ${APP_URL}/"
echo "[domain] DNS must point ${DOMAIN} and www.${DOMAIN} to this server's public IP."
