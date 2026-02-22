cd /opt/migraine-calendar-ai
git pull
docker run --rm -u $(id -u):$(id -g) -v /opt/migraine-calendar-ai/backend:/app -w /app node:22-alpine sh -lc "npm ci && npm run build"
docker compose up --build -d
docker compose exec php php artisan migrate --force
docker compose exec php php artisan db:seed --force
docker compose exec php php artisan optimize:clear
