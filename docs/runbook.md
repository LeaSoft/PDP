# 📘 PDP runbook

What to do when the DevOps platform (`devops.leasoft.co`) raises an outage for
PDP. One section per alert; each starts with the checks, then the fix, then
what to do when the fix does not work.

- **Service:** PDP in production, `https://pdp.leasoft.co` (behind Cloudflare)
- **Severity:** SEV3: an internal tool; people cannot open or update their development plans
- **Owner:** the PDP maintainers; the DevOps team for the server

The host is shared with other LeaSoft projects, and **the Traefik in this stack serves
them too** (GPS joins its `pdp_web` network). `$COMPOSE down`, or anything that stops
`traefik`, takes those sites down with PDP; restart single services instead.

On the server everything runs from the checkout the deploy resets to `main`,
with the `.env` that lives there (it is not in Git, and a deploy leaves it alone):

```bash
cd /pdp/PDP
COMPOSE="docker compose -f docker-compose.prod.yaml"
# The tag of the running deploy. Without it, `up` would switch php and nginx to `latest`
export IMAGE_TAG="$(docker inspect -f '{{.Config.Image}}' "$($COMPOSE ps -q php)" | sed 's/.*://')"
echo "$IMAGE_TAG"
```

If the php container is gone and `IMAGE_TAG` comes out empty, take the SHA of
the last green run of **🚀 PDP | Deployment & Security** on GitHub.

## 🔴 PDP is down: `/api/health` fails or answers 503

### 👀 Symptoms

The platform shows **PDP** down. The site does not load, or loads and fails on
sign-in or on the PDP list. `/api/health` names what is missing:
`{"status":"degraded","checks":{"database":"error"}}`.

### 🔎 Checks

1. Ask the health route from anywhere:

   ```bash
   curl -s -w '\n%{http_code}\n' https://pdp.leasoft.co/api/health
   ```

   - `database: error` → MySQL, step 3.
   - `502` or `504` from nginx → the `php` container, step 2.
   - Cloudflare's `521` or `522` → Traefik or the server itself, step 2.
   - Cloudflare's `525` or `526` → Traefik's certificate, step 2 with `traefik`.

2. The containers and their logs:

   ```bash
   $COMPOSE ps
   $COMPOSE logs --tail=100 php nginx
   $COMPOSE logs --tail=100 traefik
   $COMPOSE exec php tail -n 100 storage/logs/laravel.log
   ```

   The Laravel log has the reason behind `database: error`
   (`Health check: the database did not answer`).

3. MySQL and the disk:

   ```bash
   $COMPOSE logs --tail=50 mysql
   $COMPOSE exec mysql sh -c 'mysqladmin ping -uroot -p"$MYSQL_ROOT_PASSWORD"'
   df -h /
   ```

### 🛠️ Fix

- A stopped container: `$COMPOSE up -d` (with `IMAGE_TAG` set as above).
- MySQL not answering: `$COMPOSE restart mysql`, then ask `/api/health` again.
  A full disk stops MySQL first: free space (`docker image prune -f`, old logs)
  before the restart. Never `docker system prune -a` or `docker volume prune`: the
  host is shared, and they reach the other projects' images and data too.
- Errors right after a deploy that added a migration: the deploy does not run
  migrations, so run them:

  ```bash
  $COMPOSE exec php php artisan migrate --force
  ```

- A bad release: roll back to the previous build. GHCR keeps the last 10, tagged
  with their commit SHA:

  ```bash
  export IMAGE_TAG=<previous good SHA>
  $COMPOSE pull php nginx
  $COMPOSE up -d php nginx
  ```

  The next push to `main` deploys again over this, so revert or fix the bad
  commit as well. A migration the bad release ran is not undone by this.

### 🆘 When the fix does not work

Post in the DevOps channel with the output of `$COMPOSE ps`, the last 100 lines
of the php and mysql logs and the answer of `/api/health`, and keep the outage
open on the platform until the route is green again.

## 🟠 The deploy is red

### 🔎 Checks

Open the failed run of **🚀 PDP | Deployment & Security** and find the red job.

- **🧪 App | PHPUnit**: a test or the asset build failed. Nothing was built or
  deployed; production keeps the previous version. Fix the test in a new commit.
- **🐳 Images**: the Docker build failed. Nothing was deployed.
- **🔍 Security | Trivy**: a vulnerability in an image. It does not stop the
  deploy; the finding is under the repository's Code scanning alerts.
- **🚢 Prod | Deploy to server**: the SSH step failed. A failed `docker login`
  usually means the `CR_PAT` secret has expired.
- **🩺 Health | Wait for this version**: the new version did not come up
  healthy within two minutes. The last line of the step shows what
  `/api/health` answered:
  - an older `version` → the containers were not recreated; check
    `$COMPOSE ps` and `$COMPOSE logs php` on the server;
  - `"database":"error"` → follow the outage section above;
  - no JSON at all → the site is down; follow the outage section above.

### 🛠️ Fix

Fix the cause and push to `main`, or re-run the failed jobs once the cause was
outside the code (an expired token, a server that was briefly unreachable).

## 💾 Backups

The database has no scheduled backup yet. Take one by hand before risky work
(a migration on a large table, a MySQL upgrade):

```bash
$COMPOSE exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' \
  | gzip > ~/pdp-$(date +%F-%H%M).sql.gz
```
