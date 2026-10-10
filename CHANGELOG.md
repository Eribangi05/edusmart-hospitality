# Changelog

## 1.1.0 (multi-layout release)
- New entry points and layouts: public multi-page website, split-screen auth, workspace launcher, sales terminal, kitchen & bar display, front desk, staff app; role-based landing.
- Console: command palette + global search, notification centre, appearance panel (dark mode, 6 accents, density, text size), pinned pages, dashboard charts, setup guide, activity feed, rooms timeline, tours calendar, guest 360 profile, promo codes, printable invoices/manifests.
- API: `/search`, `/notifications`, `/reports/trends`, `/rooms/timeline`, `/desk`, `/onboarding`, `/guests/{id}/profile`, `/activity`, `/my-trips`, `/orders/{id}/voucher`, vouchers CRUD, website contact messages.
- Android: alerts on Home, promo codes on orders.
- Fixes: search boxes used a stale value with debounce; dark-mode card contrast; nightly audit date.

## 1.0.0 (initial build)
- Backend: multi-tenant core, auth (2FA, device tokens, remote revoke), roles/permissions, module registry, settings, custom fields, taxes, payment methods, audit log.
- Rooms, folios, night audit; POS, payments, shifts, kitchen; tourism bookings/departures/resources; inventory ledger, purchasing, recipes; staff attendance/payroll; housekeeping; reports; billing; sync API; super-admin API; data export/erasure; password reset; MoMo gateway (fake + MTN).
- Web console (tenant + platform admin) in English/Français/Kinyarwanda.
- Android app: offline-first POS, rooms, tours, tasks, clock-in, sync queue, receipts, barcode scan.
- Docker/CI, generated OpenAPI, documentation.
