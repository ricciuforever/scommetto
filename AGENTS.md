# 🧠 AGENTS & DEVELOPER GUIDE

Questa guida è destinata a Jules o a qualsiasi AI Agente che debba manutenere o evolvere il sistema.

## 🏗️ Architettura del Sistema

Il progetto è un sistema "Decoupled" dove il Backend gestisce la logica pesante e il Frontend visualizza i risultati in tempo reale scaricando file JSON statici o interrogando micro-API.

### 1. Backend (FastAPI + Background Threads)
- **Main Loop (`main.py`)**: Gestisce un `Thread` demone chiamato `single_update_loop`. 
  - **Frequenza**: 60 secondi (Savings Mode).
  - **Task**: Scarica dati live -> Controlla risultati scommesse -> Esegue analisi AI se necessario.
- **Settlement Engine (`check_bet_results.py`)**: 
  - Utilizza il **Batching** (20 fixture per chiamata API) per risparmiare quota.
  - **Smart Logic**: Confronta l'advice (testo libero dell'AI) con i nomi reali delle squadre (`home_name`, `away_name`) per determinare WIN/LOSS anche con input non standard.
- **Persistence**: I dati sono salvati in `live_matches.json` e `bets_history.json`.

### 2. Frontend (React + Vite)
- **Dashboard Real-time**: Usa uno script di "Minute Ticking" per simulare l'avanzamento dei minuti tra un sync e l'altro del server.
- **Snap-back Prevention**: Il timer locale non torna mai indietro (es. da 91' a 90') se l'API non ha ancora aggiornato lo stato del match.
- **Live Terminal**: Interroga `/api/logs` per mostrare l'output di `agent_log.txt` direttamente nell'interfaccia.

### 3. Deploy & Permissions (CRITICO)
L'errore più comune in produzione è il conflitto di permessi tra `root` e l'utente Plesk.
- **Utente Web**: `emanueletolomei.it_4qrclx883cu`
- **Gruppo**: `psacln`
- **Regola d'oro**: Tutto ciò che viene creato dal Bot deve essere accessibile al server web. Il file `deploy.sh` esegue un `chown` ricorsivo ad ogni build.

## 🛠️ Manutenzione e Debug

### Se il sito sembra "Fermo":
1. Controlla il **Live Terminal** in fondo alla dashboard.
2. Verifica se ci sono due processi python attivi: `ps aux | grep python`. Se sì, esegui `pkill -9 -f python3`.
3. Controlla la quota API nella dashboard.

### Per aggiungere nuove strategie:
Modifica `gemini_analyzer.py` per cambiare il prompt inviato a Gemini. Il sistema leggerà automaticamente i nuovi consigli e proverà a liquidarli usando la logica flessibile in `check_bet_results.py`.

## 📡 API Endpoints
- `/api/live`: Restituisce i match monitorati.
- `/api/history`: Restituisce la cronologia scommesse.
- `/api/logs`: Restituisce le ultime righe di log del bot.
- `/api/usage`: Monitoraggio quota API-Football.

---
**Nota per Jules**: Il sistema è attualmente in "Savings Mode". Se hai budget API illimitato, puoi abbassare il `time.sleep` in `main.py` a 30s e aumentare la frequenza di `check_bets`.
