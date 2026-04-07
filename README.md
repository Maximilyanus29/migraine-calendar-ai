# Migraine AI

Итерации 1+2: Laravel API + Vue SPA + PostgreSQL + Nginx через Docker Compose.

## Реализовано
- Laravel API `/api/v1`
- Auth (session cookie): `login/logout/me`
- Attacks API: `last`, список по интервалу, CRUD
- Meta API: словари для формы
- Пользовательские триггеры:
  - пользователь может добавить свой триггер (автоотправка на модерацию)
  - rate limit: не более `10` новых триггеров в день и `2` в минуту на пользователя
- Админ-модерация пользовательских триггеров (`/admin/triggers`)
- Vue SPA маршруты:
  - `/login`
  - `/calendar`
  - `/attacks/new`
  - `/attacks/:id/edit`
  - `/graphs` (заглушка)
  - `/admin/triggers` (для админа)
- Календарь 6x7 с сегментами приступов по времени и интенсивности
- Создание/редактирование приступа, автоподстановка из последнего приступа

## Запуск
```bash
docker compose up --build -d
```

Приложение: `http://localhost` (в `docker-compose.yml` порт **80:80**)

## Demo account
- email: `demo@example.com`
- password: `password`

## Admin account
- email: `admin@example.com`
- password: `admin12345`

## Сборка фронтенда
Если меняешь Vue-код, собери ассеты:
```bash
docker run --rm -u $(id -u):$(id -g) \
  -v /home/max/php/migraine-ai/backend:/app -w /app \
  node:22-alpine sh -lc "npm run build"
```

```bash

docker run --rm -u $(id -u):$(id -g) \
  -v /opt/migraine-calendar-ai/backend:/app -w /app \
  node:22-alpine sh -lc "npm install"

docker run --rm -u $(id -u):$(id -g) \
  -v /opt/migraine-calendar-ai/backend:/app -w /app \
  node:22-alpine sh -lc "npm run build"
```

## Smoke тесты после деплоя
Быстрая проверка основных сценариев API:
```bash
APP_URL=http://localhost:8081 ./smoke.sh
```

Проверка сценария модерации (approve/reject):
```bash
APP_URL=http://localhost:8081 ./smoke-admin.sh
```

В `pull.sh` smoke-проверки запускаются автоматически.
Отключить можно так:
```bash
RUN_SMOKE=0 ./pull.sh
```

Включить admin smoke прямо в деплой:
```bash
RUN_SMOKE_ADMIN=1 ./pull.sh
```

## Откат (Rollback)
Быстрый откат к предыдущему успешному релизу:
```bash
./rollback.sh
```

Откат к конкретному коммиту/тегу:
```bash
./rollback.sh <commit_or_tag>
```

Скрипт использует `pull.sh` с `DEPLOY_REF`, делает сборку, миграции и smoke-проверки.

## PWA
Добавлен базовый PWA:
- `backend/public/manifest.webmanifest`
- `backend/public/sw.js`
- иконки в `backend/public/icons/`

Service worker регистрируется только в production (`resources/js/app.js`).
Кэшируются только статика и SPA shell; API (`/api/*`) не кэшируется сервис-воркером.

## CI/CD (GitHub Actions)
Подробный чеклист первого развёртывания на VPS и настройка ключей: **[docs/deploy.md](docs/deploy.md)**.

Добавлены workflow:
- `.github/workflows/ci.yml`:
  - backend: `pint`, `phpstan`, `phpunit` (с PostgreSQL service)
  - frontend: `eslint`, `vite build`
  - shell: `shellcheck` для `pull.sh`, `smoke.sh`, `smoke-admin.sh`
- `.github/workflows/deploy.yml`:
  - деплой по SSH на `main` и вручную (`workflow_dispatch`)
  - запускает `./pull.sh` на сервере

Нужные GitHub Secrets для деплоя:
- `SSH_HOST`
- `SSH_USER`
- `SSH_KEY` (приватный ключ целиком, лучше отдельный deploy-ключ)
- `SSH_PORT` (необязательно; если не задан — используется **22**)
- `APP_URL` (например `https://your-domain.com` — для `smoke.sh`)

Пример продакшен-переменных для сервера: `backend/.env.production.example` (копируете в `backend/.env` на VPS, без коммита).

## Быстрая проверка API
```bash
curl -i -c cookie.txt -X POST http://localhost:8081/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"demo@example.com","password":"password"}'

curl -b cookie.txt http://localhost:8081/api/v1/auth/me
curl -b cookie.txt http://localhost:8081/api/v1/meta/options
```

## Примечание
Старый чистый PHP backend сохранён в `backend_plain_api/` как резервная копия.
