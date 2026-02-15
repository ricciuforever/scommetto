<?php
// app/GiaNik/Views/partials/modals/gianik_analysis.php
$analysis = $analysis ?? [];
$reasoning = $reasoning ?? '';
$event = $event ?? [];
?>

<div id="gianik-analysis-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
        onclick="event.stopPropagation()">
        <button onclick="document.getElementById('gianik-analysis-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10">
            <div class="flex items-center gap-3 mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                    <i data-lucide="brain-circuit" class="w-7 h-7"></i>
                </div>
                <div>
                    <h3 class="text-3xl font-black tracking-tight text-white uppercase italic leading-none">Analisi
                        <span class="text-accent">GiaNik</span>
                    </h3>
                    <p class="text-slate-500 text-[9px] font-black uppercase tracking-widest mt-1">Intelligenza
                        Artificiale Betfair</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Event Header -->
                <div class="glass p-6 rounded-3xl border-white/5 relative">
                    <?php if (isset($event['api_football'])): ?>
                        <div
                            class="absolute top-4 right-4 flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[8px] font-black uppercase tracking-wider shadow-lg shadow-emerald-500/5">
                            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-pulse"></span>
                            Analisi Potenziata (Dati Live)
                        </div>
                    <?php endif; ?>
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">
                        <?php echo htmlspecialchars($event['sport'] ?? 'Sport'); ?> -
                        <?php echo htmlspecialchars($event['competition'] ?? ''); ?>
                    </div>
                    <div class="text-xl font-black italic uppercase text-white">
                        <?php echo htmlspecialchars($event['event'] ?? 'Evento'); ?>
                    </div>
                </div>

                <?php if (!empty($analysis)): ?>
                    <div class="glass p-5 rounded-3xl border-white/5 flex items-center justify-between">
                        <div>
                            <div class="text-[8px] font-black text-slate-500 uppercase tracking-[.2em] mb-1">Mercato
                                Selezionato</div>
                            <div class="text-sm font-black italic uppercase text-indigo-400">
                                <?php
                                $selMarket = null;
                                $searchId = $analysis['marketId'] ?? ($event['requestedMarketId'] ?? '');
                                foreach (($event['markets'] ?? []) as $m) {
                                    if ($m['marketId'] === $searchId) {
                                        $selMarket = $m;
                                        break;
                                    }
                                }
                                echo htmlspecialchars($selMarket['marketName'] ?? 'Analisi Globale');
                                ?>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-[8px] font-black text-slate-500 uppercase tracking-[.2em] mb-1">Market ID</div>
                            <div class="text-[10px] font-mono text-slate-400">
                                <?php echo htmlspecialchars($analysis['marketId'] ?? 'N/A'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4">
                        <div class="glass p-5 rounded-3xl border-white/5">
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Consiglio</div>
                            <div class="text-sm font-black italic uppercase text-accent">
                                <?php echo htmlspecialchars($analysis['advice'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="glass p-5 rounded-3xl border-white/5">
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Quota
                            </div>
                            <div class="text-lg font-black italic uppercase text-white">
                                @ <?php echo htmlspecialchars($analysis['odds'] ?? '0.00'); ?>
                            </div>
                        </div>
                        <div class="glass p-5 rounded-3xl border-indigo-500/30 bg-indigo-500/10">
                            <div class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-1">Stake Kelly</div>
                            <div class="text-lg font-black italic uppercase text-white">
                                <?php echo number_format($analysis['stake'] ?? 0, 2); ?>€
                            </div>
                        </div>
                    </div>

                    <div class="glass p-6 rounded-3xl border-indigo-500/20 bg-indigo-500/5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Confidence &
                                Sentiment</div>
                            <div class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-400 text-[10px] font-black italic">
                                <?php echo htmlspecialchars($analysis['confidence'] ?? 0); ?>%
                            </div>
                        </div>
                        <div class="text-sm font-bold text-white italic">
                            "<?php echo htmlspecialchars($analysis['sentiment'] ?? 'Analisi neutrale'); ?>"
                        </div>
                    </div>
                <?php endif; ?>

                <div class="glass p-6 rounded-3xl border-white/5">
                    <div class="text-[10px] font-black text-accent uppercase tracking-widest mb-3">Motivazione Tecnica
                        AI</div>
                    <div
                        class="text-sm italic text-slate-300 bg-black/20 p-5 rounded-2xl max-h-48 overflow-y-auto leading-relaxed">
                        <?php
                        echo nl2br(htmlspecialchars($reasoning));
                        ?>
                    </div>
                </div>

                <div class="pt-4 flex gap-4">
                    <button onclick="document.getElementById('gianik-analysis-modal').remove()"
                        class="flex-1 py-4 rounded-2xl bg-white/5 hover:bg-white/10 text-white font-black uppercase tracking-widest text-xs transition-all border border-white/5">
                        Chiudi
                    </button>

                    <?php if (!empty($analysis)): ?>
                        <?php if(isset($_SESSION['admin_user']) && ($analysis['stake'] ?? 0) >= 2.0): ?>
                            <button onclick="placeGiaNikBet()" id="bet-btn"
                                class="flex-[2] py-4 rounded-2xl bg-accent hover:bg-accent/80 text-white font-black uppercase tracking-widest text-xs transition-all shadow-lg shadow-accent/20 flex items-center justify-center gap-2">
                                <i data-lucide="zap" class="w-4 h-4"></i> Piazza Scommessa da <?php echo number_format($analysis['stake'], 2); ?>€ <span id="btn-mode-label"
                                    class="opacity-50 ml-1"></span>
                            </button>
                        <?php elseif(!isset($_SESSION['admin_user'])): ?>
                            <div class="flex-[2] py-4 rounded-2xl bg-slate-800 text-slate-500 font-black uppercase tracking-widest text-[10px] flex items-center justify-center text-center border border-white/5">
                                Login necessario per operare
                            </div>
                        <?php else: ?>
                            <div class="flex-[2] py-4 rounded-2xl bg-slate-800 text-slate-500 font-black uppercase tracking-widest text-[10px] flex items-center justify-center text-center border border-white/5">
                                Edge Insufficiente o Stake troppo basso
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            if (window.lucide) lucide.createIcons();

            // Update label based on global mode
            const modeLabel = document.getElementById('btn-mode-label');
            if (modeLabel) {
                modeLabel.innerText = '(' + (window.gianikMode || 'virtual').toUpperCase() + ')';
            }

            // Expose the function to the global scope so the onclick handler can find it
            window.placeGiaNikBet = async function () {
                const btn = document.getElementById('bet-btn');
                const originalContent = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Elaborazione...';
                if (window.lucide) lucide.createIcons();

                const analysis = <?php echo json_encode($analysis); ?>;
                const eventData = <?php echo json_encode($event); ?>;

                const betData = {
                    marketId: analysis.marketId,
                    marketName: 'Unknown',
                    eventName: eventData.event,
                    sport: eventData.sport,
                    leagueId: eventData.api_football?.fixture?.league_id || null,
                    competition: eventData.competition,
                    advice: analysis.advice,
                    odds: parseFloat(analysis.odds || 0),
                    stake: parseFloat(analysis.stake || 2.0),
                    type: window.gianikMode || 'virtual',
                    motivation: <?php echo json_encode($reasoning); ?>,
                    runnerName: analysis.advice,
                    selectionId: null
                };

                // Find market and runner info
                const selectedMarket = (eventData.markets || []).find(m => m.marketId === betData.marketId);
                if (selectedMarket) {
                    betData.marketName = selectedMarket.marketName;
                    const runner = (selectedMarket.runners || []).find(r =>
                        r.name.toLowerCase() === betData.runnerName.toLowerCase() ||
                        r.name.toLowerCase().includes(betData.runnerName.toLowerCase())
                    );
                    if (runner) betData.selectionId = runner.selectionId;
                }

                try {
                    const response = await fetch('/api/gianik/place-bet', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(betData)
                    });
                    const result = await response.json();

                    if (result.status === 'success') {
                        alert('Scommessa piazzata con successo!');
                        document.getElementById('gianik-analysis-modal').remove();
                    } else {
                        alert('Errore: ' + result.message);
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                        if (window.lucide) lucide.createIcons();
                    }
                } catch (err) {
                    alert('Errore di rete: ' + err.message);
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                    if (window.lucide) lucide.createIcons();
                }
            };
        })();
    </script>
</div>