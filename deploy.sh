#!/bin/bash

# --- CONFIGURAZIONE ---
APP_DIR="/var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it"
LOG_FILE="$APP_DIR/deploy_log.txt"

echo "=== Deploy iniziato: $(date) ===" >> "$LOG_FILE"

# 0. UPDATE REPO
echo "Pulling latest changes..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1

# 0b. DETECT PLESK NODE
if [ -d "/opt/plesk/node/24/bin" ]; then
    export PATH=/opt/plesk/node/24/bin:$PATH
    echo "Using Plesk Node 24" >> "$LOG_FILE"
elif [ -d "/opt/plesk/node/25/bin" ]; then
    export PATH=/opt/plesk/node/25/bin:$PATH
    echo "Using Plesk Node 25" >> "$LOG_FILE"
fi

# 1. FRONTEND BUILD
echo "Cleaning and Building Frontend..." >> "$LOG_FILE"
cd "$APP_DIR/frontend"
rm -rf dist node_modules >> "$LOG_FILE" 2>&1
# Install blocks if run as root sometimes, so we use --unsafe-perm if needed
npm install >> "$LOG_FILE" 2>&1
# Use npx to be sure we call the local vite
npx vite build >> "$LOG_FILE" 2>&1

# 1b. SYNC TO PUBLIC_HTML
if [ -d "dist" ] && [ -f "dist/index.html" ]; then
    echo "Build success, syncing to public_html..." >> "$LOG_FILE"
    # Clean public_html before sync to avoid old file pollution
    rm -rf "$APP_DIR/public_html/*"
    mkdir -p "$APP_DIR/public_html"
    cp -r dist/* "$APP_DIR/public_html/" >> "$LOG_FILE" 2>&1
    cp .htaccess "$APP_DIR/public_html/" >> "$LOG_FILE" 2>&1
    # Important: Set owner to the domain user so Plesk doesn't block it
    chown -R emanueletolomei.it_4qrclx883cu:psacln "$APP_DIR/public_html"
    chmod -R 755 "$APP_DIR/public_html" >> "$LOG_FILE" 2>&1
    echo "Frontend built and synced successfully." >> "$LOG_FILE"
else
    echo "CRITICAL ERROR: Build failed. dist/index.html not found!" >> "$LOG_FILE"
    echo "Check frontend/node_modules status." >> "$LOG_FILE"
fi

# 2. BACKEND SETUP
echo "Updating Backend..." >> "$LOG_FILE"
cd "$APP_DIR/backend"
# Se il venv non esiste (es. prima volta o cancellato), lo crea con 3.11
if [ ! -d "venv" ]; then
    echo "Creating new venv with Python 3.11..." >> "$LOG_FILE"
    python3.11 -m venv venv
fi

./venv/bin/pip install --upgrade pip >> "$LOG_FILE" 2>&1
./venv/bin/pip install -r requirements.txt >> "$LOG_FILE" 2>&1

# 3. RESTART AGENTE
echo "Restarting Agent..." >> "$LOG_FILE"
pkill -f main.py || true
# Usiamo setsid per staccare completamente il processo dal deploy di Plesk
setsid ./venv/bin/python3 main.py >> agent_log.txt 2>&1 &

echo "=== Deploy completato: $(date) ===" >> "$LOG_FILE"
