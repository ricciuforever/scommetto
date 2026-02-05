#!/bin/bash

# --- CONFIGURAZIONE ---
APP_DIR="/var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it"
LOG_FILE="$APP_DIR/deploy_log.txt"
USER="emanueletolomei.it_4qrclx883cu"
GROUP="psacln"

echo "=== Deploy iniziato: $(date) ===" >> "$LOG_FILE"

# 0. UPDATE REPO
cd "$APP_DIR" || { echo "CRITICAL: Could not cd to $APP_DIR" >> "$LOG_FILE"; exit 1; }
git config --global --add safe.directory "$APP_DIR"
echo "Updating Repo..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1

# 0b. DETECT PLESK NODE
if [ -d "/opt/plesk/node/24/bin" ]; then
    export PATH=/opt/plesk/node/24/bin:$PATH
elif [ -d "/opt/plesk/node/25/bin" ]; then
    export PATH=/opt/plesk/node/25/bin:$PATH
fi

# 1. CLEANUP (Optional - keep only what's needed)
echo "Syncing files to public_html..." >> "$LOG_FILE"

# Assuming the web server points to public_html, we copy our MVC there.
# If the root is already public_html, we skip this and just set permissions.

# 2. PERMISSIONS
echo "Fixing Permissions..." >> "$LOG_FILE"
chown -R $USER:$GROUP "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod -R 777 "$APP_DIR/data" # Ensure data dir is writable

echo "=== Deploy completato (MVC PHP Mode): $(date) ===" >> "$LOG_FILE"
