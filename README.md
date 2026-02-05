# 🤖 SCOMMETTO.AI v4.0 - PHP MVC Edition

Sistema di monitoraggio e analisi scommesse live basato su **AI (Google Gemini)** e **API-Football**. 
Questa versione è interamente scritta in **PHP MVC** per massima velocità, facilità di deploy e stabilità.

## 🚀 Nuova Architettura
Il progetto ora segue lo standard MVC senza dipendenze esterne pesanti:

```text
.
├── app/
│   ├── Config/     # Configurazioni e caricamento .env
│   ├── Controllers/# Logica delle rotte (Match, Bet, Sync)
│   ├── Models/      # Interazione con MySQL (Scommesse, Usage)
│   ├── Services/    # Servizi esterni (API Football, Gemini API)
│   └── Views/       # Template HTML/PHP (Premium Dashboard)
├── assets/
│   ├── css/         # Stili Premium (Glassmorphism)
│   └── js/          # Logica Frontend Vanilla JS
├── data/            # Cache locale (JSON) e Log
├── index.php        # Front Controller (Routing)
├── bootstrap.php    # Autoloader e Inizializzazione
├── .htaccess        # Gestione URL amichevoli
└── deploy.sh        # Automazione per il server
```

## 🛠️ Requisiti
- PHP 8.0+
- MySQL
- Estensione CURL e PDO abilitate

## ⚙️ Installazione
1. Crea un database MySQL (es: `scommetto`).
2. Esegui le query SQL contenute nel messaggio di ristrutturazione per creare le tabelle `bets` e `api_usage`.
3. Configura il file `.env` nella root con le tue credenziali:
   ```env
   FOOTBALL_API_KEY=tua_chiave
   GEMINI_API_KEY=tua_chiave
   DB_HOST=localhost
   DB_NAME=scommetto
   DB_USER=root
   DB_PASS=
   ```

## 🌍 Deployment
Il deploy ora è istantaneo:
1. `git pull origin main`
2. Assicurati che la cartella `data/` sia scrivibile dal server (`chmod 777 data`).

---
*Powered by PHP MVC & AI Intelligence.*
