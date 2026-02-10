<?php
// app/GiaNik/Views/partials/recent_bets_sidebar.php
$bets = $bets ?? [];
?>

<div class="h-full flex flex-col">
    <div class="p-4 border-b border-white/10">
        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-3">Ultime Giocate GiaNik</h3>

        <div class="flex flex-col gap-3">
            <!-- Status Filters -->
            <div class="flex gap-2">
                <label class="cursor-pointer">
                    <input type="radio" name="status" value="all" <?php echo ($currentStatus === 'all' ? 'checked' : ''); ?>
                        hx-get="/api/gianik/recent-bets" hx-include="[name='status']:checked, [name='sport']" hx-target="#recent-bets-container"
                        class="hidden peer">
                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-white/10 bg-white/5 text-slate-500 peer-checked:bg-slate-500 peer-checked:text-white transition-all">Tutte</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="status" value="won" <?php echo ($currentStatus === 'won' ? 'checked' : ''); ?>
                        hx-get="/api/gianik/recent-bets" hx-include="[name='status']:checked, [name='sport']" hx-target="#recent-bets-container"
                        class="hidden peer">
                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-success/20 bg-success/5 text-success peer-checked:bg-success peer-checked:text-white transition-all">Vinte</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="status" value="lost" <?php echo ($currentStatus === 'lost' ? 'checked' : ''); ?>
                        hx-get="/api/gianik/recent-bets" hx-include="[name='status']:checked, [name='sport']" hx-target="#recent-bets-container"
                        class="hidden peer">
                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-danger/20 bg-danger/5 text-danger peer-checked:bg-danger peer-checked:text-white transition-all">Perse</span>
                </label>
            </div>

            <!-- Sport Filter -->
            <div>
                <select name="sport"
                    hx-get="/api/gianik/recent-bets"
                    hx-include="[name='status']:checked, [name='sport']"
                    hx-target="#recent-bets-container"
                    class="w-full bg-white/5 border border-white/10 rounded-lg text-[10px] font-bold text-slate-300 px-2 py-1 focus:outline-none focus:border-accent transition-all appearance-none cursor-pointer">
                    <option value="all">Tutti gli Sport</option>
                    <?php foreach ($sports as $s): ?>
                        <option value="<?php echo $s['id']; ?>" <?php echo ($currentSport === $s['id'] ? 'selected' : ''); ?>>
                            <?php echo $s['name']; ?> (<?php echo $s['count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto no-scrollbar py-2">
        <?php if (empty($bets)): ?>
            <div class="p-10 text-center opacity-20 text-[10px] font-bold uppercase italic">In attesa di analisi...</div>
        <?php else: ?>
            <?php foreach ($bets as $bet):
                $status = $bet['status'];
                $statusColor = 'text-slate-500';
                if ($status === 'won') $statusColor = 'text-success';
                if ($status === 'lost') $statusColor = 'text-danger';

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
        const sport = "<?php echo $currentSport; ?>";

        // Ensure the container's own hx-get attribute includes both filters for the next 'every 30s' trigger
        container.setAttribute('hx-get', '/api/gianik/recent-bets?status=' + status + '&sport=' + sport);
    })();
</script>
