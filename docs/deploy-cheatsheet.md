# Шпаргалка по деплою

Краткая подсказка для повседневной работы. Полная настройка с нуля — в [deploy.md](./deploy.md).

## Сервер

| | |
|---|---|
| SSH | `ssh mig` |
| IP | `72.56.6.19` |
| Каталог | `/opt/migraine-calendar-ai` |
| URL | **https://migraine-calendar.ru** |

## Обычный деплой

```bash
# 1. Локально
cd /home/max/migraine-calendar-ai
git add .
git commit -m "описание изменений"
git push origin main

# 2. На сервер
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 ./pull.sh"
```

`RUN_SEED=0` — не пересоздавать demo/admin на проде.

## Проверка после деплоя

```bash
curl -sS -o /dev/null -w "HTTP %{http_code}\n" https://migraine-calendar.ru/
ssh mig "cd /opt/migraine-calendar-ai && docker compose ps"
```

В браузере: **https://migraine-calendar.ru**

## Что делает pull.sh

1. `git pull` — новый код
2. `npm ci && npm run build` — сборка Vue
3. `docker compose up -d` — перезапуск (без пересборки PHP-образа)
4. `php artisan migrate --force` — миграции
5. Права на `storage/`, прогрев кэша
6. `smoke.sh` — автопроверка API

## Полезные флаги

```bash
SKIP_PULL=1 ./pull.sh          # без git pull
SKIP_BUILD=0 ./pull.sh         # пересобрать PHP-образ (долго на VPS!)
RUN_SMOKE=0 ./pull.sh          # без smoke-тестов
RUN_SMOKE_ADMIN=1 ./pull.sh    # + проверка админки
```

По умолчанию `SKIP_BUILD=1` — PHP-образ не пересобирается (код монтируется volume).

## Быстрые команды

```bash
# Статус контейнеров
ssh mig "cd /opt/migraine-calendar-ai && docker compose ps"

# Логи PHP
ssh mig "cd /opt/migraine-calendar-ai && docker compose logs --tail=50 php"

# Откат на предыдущий коммит
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 ./rollback.sh"
```

## Только PHP (без Vue)

```bash
ssh mig "cd /opt/migraine-calendar-ai && git pull && \
  docker compose exec -T php php artisan migrate --force && \
  docker compose restart php"
```

## Только фронт (если код уже на сервере)

```bash
ssh mig "cd /opt/migraine-calendar-ai && SKIP_PULL=1 RUN_SEED=0 ./pull.sh"
```

## Автодеплой (GitHub Actions)

Push в `main` → workflow **Deploy** (`.github/workflows/deploy.yml`).

Secrets: `SSH_HOST`, `SSH_USER`, `SSH_KEY`, `SSH_PORT`, `APP_URL`.

| Secret | Значение |
|---|---|
| `SSH_HOST` | `72.56.6.19` |
| `SSH_USER` | `root` |
| `SSH_PORT` | `22` |
| `APP_URL` | `https://migraine-calendar.ru` |

## Если сайт не открывается

**HTTP 500 после деплоя** — права на storage:

```bash
ssh mig "cd /opt/migraine-calendar-ai && \
  docker compose exec -T php sh -c 'chown -R www-data:www-data storage bootstrap/cache' && \
  docker compose exec -T -u www-data php php artisan view:cache"
```

**«This page isn't working»** — проверьте DNS и `systemctl status caddy`.

**Сборка зависла на llvm20-libs** — не использовать `SKIP_BUILD=0` на слабом VPS; деплоить с `SKIP_BUILD=1` (по умолчанию).

## Локальная разработка

```bash
cd /home/max/migraine-calendar-ai
NGINX_PUBLISH=0.0.0.0:8080 docker compose up -d   # если порт 80 занят
```

Приложение: http://localhost:8080

Demo: `demo@example.com` / `password`  
Admin: `admin@example.com` / `admin12345`
