# Ummah — Muslim Digital Ecosystem

One simple app for a connected Muslim community: social, discovery, a member card, and — over time —
masjids, halal places, organizations, education, charity, shop and wallet. Many services, each one simple.

> "Ummah" is a working name, configurable through `APP_NAME` / `NEXT_PUBLIC_APP_NAME`.

This is a **separate system** from the existing school management system. It has its own codebase,
database, authentication and deployment. The two may integrate later only through an explicit, scoped
API (see [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md#school-system-integration)).

## What exists today (Phase 1 foundation + first social features)

| Area | Included |
| --- | --- |
| Identity | Register, sign in/out, email verification, password reset, per-device sessions, sign out other devices, deactivate, permanent deletion |
| Profiles | Public profile (display name, @username, photo, bio, optional location) kept separate from encrypted private details (legal name, phone, date of birth) |
| Privacy | Searchable or not, show location, who can follow, default post audience — enforced by the API |
| Social | Chronological feed (following / everyone), text + photo posts, edit/delete, likes, comments, follow, block, report |
| Notifications | Social activity and clearly distinguished security alerts (new sign-in, password change, card events, lockouts) |
| Card | A digital member card for every account; physical RFID/NFC cards issued by staff, linked with a one-time code, frozen, reported lost, replaced, revoked |
| Administration | Role-based admin: overview, system health, reports queue, user management and suspension, role assignment, card issuing, append-only audit log |

Not built yet, by design: wallet and payments, shop, delivery, messaging, groups, discovery listings,
Flutter app. See the roadmap below.

## Repository layout

```
backend/   Laravel 13 API (PHP 8.3, PostgreSQL, Redis) — modular monolith in app/Modules/*
web/       Next.js 16 + TypeScript web app for members and administrators
docs/      Architecture, security, API, card, deployment documentation
docker-compose.yml   Local PostgreSQL, Redis and Mailpit
```

## Local development

Requirements: PHP 8.3 (pgsql, redis, gd, intl, mbstring), Composer, Node 20+, Docker (or local
PostgreSQL 16 + Redis 7).

```bash
docker compose up -d                      # PostgreSQL :5432, Redis :6379, Mailpit UI http://localhost:8025

cd backend
cp .env.example .env
composer install
php artisan key:generate
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"   # paste the output into CARD_HMAC_KEY= in .env
php artisan migrate --seed                # schema + default admin roles
php artisan serve                         # API on http://localhost:8000
php artisan queue:work                    # in another terminal: emails and notifications

cd ../web
cp .env.example .env.local
npm install
npm run dev                               # web on http://localhost:3000
```

Create an account in the web app, then make it the first administrator:

```bash
cd backend && php artisan admin:grant you@example.com super_admin
```

Verification and password-reset emails arrive in Mailpit (http://localhost:8025).

## Tests and checks

```bash
cd backend && php artisan test            # 85 feature/unit tests against PostgreSQL (database: ecosystem_test)
cd backend && ./vendor/bin/pint --test    # code style
cd web && npm run lint && npx tsc --noEmit && npm run build
```

The test suite covers authentication, lockout, expired/invalid/revoked tokens, IDOR attempts on
sessions, posts, comments, notifications and cards, the post visibility matrix, blocking, privacy
leaks, RBAC escalation, append-only audit logs, card activation abuse and lifecycle, and upload
sanitisation. CI runs all of this on every push (`.github/workflows/ci.yml`).

## Documentation

- [Architecture](docs/ARCHITECTURE.md) — modules, data model, request flow, scaling path, school integration
- [Security model](docs/SECURITY.md) — threat model and every control in place
- [API reference](docs/API.md) — all `/api/v1` endpoints
- [Card architecture](docs/CARD.md) — RFID/NFC design, lifecycle, terminals (Phase 4)
- [Deployment & operations](docs/DEPLOYMENT.md) — environment variables, production setup, backups, disaster recovery

## Roadmap

1. ✅ Foundation: identity, profiles, security, API, web, admin
2. ◐ Social: feed, posts, comments, reactions, follow, notifications done — groups and messaging next
3. Discover: masjids, halal restaurants, organizations, education, tourism
4. Card readers: terminal registry, terminal authentication, cryptographic card authentication
5. Shop and merchants
6. Charity campaigns
7. Wallet: ledger and regulated payment provider
8. Delivery
9. School system integration

The Flutter mobile app consumes the same API; it is the next client to be built.
