<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scommetto Tennis - Elite Virtual AI Better</title>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Rajdhani:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #050b18;
            --card-bg: rgba(255, 255, 255, 0.05);
            --accent-glow: #00d2ff;
            --tennis-green: #d4fc79;
            --tennis-yellow: #96e6a1;
            --text-main: #e0e0e0;
            --text-dim: #a0a0a0;
            --glass: rgba(255, 255, 255, 0.03);
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            margin: 0;
            padding: 0;
            background: var(--bg-color);
            background-image: 
                radial-gradient(circle at 20% 20%, rgba(0, 210, 255, 0.05), transparent),
                radial-gradient(circle at 80% 80%, rgba(212, 252, 121, 0.05), transparent);
            color: var(--text-main);
            font-family: 'Rajdhani', sans-serif;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border);
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .logo {
            font-family: 'Orbitron', sans-serif;
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(90deg, var(--tennis-green), var(--accent-glow));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }

        .portfolio-card {
            background: linear-gradient(135deg, rgba(0,210,255,0.1), rgba(212,252,121,0.1));
            padding: 15px 30px;
            border-radius: 15px;
            border: 1px solid var(--border);
            text-align: right;
        }

        .portfolio-label {
            font-size: 14px;
            color: var(--text-dim);
            text-transform: uppercase;
        }

        .portfolio-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--tennis-green);
            text-shadow: 0 0 10px rgba(212, 252, 121, 0.3);
        }

        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .card {
            background: var(--glass);
            backdrop-filter: blur(5px);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
        }

        .card-title {
            font-family: 'Orbitron', sans-serif;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--accent-glow);
        }

        .event-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .event-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--border);
            transition: background 0.3s;
        }

        .event-item:hover {
            background: rgba(255,255,255,0.02);
        }

        .event-info {
            display: flex;
            flex-direction: column;
        }

        .event-name {
            font-size: 18px;
            font-weight: 600;
        }

        .event-meta {
            font-size: 14px;
            color: var(--text-dim);
        }

        .btn-analyze {
            background: linear-gradient(90deg, #00d2ff, #3a7bd5);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .btn-analyze:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 210, 255, 0.4);
        }

        .bet-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-pending { background: rgba(0, 210, 255, 0.2); color: #00d2ff; }
        .badge-won { background: rgba(212, 252, 121, 0.2); color: #d4fc79; }
        .badge-lost { background: rgba(255, 75, 75, 0.2); color: #ff4b4b; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .stat-box {
            background: rgba(255,255,255,0.02);
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid var(--border);
        }

        .stat-val {
            font-size: 24px;
            font-weight: 700;
            color: var(--tennis-green);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-dim);
            text-transform: uppercase;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .live-indicator {
            width: 10px;
            height: 10px;
            background: #ff4b4b;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
            animation: pulse 1s infinite;
        }

    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="logo">SCOMMETTO TENNIS</div>
            <div class="portfolio-card">
                <div class="portfolio-label">Portafoglio Virtuale</div>
                <div class="portfolio-value">€ <?= number_format($portfolio['balance'] ?? 0, 2) ?></div>
            </div>
        </header>

        <div class="grid">
            <div class="main-col">
                <div class="card">
                    <div class="card-title">
                        <span class="live-indicator"></span> EVENTI TENNIS DISPONIBILI
                    </div>
                    <?php if (empty($upcomingEvents)): ?>
                        <p style="color: var(--text-dim)">Nessun evento disponibile al momento. Controlla il token Betfair.</p>
                    <?php else: ?>
                    <ul class="event-list">
                        <?php foreach($upcomingEvents as $event): ?>
                        <li class="event-item">
                            <div class="event-info">
                                <span class="event-name"><?= htmlspecialchars($event['event']['name']) ?></span>
                                <span class="event-meta">
                                    <?= htmlspecialchars($event['competitionName'] ?? 'Tournament') ?> | 
                                    Inizio: <?= date('H:i', strtotime($event['event']['openDate'])) ?>
                                </span>
                            </div>
                            <button class="btn-analyze" onclick="analyze('<?= $event['event']['id'] ?>', '<?= addslashes($event['event']['name']) ?>')">ANALIZZA AI</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <div class="card-title">SCOMMESSE ATTIVE (VIRTUALI)</div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; color: var(--text-dim); font-size: 14px;">
                                <th style="padding: 10px;">Evento</th>
                                <th style="padding: 10px;">Advice</th>
                                <th style="padding: 10px;">Quota</th>
                                <th style="padding: 10px;">Stake</th>
                                <th style="padding: 10px;">Stato</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($activeBets as $bet): ?>
                            <tr style="border-top: 1px solid var(--border);">
                                <td style="padding: 15px;"><?= htmlspecialchars($bet['event_name']) ?></td>
                                <td style="padding: 15px; color: var(--accent-glow)"><?= htmlspecialchars($bet['advice']) ?></td>
                                <td style="padding: 15px;"><?= number_format($bet['odds'], 2) ?></td>
                                <td style="padding: 15px;">€<?= number_format($bet['stake'], 2) ?></td>
                                <td style="padding: 15px;"><span class="bet-badge badge-pending">Attiva</span></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($activeBets)): ?>
                                <tr><td colspan="5" style="padding: 20px; text-align: center; color: var(--text-dim);">Nessun operazione attiva.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="sidebar">
                <div class="card">
                    <div class="card-title">PERFORMANCE STATS</div>
                    <div class="stats-grid">
                        <div class="stat-box">
                            <div class="stat-val"><?= count($recentBets) ?></div>
                            <div class="stat-label">Scommesse Concluse</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-val" style="color: #00d2ff">--%</div>
                            <div class="stat-label">Win Rate</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-val">€<?= number_format(($portfolio['balance'] ?? 1000) - 1000, 2) ?></div>
                            <div class="stat-label">Profit/Loss (P/L)</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-val">ELITE</div>
                            <div class="stat-label">Status AI</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-title">ULTIME OPERAZIONI</div>
                    <ul class="event-list">
                        <?php foreach($recentBets as $bet): ?>
                        <li class="event-item" style="flex-direction: column; align-items: flex-start; gap: 5px;">
                            <div style="width: 100%; display: flex; justify-content: space-between;">
                                <span style="font-weight: 600;"><?= htmlspecialchars($bet['event_name']) ?></span>
                                <span class="bet-badge badge-<?= strtolower($bet['status']) ?>"><?= $bet['status'] ?></span>
                            </div>
                            <div style="font-size: 14px; color: var(--text-dim)">
                                Advice: <span style="color: var(--accent-glow)"><?= $bet['advice'] ?></span> @<?= $bet['odds'] ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function analyze(id, name) {
            const btn = event.target;
            const originalText = btn.innerText;
            btn.innerText = "ANALISI IN CORSO...";
            btn.disabled = true;
            btn.style.opacity = "0.5";

            fetch('index.php?action=analyze&id=' + id + '&name=' + encodeURIComponent(name))
                .then(r => r.json())
                .then(data => {
                    alert('Analisi completata!\n\nAdvice: ' + (data.advice || 'Pass') + '\nConfidence: ' + (data.confidence || 0) + '%');
                    location.reload();
                })
                .catch(e => {
                    console.error(e);
                    alert('Errore durante l\'analisi. Verifica i log o il token.');
                    btn.innerText = originalText;
                    btn.disabled = false;
                    btn.style.opacity = "1";
                });
        }
    </script>
</body>
</html>
