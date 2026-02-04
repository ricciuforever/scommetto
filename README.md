# Scommetto Agente ⚽

Progetto di monitoraggio scommesse real-time basato su API-Football.

## Architettura
Il progetto è diviso in due parti:
1. **Backend (Python/FastAPI)**: Gestisce la comunicazione con le API di API-Football, il caching dei dati e l'aggiornamento automatico dei match live.
2. **Frontend (React/Vite)**: Una dashboard moderna e premium per visualizzare i match live e le statistiche delle squadre.

## Configurazione API
L'API Key è già configurata nel backend (`backend/main.py`).
- **Piano**: Free (100 richieste/giorno).
- **Strategia di polling**: Ogni 15 minuti viene interrogato l'endpoint `live=all` per ottenere tutti i match in corso nel mondo (96 richieste/giorno). Questo garantisce una copertura h24 senza superare i limiti.

## Come avviare il progetto

### 1. Avviare il Backend
```powershell
cd backend
python main.py
```
Il backend sarà disponibile su `http://localhost:8000`.

### 2. Avviare il Frontend
```powershell
cd frontend
npm install
npm run dev
```
La dashboard sarà disponibile su `http://localhost:5173`.

## Dati Inclusi
- **Squadre Serie A**: Caricate inizialmente e salvate in `serie_a_teams.json`.
- **Giocatori (Squads)**: Caricati per le principali squadre di Serie A (stagione 24/25, a causa delle limitazioni del piano Free sulle stagioni correnti).
- **Match Live**: Aggiornati automaticamente ogni 15 minuti.

## Ottimizzazione Richieste
Per restare sotto le 100 request/day:
- Squadre e Giocatori vengono scaricati una sola volta (cache).
- I match live usano un unico endpoint globale (`live=all`) invece di uno per ogni lega.
- L'aggiornamento avviene 4 volte l'ora (4 * 24 = 96 requests).

<!-- Last redeploy trigger: 2026-02-05 00:54 -->
