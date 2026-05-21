# Hostinger Deployment Guide (task.devline.studio)

## Target setup
- Domain: `https://task.devline.studio`
- Upload path: `files/public_html/task/`
- Laravel project root on server: `files/public_html/task/`
- Web requests must be routed to: `files/public_html/task/public` using root `.htaccess`

## Required upload structure
Upload the full project into `files/public_html/task/` so this structure exists:

- `files/public_html/task/app`
- `files/public_html/task/bootstrap`
- `files/public_html/task/config`
- `files/public_html/task/database`
- `files/public_html/task/public`
- `files/public_html/task/resources`
- `files/public_html/task/routes`
- `files/public_html/task/storage`
- `files/public_html/task/vendor`
- `files/public_html/task/.env`
- `files/public_html/task/artisan`
- `files/public_html/task/.htaccess`

## Required files before upload
- `vendor` must exist.
- `public/build/manifest.json` must exist.
- Copy `.env.hostinger.example` to `.env` and edit real values.
- Real `.env` must **not** be committed.
- If present, do not upload stale cache files that can break production:
  - `bootstrap/cache/config.php`
  - `bootstrap/cache/routes*.php`

## Local prepare commands (run before upload)
```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
php artisan optimize:clear
```

Then upload the whole project to:
`files/public_html/task/`

## Production .env (Hostinger)
Start from `.env.hostinger.example` and set:

- `APP_KEY` (keep your existing app key, do not rotate unless you intend to re-encrypt data/sessions)
- `DB_HOST`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `INBOUND_BRIDGE_SECRET`
- any additional mail/queue/service variables you use in production

Expected production URL values:
- `APP_URL=https://task.devline.studio`
- `ASSET_URL=https://task.devline.studio`
- `WHATSAPP_PROVIDER=whatsapp_web_bridge`
- `WHATSAPP_OUTBOUND_ENABLED=false`

## Database setup
1. Create MySQL database/user from Hostinger hPanel.
2. Put DB values in `.env`:
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
3. Import SQL using phpMyAdmin.
4. If your exported SQL includes these lines, remove them before import:
   - `DROP DATABASE`
   - `CREATE DATABASE`
   - `USE database_name`

If SSH is available:
```bash
php artisan migrate --force
```

If SSH is not available:
- Import your local exported SQL manually via phpMyAdmin.

## Storage/bootstrap folders
Ensure these folders exist on server (safe to upload with placeholders):
- `storage/framework/cache`
- `storage/framework/sessions`
- `storage/framework/views`
- `storage/logs`
- `bootstrap/cache`

## WhatsApp Web Bridge production env
In `whatsapp-bridge/.env` use:

```env
LARAVEL_WEBHOOK_URL=https://task.devline.studio/webhooks/inbound-message
LARAVEL_HEARTBEAT_URL=https://task.devline.studio/webhooks/bridge/heartbeat
BRIDGE_SECRET=change_me
GROUP_ID=
GROUP_NAME=
IGNORE_OLD_MESSAGES=true
HEARTBEAT_INTERVAL_SECONDS=30
```

## Mobile app API base URL
Use this base URL in mobile app config (not hardcoded in source):

`https://task.devline.studio/api/mobile`

## Post-upload health checks
Open:
- `https://task.devline.studio/up`
- `https://task.devline.studio/admin`
- `https://task.devline.studio/build/manifest.json`

Webhook test (manual inbound):
```bash
curl -X POST https://task.devline.studio/webhooks/inbound-message \
  -H "Content-Type: application/json" \
  -d "{\"from\":\"966500000000\",\"to\":\"company\",\"body\":\"health check\",\"message_type\":\"text\"}"
```

Bridge heartbeat test:
```bash
curl -X POST https://task.devline.studio/webhooks/bridge/heartbeat \
  -H "Content-Type: application/json" \
  -H "X-Bridge-Token: YOUR_BRIDGE_SECRET" \
  -d "{\"provider\":\"whatsapp_web_bridge\",\"status\":\"connected\"}"
```

Mobile API login test:
```bash
curl -X POST https://task.devline.studio/api/mobile/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"employee@example.com\",\"password\":\"password\",\"device_name\":\"mobile-app\"}"
```
