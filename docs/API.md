# API reference — `/api/v1`

JSON only. Authenticate with `Authorization: Bearer <token>` (the web app does this server-side).
Errors: `{ "message": "...", "errors": { "field": ["..."] } }` with 401 (unauthenticated), 403
(forbidden / unverified email / inactive account, `code: account_inactive`), 404 (not found or not
visible to you), 409 (invalid state transition), 422 (validation), 429 (rate limited).
Lists use cursor pagination (`meta.next_cursor`, pass back as `?cursor=`) except admin tables
(`?page=`, `meta.current_page|last_page|total`). Every response carries `X-Request-Id`.

Legend: 🔓 public · 🔑 signed in · ✉️ verified email · 🛡 permission

## Auth & account

| Method | Path | | Notes |
| --- | --- | --- | --- |
| POST | `auth/register` | 🔓 | `email, password, password_confirmation, username, display_name, device_name?` → `{token, user}` |
| POST | `auth/login` | 🔓 | `email, password, device_name?` → `{token, user}` |
| POST | `auth/logout` | 🔑 | Revokes the current token |
| POST | `auth/forgot-password` | 🔓 | `email` — always the same response |
| POST | `auth/reset-password` | 🔓 | `token, email, password, password_confirmation` — revokes all sessions |
| GET | `auth/email/verify/{id}/{hash}?expires&signature` | 🔓 | Signed link from the email |
| POST | `auth/email/resend` | 🔑 | |
| GET | `me` | 🔑 | Own account: email, verified, profile, privacy, permissions |
| PATCH | `me/profile` | 🔑 | `username?, display_name?, bio?, location?` |
| POST / DELETE | `me/avatar` | 🔑 | multipart `avatar` (JPEG/PNG/WebP ≤5 MB) |
| GET / PATCH | `me/private-profile` | 🔑 | `legal_name?, phone? (E.164), date_of_birth? (Y-m-d)` — encrypted, owner only |
| GET / PATCH | `me/privacy` | 🔑 | `profile_searchable, show_location, who_can_follow, who_can_message, default_post_visibility` |
| PUT | `me/password` | 🔑 | `current_password, password, password_confirmation` — signs out other devices |
| GET | `me/sessions` | 🔑 | Devices with `is_current` |
| DELETE | `me/sessions/{id}` | 🔑 | Sign out one device |
| DELETE | `me/sessions` | 🔑 | Sign out all other devices |
| POST | `me/deactivate` | 🔑 | `password` |
| DELETE | `me` | 🔑 | `password, confirmation: "DELETE"` — permanent |

## Social

| Method | Path | | Notes |
| --- | --- | --- | --- |
| GET | `feed?scope=following\|everyone` | 🔑 | Chronological |
| POST | `posts` | ✉️ | multipart `body?, visibility? (public\|followers\|only_me), images[] (≤4)` |
| GET / PATCH / DELETE | `posts/{id}` | 🔑 / ✉️ / 🔑 | Edit and delete: author only |
| PUT / DELETE | `posts/{id}/reaction` | ✉️ | `type: like` — idempotent |
| GET / POST | `posts/{id}/comments` | 🔑 / ✉️ | `body` |
| DELETE | `comments/{id}` | 🔑 | Comment author or post author |
| GET | `profiles/search?q=` | 🔑 | Respects searchability and blocks |
| GET | `profiles/{username}` | 🔑 | Public profile + counts + relationship |
| GET | `profiles/{username}/posts\|followers\|following` | 🔑 | |
| POST / DELETE | `profiles/{username}/follow` | ✉️ | 403 if they don't accept followers |
| POST / DELETE | `profiles/{username}/block` | 🔑 | Blocking removes follows both ways |
| GET | `me/blocks` | 🔑 | |
| POST | `reports` | 🔑 | `target_type (user\|post\|comment), target_id (username for users), reason, details?` |

## Notifications

| Method | Path | | Notes |
| --- | --- | --- | --- |
| GET | `notifications?category=social\|security` | 🔑 | `category, event, message, actor, post_id, read_at` |
| GET | `notifications/unread-count` | 🔑 | `{unread, security_unread}` |
| POST | `notifications/{id}/read`, `notifications/read-all` | 🔑 | |

## Cards

| Method | Path | | Notes |
| --- | --- | --- | --- |
| GET | `me/cards` | 🔑 | Digital card first, then physical; includes allowed `actions` |
| POST | `me/cards/link` | ✉️ | `activation_code, last4` — 5 attempts/hour |
| POST | `me/cards/{id}/freeze\|unfreeze\|report-lost\|request-replacement` | 🔑 | 409 on invalid transition |
| GET | `me/cards/{id}/events` | 🔑 | Card history |

## Administration (`admin/*`)

| Method | Path | Permission |
| --- | --- | --- |
| GET | `admin/overview` | any admin role |
| GET | `admin/system/health` | `system.health` |
| GET | `admin/users?q&status`, `admin/users/{id}` | `users.view` |
| POST | `admin/users/{id}/suspend` (`reason`), `.../unsuspend` | `users.suspend` |
| GET | `admin/roles` · POST `admin/users/{id}/roles` (`role`) · DELETE `admin/users/{id}/roles/{slug}` | `roles.manage` |
| GET | `admin/reports?status`, `admin/reports/{id}` · POST `.../resolve` (`decision: dismiss\|hide_content\|suspend_user, note?`) | `reports.review` |
| POST | `admin/posts/{id}/hide\|restore`, `admin/comments/{id}/hide\|restore` · GET `admin/moderation/actions` | `content.moderate` |
| GET | `admin/cards?status&type&replacement_requested&q`, `admin/cards/{id}` · POST `.../revoke` (`reason`) | `cards.manage` |
| POST | `admin/cards` (`chip_uid, username?, replaces_card_id?`) → returns card number + activation code **once** | `cards.issue` |
| GET | `admin/audit-logs?action&actor_id&subject_id` | `audit.view` |

Default roles (`php artisan db:seed`): `super_admin` (all), `moderator`, `card_officer`, `auditor`.

## Health

`GET /up` (unauthenticated liveness) · `GET /api/v1/admin/system/health` (database, Redis, queue, storage).
