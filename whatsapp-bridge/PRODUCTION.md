# WhatsApp Bridge — Production Setup

## Prerequisites
- Node.js 22: `/opt/alt/alt-nodejs22/root/usr/bin/node`
- PM2 installed at: `~/.npm-global/bin/pm2`

## First-time Setup

```bash
cd ~/domains/devline.studio/public_html/task/whatsapp-bridge
export PATH="$HOME/.npm-global/bin:/opt/alt/alt-nodejs22/root/usr/bin:$PATH"

# Install dependencies
npm ci --omit=dev

# Copy and edit environment
cp .env.example .env
nano .env

# Start with PM2
pm2 delete whatsapp-bridge 2>/dev/null || true
pm2 start ecosystem.config.cjs
pm2 save
pm2 status
```

## Cron Watchdog (every minute)

```
* * * * * /home/u424916160/domains/devline.studio/public_html/task/whatsapp-bridge/scripts/check-whatsapp-bridge.sh >> /home/u424916160/whatsapp-bridge-watchdog.log 2>&1
```

## Useful Commands

```bash
export PATH="$HOME/.npm-global/bin:/opt/alt/alt-nodejs22/root/usr/bin:$PATH"

# Status
pm2 status

# Logs
pm2 logs whatsapp-bridge --lines 50

# Restart
pm2 restart whatsapp-bridge

# Stop
pm2 stop whatsapp-bridge

# Full reload with ecosystem
pm2 delete whatsapp-bridge && pm2 start ecosystem.config.cjs && pm2 save
```

## Session Replacement (440)

If WhatsApp was linked from another device, the bridge logs:
> "WhatsApp session was replaced from another device. Re-link is required."

The bridge will NOT auto-reconnect in this case. To fix:
1. Delete the auth directory: `rm -rf auth/`
2. Restart: `pm2 restart whatsapp-bridge`
3. Scan the new QR code from the admin panel or terminal logs.

## Environment Variables

| Variable | Description |
|---|---|
| `APP_ENV` | Set to `production` so public URL fallbacks never resolve to localhost |
| `PUBLIC_APP_URL` | Public app base URL (`https://task.devline.studio`) |
| `LARAVEL_APP_URL` | Public Laravel base URL (`https://task.devline.studio`) |
| `LARAVEL_API_URL` | Public Laravel API base URL (`https://task.devline.studio/api`) |
| `LARAVEL_PUBLIC_URL` | Public root used for bridge media URLs |
| `LARAVEL_WEBHOOK_URL` | Laravel inbound message endpoint |
| `LARAVEL_HEARTBEAT_URL` | Laravel heartbeat endpoint |
| `LARAVEL_OUTBOUND_PULL_URL` | Laravel outbound pull endpoint |
| `BRIDGE_SECRET` | Shared secret for authentication |
| `GROUP_ID` | Restrict to specific WhatsApp group |
| `GROUP_NAME` | Restrict by group name |
| `IGNORE_OLD_MESSAGES` | Skip messages from before startup |
| `HEARTBEAT_INTERVAL_SECONDS` | Heartbeat frequency (default: 30) |
| `OUTBOUND_POLL_SECONDS` | Outbound poll frequency (default: 5) |
| `API_PORT` | Local bridge API port (default: 3001, stays internal on `127.0.0.1`) |
