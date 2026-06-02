# Домен и SSL: migraine-calendar.ru

VPS: **72.56.6.19**. Caddy установлен на сервере.

## DNS (сделать в панели регистратора)

| Тип | Имя | Значение |
|---|---|---|
| **A** | `@` | `72.56.6.19` |
| **A** | `www` | `72.56.6.19` |

Проверка:

```bash
dig +short migraine-calendar.ru A @8.8.8.8
# должно вернуть: 72.56.6.19
```

После обновления DNS (5–30 мин, иногда до 24 ч) Caddy сам получит Let's Encrypt:

```bash
ssh mig "systemctl restart caddy && journalctl -u caddy -f"
```

Сайт: **https://migraine-calendar.ru**

## Уже настроено на сервере

- Docker nginx слушает `127.0.0.1:8080` (файл `/opt/migraine-calendar-ai/.env`: `NGINX_PUBLISH=127.0.0.1:8080`)
- Caddy проксирует 443 → 8080
- `APP_URL=https://migraine-calendar.ru` в `backend/.env`
- `SESSION_SECURE_COOKIE=true`

Повторная настройка (если нужно):

```bash
ssh mig "bash /opt/migraine-calendar-ai/scripts/setup-domain.sh"
```

---

## Старый документ (общая инструкция)

Ниже — универсальная инструкция для любого домена.

```
Браузер ──HTTPS:443──► Caddy (на хосте VPS)
                            │
                            └──HTTP──► Docker nginx (127.0.0.1:8080)
```

---

## 1. Купить домен

Где удобно (если VPS на Timeweb — логично домен там же):

| Регистратор | Плюсы |
|---|---|
| [Timeweb](https://timeweb.com) | один кабинет с VPS, простой DNS |
| [Reg.ru](https://www.reg.ru) | популярен в РФ, дёшево `.ru` |
| [Cloudflare Registrar](https://www.cloudflare.com/products/registrar/) | домен по себестоимости + бесплатный DNS/CDN |
| Namecheap / Porkbun | если нужен `.com` без привязки к РФ |

Для личного проекта часто берут **`.ru`**, **`.com`** или **`.app`**.

---

## 2. Настроить DNS

В панели регистратора → **DNS / Управление зоной** → добавить записи:

| Тип | Имя | Значение | TTL |
|---|---|---|---|
| **A** | `@` | `72.56.6.19` | 300–3600 |
| **A** | `www` | `72.56.6.19` | 300–3600 |

Пример: домен `migraine.example.ru` → оба `@` и `www` указывают на IP сервера.

Проверка (через 5–30 мин, иногда до 24 ч):

```bash
dig +short migraine.example.ru
dig +short www.migraine.example.ru
# оба должны вернуть 72.56.6.19
```

---

## 3. SSL: варианты

| Способ | Сложность | Автопродление | Для нашего Docker |
|---|---|---|---|
| **Caddy на VPS** | низкая | да | ✅ рекомендуем |
| Certbot + nginx на VPS | средняя | да (cron) | ✅ |
| SSL только в Cloudflare | низкая | да | ⚠️ нужен режим Full + сертификат на origin |
| SSL внутри Docker nginx | высокая | вручную | не рекомендуем |

**Let's Encrypt** — бесплатный сертификат, продлевается автоматически (Caddy/Certbot).

---

## 4. Настройка SSL через Caddy (рекомендуется)

### 4.1. На VPS: освободить порты 80/443 для Caddy

Сейчас Docker nginx слушает `0.0.0.0:80`. Нужно перевести его на localhost.

В `/opt/migraine-calendar-ai/docker-compose.yml` у nginx:

```yaml
ports:
  - "127.0.0.1:8080:80"
```

Применить:

```bash
ssh mig "cd /opt/migraine-calendar-ai && docker compose up -d"
```

Проверка: `curl -sS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/` → `200`.

### 4.2. Установить Caddy на VPS

```bash
ssh mig
apt update && apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' | tee /etc/apt/sources.list.d/caddy-stable.list
apt update && apt install -y caddy
```

### 4.3. Конфиг Caddy

Замените `migraine.example.ru` на **ваш** домен.

```bash
cat > /etc/caddy/Caddyfile <<'EOF'
migraine.example.ru, www.migraine.example.ru {
    reverse_proxy 127.0.0.1:8080
}
EOF

systemctl reload caddy
systemctl status caddy
```

Caddy сам:
- получит сертификат Let's Encrypt;
- будет обновлять его;
- редиректит HTTP → HTTPS.

### 4.4. Проверка

```bash
curl -sS -o /dev/null -w "HTTP %{http_code}\n" https://migraine.example.ru/
```

В браузере: `https://ваш-домен.ru`

---

## 5. Обновить приложение под новый URL

### На сервере: `backend/.env`

```bash
ssh mig "nano /opt/migraine-calendar-ai/backend/.env"
```

```env
APP_URL=https://migraine.example.ru
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
```

Применить:

```bash
ssh mig "cd /opt/migraine-calendar-ai && \
  docker compose exec -T php php artisan optimize:clear && \
  docker compose exec -T -u www-data php php artisan config:cache"
```

### nginx в репозитории (опционально, для порядка)

`docker/nginx/default.conf`:

```nginx
server_name migraine.example.ru www.migraine.example.ru;
```

### GitHub Actions

Secret **`APP_URL`** → `https://migraine.example.ru`

### Локальная шпаргалка

`docs/deploy-cheatsheet.md` — обновить URL в командах деплоя.

---

## 6. Альтернатива: Cloudflare (без Caddy)

1. Домен в Cloudflare → DNS: A `@` и `www` → `72.56.6.19`, **Proxy включён** (оранжевое облако).
2. SSL/TLS → режим **Full (strict)** — на origin всё равно нужен сертификат (Caddy или Cloudflare Origin Certificate).
3. Режим **Flexible** — проще, но **не подходит** для Laravel session cookies в production.

Для Laravel с сессиями лучше **Caddy + Let's Encrypt** или **Cloudflare Full + Caddy**.

---

## 7. Чеклист

- [ ] Домен куплен
- [ ] A-записи `@` и `www` → `72.56.6.19`
- [ ] `dig` показывает правильный IP
- [ ] Docker nginx на `127.0.0.1:8080:80`
- [ ] Caddy установлен, `Caddyfile` с доменом
- [ ] `https://домен` открывается
- [ ] `APP_URL` и `SESSION_SECURE_COOKIE=true` в `.env`
- [ ] GitHub Secret `APP_URL` обновлён
- [ ] Деплой: `APP_URL=https://домен RUN_SEED=0 ./pull.sh`

---

## 8. Частые проблемы

**Let's Encrypt не выдаёт сертификат**
- DNS ещё не обновился — подождать и проверить `dig`
- Порт 80/443 закрыт у провайдера — открыть в панели Timeweb (Firewall / Security groups)
- Docker всё ещё слушает `0.0.0.0:80` — Caddy не может пройти HTTP-challenge

**Сайт открывается по IP, но не по домену**
- Проверить A-запись
- Проверить `server_name` / Caddyfile

**После HTTPS ломается вход (cookies)**
- `APP_URL` должен быть `https://...`
- `SESSION_SECURE_COOKIE=true`
- `php artisan config:cache` после смены `.env`

**www и без www**
- В Caddyfile указать оба: `example.ru, www.example.ru`
- Или редирект www → apex (Caddy делает автоматически при настройке canonical)

---

## 9. Стоимость (ориентир)

| | |
|---|---|
| Домен `.ru` | ~200–500 ₽/год |
| Домен `.com` | ~$10–15/год |
| SSL Let's Encrypt | бесплатно |
| Caddy | бесплатно |
