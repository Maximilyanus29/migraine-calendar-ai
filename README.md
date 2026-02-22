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

Приложение: `http://localhost:8081`

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
