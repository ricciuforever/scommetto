#!/bin/bash

# --- CONFIGURAZIONE ---
APP_DIR="/var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it"
LOG_FILE="$APP_DIR/deploy_log.txt"

echo "=== Deploy iniziato: $(date) ===" >> "$LOG_FILE"

# 1. FRONTEND BUILD
echo "Building Frontend..." >> "$LOG_FILE"
cd "$APP_DIR/frontend"
npm install >> "$LOG_FILE" 2>&1
npm run build >> "$LOG_FILE" 2>&1

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
