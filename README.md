# 📘 Laravel + Vite — Docker Guide

Run Laravel + Vite locally with **Docker** for both dev and production.

---

## 🧪 Development

### ▶ Start

```bash
cp .env.example .env
```

```bash
docker compose up -d # or: docker compose up -d --build
```

Services:

* `app` — Node + Vite dev server
* `php` — PHP-FPM
* `nginx` — [http://localhost:8080](http://localhost:8080)
* `mysql` — port 3306
* `phpmyadmin` — [http://localhost:8081](http://localhost:8081)

First run: copies `.env`, installs deps, runs migrations, sets key, links storage.

### 📝 Code Mount

```yaml
volumes:
  - ./:/var/www/html
```

Edits are live — no rebuild needed.

### 🔧 Common Commands

```bash
docker compose exec php php artisan migrate
docker compose exec php composer require <pkg>
docker compose exec app npm i <pkg> -D
docker compose down -v # stop + clear DB
```

---

## 🚀 Production (Local)

Builds frontend once → serves via Nginx & PHP-FPM (no Vite server).

### ▶ Start

```bash
docker compose -f docker-compose.prod.yaml up -d --build
```

### ⚙ First Init

```bash
docker compose -f docker-compose.prod.yaml exec php sh -lc '[ -f .env ] || cp .env.example .env'
docker compose -f docker-compose.prod.yaml exec php php artisan key:generate --force
docker compose -f docker-compose.prod.yaml exec php php artisan migrate --force
docker compose -f docker-compose.prod.yaml exec php php artisan storage:link || true
docker compose -f docker-compose.prod.yaml exec php php artisan config:cache
```

Visit:

* App → [http://localhost](http://localhost)
* phpMyAdmin → [http://localhost:8081](http://localhost:8081)

---

## 🔐 CSRF + Sanctum (коли фронтенд на іншому домені)

Щоб уникнути помилки "CSRF token mismatch" у випадку, коли фронтенд (Vite build) розміщений на іншому домені/субдомені, налаштуйте наступне:

1) Налаштування .env бекенду (Laravel):

- APP_URL=https://api.example.com
- SESSION_DRIVER=cookie
- SESSION_DOMAIN=.example.com            # з крапкою попереду — дозволяє cookie для всіх субдоменів
- SESSION_SAME_SITE=none                 # потрібен для крос-доменного обміну cookie
- SESSION_SECURE_COOKIE=true             # обов'язково для HTTPS, щоб SameSite=None працював
- SANCTUM_STATEFUL_DOMAINS=app.example.com,frontend.example.com

Примітки:
- Для локалки можна використовувати власні домени у /etc/hosts (наприклад, api.test, app.test) і відповідно виставити SESSION_DOMAIN=.test.
- Після змін .env: php artisan config:clear && php artisan route:clear

2) Налаштування фронтенду (Vite):

- Додайте у .env.vite або .env: VITE_BACKEND_URL=https://api.example.com
- Наш фронтенд використовує загальний хелпер resources/js/lib/csrf.ts, який:
  - викликає GET /sanctum/csrf-cookie з credentials: 'include'
  - зчитує cookie XSRF-TOKEN
  - додає заголовок X-XSRF-TOKEN до не-GET запитів
  - завжди включає credentials: 'include' і Accept: application/json

3) Точки входу на фронті вже оновлені:

- AppLayout.vue, AuthLayout.vue — вибір рівня користувача через fetchJson (Sanctum + XSRF).
- Templates.vue — усі запити переходять на fetchJson, тож працюють крос-доменно.

4) Як перевірити

- Відкрийте SPA на фронтенд-домені (наприклад, https://app.example.com).
- В інструментах розробника перевірте, що перед POST запитами відбувається GET https://api.example.com/sanctum/csrf-cookie (200 OK), а запит POST має заголовок X-XSRF-TOKEN і кукі з доменом .example.com.
- Переконайтесь, що усі запити йдуть з credentials: include.

Додатково:
- За потреби дозволи CORS мають бути налаштовані на бекенді (config/cors.php) для вашого фронтенд-домену. У більшості випадків з Sanctum та stateful доменами достатньо правильних cookie й не потрібні широкі CORS правила для stateful запитів.
