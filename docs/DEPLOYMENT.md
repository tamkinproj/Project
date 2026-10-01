# Deployment and operations

## Production topology

```
Internet ─▶ Load balancer / TLS (HTTPS only, HTTP→HTTPS redirect)
              ├─▶ web  (Next.js, `node .next/standalone/server.js`, ≥2 instances)
              └─▶ api  (Laravel on PHP-FPM + nginx, ≥2 instances)  ◀── web calls it on the private network
                    ├─▶ PostgreSQL 16 (managed, private network, automated backups, PITR)
                    ├─▶ Redis 7 (managed, private, password/TLS)
                    ├─▶ S3-compatible bucket (private) + optional CDN
                    └─▶ queue workers: `php artisan queue:work redis --tries=3` (supervisor/systemd)
                        scheduler: `php artisan schedule:run` every minute
```

The API does not need to be publicly reachable except for `/media/*` when media is served from
local disk. With S3, expose only the web app publicly.

## Backend environment (`backend/.env`)

| Variable | Production value / notes |
| --- | --- |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_KEY` | `php artisan key:generate --show`; store in a secrets manager and back it up — losing it makes private profiles unreadable |
| `APP_URL` | Public API URL (used for signed media URLs) |
| `FRONTEND_URL` | Public web URL (email links) |
| `TRUSTED_PROXIES` | IPs/CIDRs of the load balancer and web servers |
| `CORS_ALLOWED_ORIGINS` | Empty unless a browser client must call the API directly |
| `DB_*` | Dedicated DB user with only the privileges the app needs; TLS to the database |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` / `redis` |
| `AUTH_TOKEN_TTL_MINUTES` | Session lifetime (default 30 days) |
| `CARD_HMAC_KEY` | 32 random bytes, base64; secrets manager |
| `MEDIA_DISK` | `media-s3` with the `AWS_*` variables (bucket private, block public access) |
| `MAIL_*` | Transactional email provider |

PHP: `expose_php=Off`, OPcache on. Run `php artisan config:cache route:cache event:cache` on deploy.

## Web environment (`web/.env.production`)

| Variable | Notes |
| --- | --- |
| `API_URL` | Internal API URL |
| `APP_ORIGIN` | Public `https://` origin — enables Secure `__Host-` cookies, HSTS and `upgrade-insecure-requests` |
| `TRUSTED_PROXY_HOPS` | Number of proxies in front of the web server that append to `X-Forwarded-For` (usually 1) |
| `MEDIA_ORIGINS` | Origin(s) serving media images (bucket/CDN or API) |
| `NEXT_PUBLIC_APP_NAME` | Display name |

## Deploy steps

```bash
# API
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolesSeeder --force     # idempotent
php artisan config:cache && php artisan route:cache
php artisan queue:restart

# Web
npm ci && npm run build      # output: .next/standalone
```

Zero-downtime: run migrations that are backwards-compatible with the previous release first, then
roll instances.

## Monitoring

- Liveness: `GET /up` (API), `GET /login` (web). Readiness and dependency status: admin
  **System health** (`/api/v1/admin/system/health`): database, Redis, queue backlog and failed
  jobs, storage write test.
- Logs: set `LOG_CHANNEL=stderr` and ship JSON to your log platform; every line carries the
  `request_id` that also appears in responses and audit entries.
- Alert on: 5xx rate, failed jobs > 0, queue backlog, repeated `auth.login_failed` / `card.activation_failed`,
  any `moderation.suspend_user` or `admin.role_granted` outside business hours.
- Add error tracking (e.g. Sentry) and APM before public launch.

## Backups and disaster recovery

| What | How | Target |
| --- | --- | --- |
| PostgreSQL | Managed automated backups + point-in-time recovery (WAL), daily logical dump (`pg_dump -Fc`) copied to another region/account | RPO ≤ 15 min, RTO ≤ 4 h |
| Object storage | Bucket versioning + cross-region replication | Same region failure tolerated |
| Secrets (`APP_KEY`, `CARD_HMAC_KEY`) | Secrets manager with versioning; offline escrow copy | Without them encrypted data and card hashes are unusable |
| Redis | Not backed up (cache, rate limits, queues); jobs are retryable | — |

**Restore test (monthly):** restore the latest dump into a staging database, run
`php artisan migrate --pretend` against it, start the API against the restored data with production
secrets from escrow, sign in with a test account and open a card page. Record the time taken.

**Incident steps:** revoke compromised credentials (`me/sessions`, admin suspension, card revoke),
rotate secrets, review `audit_logs` by `request_id`/IP, notify affected users as required by law.

## Local development

See the root [README](../README.md). `docker compose up -d` provides PostgreSQL, Redis and Mailpit.
