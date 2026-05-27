# WhatsApp Web Bridge (Read-Only)

This bridge connects to WhatsApp Web using **Baileys**, listens to inbound **group messages** only, and forwards them to Laravel:

- `POST /webhooks/inbound-message`
- `POST /webhooks/bridge/heartbeat`

It is internal-use and read-only:

- No outbound sending
- No auto-replies
- No spam actions

## Requirements

- Node.js 20+
- Laravel app running and accessible from this machine

## Setup

```bash
cd whatsapp-bridge
npm install
cp .env.example .env
npm run start
```

## Environment

Edit `whatsapp-bridge/.env`:

```dotenv
APP_ENV=production
PUBLIC_APP_URL=https://task.devline.studio
LARAVEL_APP_URL=https://task.devline.studio
LARAVEL_API_URL=https://task.devline.studio/api
LARAVEL_PUBLIC_URL=https://task.devline.studio
LARAVEL_WEBHOOK_URL=https://task.devline.studio/webhooks/inbound-message
LARAVEL_HEARTBEAT_URL=https://task.devline.studio/webhooks/bridge/heartbeat
LARAVEL_OUTBOUND_PULL_URL=https://task.devline.studio/webhooks/bridge/outbound
BRIDGE_SECRET=change_me
GROUP_ID=
GROUP_NAME=
IGNORE_OLD_MESSAGES=true
HEARTBEAT_INTERVAL_SECONDS=30
```

Notes:

- `BRIDGE_SECRET` must match Laravel `INBOUND_BRIDGE_SECRET` (or API Settings bridge secret).
- If `GROUP_ID` is set, only that group is listened to.
- If `GROUP_NAME` is set (and `GROUP_ID` is empty), bridge resolves by group subject.
- If both are empty, bridge listens to all groups and logs a warning.

## Startup Behavior

On start:

1. QR is printed in terminal.
2. Session is saved in `whatsapp-bridge/auth`.
3. All visible groups are listed as:

```text
Group Name | Group ID
```

Use this list to copy `GROUP_ID` into `.env`.

## How to enable WhatsApp Web Bridge

1. Go to **Admin Panel → API Settings**.
2. Select provider: **WhatsApp Web Bridge**.
3. Set bridge secret.
4. Save settings.
5. Copy webhook URL: `https://task.devline.studio/webhooks/inbound-message`.
6. Copy heartbeat URL: `https://task.devline.studio/webhooks/bridge/heartbeat`.
7. Configure `whatsapp-bridge/.env`.
8. Run:

```bash
cd whatsapp-bridge
npm install
npm run start
```

9. Scan QR from WhatsApp Linked Devices.
10. Check API Settings status panel.
11. Send a message in WhatsApp group.
12. Check WhatsApp Inbox.

## Manual Test

1. Start Laravel:

```bash
php artisan serve
```

2. Start bridge:

```bash
cd whatsapp-bridge
npm run start
```

3. Send group text message.
4. Verify in Laravel DB `whatsapp_messages`:

- `direction = inbound`
- `from_phone` populated
- `group_id` / `group_name` populated
- `provider_message_id` stored in `whatsapp_message_id`

5. Verify `bridge_statuses`:

- `provider = whatsapp_web_bridge`
- `status = connected`
- `last_heartbeat_at` updates every ~30s
- `last_message_at` updates when a message is forwarded
