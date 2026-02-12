<?php
// app/GiaNik/Views/partials/skipped_matches_sidebar.php
$skippedMatches = $skippedMatches ?? [];
?>

<div class="px-4 py-2">
    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-4 px-4">
        <i data-lucide="eye-off" class="w-3 h-3 inline-block mr-1"></i> Match Scartati
    </span>

    <div class="space-y-1">
        <?php if (empty($skippedMatches)): ?>
            <div class="px-4 py-3 opacity-20 text-[10px] font-bold uppercase italic">Nessun match scartato...</div>
        <?php else: ?>
            <?php foreach ($skippedMatches as $sm): ?>
                <div class="px-4 py-2 rounded-xl hover:bg-white/5 transition-all group relative cursor-help">
                    <div class="text-[10px] font-bold text-slate-300 truncate leading-tight"><?php echo $sm['event_name']; ?></div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-[8px] font-black px-1.5 py-0.5 rounded bg-danger/10 text-danger uppercase border border-danger/20"><?php echo $sm['reason']; ?></span>
                        <span class="text-[8px] font-medium text-slate-600"><?php echo date('H:i', strtotime($sm['created_at'])); ?></span>
                    </div>

                    <!-- Floating Tooltip (CSS Based) -->
                    <div class="invisible group-hover:visible absolute left-full top-0 ml-4 p-3 bg-slate-900 border border-white/10 rounded-2xl shadow-2xl z-[100] w-64 backdrop-blur-xl pointer-events-none">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-1.5 h-1.5 rounded-full bg-danger"></div>
                            <span class="text-[8px] text-slate-400 uppercase font-black tracking-widest">Dettaglio Esclusione</span>
                        </div>
                        <div class="text-[10px] text-white leading-relaxed font-medium">
                            <?php echo htmlspecialchars($sm['details']); ?>
                        </div>
                        <div class="mt-2 pt-2 border-t border-white/5 text-[8px] text-slate-500 italic">
                            Mercato: <?php echo $sm['market_name']; ?>
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
