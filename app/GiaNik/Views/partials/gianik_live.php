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
        </div>

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
    </div>
</div>

<?php if (empty($groupedMatches)): ?>
    <div class="text-center py-10">
        <span class="text-slate-500 text-sm font-bold uppercase">Nessun evento live disponibile</span>
    </div>
<?php else: ?>

    <!-- Events List (One per row) -->
    <div class="animate-fade-in space-y-3 px-2">
        <?php
        $allMatches = [];
        foreach ($groupedMatches as $matches)
            $allMatches = array_merge($allMatches, $matches);
        foreach ($allMatches as $m):
            $marketId = $m['marketId'];
            $fixtureId = $m['fixture_id'];
            $homeId = $m['home_id'];
            $awayId = $m['away_id'];
            ?>
            <div
                class="glass p-4 rounded-[32px] border border-white/5 hover:border-accent/20 transition-all group flex flex-col gap-4">

                <div class="flex items-center gap-6">
                    <!-- Match Info Section -->
                    <div class="flex-1 flex items-center justify-between">

                        <!-- Home Team -->
                        <div class="flex items-center gap-4 flex-1 justify-end cursor-pointer hover:opacity-80 transition-opacity"
                            <?php if ($homeId): ?>
                                hx-get="/api/gianik/team-details?teamId=<?php echo $homeId; ?>&leagueId=<?php echo $m['league_id'] ?? ''; ?>&season=<?php echo $m['season'] ?? ''; ?>"
                                hx-target="#global-modal-container" <?php endif; ?>>
                            <span class="text-xs font-black uppercase text-white truncate text-right max-w-[200px]">
                                <?php echo $m['home_name']; ?>
                            </span>
                            <?php if ($m['home_logo']): ?>
                                <div
                                    class="w-10 h-10 rounded-full bg-white/5 p-2 border border-white/5 flex items-center justify-center overflow-hidden shrink-0">
                                    <img src="<?php echo $m['home_logo']; ?>" class="w-full h-full object-contain" alt="">
                                </div>
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0">
                                    <i data-lucide="shield" class="w-5 h-5 text-slate-600"></i>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Center: Score & Competition -->
                        <div class="flex flex-col items-center min-w-[180px] px-4">
                            <div class="flex items-center gap-1.5 mb-1">
                                <?php if ($m['flag']): ?>
                                    <img src="<?php echo $m['flag']; ?>" class="w-3 h-2 object-cover rounded-sm opacity-80" alt="">
                                <?php endif; ?>
                                <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">
                                    <?php echo $m['country'] ?? ''; ?>
                                </span>
                            </div>
                            <div
                                class="text-[9px] font-black text-accent uppercase tracking-widest mb-1 text-center leading-tight">
                                <?php echo $m['competition']; ?>
                            </div>
                            <div class="flex items-center gap-2">
                                <div
                                    class="bg-white/10 px-4 py-1 rounded-xl border border-white/5 font-black text-xl tabular-nums text-white">
                                    <?php echo $m['score'] ?: '0-0'; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 mt-1">
                                <?php if ($m['has_api_data']): ?>
                                    <div class="w-1 h-1 rounded-full bg-success shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                                <?php endif; ?>
                                <span class="text-[9px] font-black uppercase text-slate-500 tracking-tighter">
                                    <?php echo $m['status_label']; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Manual Betting Section (1X2) -->
                        <div class="flex items-center gap-2 px-6">
                            <?php foreach (($m['runners'] ?? []) as $r):
                                if (in_array($r['name'], [$m['home_name'], $m['away_name'], 'The Draw'])):
                                    $label = ($r['name'] === 'The Draw') ? 'X' : (($r['name'] === $m['home_name']) ? '1' : '2');
                                    ?>
                                    <button onclick='openManualBetModal(<?php echo json_encode([
                                        "marketId" => $m["marketId"],
                                        "marketName" => "Match Odds",
                                        "selectionId" => $r["selectionId"],
                                        "runnerName" => $r["name"],
                                        "odds" => $r["back"],
                                        "eventName" => $m["event"],
                                        "sport" => $m["sport"]
                                    ]); ?>)'
                                        class="flex flex-col items-center justify-center w-12 h-12 rounded-xl bg-indigo-500/5 hover:bg-indigo-500/20 border border-indigo-500/10 transition-all">
                                        <span class="text-[9px] font-black text-slate-500 uppercase"><?php echo $label; ?></span>
                                        <span
                                            class="text-xs font-black text-indigo-400 tabular-nums"><?php echo $r['back'] !== '-' ? number_format((float) $r['back'], 2) : '-'; ?></span>
                                    </button>
                                <?php endif; endforeach; ?>
                        </div>

                        <!-- Away Team -->
                        <div class="flex items-center gap-4 flex-1 justify-start cursor-pointer hover:opacity-80 transition-opacity"
                            <?php if ($awayId): ?>
                                hx-get="/api/gianik/team-details?teamId=<?php echo $awayId; ?>&leagueId=<?php echo $m['league_id'] ?? ''; ?>&season=<?php echo $m['season'] ?? ''; ?>"
                                hx-target="#global-modal-container" <?php endif; ?>>
                            <?php if ($m['away_logo']): ?>
                                <div
                                    class="w-10 h-10 rounded-full bg-white/5 p-2 border border-white/5 flex items-center justify-center overflow-hidden shrink-0">
                                    <img src="<?php echo $m['away_logo']; ?>" class="w-full h-full object-contain" alt="">
                                </div>
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0">
                                    <i data-lucide="shield" class="w-5 h-5 text-slate-600"></i>
                                </div>
                            <?php endif; ?>
                            <span class="text-xs font-black uppercase text-white truncate max-w-[200px]">
                                <?php echo $m['away_name']; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Action Section -->
                    <div class="flex items-center gap-4 min-w-[320px] border-l border-white/5 pl-6">
                        <div class="flex flex-col mr-2">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter italic">Matched</span>
                            <span
                                class="text-sm font-black text-white leading-none">€<?php echo number_format($m['totalMatched'], 0, ',', '.'); ?></span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button hx-get="/api/gianik/analyze?marketId=<?php echo $marketId; ?>"
                                hx-target="#global-modal-container"
                                hx-indicator="#indicator-<?php echo str_replace('.', '-', $marketId); ?>"
                                class="px-4 py-3 bg-accent/10 hover:bg-accent/20 text-accent rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 relative border border-accent/10">
                                <div id="indicator-<?php echo str_replace('.', '-', $marketId); ?>"
                                    class="htmx-indicator absolute inset-0 flex items-center justify-center bg-accent/10 rounded-xl">
                                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                </div>
                                <i data-lucide="brain-circuit" class="w-4 h-4"></i> Analisi IA
                            </button>

                            <button hx-get="/api/gianik/predictions?fixtureId=<?php echo $fixtureId; ?>"
                                hx-target="#global-modal-container"
                                class="p-3 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 rounded-xl border border-indigo-500/10 transition-all"
                                title="Predictions Crystal Ball">
                                <span class="text-lg">🔮</span>
                            </button>

                            <?php if ($fixtureId): ?>
                                <div class="flex items-center gap-1">
                                    <button onclick="openMatchH2HModal(<?php echo $fixtureId; ?>)"
                                        class="p-3 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 rounded-xl border border-amber-500/10 transition-all"
                                        title="🔄 H2H & Form">
                                        <i data-lucide="arrow-left-right" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Detailed Match Info (Events, Stadium, etc) -->
                <?php if ($fixtureId): ?>
                    <div class="border-t border-white/5 pt-4" hx-get="/api/gianik/match-details?fixtureId=<?php echo $fixtureId; ?>"
                        hx-trigger="load" hx-swap="innerHTML">
                        <div class="flex items-center justify-center py-2 opacity-20">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<script>
    if (window.lucide) lucide.createIcons();

    function openManualBetModal(data) {
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