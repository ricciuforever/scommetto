<?php
// app/GiaNik/Views/partials/gianik_live.php
$groupedMatches = $groupedMatches ?? [];
$account = $account ?? ['available' => 0, 'exposure' => 0];

?>

<style>
    @keyframes pulse-slow {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.8;
            transform: scale(1.02);
        }
    }

    .animate-pulse-slow {
        animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<!-- Account Summary (Compact Bar) -->
<div class="glass px-6 py-3 rounded-xl border-white/5 flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-8">
        <!-- Real Betfair Account -->
        <div class="flex items-center gap-4">
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Disponibile</span>
                <div class="text-lg font-black tabular-nums text-white leading-none">
                    €<?php echo number_format($account['available'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Esposizione</span>
                <div class="text-lg font-black tabular-nums text-warning leading-none">
                    €<?php echo number_format($account['exposure'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Totale</span>
                <div class="text-lg font-black tabular-nums text-slate-400 leading-none">
                    €<?php echo number_format($account['available'] + $account['exposure'], 2); ?></div>
            </div>

            <div class="h-8 w-px bg-white/10 mx-2"></div>

            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">P&L Netta (Settled)</span>
                <div
                    class="text-lg font-black tabular-nums <?php echo $realPortfolioStats['net_profit'] >= 0 ? 'text-success' : 'text-danger'; ?> leading-none">
                    €<?php echo number_format($realPortfolioStats['net_profit'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Win Rate</span>
                <div class="text-lg font-black tabular-nums text-indigo-400 leading-none">
                    <?php echo $realPortfolioStats['win_rate']; ?>%
                </div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">ROI</span>
                <div class="text-lg font-black tabular-nums text-accent leading-none">
                    <?php echo $realPortfolioStats['roi']; ?>%
                </div>
            </div>
        </div>

        <div class="h-8 w-px bg-white/10 mx-2"></div>

        <!-- Mode Toggle -->
        <div class="flex bg-black/40 p-1 rounded-xl border border-white/5">
            <button onclick="setGiaNikMode('virtual')" id="mode-virtual" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all <?= $operationalMode === 'virtual' ? 'bg-accent text-white shadow-lg shadow-accent/20' : 'text-slate-500 hover:text-white' ?>">
                VIRTUAL
            </button>
            <button onclick="setGiaNikMode('real')" id="mode-real" class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase tracking-widest transition-all <?= $operationalMode === 'real' ? 'bg-accent text-white shadow-lg shadow-accent/20' : 'text-slate-500 hover:text-white' ?>">
                REAL
            </button>
        </div>

        <?php if ($operationalMode !== 'real'): ?>
            <div class="h-8 w-px bg-white/10 mx-2"></div>

            <!-- Virtual GiaNik Account -->
            <div class="flex items-center gap-4">
                <div>
                    <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Disponibile</span>
                    <div class="text-lg font-black tabular-nums text-white leading-none">
                        €<?php echo number_format($virtualAccount['available'], 2); ?></div>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual In Gioco</span>
                    <div class="text-lg font-black tabular-nums text-accent leading-none">
                        €<?php echo number_format($virtualAccount['exposure'], 2); ?></div>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Totale</span>
                    <div class="text-lg font-black tabular-nums text-slate-400 leading-none">
                        €<?php echo number_format($virtualAccount['total'], 2); ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Portfolio Trend Chart -->
<div class="glass p-6 rounded-[32px] border-white/5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-[10px] font-black uppercase text-slate-500 tracking-[.2em]">Andamento Portafoglio <?php echo $operationalMode === 'real' ? 'REALE' : 'VIRTUALE'; ?> (Start 100€)
        </h4>
        <div
            class="text-[10px] font-black text-slate-400 uppercase tracking-widest bg-white/5 px-3 py-1 rounded-full border border-white/5">
            <?php echo $portfolioStats['total_bets'] ?? 0; ?> Scommesse Chiuse
        </div>
    </div>
    <div class="h-48 w-full">
        <canvas id="portfolioChart"></canvas>
    </div>
</div>

<?php if (empty($allMatches)): ?>
    <div class="text-center py-10">
        <span class="text-slate-500 text-sm font-bold uppercase">Nessun evento live disponibile</span>
    </div>
<?php else: ?>

    <!-- Events List (One per row) -->
    <div class="animate-fade-in space-y-3 px-2">
        <?php
        foreach ($allMatches as $m):
            $marketId = $m['marketId'];
            $fixtureId = $m['fixture_id'];
            $homeId = $m['home_id'];
            $awayId = $m['away_id'];
            $isJustUpdated = ($m['just_updated'] && (time() - $m['just_updated'] <= 15));
            ?>
            <div id="match-card-<?php echo str_replace('.', '-', $marketId); ?>"
                class="glass p-4 rounded-[32px] border <?php echo $isJustUpdated ? 'border-accent shadow-[0_0_20px_rgba(var(--accent-rgb),0.3)] animate-pulse' : 'border-white/5'; ?> hover:border-accent/20 transition-all group flex flex-col gap-4 relative">

                <div class="grid grid-cols-[minmax(120px,1fr)_48px_180px_48px_minmax(120px,1fr)_80px_360px] items-center gap-4 px-6">
                    <!-- Home Team -->
                    <div class="flex items-center justify-end overflow-hidden">
                        <span class="text-xs font-black uppercase text-white truncate text-right mr-4">
                            <?php echo $m['home_name']; ?>
                        </span>
                    </div>

                    <!-- Home Logo -->
                    <div class="flex justify-center shrink-0">
                        <?php if ($m['home_logo']): ?>
                            <div class="w-12 h-12 flex items-center justify-center overflow-hidden">
                                <img src="<?php echo $m['home_logo']; ?>" class="w-full h-full object-contain" alt="">
                            </div>
                        <?php else: ?>
                            <div class="w-12 h-12 flex items-center justify-center">
                                <i data-lucide="shield" class="w-6 h-6 text-slate-600"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Center: Score & Competition -->
                    <div class="flex flex-col items-center px-4">
                        <div class="flex items-center gap-1.5 mb-1">
                            <?php if ($m['flag']): ?>
                                <img src="<?php echo $m['flag']; ?>" class="w-3 h-2 object-cover rounded-sm opacity-80" alt="">
                            <?php endif; ?>
                            <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">
                                <?php echo $m['country'] ?? ''; ?>
                            </span>
                        </div>
                        <div class="text-[9px] font-black text-accent uppercase tracking-widest mb-1 text-center leading-tight truncate w-full">
                            <?php echo $m['competition']; ?>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="bg-white/10 px-4 py-1 rounded-xl border border-white/5 font-black text-xl tabular-nums text-white">
                                <?php echo $m['score'] ?: '0-0'; ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            <div class="flex items-center gap-1">
                                <?php if ($m['has_api_data']): ?>
                                    <div class="w-1 h-1 rounded-full bg-success shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                                <?php endif; ?>
                                <span class="match-status-label text-[9px] font-black uppercase text-slate-500 tracking-tighter"
                                    data-elapsed="<?php echo $m['elapsed'] ?? 0; ?>"
                                    data-status="<?php echo $m['status_short'] ?? ''; ?>">
                                    <?php echo $m['status_label']; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Away Logo -->
                    <div class="flex justify-center shrink-0">
                        <?php if ($m['away_logo']): ?>
                            <div class="w-12 h-12 flex items-center justify-center overflow-hidden">
                                <img src="<?php echo $m['away_logo']; ?>" class="w-full h-full object-contain" alt="">
                            </div>
                        <?php else: ?>
                            <div class="w-12 h-12 flex items-center justify-center">
                                <i data-lucide="shield" class="w-6 h-6 text-slate-600"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Away Team -->
                    <div class="flex items-center justify-start overflow-hidden">
                        <span class="text-xs font-black uppercase text-white truncate ml-4">
                            <?php echo $m['away_name']; ?>
                        </span>
                    </div>

                    <!-- Matched -->
                    <div class="flex flex-col border-l border-white/5 pl-4">
                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter italic">Matched</span>
                        <span class="text-sm font-black text-white leading-none">€<?php echo number_format($m['totalMatched'], 0, ',', '.'); ?></span>
                    </div>

                    <!-- Scommesse & Andamento Section -->
                    <div class="grid grid-cols-2 gap-4 border-l border-white/5 pl-6">
                        <!-- Scommesse Detail -->
                        <div class="flex flex-col gap-1 min-h-[44px] justify-center">
                            <?php if (!empty($m['my_bets'])): ?>
                                <?php foreach ($m['my_bets'] as $bet): ?>
                                    <div class="flex items-center justify-between gap-2 px-2 py-1 rounded-lg <?php echo $bet['type'] === 'real' ? 'bg-accent/10 border-accent/20' : 'bg-indigo-500/10 border-indigo-500/20'; ?> border">
                                        <div class="flex flex-col leading-none">
                                            <span class="text-[7px] font-black uppercase text-white/70 truncate max-w-[80px]"><?php echo $bet['runner']; ?></span>
                                            <span class="text-[8px] font-black text-white">@<?php echo number_format($bet['odds'], 2); ?></span>
                                        </div>
                                        <div class="text-right leading-none">
                                            <div class="text-[9px] font-black <?php echo $bet['pl'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo ($bet['pl'] >= 0 ? '+' : '') . number_format($bet['pl'], 2); ?>€
                                            </div>
                                            <span class="text-[6px] font-bold text-white/30 uppercase"><?php echo $bet['type']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-[8px] font-black text-slate-600 uppercase italic tracking-tighter">Nessuna scommessa</span>
                            <?php endif; ?>
                        </div>

                        <!-- Andamento Detail -->
                        <div class="flex flex-col justify-center border-l border-white/5 pl-3 min-h-[44px]">
                            <?php if (isset($m['intensity'])): ?>
                                <div class="flex flex-col gap-1">
                                    <div class="flex items-center gap-1.5">
                                        <i data-lucide="zap" class="w-3 h-3 <?php echo $m['intensity']['color']; ?>"></i>
                                        <span class="text-[9px] font-black <?php echo $m['intensity']['color']; ?> uppercase tracking-widest"><?php echo $m['intensity']['label']; ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[7px] font-bold text-slate-500 uppercase">Index</span>
                                        <span class="text-[8px] font-black text-white"><?php echo $m['intensity']['val']; ?>x</span>
                                    </div>
                                    <div class="flex gap-2">
                                        <div class="flex-1 bg-white/5 rounded px-1 py-0.5 flex justify-between items-center">
                                            <span class="text-[6px] font-bold text-slate-500">H</span>
                                            <span class="text-[7px] font-black text-white"><?php echo $m['intensity']['home']; ?></span>
                                        </div>
                                        <div class="flex-1 bg-white/5 rounded px-1 py-0.5 flex justify-between items-center">
                                            <span class="text-[6px] font-bold text-slate-500">A</span>
                                            <span class="text-[7px] font-black text-white"><?php echo $m['intensity']['away']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span class="text-[8px] font-black text-slate-600 uppercase italic tracking-tighter">Dati non disp.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>


<script>
    if (window.lucide) lucide.createIcons();

    // GiaNik Audio Jukebox - Idempotent declaration for HTMX
    if (!window.GiaNikAudio) {
        window.GiaNikAudio = {
            sounds: {
                event: 'https://cdn.jsdelivr.net/gh/GiaNik/assets/notification.mp3',
                win: 'https://assets.mixkit.co/active_storage/sfx/1435/1435-preview.mp3',
                loss: 'https://assets.mixkit.co/active_storage/sfx/2019/2019-preview.mp3',
                goal: 'https://assets.mixkit.co/active_storage/sfx/1430/1430-preview.mp3'
            },
            play(type) {
                if (!window.isGiaNikSoundEnabled) return;
                const src = this.sounds[type] || this.sounds.event;
                const audio = new Audio(src);
                audio.play().catch(e => console.log('Audio blocked:', e));
            }
        };
    }

    // Client-side Match Timer Ticker
    (function () {
        if (window.gianikTimerInterval) clearInterval(window.gianikTimerInterval);

        window.gianikTimerInterval = setInterval(() => {
            const labels = document.querySelectorAll('.match-status-label');
            labels.forEach(label => {
                const status = label.getAttribute('data-status');
                let elapsed = parseInt(label.getAttribute('data-elapsed'));

                const liveStatuses = ['1H', '2H', 'LIVE'];
                if (liveStatuses.includes(status) && elapsed > 0 && elapsed < 120) {
                    elapsed++;
                    label.setAttribute('data-elapsed', elapsed);
                    label.innerText = `${status} ${elapsed}'`;
                }
            });
        }, 60000);
    })();

    // Event Highlighting, Sound and Reordering
    (function () {
        const justUpdated = <?php echo json_encode(array_values(array_filter(array_map(fn($m) => $m['just_updated'] ? ['id' => $m['marketId'], 'is_goal' => ($m['is_goal'] ?? false)] : null, $allMatches)))); ?>;
        const settlement = <?php echo json_encode($settlementResults ?? ['wins' => 0, 'losses' => 0]); ?>;

        // Handle Settlements
        if (settlement.wins > 0) window.GiaNikAudio.play('win');
        else if (settlement.losses > 0) window.GiaNikAudio.play('loss');

        if (justUpdated.length > 0) {
            let playedGoal = false;

            // Reorder and highlight
            const container = document.querySelector('.animate-fade-in.space-y-3.px-2');

            justUpdated.forEach(upd => {
                const cardId = 'match-card-' + upd.id.replace('.', '-');
                const card = document.getElementById(cardId);

                if (card) {
                    // Move to top if it's not already there
                    if (container && container.firstChild !== card) {
                        container.prepend(card);
                    }

                    if (upd.is_goal) {
                        if (!playedGoal) {
                            window.GiaNikAudio.play('goal');
                            playedGoal = true;
                        }
                    } else if (settlement.wins === 0 && settlement.losses === 0) {
                        // General event sound if no goal/settlement
                        window.GiaNikAudio.play('event');
                    }

                    // Removal timer
                    setTimeout(() => {
                        const c = document.getElementById(cardId);
                        if (c) {
                            c.classList.remove('border-accent', 'shadow-[0_0_20px_rgba(var(--accent-rgb),0.3)]', 'animate-pulse');
                            c.classList.add('border-white/5');
                        }
                    }, 15000);
                }
            });
        }
    })();

    function openManualBetModal(data) {
        <?php if(!isset($_SESSION['admin_user'])): ?>
        alert('Accesso negato. Effettua il login come amministratore per operare.');
        return;
        <?php endif; ?>

        // Build modal on the fly
        const modalId = 'manual-bet-modal';
        const existing = document.getElementById(modalId);
        if (existing) existing.remove();

        const mode = window.gianikMode || 'virtual';

        const html = `
        <div id="${modalId}" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in" onclick="if(event.target === this) this.remove()">
            <div class="bg-slate-900 w-full max-w-md rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative p-8" onclick="event.stopPropagation()">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-accent/10 flex items-center justify-center text-accent">
                        <i data-lucide="zap" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black uppercase italic text-white">Punta Manuale</h3>
                        <p class="text-[9px] font-black uppercase text-slate-500 tracking-widest">${mode} Mode Active</p>
                    </div>
                </div>

                <div class="space-y-4 mb-8">
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                        <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">${data.eventName}</div>
                        <div class="text-sm font-black text-white italic uppercase">${data.runnerName}</div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 rounded-2xl bg-white/5 border border-white/5">
                            <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">Quota</div>
                            <div class="text-xl font-black text-white">@ ${data.odds}</div>
                        </div>
                        <div class="p-4 rounded-2xl bg-indigo-500/10 border border-indigo-500/20">
                            <div class="text-[8px] font-black text-indigo-400 uppercase tracking-widest mb-1">Stake (€)</div>
                            <input type="number" id="manual-stake" value="2.00" step="0.50" min="2.00" 
                                class="w-full bg-transparent border-none p-0 text-xl font-black text-white outline-none focus:ring-0">
                        </div>
                    </div>
                </div>

                <button id="manual-bet-confirm" 
                    class="w-full py-4 rounded-2xl bg-accent hover:bg-accent/80 text-white font-black uppercase tracking-widest text-xs transition-all shadow-lg shadow-accent/20 flex items-center justify-center gap-2">
                    <i data-lucide="check-circle" class="w-4 h-4"></i> Conferma Scommessa (${mode.toUpperCase()})
                </button>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
        if (window.lucide) lucide.createIcons();

        document.getElementById('manual-bet-confirm').onclick = async function () {
            const btn = this;
            const stake = parseFloat(document.getElementById('manual-stake').value);
            btn.disabled = true;
            btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Invio...';
            if (window.lucide) lucide.createIcons();

            try {
                const res = await fetch('/api/gianik/place-bet', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        marketId: data.marketId,
                        selectionId: data.selectionId,
                        runnerName: data.runnerName,
                        eventName: data.eventName,
                        sport: data.sport,
                        odds: data.odds,
                        stake: stake,
                        type: mode,
                        motivation: 'Piazzata manualmente dall\'utente.'
                    })
                });
                const result = await res.json();
                if (result.status === 'success') {
                    alert('Scommessa piazzata con successo!');
                    document.getElementById(modalId).remove();
                } else {
                    alert('Errore: ' + result.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Conferma Scommessa';
                    if (window.lucide) lucide.createIcons();
                }
            } catch (err) {
                alert('Errore di rete: ' + err.message);
                btn.disabled = false;
                btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Conferma Scommessa';
            }
        };
    }
</script>

<!-- Initialize Chart.js safely -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        // Prevent double init if already active
        if (window.myPortfolioChart) {
            window.myPortfolioChart.destroy();
        }

        const ctx = document.getElementById('portfolioChart');
        if (!ctx) return;

        // Wait for Chart.js to load if using CDN
        const initChart = () => {
            if (typeof Chart === 'undefined') {
                setTimeout(initChart, 100);
                return;
            }

            window.myPortfolioChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($portfolioStats['labels'] ?? []); ?>,
                    datasets: [{
                        label: 'Bilancio (€)',
                        data: <?php echo json_encode($portfolioStats['history'] ?? [100]); ?>,
                        borderWidth: 3,
                        pointRadius: 0,
                        pointHoverRadius: 0,
                        fill: {
                            target: { value: 100 },
                            above: 'rgba(34, 197, 94, 0.15)',
                            below: 'rgba(239, 68, 68, 0.15)'
                        },
                        tension: 0.4,
                        segment: {
                            borderColor: ctx => {
                                if (ctx.p1.parsed.y > 100) return '#22c55e';
                                if (ctx.p1.parsed.y < 100) return '#ef4444';
                                return '#64748b';
                            }
                        }
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { size: 10, weight: 'bold' },
                            bodyFont: { size: 12, weight: '900' },
                            padding: 12,
                            displayColors: false,
                            callbacks: {
                                label: function (context) {
                                    return '€' + context.parsed.y.toFixed(2);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { size: 9, weight: '900' } }
                        },
                        y: {
                            grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                            ticks: {
                                color: '#64748b',
                                font: { size: 9, weight: '700' },
                                callback: function (value) { return '€' + value; }
                            }
                        }
                    }
                },
                plugins: [{
                    id: 'thresholdLine',
                    afterDraw: chart => {
                        const { ctx, chartArea: { left, right }, scales: { y } } = chart;
                        const yPos = y.getPixelForValue(100);
                        ctx.save();
                        ctx.beginPath();
                        ctx.lineWidth = 1;
                        ctx.strokeStyle = 'rgba(255, 255, 255, 0.4)'; // Sottile ma visibile
                        ctx.moveTo(left, yPos);
                        ctx.lineTo(right, yPos);
                        ctx.stroke();
                        ctx.restore();
                    }
                }]
            });
        };

        if (window.Chart) {
            initChart();
        } else {
            // Find the script tag and wait for load
            const script = document.querySelector('script[src*="chart.js"]');
            if (script) {
                script.addEventListener('load', initChart);
            } else {
                initChart();
            }
        }
    })();
</script>