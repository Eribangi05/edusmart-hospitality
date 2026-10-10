# Android app

Kotlin + Jetpack Compose (Material 3), min SDK 26, package `com.edusmartconsult.hospitality`. Flavors: `dev` (emulator host `10.0.2.2:8000`), `staging`, `prod`.

## How offline works
- **Reads** come from a local Room database. Every synced record is stored as JSON keyed by (entity, id), so tenant custom fields and future server fields need no app update.
- **Offline writes** (new order, send to kitchen, cash payment, task start/complete/inspect, clock in/out) update local state immediately and add an *outbox* item with a client UUID and idempotency key.
- **Sync** (`SyncEngine`, WorkManager on connectivity + every 15 min + manual): push the outbox in order through `POST /sync/push` (the server replays each item through the normal API, so permissions and business rules still apply), then pull changes with per-entity cursors (`GET /sync/pull`; soft-deleted rows are tombstones). Records created offline keep their ID, so the server copy replaces the local one in place.
- **Conflicts** are explicit: if the server rejects a queued item (e.g. room no longer available) it appears under More → Sync queue with the reason, and can be retried or discarded. Server totals always win; offline totals are shown as estimates using the same tax engine.
- **Online-only** actions (clearly messaged): reservations, check-in/out and folio payments, bookings and quotes, MoMo payments, voids/discounts needing a manager PIN, cash-shift open/close, kitchen screen.
- Order numbers created offline use a device prefix (`D1A2B-0007`) so tills never collide.

## Structure
`core/` JSON helpers, tax engine, UUIDv7 · `data/net` API client with single-flight token refresh · `data/local` Room · `data/sync` sync engine + worker · `data/Store` data hub · `ui/` Compose screens · `print/` ESC/POS over Bluetooth.

## Receipts and barcodes
Pair a Bluetooth ESC/POS thermal printer in Android settings, then choose it in Settings → Receipt printer. Barcode scanning uses Google's code scanner (no camera permission).

## Languages
English, French, Kinyarwanda (`values`, `values-fr`, `values-rw`); the app follows the phone language or the in-app choice (Settings → Language). Company label overrides (Settings → Labels in the web console) apply to key labels.

## Build and test
```bash
./gradlew :app:assembleDevDebug :app:testDevDebugUnitTest :app:lintDevDebug
./gradlew :app:assembleProdRelease     # R8-shrunk (unsigned unless RELEASE_KEYSTORE is set)
```
If your network rate-limits Maven Central (HTTP 429), set `org.gradle.workers.max=1` (already default here) and re-run; Gradle resumes from its cache.

## Known limits
Not run on a device in the build environment; push notifications not wired (needs Firebase); local DB not encrypted; no certificate pins yet; shop returns/price lists not built.
