# Security model and launch checklist

## Controls in place
- **Tenant isolation**: every business table has `tenant_id`; a global scope filters every query and fails closed with no tenant; the tenant comes from the authenticated token, never the request; foreign-key inputs are validated against the same tenant (`ResourceRegistry::ref`). Isolation tests cover reading, updating, deleting, referencing and listing across tenants, plus sync pull/push.
- **AuthN**: Argon2/bcrypt passwords (10+ chars), per-email+IP lockout, TOTP 2FA (mandatory for platform admins), 15-minute access tokens + rotating refresh tokens bound to a device (reuse revokes the device), remote revoke, password reset signs out everywhere, no account enumeration on forgot-password.
- **AuthZ**: deny by default; permission per module/action checked on every route; sensitive POS actions need `pos.void` / `pos.discount` or a manager PIN (attempts audited); module and plan gating; suspended tenants are read-only.
- **Data integrity**: money is integer RWF; ledgers (folio entries, payments, stock movements, orders) are append-only/voided; the server recomputes every total; idempotency keys on payments and sync pushes; version conflicts return 409.
- **Auditability**: audit log of logins, voids, discounts, role/user/settings changes, impersonation, exports; audit rows cannot be updated or deleted through the app.
- **Web**: CSP, `X-Frame-Options: DENY`, `nosniff`, referrer policy, HSTS over HTTPS, CSRF on cookie sessions, session regeneration at login, `Cache-Control: no-store` on the API. (CSP allows `unsafe-eval` because Alpine.js evaluates expressions; moving to the Alpine CSP build is a future hardening.)
- **Mobile**: tokens in Keystore-backed encrypted preferences, HTTPS only (cleartext allowed only for the emulator host in dev), backups disabled, R8 shrinking, no secrets in the APK.
- **Payments**: no card data stored; MoMo requests go to MTN; webhook is secret-token protected and re-verifies status with MTN.

## Before launch
- [ ] Set production `.env` (APP_DEBUG=false, strong DB passwords, mail, MoMo, SANCTUM_STATEFUL_DOMAINS) and TLS; HSTS on.
- [ ] Add production certificate pins to the Android network security config; consider SQLCipher for the local DB.
- [ ] Penetration test or at least an OWASP ASVS L1 / MASVS review; dependency audit (`composer audit`, `npm audit`, Gradle dependency updates; Room/Compose/AGP versions are a release behind).
- [ ] Verify a backup restore end to end; set log retention; add error tracking and uptime alerts.
- [ ] Legal: privacy policy and terms reviewed under Rwanda's data-protection law; confirm tax-receipt/EBM obligations with the RRA; MTN MoMo production agreement.
- [ ] Native-speaker review of French and Kinyarwanda strings.
- [ ] Test on real devices (phone + tablet), slow networks, airplane mode (POS, tasks, clock-in), and a real receipt printer.
