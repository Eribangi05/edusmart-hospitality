# Edusmart Hospitality

Multi-tenant hospitality platform for Rwanda: **hotels, guest houses, restaurants, bars, shops and tour operators** manage their business from a native **Android app** (offline-first) and a **web console**, backed by one **Laravel API**.

| Part | Path | Stack |
|---|---|---|
| Backend + web workspaces + super-admin | [`backend/`](backend) | Laravel 13, PHP 8.3, MySQL 8 (SQLite for dev/tests), Redis, Alpine.js + Tailwind |
| Android app | [`android/`](android) | Kotlin, Jetpack Compose, Room, WorkManager, OkHttp (min SDK 26) |
| API contract | [`api-spec/openapi.json`](api-spec) | generated from the routes (`php artisan api:openapi`) |
| Docs | [`docs/`](docs) | architecture, configuration, deployment, runbook, security |

Currency RWF (whole francs), languages English / Français / Kinyarwanda, payments MTN MoMo (Collections API), package `com.edusmartconsult.hospitality`, web `hospitality.edusmartconsult.com`.

## What is built

**Eight purpose-built entry points** (see [docs/WORKSPACES.md](docs/WORKSPACES.md)): a multi-page public website, split-screen sign-in, a workspace launcher, the manager console (charts, setup guide, command palette, notifications, themes), a full-screen touch **sales terminal**, a **kitchen & bar display**, a **front desk** (arrivals, rooms timeline, tours calendar, guest profiles), a mobile **staff app**, and the platform admin. Each person lands in the layout for their job.

- **Platform**: company sign-up, 30-day trial, plans, per-company module switches, users, roles with a module × action permission matrix (plus special permissions such as void / discount), TOTP 2FA, device-bound tokens with remote revoke, audit log, password reset, data export and erasure, super-admin console (tenants, plans, suspend, audited impersonation, metrics).
- **Rooms**: room types, rooms, seasonal/weekday rate plans, availability and overbooking protection, reservations, check-in/out with guest ID, folios (append-only ledger, voids not deletes), night audit, room board.
- **Restaurant / bar / shop POS**: menu with categories, tables, orders computed server-side (inclusive/exclusive VAT, service charge, discounts before tax, tips), kitchen/bar screen, split and merge bills, manager-PIN voids and discounts, shifts with cash-up, cash / MTN MoMo / charge-to-room, barcode scan, ESC/POS Bluetooth receipts.
- **Tourism**: tours, activities, transfers, rentals, spa, events and packages in one catalog; per-person (child %, group tiers), per-group, per-day/hour pricing; departures with seat capacity; bookings with participants, deposits and balances; guide/vehicle assignment with clash detection; manifests; charge to a hotel guest's room folio.
- **Inventory**: append-only stock ledger, suppliers, purchase orders and goods receipts, stocktakes, recipes that deduct ingredients on sale, low-stock alerts.
- **Staff & housekeeping**: employees, roster, clock-in/out (offline), leave, payroll export, room status, task flow (start / complete / inspect) with photos, maintenance tickets.
- **Finance & reports**: dashboard, sales by day/item/method/cashier, occupancy / ADR / RevPAR, cash reconciliation, expenses, profit, CSV export.
- **Flexibility**: every company can add **custom fields** to guests, reservations, bookings, menu items…; rename any label per language; edit tax/levy/service rates and payment methods; define roles; switch modules on/off; price services by different models. See [docs/CONFIGURATION.md](docs/CONFIGURATION.md).
- **Offline**: the Android app keeps working without a connection (orders, cash payments, tasks, clock-in, room board) and syncs through an idempotent outbox. See [docs/ANDROID.md](docs/ANDROID.md).

## Quick start

```bash
# Backend + web console
cd backend
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed
php artisan demo:seed --password='choose-a-password'     # demo lodge + restaurant + tour operator
php artisan serve                                        # http://localhost:8000  (log in as demo@edusmart.test)
php artisan test                                         # 82 tests

# Android (needs JDK 17+ and the Android SDK; `dev` flavor talks to http://10.0.2.2:8000)
cd android
echo "sdk.dir=$ANDROID_HOME" > local.properties
./gradlew :app:assembleDevDebug :app:testDevDebugUnitTest
# optional contract test against a running backend:
LIVE_API=http://localhost:8000/api/v1/ ./gradlew :app:testDevDebugUnitTest --tests '*LiveBackendTest'
```

First platform admin: `php artisan platform:create-admin you@example.com "Your Name" 'a-long-password'`, then enable 2FA at `/admin`.

## Honest status and limits

Verified in this repository: 82 backend tests (tenant isolation, permissions, rooms, POS, tourism, inventory, billing, sync, security, privacy), headless-browser runs of every console page and of the terminal, kitchen display, front desk and staff flows, 18 Android unit tests, an Android contract test against the live backend, a lint-clean debug build and an R8 release build (2.4 MB).

**Not yet verified / not built** (do these before real customers):
- The Android UI has not been run on a physical device or emulator (none in the build environment). Run through the flows on a phone and a tablet; ESC/POS printing is untested on real printers.
- MTN MoMo is implemented to MTN's published Open API but was only exercised with the built-in fake gateway. Register for the MoMo developer sandbox, then production, and test end to end (see docs/DEPLOYMENT.md). Sandbox uses EUR; production uses RWF.
- Push notifications (FCM) are not wired (needs your Firebase project); the app syncs by polling every 15 minutes and on every change.
- Local database encryption (SQLCipher) and TLS certificate pinning are not enabled (add your production pins in `network_security_config.xml`).
- Rwanda Revenue Authority EBM / e-invoice integration is **not** implemented; confirm tax-receipt obligations with the RRA or an accountant. The VAT default (18%, exclusive) is editable per company.
- French and Kinyarwanda text was written without a native reviewer; have it reviewed.
- The "shop" module reuses the POS and inventory (barcode, stock, recipes); dedicated returns and price lists are not built. Email verification at sign-up is not enforced.
- Privacy policy and terms pages are placeholders for legal review.
