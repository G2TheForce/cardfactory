# Deploying SmartCard Studio

This guide sets up a production server for SmartCard Studio on Ubuntu 24.04 LTS with
Nginx, PHP-FPM 8.3, MySQL 8, Redis, a Supervisor-managed queue worker and the Laravel
scheduler. Sample configuration files are in [`deploy/`](../deploy):

| File | What it is |
| ---- | ---------- |
| [`deploy/nginx.conf`](../deploy/nginx.conf) | Nginx site (HTTP; Certbot adds HTTPS) |
| [`deploy/supervisor.conf`](../deploy/supervisor.conf) | Queue workers for exports, bulk runs and mail |
| [`deploy/crontab`](../deploy/crontab) | Scheduler entry |
| [`deploy/deploy.sh`](../deploy/deploy.sh) | Zero-downtime deploy script (release folders + symlink) |

One server with 2 vCPU and 4 GB RAM comfortably runs the web app, the database, Redis and
two export workers. Headless Chrome uses roughly 200–400 MB per running export.

## 1. Server packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server redis-server supervisor git unzip curl \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-gd php8.3-intl \
  php8.3-zip php8.3-bcmath php8.3-curl php8.3-redis php8.3-opcache \
  chromium ghostscript fonts-dejavu-core

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
# Node.js 20+ (for building assets during deploys)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

- **Chromium** is needed for PNG and PDF exports. On Ubuntu the `chromium` package is a
  snap; if snaps are not available, install Google Chrome from Google's .deb instead and
  set `CHROME_PATH=/usr/bin/google-chrome`. Check with `chromium --headless --version`.
- **Ghostscript** enables the CMYK PDF option. Without it the option is hidden.
- Without Chrome the app still works: PNG and PDF exports are disabled with a message, SVG
  exports keep working.

### PHP settings

`/etc/php/8.3/fpm/conf.d/99-smartcard.ini` (and the same file under `cli/conf.d` for the
queue workers):

```ini
expose_php = Off
memory_limit = 512M            ; large spreadsheets and combined PDFs
upload_max_filesize = 12M      ; bulk spreadsheets (5 MB default limit) and images (10 MB)
post_max_size = 12M
max_execution_time = 120
opcache.enable = 1
opcache.memory_consumption = 192
opcache.validate_timestamps = 0  ; the deploy script reloads PHP-FPM
```

## 2. Database and Redis

```sql
CREATE DATABASE smartcard_studio CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'smartcard'@'localhost' IDENTIFIED BY 'a-long-random-password';
GRANT ALL ON smartcard_studio.* TO 'smartcard'@'localhost';
```

Redis needs no changes for a single server. It is used for cache, sessions and queues.

## 3. Folders and the production `.env`

```bash
sudo mkdir -p /var/www/smartcard-studio/{releases,shared/storage}
sudo chown -R deploy:www-data /var/www/smartcard-studio
sudo chmod -R g+rwX /var/www/smartcard-studio/shared
```

Create `/var/www/smartcard-studio/shared/.env` from `.env.example` and change at least:

```dotenv
APP_NAME="SmartCard Studio"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cards.example.com      # used in QR codes, NFC links and emails: set it before printing anything
APP_KEY=                               # php artisan key:generate --show, then paste here

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=smartcard_studio
DB_USERNAME=smartcard
DB_PASSWORD=a-long-random-password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=3700           # above the 3600 s export and bulk job timeout

SESSION_SECURE_COOKIE=true
SECURITY_CSP=true
SECURITY_HSTS=true                     # only once every subdomain is served over HTTPS

MAIL_MAILER=smtp                       # password resets and contact-form mail
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=hello@example.com
SMARTCARD_CONTACT_EMAIL=sales@example.com

CHROME_PATH=/snap/bin/chromium         # or leave empty to auto-detect
CHROME_NO_SANDBOX=true                 # needed when workers run as a system user without user namespaces
APP_CURRENCY=USD
ANALYTICS_COUNTRY_HEADER=CF-IPCountry  # empty if you're not behind Cloudflare (or set your CDN's header)
```

Never set `SEED_DEMO_DATA=true` on a live site: it creates the demo accounts with the
password `password`. With `APP_ENV=production` the seeder only creates roles,
permissions and the 20 built-in themes.

## 4. First deploy

For the very first release, run the steps by hand once (later deploys use the script,
see [Updating](#7-updating)):

```bash
cd /var/www/smartcard-studio
git clone --depth 1 https://github.com/G2TheForce/cardfactory.git releases/first
cd releases/first
rm -rf storage && ln -s ../../shared/storage storage && ln -s ../../shared/.env .env
mkdir -p storage/app/{public,private} storage/framework/{cache,sessions,views} storage/logs
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules
php artisan migrate --force --seed     # roles, permissions and themes (no demo data in production)
php artisan storage:link
php artisan optimize
ln -sfn /var/www/smartcard-studio/releases/first /var/www/smartcard-studio/current
```

Create the first administrator:

```bash
cd /var/www/smartcard-studio/current
php artisan smartcard:create-admin you@example.com --name="Your Name"   # asks for a password
```

Sign in, then add the rest of your team under **Administration → Users**.

## 5. Web server and HTTPS

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/smartcard-studio   # edit server_name and paths
sudo ln -s /etc/nginx/sites-available/smartcard-studio /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d cards.example.com
```

If you are behind a load balancer or CDN, set `TRUSTED_PROXIES` to its IP ranges (or `*`
when only the proxy can reach the server) so Laravel sees HTTPS, the real client IP for
rate limits, and secure cookies work.

## 6. Queue workers and scheduler

```bash
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/smartcard-studio.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status

sudo crontab -u www-data deploy/crontab
```

What runs in the background:

| Job | Queue / schedule |
| --- | ---------------- |
| PNG/PDF/SVG exports, batch ZIP and combined PDF | `exports` queue, up to 1 hour |
| Bulk generator runs | `exports` queue, up to 1 hour |
| Mail (password reset, contact form) | `default` queue |
| `exports:prune` | daily: deletes export files after `EXPORT_RETENTION_DAYS` |
| `imports:prune` | daily: deletes unfinished bulk runs and their spreadsheets after `IMPORT_RETENTION_DAYS` |
| `profiles:refresh-social-counts` | daily at 04:30: follower counts for live profiles (see below) |
| `queue:prune-failed`, `auth:clear-resets`, `sanctum:prune-expired` | housekeeping |

If exports stay "queued", the worker is not running: `supervisorctl status` and
`storage/logs/worker.log` tell you why.

### Follower counts on digital profiles

Profiles can show "12.4K followers" on their Follow / Subscribe buttons. Counts
are fetched once a day from each network's official API when its key is set in
`.env`, and can always be typed in by hand on the profile's **Social** tab.

| Network | What to set | Notes |
| ------- | ----------- | ----- |
| YouTube | `YOUTUBE_API_KEY` | Free. Google Cloud console > enable "YouTube Data API v3" > Credentials > API key. |
| GitHub | nothing (`GITHUB_TOKEN` optional) | Public API; a token raises the rate limit. |
| X (Twitter) | `X_BEARER_TOKEN` | User lookups need a paid X API plan. |
| Instagram | `META_ACCESS_TOKEN`, `INSTAGRAM_BUSINESS_ACCOUNT_ID` | Uses business discovery, so it only reads business and creator accounts. Your own account must be an Instagram business account linked to a Facebook page. |
| Facebook | `META_ACCESS_TOKEN` | Facebook pages only, not personal profiles. |
| LinkedIn, TikTok | not available | No public follower API. Type the count in. |

Counts are stored on the profile, so the public page never waits on these APIs,
and a failed fetch keeps the last known count.

### Publishing a profile as a static website

**Digital profiles > a profile > Publish as a website** downloads a ZIP with
`index.html`, the vCard, styles, fonts and images. Upload the folder to any
static host (Netlify Drop, GitHub Pages, Cloudflare Pages or plain hosting); a
README inside has the steps. It needs nothing from this server, so it works even
when the app only runs locally. Enter the site address before downloading so the
QR code and share button point at it. Visits to an exported site are not counted
in Analytics, and its follower counts are as of the download.

## 7. Updating

```bash
sudo -u deploy /var/www/smartcard-studio/current/deploy/deploy.sh main
```

The script builds a new release next to the running one, runs migrations, syncs roles and
the built-in card themes, caches config,
routes and views, switches the `current` symlink, reloads PHP-FPM and restarts the workers
gracefully (a running export finishes first). The last five releases are kept; to roll
back, point `current` at the previous folder and reload PHP-FPM (roll back migrations
first if the release added any).

## 8. Backups

Back up the database and the uploaded files together. Exports are temporary and need no
backup.

```bash
# /etc/cron.d/smartcard-backup
15 2 * * * deploy mysqldump --single-transaction --quick smartcard_studio | gzip > /var/backups/smartcard/db-$(date +\%F).sql.gz
30 2 * * * deploy tar -czf /var/backups/smartcard/uploads-$(date +\%F).tar.gz -C /var/www/smartcard-studio/shared/storage/app public private/font-cache
45 3 * * * deploy find /var/backups/smartcard -type f -mtime +14 -delete
```

Copy `/var/backups/smartcard` off the server (object storage, another host). Keep a copy
of `shared/.env`: without `APP_KEY` encrypted values cannot be read. Test a restore now
and then:

```bash
gunzip < db-2026-11-05.sql.gz | mysql smartcard_studio
tar -xzf uploads-2026-11-05.tar.gz -C /var/www/smartcard-studio/shared/storage/app
```

## 9. Monitoring

- `GET /up` returns 200 when the app boots: point your uptime monitor at it.
- Errors go to `storage/logs/laravel-YYYY-MM-DD.log` (`LOG_CHANNEL=daily`).
- Failed jobs: `php artisan queue:failed`; retry with `php artisan queue:retry all`.
- Disk: exports and font caches live in `storage/app/private`; uploads in
  `storage/app/public`.

## 10. Performance

- `php artisan optimize` (run by the deploy script) caches config, routes, events and
  views. Run `php artisan optimize:clear` if you edit `.env` by hand, then `optimize` again.
- Card previews are cached per design version, so lists stay fast with many designs.
- Redis for cache, sessions and queues keeps database load low.
- Static assets under `/build` are fingerprinted and cached for a year by Nginx.
- Add workers (`numprocs`) if exports queue up; each running export uses one Chrome
  process.
- Lazy loading (N+1 queries) throws in development and tests, so new code can't
  silently add per-row queries; production never throws for this.

## 11. Security checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`
- [ ] HTTPS with `SESSION_SECURE_COOKIE=true`; `SECURITY_HSTS=true` once HTTPS-only
- [ ] No demo users (`SEED_DEMO_DATA` unset); first admin created by hand
- [ ] `.env` readable only by the deploy user and `www-data` (`chmod 640`)
- [ ] Nginx serves only `public/`; PHP never runs under `/storage`
- [ ] Database user limited to the app database; MySQL and Redis not reachable from outside
- [ ] Supervisor worker and cron run as `www-data`, not root
- [ ] Backups copied off the server and a restore tested
- [ ] `composer audit` and `npm audit` clean (CI runs both on every pull request)

What the application already does is listed under
[Security notes](../README.md#security-notes) in the README.

## Troubleshooting

| Symptom | Fix |
| ------- | --- |
| PNG/PDF exports disabled | Chrome not found: install it or set `CHROME_PATH` |
| Exports fail with "No usable sandbox" | Set `CHROME_NO_SANDBOX=true` |
| Fonts missing in exported PDFs | The server could not reach Google Fonts: allow outbound HTTPS to `fonts.googleapis.com` and `fonts.gstatic.com` (fonts are cached after the first download) |
| QR codes point at `localhost` | `APP_URL` was wrong when they were created: fix it, `php artisan optimize`, and re-export |
| Uploads fail with 413 | Raise `client_max_body_size` (Nginx) and `upload_max_filesize`/`post_max_size` (PHP) |
| Changes to `.env` ignored | `php artisan optimize:clear && php artisan optimize` |
