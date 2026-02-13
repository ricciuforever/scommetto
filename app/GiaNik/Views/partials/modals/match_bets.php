<?php
// app/GiaNik/Views/partials/modals/match_bets.php
$event = $event ?? [];
?>

<div id="match-bets-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative flex flex-col max-h-[90vh]"
        onclick="event.stopPropagation()">

        <div class="p-8 border-b border-white/5 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                    <i data-lucide="layout-grid" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-white uppercase italic leading-none">Mercati <span class="text-accent">Betfair</span></h3>
                    <p class="text-slate-500 text-[9px] font-black uppercase tracking-widest mt-1"><?php echo htmlspecialchars($event['event'] ?? ''); ?></p>
                </div>
            </div>
            <button onclick="document.getElementById('match-bets-modal').remove()"
                class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            <?php if (empty($event['markets'])): ?>
                <div class="text-center py-10">
                    <span class="text-slate-500 text-sm font-bold uppercase">Nessun mercato aperto disponibile</span>
                </div>
            <?php else: ?>
                <?php foreach ($event['markets'] as $m): ?>
                    <div class="glass p-6 rounded-3xl border-white/5 bg-white/[0.02]">
                        <div class="flex items-center justify-between mb-4 border-b border-white/5 pb-3">
                            <div>
                                <h4 class="text-sm font-black text-white uppercase italic"><?php echo htmlspecialchars($m['marketName']); ?></h4>
                                <span class="text-[8px] font-mono text-slate-500">ID: <?php echo $m['marketId']; ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-tighter">Matched</span>
                                <div class="text-xs font-black text-white">€<?php echo number_format($m['totalMatched'], 0, ',', '.'); ?></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <?php foreach ($m['runners'] as $r): ?>
                                <button
                                    onclick='openManualBetModal({
                                        marketId: "<?php echo $m['marketId']; ?>",
                                        selectionId: "<?php echo $r['selectionId']; ?>",
                                        runnerName: "<?php echo addslashes($r['name']); ?>",
                                        eventName: "<?php echo addslashes($event['event']); ?>",
                                        sport: "<?php echo $event['sport']; ?>",
                                        odds: <?php echo $r['back']; ?>
                                    })'
                                    class="flex items-center justify-between p-3 rounded-2xl bg-white/5 border border-white/5 hover:border-accent/30 hover:bg-accent/5 transition-all group">
                                    <span class="text-xs font-bold text-slate-300 group-hover:text-white"><?php echo htmlspecialchars($r['name']); ?></span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-accent">@ <?php echo number_format($r['back'], 2); ?></span>
                                        <i data-lucide="plus-circle" class="w-3 h-3 text-slate-600 group-hover:text-accent"></i>
                                    </div>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        if (window.lucide) lucide.createIcons();
    </script>
</div>