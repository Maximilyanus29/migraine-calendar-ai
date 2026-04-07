# Деплой на VPS (Docker + CI/CD)

Подключаться к серверу за вас из Cursor/CI **не получится**: приватный ключ хранится у вас (и в GitHub Secrets), не в репозитории. Этот документ — что сделать **один раз на VPS** и как работает автодеплой.

## 1. Доступ по SSH (только ключ)

На локальной машине:

```bash
ssh-keygen -t ed25519 -C "deploy-migraine-ai" -f ~/.ssh/migraine_deploy -N ""
```

Публичный ключ `~/.ssh/migraine_deploy.pub` добавьте на сервер:

```bash
# на сервере под пользователем деплоя (см. ниже)
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo 'ssh-ed25519 AAAA...your-public-key...' >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Рекомендуется **не использовать root**: пользователь с `sudo` или в группе `docker`.

Отключите вход по паролю (после проверки входа по ключу):

```bash
sudo sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo systemctl reload sshd
```

## 2. Сервер: Docker

Пример для Ubuntu 22.04/24.04:

```bash
sudo apt update && sudo apt install -y git ca-certificates curl
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker "$USER"
# перелогиньтесь, чтобы применилась группа docker
```

## 3. Клонирование и первый запуск

```bash
sudo mkdir -p /opt/migraine-calendar-ai
sudo chown "$USER:$USER" /opt/migraine-calendar-ai
cd /opt/migraine-calendar-ai
git clone <URL_ВАШЕГО_РЕПОЗИТОРИЯ> .
```

Создайте `backend/.env` на сервере (не коммитьте):

```bash
cp backend/.env.example backend/.env
nano backend/.env   # или vim
```

Обязательно задайте хотя бы:

- `APP_KEY` — сгенерировать: `docker compose run --rm php php artisan key:generate --show` (или локально `php artisan key:generate`), вставить в `.env`
- `APP_ENV=production`, `APP_DEBUG=false`
- `APP_URL=https://ваш-домен.ru` (или `http://IP` на время проверки)
- `DB_*` — как в `docker-compose.yml` (по умолчанию `postgres`, `migraine` / пользователь / пароль), **в продакшене смените пароль БД** в compose и в `.env`.

Первый подъём:

```bash
cd /opt/migraine-calendar-ai
docker compose up --build -d
docker compose exec -T php php artisan migrate --force
docker compose exec -T php php artisan db:seed --force   # один раз, демо/админ
```

Проверка: `curl -sS -o /dev/null -w "%{http_code}" http://127.0.0.1/up` → `200`.

## 4. Скрипт деплоя `pull.sh`

На сервере после клона уже должен быть каталог `/opt/migraine-calendar-ai` и выполняемые скрипты:

```bash
cd /opt/migraine-calendar-ai
chmod +x pull.sh rollback.sh smoke.sh smoke-admin.sh
```

Переменные окружения при вызове:

| Переменная | Назначение |
|------------|------------|
| `APP_URL` | Базовый URL для `smoke.sh` (как в браузере) |
| `RUN_SEED=0` | После первого деплоя обычно отключают повторный seed |
| `RUN_SMOKE=0` | Отключить смоук после деплоя |

Пример ручного деплоя:

```bash
cd /opt/migraine-calendar-ai
APP_URL=https://ваш-домен.ru RUN_SEED=0 ./pull.sh
```

## 5. GitHub Actions → VPS (CI/CD)

Workflow: [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml).

### Secrets в репозитории (Settings → Secrets and variables → Actions)

| Secret | Описание |
|--------|----------|
| `SSH_HOST` | IP или hostname VPS |
| `SSH_USER` | Пользователь с правом `docker compose` в `/opt/migraine-calendar-ai` |
| `SSH_KEY` | **Весь** приватный ключ (мультистрочный), лучше отдельный deploy-key |
| `SSH_PORT` | Порт SSH, обычно `22` |
| `APP_URL` | Публичный URL приложения для смоук-тестов |

Приватный ключ в Secret вставляют **целиком**, включая строки `BEGIN OPENSSH PRIVATE KEY` / `END ...`.

Деплой запускается при push в `main` и вручную (**Actions → Deploy → Run workflow**).

На сервере должен быть настроен `git pull` (deploy key для **read** репозитория или HTTPS с token — отдельно от SSH Actions).

### Deploy key для `git pull` на сервере

GitHub → Repo → Settings → Deploy keys → добавить **read-only** ключ; публичную часть положить в `authorized_keys` не нужно для deploy key — нужна настройка в GitHub. Приватный ключ положить на сервер, например `/home/deployer/.ssh/github_migraine_read`, и:

```bash
cd /opt/migraine-calendar-ai
git remote set-url origin git@github.com:ORG/REPO.git
```

В `~/.ssh/config`:

```
Host github.com
  IdentityFile ~/.ssh/github_migraine_read
  IdentitiesOnly yes
```

## 6. Nginx и домен

Файл [`docker/nginx/default.conf`](../docker/nginx/default.conf) слушает порт 80. Замените `server_name` на ваш домен или оставьте универсальный префикс для проверки.

HTTPS: на VPS часто ставят **Caddy** или **certbot** на хосте и проксируют на `127.0.0.1:80`, либо используют внешний CDN с TLS. Детали зависят от провайдера; минимум — открыть `80`/`443` в фаерволе только для нужных IP при необходимости.

## 7. Чеклист безопасности

- Нет паролей и ключей в репозитории
- `APP_DEBUG=false` в продакшене
- Уникальный пароль PostgreSQL в compose + `.env`
- Регулярные бэкапы тома `postgres_data` или дампы `pg_dump`
- После первого запуска: `RUN_SEED=0` в деплое, если не хотите повторять сиды

## 8. Почему «по ключу ты не подключишься» из чата

Среда разработки агента **не имеет** вашего `~/.ssh/id_ed25519` и не должна хранить секреты. Деплой делают: **GitHub Actions** (секреты) или **ваш терминал** после `ssh -i ... user@host`.
