<?php
// app/GiaNik/Views/partials/recent_bets_sidebar.php
$bets = $bets ?? [];
?>

<div class="h-full flex flex-col">
    <div class="p-4 border-b border-white/10">
        <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500">Ultime Giocate GiaNik</h3>
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

                    <div class="text-[8px] font-black text-accent uppercase mb-1 opacity-60"><?php echo $bet['sport']; ?></div>
                    <div class="text-[10px] font-bold text-white leading-tight mb-2 group-hover:text-accent transition-colors truncate"><?php echo $bet['event_name']; ?></div>

                    <div class="flex items-center justify-between mt-2">
                        <div class="flex flex-col">
                            <span class="text-[8px] text-slate-500 uppercase font-black">Runner</span>
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
</script>
