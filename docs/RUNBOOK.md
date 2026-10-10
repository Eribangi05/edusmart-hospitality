# Operations runbook

| Situation | What to do |
|---|---|
| Customer reports lost/stolen phone | Owner: Settings → Devices → Revoke (immediate sign-out). Platform: same via impersonation if needed. Change the user's password. |
| User locked out (too many attempts) | Wait 5 minutes (limit is per email + IP) or use password reset. |
| Customer cannot log in after non-payment | Tenant status `suspended` is read-only, not locked out. They pay under Settings → Subscription; or platform admin marks the invoice paid with the MoMo reference. |
| Mobile-money payment stuck "pending" | `php artisan payments:reconcile` (runs every 5 min). If MTN never answers it stays pending; the cashier can take cash instead (the pending payment can be ignored; it does not count toward `paid`). |
| Android "needs attention" items | App → More → Sync queue shows the server's reason (e.g. room no longer available). Retry or discard. Nothing is silently dropped. |
| Wrong tax rate set | Settings → Taxes. Past orders keep their stored totals; new orders use the new rate. |
| Night audit missed | `php artisan rooms:night-audit --date=YYYY-MM-DD` (idempotent per reservation and night). |
| Restore from backup | Restore DB dump into a fresh MySQL, point `.env` at it, run `php artisan migrate --force`. Android devices re-sync by cursor; items created offline after the backup replay through the outbox (idempotent). |
| Tenant asks for their data | Owner: Settings → export (`GET /api/v1/data-export`, NDJSON). |
| Tenant asks for deletion | Owner requests it in the app (`POST /api/v1/tenant/deletion-request`) → confirm identity, ensure they exported → platform console `DELETE /admin/tenants/{id}` with the slug typed back, or `php artisan tenant:delete <slug>`. Irreversible; also remove from backups per your retention policy. |
| Suspected tenant data leak | Run the isolation tests (`php artisan test --filter=Tenancy`), search `audit_logs`, revoke devices, rotate `APP_KEY` only with a plan. |
| Rotate secrets | MoMo keys and webhook token: update `.env`, `php artisan config:cache`, restart. Database password: change in MySQL and `.env`. |
| Support access to a customer's workspace | Platform console → Open workspace as owner (requires a reason, 30-minute token, recorded in the customer's audit log). |
| Billing run | `php artisan billing:run` (daily). Trial → grace (7 days) → suspended. |
