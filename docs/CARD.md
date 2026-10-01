# Card architecture

The member card is an **identity credential**. It never stores money, balances or personal data.
Every use is decided by the server, so freezing, reporting lost or revoking takes effect everywhere
immediately.

```
RFID/NFC card ──▶ Terminal (reader) ──▶ API ──▶ resolve card ──▶ check status, holder, terminal ──▶ service decision
```

## Identifiers are separate

| Identifier | Where | Stored as |
| --- | --- | --- |
| User ID | Account | ULID |
| Card ID | `cards.id` | ULID (never printed) |
| Card number | Printed on the card (12 digits, Luhn) | HMAC-SHA256 + last 4 digits |
| Chip UID | Read from the chip by a reader | HMAC-SHA256 only |
| Activation code | Card mailer (`XXXXX-XXXXX`, ~50 bits) | HMAC-SHA256, single use, expires in 90 days |
| Wallet ID / Transaction ID | Future wallet | Separate tables and IDs |

HMACs use `CARD_HMAC_KEY` with domain separation (`chip-uid:`, `card-number:`, `activation-code:`),
so a database leak alone doesn't reveal or allow cloning any value. Card numbers are deliberately
not 16 digits, so they can't be confused with payment card numbers.

## Lifecycle

```
                 issue (staff)                 link (member: code + last 4)
 (blank card) ─────────────────▶ pending_activation ─────────────────────────▶ active ◀──unfreeze── frozen
                                                                                 │   └──freeze──────▶ │
                                                                                 └──report lost──▶ lost ◀┘ (permanent)
 any state ──revoke (staff)──▶ revoked (permanent)
 old card ──when its replacement is activated──▶ replaced (permanent)
```

- Every account gets a **digital card** at registration (freeze/unfreeze only).
- Staff issue **physical cards** by tapping a blank card on a reader to capture its UID, optionally
  pre-assigning it to a member. The card number and activation code are shown once to print.
- Members link a card with the activation code **and** the last four digits; all failures look the
  same, attempts are rate limited (5/hour) and audited.
- Report lost is irreversible and automatically requests a replacement; staff see replacement
  requests and issue a new card that retires the old one when activated.
- Every transition locks the row (`SELECT … FOR UPDATE`), writes a `card_events` row and an audit
  entry, and notifies the holder (security notification; email for sensitive changes).

## Why the UID alone is not trusted

Most cheap cards (e.g. MIFARE Classic, basic NTAG) expose a static UID that can be cloned.
`CardService::resolveChip()` therefore only *identifies* a card; it returns a card only when it is
active and its holder is active. It must never be the sole basis for payments or access.

## Phase 4: terminals and cryptographic cards

Planned design (hardware-agnostic):

- **Terminal registry**: `terminals (id, organization_id, location, type, status, credential_version,
  last_seen_at)`. A terminal authenticates with its own credential (mTLS client certificate or a
  signed-request key), separate from user auth, revocable instantly, and rate limited.
- **Card authentication**: use cards with cryptographic support — NTAG 424 DNA (AES-128 SUN/SDM
  dynamic messages) or MIFARE DESFire EV3 (mutual authentication). The terminal forwards the
  card's dynamic cryptogram; the API verifies it with per-card diversified keys held server-side
  (later in an HSM/KMS). Replayed cryptograms are rejected using the card's counter.
- **Transport-agnostic**: USB, Bluetooth, Wi-Fi readers and phone NFC all produce the same request:
  `{terminal_id, card_cryptogram|uid, counter, nonce, timestamp}`, signed by the terminal.
- **Offline**: non-financial uses (e.g. attendance) may queue signed events locally and sync later
  with server-side de-duplication. Financial transactions are never approved offline unless the
  financial architecture explicitly supports secure offline authorisation.
- **Payments (Phase 7)**: card → terminal → API → transaction authorisation → ledger → regulated
  payment provider, with idempotency keys and step-up rules for higher amounts.
