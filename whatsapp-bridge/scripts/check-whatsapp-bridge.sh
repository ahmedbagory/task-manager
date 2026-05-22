#!/bin/bash
# WhatsApp Bridge Watchdog — ensures PM2 and the bridge are always running.

export PATH="$HOME/.npm-global/bin:/opt/alt/alt-nodejs22/root/usr/bin:$PATH"
export HOME="/home/u424916160"

BRIDGE_DIR="$HOME/domains/devline.studio/public_html/task/whatsapp-bridge"
ECOSYSTEM="$BRIDGE_DIR/ecosystem.config.cjs"
LOG_PREFIX="$(date '+%Y-%m-%d %H:%M:%S') [watchdog]"

# Check if PM2 daemon is running
if ! pm2 pid > /dev/null 2>&1 || [ -z "$(pm2 pid)" ]; then
    echo "$LOG_PREFIX PM2 daemon not running — resurrecting."
    pm2 resurrect 2>&1
    exit 0
fi

# Check if bridge process exists in PM2
STATUS=$(pm2 show whatsapp-bridge 2>/dev/null | grep "status" | head -1 | awk '{print $4}')

if [ -z "$STATUS" ]; then
    echo "$LOG_PREFIX Bridge not found in PM2 — starting with ecosystem."
    cd "$BRIDGE_DIR" && pm2 start "$ECOSYSTEM" 2>&1
    pm2 save 2>&1
elif [ "$STATUS" != "online" ]; then
    echo "$LOG_PREFIX Bridge not online (status=$STATUS) — restarting."
    pm2 restart whatsapp-bridge 2>&1
    pm2 save 2>&1
fi
