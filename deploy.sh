#!/bin/bash
# 🚀 DEPLOY SCRIPT v4.0 - PHP MVC EDITION

APP_DIR="/var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it"
LOG_FILE="$APP_DIR/data/deploy.log"

echo "[$(date)] --- START DEPLOY ---" >> "$LOG_FILE"

# 1. UPDATE REPO
cd "$APP_DIR" || exit
git pull origin main >> "$LOG_FILE" 2>&1

# 2. PERMISSIONS
echo "Setting permissions..." >> "$LOG_FILE"
chmod -R 755 .
chmod -R 777 data
chmod -R 777 logs 2>/dev/null || true

# 3. CLEANUP
rm -f data/live_matches.json.tmp 2>/dev/null

echo "[$(date)] --- DEPLOY COMPLETE ---" >> "$LOG_FILE"
