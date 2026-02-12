<?php
// app/GiaNik/Views/partials/recent_bets_sidebar.php
$bets = $bets ?? [];
?>

<div class="h-full flex flex-col">
    <div class="p-4 border-b border-white/10">
        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-3">Ultime Giocate GiaNik</h3>

        <div class="flex flex-col gap-3">
            <!-- Status Filters (Unified) -->
            <div class="flex flex-wrap gap-2">
                <label class="cursor-pointer">
                    <input type="radio" name="status" value="all" <?php echo ($currentStatus === 'all' ? 'checked' : ''); ?>
                        hx-get="/api/gianik/recent-bets" hx-include="[name='status']:checked" hx-target="#recent-bets-container"
                        class="hidden peer">
                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-white/10 bg-white/5 text-slate-500 peer-checked:bg-slate-500 peer-checked:text-white transition-all">Tutte</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="status" value="pending" <?php echo ($currentStatus === 'pending' ? 'checked' : ''); ?>
                        hx-get="/api/gianik/recent-bets" hx-include="[name='status']:checked" hx-target="#recent-bets-container"
                        class="hidden peer">
                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-warning/20 bg-warning/5 text-warning peer-checked:bg-warning peer-checked:text-white transition-all">In Corso</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="status" value="won" <?php echo ($currentStatus === 'won' ? 'checked' : ''); ?>
                        hx-get="/api/gianik/recent-bets" hx-include="[name='status']:checked" hx-target="#recent-bets-container"
                        class="hidden peer">
                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-success/20 bg-success/5 text-success peer-checked:bg-success peer-checked:text-white transition-all">Vinte</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="status" value="lost" <?php echo ($currentStatus === 'lost' ? 'checked' : ''); ?>
                        hx-get="/api/gianik/recent-bets" hx-include="[name='status']:checked" hx-target="#recent-bets-container"
                        class="hidden peer">
                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-danger/20 bg-danger/5 text-danger peer-checked:bg-danger peer-checked:text-white transition-all">Perse</span>
                </label>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto no-scrollbar py-2">
        <?php if ($currentStatus === 'all' && !empty($skippedMatches)): ?>
            <div class="px-4 py-2 bg-slate-900/50 border-y border-white/5 mb-2">
                 <h4 class="text-[8px] font-black uppercase text-slate-500 tracking-widest flex items-center gap-2">
                     <i data-lucide="eye-off" class="w-2.5 h-2.5"></i> Match Scartati (Ultimi)
                 </h4>
            </div>
            <?php foreach ($skippedMatches as $sm): ?>
                <div class="px-4 py-2 border-b border-white/5 opacity-50 hover:opacity-100 transition-opacity group relative">
                    <div class="text-[9px] font-bold text-slate-300 truncate"><?php echo $sm['event_name']; ?></div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-[7px] font-black px-1.5 py-0.5 rounded bg-danger/10 text-danger uppercase border border-danger/20"><?php echo $sm['reason']; ?></span>
                        <span class="text-[7px] font-medium text-slate-600"><?php echo date('H:i', strtotime($sm['created_at'])); ?></span>
                    </div>
                    <!-- Tooltip con dettagli -->
                    <div class="hidden group-hover:block absolute left-full top-0 ml-2 p-2 bg-slate-900 border border-white/10 rounded-lg shadow-2xl z-50 w-48 pointer-events-none">
                        <div class="text-[8px] text-slate-400 uppercase font-black mb-1">Dettaglio Scarto</div>
                        <div class="text-[9px] text-white leading-tight"><?php echo htmlspecialchars($sm['details']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="px-4 py-2 bg-slate-900/50 border-y border-white/5 my-2">
                 <h4 class="text-[8px] font-black uppercase text-slate-500 tracking-widest flex items-center gap-2">
                     <i data-lucide="history" class="w-2.5 h-2.5"></i> Storico Giocate
                 </h4>
            </div>
        <?php endif; ?>

        <?php if (empty($bets)): ?>
            <div class="p-10 text-center opacity-20 text-[10px] font-bold uppercase italic">In attesa di analisi...</div>
        <?php else: ?>
            <?php foreach ($bets as $bet):
                $status = $bet['status'];
                $statusColor = 'text-slate-500';
                if ($status === 'won') {
                    $statusColor = 'text-success';
                } elseif ($status === 'lost') {
                    $statusColor = 'text-danger';
                }

                $profit = (float)$bet['profit'];
                $profitFormatted = ($profit > 0 ? '+' : '') . number_format($profit, 2) . '€';
            ?>
                <div
                    onclick="openBetDetails(<?php echo $bet['id']; ?>)"
                    class="p-4 border-b border-white/5 hover:bg-white/5 transition-all cursor-pointer group relative overflow-hidden">

                    <?php if ($status === 'won'): ?>
                        <div class="absolute top-0 right-0 w-8 h-8 bg-success/10 rounded-bl-full flex items-center justify-end pr-1 pt-1">
                             <i data-lucide="check" class="w-3 h-3 text-success"></i>
                        </div>
                    <?php elseif ($status === 'lost'): ?>
                        <div class="absolute top-0 right-0 w-8 h-8 bg-danger/10 rounded-bl-full flex items-center justify-end pr-1 pt-1">
                             <i data-lucide="x" class="w-3 h-3 text-danger"></i>
                        </div>
                    <?php endif; ?>

                    <div class="text-[8px] font-black text-accent uppercase mb-1 opacity-60"><?php echo $bet['sport_it'] ?? $bet['sport']; ?></div>
                    <div class="text-[10px] font-bold text-white leading-tight mb-2 group-hover:text-accent transition-colors truncate"><?php echo $bet['event_name']; ?></div>

                    <div class="flex items-center justify-between mt-2">
                        <div class="flex flex-col">
                            <span class="text-[8px] text-slate-500 uppercase font-black"><?php echo htmlspecialchars($bet['market_name'] ?? 'Runner'); ?></span>
                            <span class="text-[10px] font-black text-slate-300"><?php echo $bet['runner_name']; ?></span>
                        </div>
                        <div class="text-right">
                             <span class="text-[10px] font-black text-white">@<?php echo number_format($bet['odds'], 2); ?></span>
                             <div class="text-[8px] font-black <?php echo $statusColor; ?> uppercase">
                                <?php echo $status === 'pending' ? 'Puntata: ' . $bet['stake'] . '€' : $profitFormatted; ?>
                             </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();

    // Maintain radio state after HTMX swap and update container hx-get for auto-refresh
    (function() {
        const container = document.querySelector('#recent-bets-container');
        const status = "<?php echo $currentStatus; ?>";

        // Ensure the container's own hx-get attribute includes the filters for the next 'every 30s' trigger
        container.setAttribute('hx-get', '/api/gianik/recent-bets?status=' + status);
    })();
</script>
