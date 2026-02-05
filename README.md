# 🤖 AGENTE SCOMMESSE PRO v2.6

Sistema di monitoraggio e analisi scommesse live basato su **AI (Google Gemini)** e **API-Football**. Progettato per girare H24 su architettura Plesk/Linux.

## 🚀 Struttura del Progetto

```text
.
├── frontend/             # React (Vite) + Vanilla CSS
│   ├── src/App.jsx       # Cuore della Dashboard
│   └── public_html/      # Cartella di output per il Web Server
├── backend/              # FastAPI (Python 3.11)
│   ├── main.py           # Entry point & Background Loop
│   ├── check_bet_results.py # Logica di liquidazione WIN/LOSS
│   ├── gemini_analyzer.py   # Integrazione con Google Gemini
│   └── agent_log.txt     # Log in tempo reale del Bot
├── deploy.sh             # Script di automazione Build & Deploy
└── AGENTS.md             # Istruzioni tecniche per Sviluppatori/AI
```

## 🛠️ Come Avviare (Sviluppo Locale)

### Backend
1. `cd backend`
2. `python -m venv venv`
3. `source venv/bin/activate` (o `venv\Scripts\activate` su Windows)
4. `pip install -r requirements.txt`
5. Crea un `.env` con:
   - `API_KEY`: Tua chiave API-Football
   - `GEMINI_API_KEY`: Tua chiave Google AI
6. `uvicorn main:app --reload`

### Frontend
1. `cd frontend`
2. `npm install`
3. `npm run dev`

## 🌍 Deployment (Plesk)

Il deployment è automatizzato. Per aggiornare il server:
1. Carica le modifiche su GitHub.
2. Accedi via SSH.
3. Esegui:
   ```bash
   cd /var/www/vhosts/emanueletolomei.it/scommetto.emanueletolomei.it
   git pull origin main
   chmod +x deploy.sh
   ./deploy.sh
   ```

## 📈 Modalità Risparmio Quota
Il sistema è configurato per non eccedere le 7500 call giornaliere:
- **Sync Live**: Ogni 60 secondi.
- **Settlement**: Ogni 5 minuti.
- **Consumo stimato**: ~1800-2000 call/24h.

---
*Created with ❤️ for Jules.*
