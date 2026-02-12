# 🎾 Scommetto Tennis - Virtual AI Better

Questo modulo è un agente di scommesse virtuale completamente indipendente, specializzato nel tennis.

## Caratteristiche
- **Dati Storici**: Utilizza i repository di Jeff Sackmann (CSV) per l'analisi dei giocatori.
- **AI Core**: Basato su Google Gemini (flash-lite) con prompt ottimizzati per il tennis.
- **Virtual Portfolio**: Gestisce un portafoglio separato (1000€ iniziali) salvato in un database SQLite locale.
- **Betfair Integration**: Sincronizza quote e mercati reali da Betfair (solo Tennis).
- **Design Premium**: Dashboard moderna ed elegante.

## Struttura
- `/app/Config`: Configurazione specifica.
- `/app/Services`: Logica di recupero dati (CSV e API) e AI.
- `/app/Database/tennis.sqlite`: Database dei risultati virtuali.
- `index.php`: Interfaccia principale.

## Installazione
Assicurati che il file `.env` principale contenga `GEMINI_API_KEY` e le credenziali Betfair.
Per inizializzare il database, esegui (se hai PHP nel terminale):
```bash
php tennis-bet/init_db.php
```
Altrimenti, il database verrà creato automaticamente al primo accesso se le cartelle hanno i permessi corretti.

---
*Creato con passione da Antigravity per Scommetto.*
