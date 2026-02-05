#!/bin/bash

# --- CONFIGURAZIONE ---
APP_DIR="/var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it"
LOG_FILE="$APP_DIR/deploy_log.txt"
USER="emanueletolomei.it_4qrclx883cu"
GROUP="psacln"

echo "=== Deploy iniziato: $(date) ===" >> "$LOG_FILE"

# 0. UPDATE REPO
echo "Updating Repo..." >> "$LOG_FILE"
git pull origin main >> "$LOG_FILE" 2>&1 # Commented for sandbox, user has it active

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

# Capture old PID if exists
OLD_PID=$(pgrep -f "main.py" | head -n 1)

# Clean up any existing process on port 8000 or running main.py
fuser -k 8000/tcp >> "$LOG_FILE" 2>&1 || true
pkill -9 -f main.py >> "$LOG_FILE" 2>&1 || true

# Wait for port to be released
echo "Waiting for port 8000 to be released..." >> "$LOG_FILE"
sleep 5

# Check if port 8000 is still busy
if lsof -i :8000 > /dev/null; then
    echo "CRITICAL ERROR: Port 8000 is still in use. Could not kill the old process." >> "$LOG_FILE"
    echo "This usually happens due to permission conflicts (process owned by root?)." >> "$LOG_FILE"
    echo "Please run: 'sudo fuser -k 8000/tcp' manually and then restart the deploy." >> "$LOG_FILE"
else
    # Fix global ownership before start to ensure logs can be written
    chown -R $USER:$GROUP "$APP_DIR"
    chmod -R 755 "$APP_DIR"

    # Start
    echo "Starting new instance..." >> "$LOG_FILE"
    setsid ./venv/bin/python3 main.py >> agent_log.txt 2>&1 &
    sleep 3

    NEW_PID=$(pgrep -f "main.py" | sort -n | tail -n 1)

    if [ ! -z "$NEW_PID" ] && [ "$NEW_PID" != "$OLD_PID" ]; then
        echo "Agent started successfully (PID: $NEW_PID)." >> "$LOG_FILE"
    else
        echo "ERROR: Agent failed to start. Check backend/agent_log.txt" >> "$LOG_FILE"
    fi
fi

echo "=== Deploy completato: $(date) ===" >> "$LOG_FILE"
