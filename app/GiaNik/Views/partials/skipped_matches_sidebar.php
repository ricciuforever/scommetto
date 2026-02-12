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
                <div class="px-4 py-2 rounded-xl hover:bg-white/5 transition-all group cursor-pointer" onclick="this.querySelector('.detail-box').classList.toggle('hidden')">
                    <div class="text-[10px] font-bold text-slate-300 truncate leading-tight"><?php echo $sm['event_name']; ?></div>
                    <div class="flex items-center justify-between mt-1">
                        <span class="text-[8px] font-black px-1.5 py-0.5 rounded bg-danger/10 text-danger uppercase border border-danger/20"><?php echo $sm['reason']; ?></span>
                        <span class="text-[8px] font-medium text-slate-600"><?php echo date('H:i', strtotime($sm['created_at'])); ?></span>
                    </div>

                    <!-- Expandable Detail Box -->
                    <div class="detail-box hidden mt-2 p-2 bg-black/20 rounded-lg border border-white/5">
                        <div class="text-[9px] text-slate-300 leading-relaxed">
                            <?php echo htmlspecialchars($sm['details']); ?>
                        </div>
                        <div class="mt-1 pt-1 border-t border-white/5 text-[7px] text-slate-500 italic">
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
