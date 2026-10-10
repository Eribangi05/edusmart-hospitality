# Architecture

> Implementation notes (see README for current status): local DB encryption (SQLCipher) and FCM push described below are **planned, not built**; the app polls via WorkManager. Tokens are Sanctum personal access tokens (15 min) with an app-level rotating refresh token. The generated API contract is `api-spec/openapi.json`. The "shop" module reuses POS + inventory.

(Original Phase 0 design follows.)

## 1. System overview

```
 Android app (Kotlin/Compose, Room, WorkManager)      Browser (Livewire + Tailwind)
        │  HTTPS /api/v1  (Sanctum token, device-bound)        │  session + 2FA
        ▼                                                      ▼
   ┌───────────────────────── Laravel backend ─────────────────────────┐
   │ API  · Tenant console · Super-admin console · Webhooks            │
   │ Queue workers (Redis) · Scheduler (night audit, reports, billing) │
   └───────┬───────────────┬───────────────┬───────────────┬──────────┘
        MySQL 8          Redis         Object storage     FCM push
                                      (ID photos, files)
                                  MTN MoMo API (collections, webhooks)
```

Hosts: `hospitality.edusmartconsult.com` (web), `api.hospitality.edusmartconsult.com` (API). The static site edusmartconsult.com only links to these.

## 2. Core ERD (platform)

- **tenants** (id uuid, name, slug, plan_id, status [trial|active|grace|suspended], trial_ends_at, country=RW, currency=RWF, locale)
- **properties** (id, tenant_id, name, type [hotel|restaurant|bar|shop], address, timezone=Africa/Kigali)
- **users** (id, tenant_id, name, email, phone, password, pin_hash, locale, totp_secret, is_active)
- **roles** (id, tenant_id, name, is_system) / **role_permissions** (role_id, module, action) / **user_roles** (user_id, role_id, property_id nullable)
- **modules** (key: rooms|restaurant|bar|shop|inventory|staff|housekeeping|finance) / **tenant_modules** (tenant_id, module_key, enabled_at)
- **plans**, **plan_modules**, **subscriptions**, **invoices**, **payments**
- **devices** (id, user_id, tenant_id, name, platform, app_version, push_token, last_seen_at, revoked_at)
- **audit_logs** (id, tenant_id, user_id, device_id, action, entity, entity_id, before, after, ip, created_at) append-only

Every business table: `id uuid` (client-generated allowed), `tenant_id`, `property_id` where relevant, `created_at`, `updated_at`, `deleted_at`, `version` (int, incremented on each change).

## 3. Auth flows

1. **Login**: email+password (rate-limited) → if user has 2FA: `202 {challenge_id}` → `POST /auth/2fa` with TOTP → token pair.
2. **Tokens**: short-lived access token (15 min) + rotating refresh token, both bound to a `device_id`. Refresh reuse detection revokes the whole device.
3. **Shared tablets**: after first login, a user may unlock with a 4–6 digit PIN (server-verified when online, locally throttled and Keystore-protected when offline; PIN cannot grant permissions beyond the user's roles).
4. **Remote revoke**: owner revokes a device in the console → next API call returns `401 device_revoked`; app wipes local data and tokens.
5. **Tenant derivation**: tenant is read from the token, never from request input.

## 4. Permission matrix

Module × action (view, create, edit, delete) plus a few special actions: `pos.void`, `pos.discount`, `pos.refund`, `rooms.rate_override`, `finance.export`. Deny by default. Seed roles: owner (all), manager, receptionist, cashier, waiter, bartender, kitchen, storekeeper, housekeeper, accountant. Owners edit the matrix in the web console. `/me/context` returns enabled modules and the user's effective permissions; the app builds its navigation from it, and the server re-checks every request.

## 5. Offline-sync protocol

- **IDs**: every entity created on a device gets a UUIDv7 client-side. Human-facing numbers (order #, folio #, receipt #) are issued per device with a device prefix (e.g. `D3-0042`) so they never collide offline.
- **Outbox**: each local change is an outbox row `{id, entity, op, payload, idempotency_key, base_version, created_at}`. WorkManager pushes in batches (`POST /sync/push`, max 100 items) in creation order.
- **Push response**: per-item result `applied | duplicate | conflict | rejected(reason)`. The app marks rows done, or surfaces conflicts and rejections to the user.
- **Pull**: `GET /sync/pull?cursor=...&entities=...` returns changes ordered by a monotonic per-tenant change sequence, plus tombstones; paginated. Cursor stored per entity group.
- **Idempotency**: server stores `(tenant_id, idempotency_key)` for 30 days; replays return the original result.
- **Conflict rules**

| Entity | Rule |
|---|---|
| Menu, prices, rates, room types | Server wins (edited in web console) |
| Room availability/reservations | Server wins; a queued reservation or check-in that now conflicts is rejected and shown to the user |
| Orders, order lines, payments, shifts, folio entries, stock movements | Append-only; no conflicts. Corrections are new void/adjust records |
| Housekeeping tasks, maintenance tickets | Field-level merge; status moves forward only (pending → in progress → done → inspected) |
| Attendance | Append-only clock events; server flags anomalies |
| Guest profiles | Last-writer-wins per field using `version` |

- **Time**: devices send local time and UTC; the server records receive time and flags large clock skew.
- **Offline limits**: card/MoMo payments need connectivity to confirm. Offline mode accepts cash and "charge to room" only; MoMo shows as pending until confirmed.

## 6. MTN MoMo integration

Use the MoMo Collections API (request-to-pay) in the sandbox first. Flow: POS creates a payment intent with an idempotency key → backend calls request-to-pay with the customer's phone number → customer approves on their phone → MTN callback (verified, idempotent) or polling marks the payment `successful | failed | pending`. Same integration collects SaaS subscription fees. Production access requires MTN onboarding and approval, so credentials are environment variables and the code runs against the sandbox until you have them. Amounts are whole RWF.

## 7. Threat model (summary)

| Threat | Mitigation |
|---|---|
| Cross-tenant data access | Global scope + token-derived tenant + isolation test suite in CI |
| Stolen/lost phone | Device-bound tokens, remote revoke, Keystore storage, biometric/PIN lock, local DB encryption (SQLCipher) |
| Replayed payment/order requests | Idempotency keys, append-only ledgers |
| Malicious offline payload | Server revalidates every pushed item against current permissions, prices and stock; never trusts client totals |
| Cashier fraud | Void/discount permissions with manager PIN, audit log, shift cash-up reconciliation |
| Webhook forgery | Signature/secret verification, source checks, idempotent handling |
| API abuse | Rate limits per user/device/IP, payload size limits, WAF at Nginx |
| Data loss | Daily encrypted backups, tested restore, soft deletes |

## 8. Decisions and open questions

Decided: Laravel 11 / MySQL 8 / Redis; native Android (Kotlin, Compose); package `com.edusmartconsult.hospitality`; RWF stored as whole francs; MTN MoMo.

Open (need your input):
1. Hosting provider and region for the VPS (latency in Kigali matters for sync).
2. Tax: VAT rate and whether receipts must integrate with the Rwanda Revenue Authority EBM. Verify with RRA or an accountant before launch.
3. Receipt printer model(s) the pilot uses.
4. Pilot customer name and whether they have reliable internet.
5. MTN MoMo developer account status.
6. Whether Airtel Money should follow MTN.
