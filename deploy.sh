#!/bin/bash

# --- CONFIGURAZIONE ---
APP_DIR="/var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it"
LOG_FILE="$APP_DIR/deploy_log.txt"

echo "=== Deploy iniziato: $(date) ===" >> "$LOG_FILE"

# 0. UPDATE REPO
echo "Pulling latest changes..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1

# 1. FRONTEND BUILD
echo "Cleaning and Building Frontend..." >> "$LOG_FILE"
cd "$APP_DIR/frontend"
rm -rf dist >> "$LOG_FILE" 2>&1
npm install >> "$LOG_FILE" 2>&1
npm run build >> "$LOG_FILE" 2>&1

# 1b. SYNC TO PUBLIC_HTML
echo "Syncing to public_html..." >> "$LOG_FILE"
mkdir -p "$APP_DIR/public_html"
cp -r dist/* "$APP_DIR/public_html/" >> "$LOG_FILE" 2>&1
# Copy .htaccess for routing
cp .htaccess "$APP_DIR/public_html/" >> "$LOG_FILE" 2>&1
# Fix permissions (using whoami to be dynamic)
chmod -R 755 "$APP_DIR/public_html" >> "$LOG_FILE" 2>&1
echo "Frontend built and synced to public_html" >> "$LOG_FILE"

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
