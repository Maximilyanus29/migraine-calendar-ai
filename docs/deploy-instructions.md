# Как задеплоить Migraine Calendar AI

Пошаговая инструкция для самостоятельного деплоя на прод.

Краткая шпаргалка: [deploy-cheatsheet.md](./deploy-cheatsheet.md)  
Первоначальная настройка сервера с нуля: [deploy.md](./deploy.md)

---

## Что где находится

| | |
|---|---|
| **Сайт** | https://migraine-calendar.ru |
| **Сервер** | VPS `72.56.6.19` |
| **SSH** | `ssh mig` (должен быть настроен в `~/.ssh/config`) |
| **Проект на сервере** | `/opt/migraine-calendar-ai` |
| **Репозиторий локально** | `/home/max/migraine-calendar-ai` |

На сервере работают:
- **Docker** — приложение (nginx + PHP + PostgreSQL)
- **Caddy** — HTTPS (Let's Encrypt) на портах 80/443

---

## Обычный деплой (90% случаев)

Когда вы изменили код, закоммитили и хотите выкатить на прод.

### Шаг 1. Локально — commit и push

```bash
cd /home/max/migraine-calendar-ai

git status                    # посмотреть изменения
git add .                     # или выборочно: git add backend/...
git commit -m "кратко: что сделали"
git push origin main
```

Без `git push` сервер не получит новый код.

### Шаг 2. Запустить деплой на сервере

```bash
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 ./pull.sh"
```

- `APP_URL=...` — нужен для автопроверки после деплоя
- `RUN_SEED=0` — **важно на проде**: не пересоздавать demo/admin пользователей

Скрипт `pull.sh` сам:
1. Скачает код (`git pull`)
2. Соберёт фронтенд (`npm ci && npm run build`)
3. Перезапустит Docker-контейнеры
4. Применит миграции БД
5. Очистит и прогреет кэш Laravel
6. Запустит smoke-тесты API

В конце должно быть: `[deploy] Done.` и `[smoke] OK`.

### Шаг 3. Проверить в браузере

Откройте https://migraine-calendar.ru

Если менялся CSS/JS и в браузере старая версия:
- **Safari** — жёсткое обновление или закрыть вкладку
- **Яндекс.Браузер** — часто нужно очистить кэш сайта или открыть **инкогнито**
- Если появился баннер «Доступна новая версия» — нажмите **Обновить**

Проверка из терминала:

```bash
curl -sS -o /dev/null -w "HTTP %{http_code}\n" https://migraine-calendar.ru/
# ожидается: HTTP 200
```

---

## Что меняли — что деплоить

| Меняли | Достаточно |
|---|---|
| Vue, CSS, JS | полный `./pull.sh` (сборка фронта) |
| PHP (контроллеры, модели, миграции) | полный `./pull.sh` |
| Только `docker-compose.yml` / Dockerfile | полный `./pull.sh`; образ PHP пересобирается только с `SKIP_BUILD=0` |
| `.env` на сервере вручную | см. раздел «Только конфиг» ниже |

**Не коммитьте** `backend/.env` — он только на сервере и локально у вас.

---

## Полезные варианты команды

```bash
# Стандартный деплой
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 ./pull.sh"

# Без smoke-тестов (быстрее)
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 RUN_SMOKE=0 ./pull.sh"

# Код уже на сервере, только пересобрать фронт и перезапустить
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 SKIP_PULL=1 ./pull.sh"

# Только PHP, без пересборки фронта (мелкий бэкенд-фикс)
ssh mig "cd /opt/migraine-calendar-ai && git pull && \
  docker compose exec -T php php artisan migrate --force && \
  docker compose exec -T php php artisan optimize:clear && \
  docker compose exec -T php sh -c 'chown -R www-data:www-data storage bootstrap/cache' && \
  docker compose exec -T -u www-data php php artisan config:cache && \
  docker compose exec -T -u www-data php php artisan view:cache && \
  docker compose restart php"
```

### Флаги `pull.sh`

| Флаг | Значение |
|---|---|
| `RUN_SEED=0` | Не запускать сиды (всегда на проде) |
| `RUN_SMOKE=0` | Пропустить smoke-тесты |
| `SKIP_PULL=1` | Не делать `git pull` |
| `SKIP_BUILD=0` | Пересобрать PHP Docker-образ (**долго**, на VPS может «зависнуть») |

По умолчанию `SKIP_BUILD=1` — PHP-образ **не** пересобирается. Код PHP монтируется в контейнер с диска, для обычных правок пересборка не нужна.

---

## Откат, если что-то сломалось

```bash
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 ./rollback.sh"
```

Откатит на предыдущий успешный коммит (записан в `.deploy_previous` на сервере).

---

## Диагностика

### Статус контейнеров

```bash
ssh mig "cd /opt/migraine-calendar-ai && docker compose ps"
```

Все три сервиса (`nginx`, `php`, `postgres`) должны быть `Up`. Postgres — `healthy`.

### Логи

```bash
# PHP
ssh mig "cd /opt/migraine-calendar-ai && docker compose logs --tail=80 php"

# Nginx
ssh mig "cd /opt/migraine-calendar-ai && docker compose logs --tail=50 nginx"

# Caddy (HTTPS)
ssh mig "journalctl -u caddy -n 30 --no-pager"
```

### Laravel-лог на сервере

```bash
ssh mig "tail -50 /opt/migraine-calendar-ai/backend/storage/logs/laravel.log"
```

---

## Частые проблемы

### Сайт не открывается / HTTP 500

Права на `storage` (часто после деплоя):

```bash
ssh mig "cd /opt/migraine-calendar-ai && \
  docker compose exec -T php sh -c 'chown -R www-data:www-data storage bootstrap/cache' && \
  docker compose exec -T -u www-data php php artisan view:cache"
```

### В браузере старый вид, API работает

Service worker / кэш браузера. Решения:
- инкогнито
- очистка данных сайта в настройках браузера
- баннер «Обновить» в приложении

### Деплой завис на сборке PHP (`llvm20-libs`)

Прервите (`Ctrl+C`) и деплойте **без** пересборки образа:

```bash
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 SKIP_BUILD=1 ./pull.sh"
```

`SKIP_BUILD=1` — значение по умолчанию.

### `git pull` на сервере не работает

Проверьте доступ репозитория с сервера:

```bash
ssh mig "cd /opt/migraine-calendar-ai && git pull --ff-only"
```

Если ошибка авторизации — настроить deploy key или token для GitHub (см. [deploy.md](./deploy.md)).

### HTTPS не работает

```bash
ssh mig "systemctl status caddy"
ssh mig "dig +short migraine-calendar.ru A @8.8.8.8"
# должен вернуть 72.56.6.19
```

---

## Автодеплой через GitHub (опционально)

При push в `main` может срабатывать workflow **Deploy** (`.github/workflows/deploy.yml`).

Нужны Secrets в репозитории → Settings → Secrets → Actions:

| Secret | Значение |
|---|---|
| `SSH_HOST` | `72.56.6.19` |
| `SSH_USER` | `root` |
| `SSH_KEY` | приватный SSH-ключ (целиком) |
| `SSH_PORT` | `22` |
| `APP_URL` | `https://migraine-calendar.ru` |

Тогда после `git push origin main` деплой идёт сам. Ручная команда из шага 2 не нужна.

---

## Локальная разработка (не прод)

```bash
cd /home/max/migraine-calendar-ai

# если порт 80 занят
NGINX_PUBLISH=0.0.0.0:8080 docker compose up -d

# после изменений Vue/CSS
docker run --rm -u $(id -u):$(id -g) \
  -v "$PWD/backend:/app" -w /app \
  node:22-alpine sh -lc "npm run build"
```

Локально: http://localhost:8080  
Demo: `demo@example.com` / `password`

---

## Шпаргалка одной строкой

```bash
# commit → push → deploy
cd /home/max/migraine-calendar-ai && \
git push origin main && \
ssh mig "cd /opt/migraine-calendar-ai && APP_URL=https://migraine-calendar.ru RUN_SEED=0 ./pull.sh"
```
