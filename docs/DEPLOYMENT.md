# Deployment guide

Target: one VPS (2 vCPU / 4 GB is enough to start) close to Kigali, running the Docker stack in `docker-compose.yml` (PHP-FPM app, nginx, MySQL 8, Redis, scheduler, queue worker) behind TLS.

## 1. Domains and DNS
- `edusmartconsult.com` stays your static site. Create `hospitality.edusmartconsult.com` (A record → VPS IP). The app serves the web console and the API (`/api/v1`) from this one host, so `api.hospitality…` is optional.
- Terminate TLS in front of nginx (Caddy/Traefik/Cloudflare). Redirect HTTP → HTTPS. Keep `APP_URL=https://hospitality.edusmartconsult.com`.

## 2. Configure and start
On the server (Ubuntu 24.04 with Docker installed, ports 80 and 443 open):
```bash
git clone https://github.com/Eribangi05/edusmart-hospitality.git && cd edusmart-hospitality
cp backend/.env.example backend/.env
nano backend/.env        # see the values below
printf 'DB_PASSWORD=%s\nDB_ROOT_PASSWORD=%s\nDOMAIN=hospitality.edusmartconsult.com\n' "$(openssl rand -hex 16)" "$(openssl rand -hex 16)" > .env
docker compose build
docker compose run --rm app php artisan key:generate --show      # paste the result into APP_KEY in backend/.env
docker compose up -d
docker compose exec app php artisan migrate --force --seed
docker compose exec app php artisan platform:create-admin you@example.com "Your Name" 'a-long-password'
docker compose exec app php artisan config:cache && docker compose exec app php artisan route:cache
```
Values for `backend/.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://hospitality.edusmartconsult.com`, `APP_KEY=base64:...`, `DB_CONNECTION=mysql`, `DB_HOST=db`, `DB_DATABASE=hospitality`, `DB_USERNAME=hospitality`, `DB_PASSWORD=` (same as the root `.env`), `SESSION_DRIVER=redis`, `SESSION_SECURE_COOKIE=true`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, `REDIS_HOST=redis`, `SANCTUM_STATEFUL_DOMAINS=hospitality.edusmartconsult.com`, `MAIL_*` (needed for password reset), `MOMO_*`.
Never commit either `.env`. Rotating `APP_KEY` later breaks stored 2FA secrets, so back it up.

## 3. MTN MoMo go-live
1. Create an account at the MTN MoMo developer portal, subscribe to **Collections**, create the sandbox API user and key (`MOMO_SUBSCRIPTION_KEY`, `MOMO_API_USER`, `MOMO_API_KEY`), keep `MOMO_BASE_URL=https://sandbox.momodeveloper.mtn.com`, `MOMO_DRIVER=mtn`, `MOMO_CURRENCY=EUR` (the sandbox only accepts EUR).
2. Set `MOMO_CALLBACK_URL=https://hospitality.edusmartconsult.com/api/v1/webhooks/momo/<MOMO_WEBHOOK_TOKEN>` (long random token). The webhook never trusts the body; it re-queries MTN for the real status. `payments:reconcile` also polls pending payments every 5 minutes.
3. Test: POS order → pay with MoMo → approve in the sandbox → order becomes paid; test decline and timeout.
4. Apply for production access with MTN Rwanda; then switch `MOMO_BASE_URL`, `MOMO_TARGET_ENV=mtnrwanda` (as given by MTN), `MOMO_CURRENCY=RWF`. Subscription invoices use the same gateway (the platform's own MoMo account).

## 4. Backups and monitoring
- Nightly `mysqldump` (or managed backups) encrypted and copied off the server; **test a restore monthly**. Example: `docker compose exec db sh -c 'mysqldump -u root -p"$MYSQL_ROOT_PASSWORD" hospitality' | gzip | age -r <pubkey> > backup-$(date +%F).sql.gz.age`.
- Back up the `app_storage` volume (uploaded files, logs) and `.env`.
- Add an uptime check on `/up` and `/api/v1/health`, and error tracking (Sentry/Flare) via `LOG_CHANNEL`.
- The `scheduler` container runs: night audit (02:00 Kigali), billing (03:00), MoMo reconciliation (5 min), token cleanup (04:00).

## 5. Website integration
See [WEBSITE_INTEGRATION.md](WEBSITE_INTEGRATION.md).

## 6. Android release
```bash
cd android
export RELEASE_KEYSTORE=/secure/path/release.jks RELEASE_KEYSTORE_PASSWORD=… RELEASE_KEY_ALIAS=… RELEASE_KEY_PASSWORD=…
./gradlew :app:bundleProdRelease          # signed AAB for Google Play
```
- Create the upload key once (`keytool -genkeypair -v -keystore release.jks -alias upload -keyalg RSA -keysize 2048 -validity 10000`) and **store it and its passwords in two safe places**; use Play App Signing.
- Add certificate pins for the production host in `android/app/src/main/res/xml/network_security_config.xml` before release.
- Play Console: internal testing → closed testing → production; fill the Data Safety form (account info, business and guest data, device id; data encrypted in transit; deletion on request) and link the privacy policy.
- Update `API_BASE_URL` for the `prod` flavor in `app/build.gradle.kts` if the host differs.

## 7. Upgrades
`git pull && docker compose up -d --build && docker compose exec app php artisan migrate --force && docker compose exec app php artisan optimize`. The API is versioned (`/api/v1`); keep old app versions working until their users update (additive changes only within v1).
