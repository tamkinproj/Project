# Security model

Security is enforced by the API. Hiding something in a client is never the control.

## Authentication and sessions

| Control | Implementation |
| --- | --- |
| Password storage | bcrypt (Laravel `hashed` cast), rehashed on login when cost changes |
| Password policy | ≥10 chars, ≤128; in production also checked against known breaches (`uncompromised()`) |
| Tokens | Sanctum personal access tokens; only the SHA-256 hash is stored. One token = one device session with name, IP, user agent, last use and a 30-day expiry |
| Web sessions | Token held in an httpOnly, SameSite=Lax cookie (`__Host-` prefixed and Secure over HTTPS). Browser JavaScript never sees it |
| Login throttling | 5/min per email+IP, 30/min per IP, and an account lock after 10 failures from any IP (15 min) with a security notification |
| User enumeration | Same error and similar timing for unknown email vs. wrong password; password reset always returns the same message |
| Email verification | Signed, expiring links; creating content, following and linking cards require a verified email |
| Password reset / change | Reset revokes every session; change revokes all other sessions; both send an email alert |
| Session management | Users list devices, sign out one, or sign out all others |
| Account states | `active`, `deactivated` (user, reversible by signing in), `suspended` (moderator; tokens revoked and refused) |
| Account deletion | Password + typed confirmation; erases profile, private data, content and media; revokes cards; recorded in the audit log |

MFA and passkeys are planned before the wallet launches; wallet operations will require step-up auth.

## Authorization

- Every protected route: `auth:sanctum` → `active` (account must be active) → route-specific checks.
- **Ownership by query scope.** Owner-only resources are looked up through the user's own relation
  (`$user->cards()->findOrFail($id)`), so someone else's ID returns 404, not 403 — no existence leak.
- **Visibility in one place.** `Post::scopeVisibleTo()` applies moderation status, author account
  status, blocks in both directions and audience (public / followers / only me) for every read path.
- **Privacy settings** (searchability, location, follow policy) are applied in queries and resources.
- **RBAC.** Permissions are an enum in code; roles grant subsets. Each admin route is gated by
  `can:<permission>`. Only super admins grant the super-admin role; nobody changes their own roles;
  the last super admin can't be removed; moderators can't suspend administrators.
- Administrators can't see private profile fields at all.

## Input, output and files

- All input validated by form requests/validators; enums validated with `Rule::enum`.
- ULID route patterns reject malformed IDs before they reach the database.
- Eloquent query bindings everywhere; LIKE wildcards in search terms are escaped.
- Text is stored as plain text with control characters stripped; clients render it escaped (React).
- Images: type and size validated, then **decoded and re-encoded to WebP** with GD — this strips
  EXIF/GPS location data and discards any non-image payload. Max 2048 px, 5 MB, 4 per post.
- Media is private; clients receive short-lived signed URLs.

## Transport and headers

- API: `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`,
  `Permissions-Policy`, `CSP: default-src 'none'`, `Cache-Control: no-store` on all API responses,
  HSTS over HTTPS, request IDs on every response.
- Web: per-request CSP nonce with `strict-dynamic`, `frame-ancestors 'none'`, `object-src 'none'`,
  `form-action 'self'`, HSTS over HTTPS, COOP, no `X-Powered-By`.
- CSRF: cookie is SameSite=Lax **and** every mutating proxy request must come from the app's origin.
- CORS: no browser origins allowed by default (`CORS_ALLOWED_ORIGINS`).
- Client IPs: the web proxy takes the IP from the configured number of trusted hops
  (`TRUSTED_PROXY_HOPS`); the API only trusts `X-Forwarded-For` from `TRUSTED_PROXIES`.
- 404s never reveal internal class names.

## Secrets and keys

| Secret | Purpose | Rotation |
| --- | --- | --- |
| `APP_KEY` | Encrypts private profile fields, signs URLs | Use Laravel's `APP_PREVIOUS_KEYS` to rotate without data loss |
| `CARD_HMAC_KEY` | Keyed hashing of chip UIDs, card numbers, activation codes | Rotation requires re-enrolling cards; plan with a key version (`credential_version`) |
| DB / Redis / SMTP / S3 credentials | Infrastructure | Rotate via your secrets manager |

Secrets live only in environment variables / a secrets manager. `.env*` files are git-ignored
(`.env.example` files contain no secrets).

## Audit logging

`audit_logs` records sign-ins (and failures for existing accounts), registrations, verification,
password resets/changes, session revocations, private-profile changes (field names only),
username changes, blocks, every card transition and failed activation, every moderation action,
and role changes — with actor, subject, IP, user agent and request ID. The table is append-only:
the model refuses updates/deletes and a PostgreSQL trigger rejects `UPDATE`/`DELETE`. Metadata
never contains passwords, tokens, codes, chip UIDs or personal values.

## Abuse controls

Rate limits (Redis): API 120/min, registration 10/h per IP, password reset 5/15 min, content 20/min,
interactions 60/min, reports 20/h, card linking 5/h, sensitive account actions 10/15 min.
Moderation: reports (one open report per person per target), hide/restore content, suspend/restore
accounts, full moderation history.

## Known gaps / next steps

- MFA (TOTP / passkeys) and step-up auth for sensitive actions.
- Malware scanning for future document uploads (images are already re-encoded).
- Email change flow with confirmation to both addresses.
- Centralised error tracking (e.g. Sentry) and alerting on security events.
- Terminal authentication and cryptographic card authentication (Phase 4, see CARD.md).
