<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scommetto PRO - Live Betting Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body>
    <header class="glass-panel">
        <div class="logo">SCOMMETTO.AI</div>
        <div class="api-status">
            <span id="usage-val">...</span> / 7500 API Credits
        </div>
    </header>

    <main class="container">
        <div class="stats-bar">
            <div class="glass-panel stat-item">
                <span class="stat-value" id="active-matches-count">0</span>
                <span class="stat-label">Match Live</span>
            </div>
            <div class="glass-panel stat-item">
                <span class="stat-value" id="pending-bets-count">0</span>
                <span class="stat-label">Pending Bets</span>
            </div>
            <div class="glass-panel stat-item">
                <span class="stat-value text-success" id="roi-val">0%</span>
                <span class="stat-label">Estimated ROI</span>
            </div>
        </div>

        <div class="dashboard-grid">
            <section>
                <h2 style="margin-bottom: 1.5rem;">Partite in Diretta</h2>
                <div id="league-filters" class="league-filters-container">
                    <!-- Filters populated by JS -->
                </div>
                <div id="live-matches-container">
                    <!-- Matches populated by JS -->
                </div>
            </section>

            <aside>
                <h2 style="margin-bottom: 1.5rem;">Storico Recente</h2>
                <div class="glass-panel" id="history-container" style="padding: 0.5rem;">
                    <!-- History populated by JS -->
                </div>
            </aside>
        </div>
    </main>

    <!-- Modal for Analysis -->
    <div id="analysis-modal" class="modal"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:1000;">
        <div class="glass-panel" style="max-width: 600px; margin: 100px auto; padding: 2rem; position:relative;">
            <button onclick="closeModal()"
                style="position:absolute; top:1rem; right:1rem; background:none; border:none; color:white; cursor:pointer;">&times;</button>
            <h3 id="modal-title">Analisi Prossima Giocata</h3>
            <div id="modal-body" style="margin: 1.5rem 0; line-height: 1.6;">
                Auto-Generating intelligence...
            </div>
            <div id="modal-footer" style="display:flex; justify-content:flex-end; gap:1rem;">
                <button class="btn-analyze" id="place-bet-btn" style="display:none;">Piazzare Scommessa</button>
            </div>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
</body>

</html>