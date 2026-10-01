# Architecture

## Principles

- **Separate system.** Own code, database, auth and deployment. The school system is never queried directly.
- **Modular monolith first.** One Laravel application, divided into modules with clear boundaries, so a
  module (e.g. wallet) can be extracted into its own service later without rewriting the others.
- **The API is the authority.** Every rule — visibility, privacy, blocking, permissions — is enforced
  server-side. Clients (web today, Flutter next) only present what the API returns.
- **Simple, then expand.** No microservices, no message bus, no recommendation engine until needed.

## System overview

```
 Browser ──HTTPS──▶ Next.js web (BFF)                    Flutter app (next)
                     │  httpOnly session cookie            │  bearer token in secure storage
                     │  CSRF origin check, CSP nonces      │
                     └──────────── server-to-server ───────┴──▶ Laravel API  /api/v1
                                                                 │
                                    ┌────────────────────────────┼─────────────────────┐
                                    ▼                            ▼                     ▼
                              PostgreSQL 16                 Redis 7               Object storage
                        (all relational data,        (cache, rate limits,     (avatars, post photos —
                         append-only audit log)        queues)                  private, signed URLs)
```

Browsers never call the API directly and never see an API token. The web server's
`/api/proxy/*` route attaches the token from an httpOnly cookie. CORS on the API allows no browser
origins by default.

## Backend modules (`backend/app/Modules`)

| Module | Owns | Notes |
| --- | --- | --- |
| Identity | users, profiles, private_profiles, privacy_settings, personal_access_tokens | Auth, sessions, account lifecycle |
| Social | posts, post_media, comments, reactions, follows, blocks | `Post::scopeVisibleTo()` is the single visibility rule |
| Moderation | reports, moderation_actions | Report intake and moderator actions |
| Notifications | notifications | Social activity and security alerts |
| Card | cards, card_events | Card credential lifecycle (see [CARD.md](CARD.md)) |
| Admin | roles, role_permissions, role_user | RBAC, system health, admin endpoints |
| Audit | audit_logs | Append-only trail used by every module |

Each module has its own `Models`, `Services`, `Http/{Controllers,Requests,Resources}`, `Enums` and
`routes.php`, mounted under `/api/v1` by `routes/api.php`. Cross-cutting HTTP concerns live in
`app/Support` (request IDs, security headers, active-account check, image processing, signed media URLs).

**Boundary rules.** Modules talk through services, not each other's tables, except for read-only
joins needed for visibility (e.g. Social reading `blocks`). Future financial modules (wallet,
ledger) must not be written to by any other module: they will expose services/APIs, and the social
code will only *request* operations.

## Data model

All primary keys are ULIDs (time-ordered, unguessable, safe to expose). Users are never exposed by
ID in public responses — public identity is the `@username`.

```
users (auth only: email, password hash, status)
 ├─1:1─ profiles           public: username, display_name, bio, location, avatar_path
 ├─1:1─ private_profiles   legal_name, phone, date_of_birth — encrypted at rest (APP_KEY)
 ├─1:1─ privacy_settings   searchable, show_location, who_can_follow, who_can_message, default audience
 ├─1:n─ personal_access_tokens   one per device session: name, ip, user agent, last used, expiry
 ├─1:n─ posts ─1:n─ post_media (metadata only; bytes in object storage)
 │        └─1:n─ comments, reactions (post_id, user_id)
 ├─n:n─ follows (follower_id, followee_id)      CHECK follower ≠ followee
 ├─n:n─ blocks  (blocker_id, blocked_id)        CHECK blocker ≠ blocked
 ├─1:n─ cards ─1:n─ card_events                 card id ≠ user id ≠ chip UID
 └─n:n─ roles (role_user) ─ role_permissions
reports (polymorphic: user|post|comment)  ─ moderation_actions
notifications (Laravel standard, actor IDs only — names resolved at read time)
audit_logs (append-only, no foreign keys so history survives deletions)
```

Polymorphic columns store stable aliases (`user`, `post`, `comment`, `card`, `report`) via an
enforced morph map, never PHP class names. Counters (`comments_count`, `reactions_count`) are
denormalised for cheap feeds and recalculated when bulk changes happen (account deletion, moderation).

## Request flow (web)

1. `web/src/proxy.ts` generates a CSP nonce and redirects to `/login` when no session cookie exists
   (an optimistic check only).
2. Server layout `(app)/layout.tsx` calls `GET /api/v1/me` with the token; an invalid token sends the
   user to sign in.
3. Client components call `/api/proxy/<path>`; the route handler checks the request origin for
   mutations, forwards the client IP (from the trusted hop) and user agent, and attaches the token.
4. Laravel: request ID → security headers → JSON → Sanctum token → active account → rate limit →
   (verified email) → permission gate → controller → policy/scope → resource.

## Scaling path

- Stateless API and web servers scale horizontally behind a load balancer; sessions are tokens in
  PostgreSQL, rate-limit and cache state in Redis.
- Media is on S3-compatible storage; add a CDN in front of the bucket.
- Read replicas for feed/profile reads when needed; feeds already use cursor pagination on indexed ULIDs.
- Real-time (messaging, order tracking) will use Laravel Reverb (WebSockets) for those features only.
- Extraction candidates, in order: notifications delivery, media processing, wallet/ledger (which
  will start as its own service and database because of its security and regulatory profile).

## School system integration

Planned for Phase 9. Constraints that the design already respects:

- No shared database and no direct queries in either direction.
- The school system will be a registered **integration client** with its own credentials and an
  allow-list of scopes, e.g. `identity.link` (link a school account to a platform account the
  student explicitly approves) and `card.issue` (request a platform card for a linked student).
- Data crossing the boundary is minimal and explicit: by default only "this platform user is a
  verified student of school X". Grades, attendance, teacher records, discipline and private school
  data never cross.
- Linking is consented by the student (and guardian where required) from inside the platform app;
  it can be revoked, and revocation is audited.
- All integration calls are audited with `actor_type = integration`.
