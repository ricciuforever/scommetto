#!/bin/bash

# --- CONFIGURAZIONE ---
APP_DIR="/var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it"
LOG_FILE="$APP_DIR/deploy_log.txt"
USER="emanueletolomei.it_4qrclx883cu"
GROUP="psacln"

echo "=== Deploy iniziato: $(date) ===" >> "$LOG_FILE"

# 0. UPDATE REPO
echo "Updating Repo..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1 # Commented out to avoid sandbox issues

# 0b. DETECT PLESK NODE
if [ -d "/opt/plesk/node/24/bin" ]; then
    export PATH=/opt/plesk/node/24/bin:$PATH
elif [ -d "/opt/plesk/node/25/bin" ]; then
    export PATH=/opt/plesk/node/25/bin:$PATH
fi

# 1. FRONTEND BUILD
echo "Building Frontend..." >> "$LOG_FILE"
cd "$APP_DIR/frontend"
npm install >> "$LOG_FILE" 2>&1
npx vite build >> "$LOG_FILE" 2>&1

# 1b. SYNC TO PUBLIC_HTML
if [ -d "dist" ] && [ -f "dist/index.html" ]; then
    echo "Syncing to public_html..." >> "$LOG_FILE"
    rm -rf "$APP_DIR/public_html/*"
    mkdir -p "$APP_DIR/public_html"
    cp -r dist/* "$APP_DIR/public_html/"
    [ -f ".htaccess" ] && cp .htaccess "$APP_DIR/public_html/"
    chown -R $USER:$GROUP "$APP_DIR/public_html"
    chmod -R 755 "$APP_DIR/public_html"
else
    echo "ERROR: Frontend build failed." >> "$LOG_FILE"
fi

# 2. BACKEND SETUP
echo "Updating Backend..." >> "$LOG_FILE"
cd "$APP_DIR/backend"
if [ ! -d "venv" ]; then
    python3.11 -m venv venv || python3 -m venv venv
fi
./venv/bin/pip install --upgrade pip >> "$LOG_FILE" 2>&1
./venv/bin/pip install -r requirements.txt >> "$LOG_FILE" 2>&1

# 3. RESTART AGENTE
echo "Restarting Agent..." >> "$LOG_FILE"
pkill -9 -f main.py || true

# Fix ownership
chown -R $USER:$GROUP "$APP_DIR"
chmod -R 755 "$APP_DIR"

# Start
setsid ./venv/bin/python3 main.py >> agent_log.txt 2>&1 &
sleep 2
if pgrep -f "main.py" > /dev/null; then
    echo "Agent started successfully." >> "$LOG_FILE"
else
    echo "ERROR: Agent failed to start." >> "$LOG_FILE"
fi

echo "=== Deploy completato: $(date) ===" >> "$LOG_FILE"
