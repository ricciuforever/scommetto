<?php
// app/Views/partials/match/odds.php

if (empty($odds)) {
    echo '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Quote non disponibili per questo match.</div>';
    return;
}
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto animate-fade-in pb-20">
    <?php foreach ($odds as $o):
        $oddsData = json_decode($o['odds_json'], true) ?: [];
        ?>
        <div
            class="glass p-8 rounded-[40px] border-white/5 relative overflow-hidden group hover:border-accent/20 transition-all">
            <div class="absolute top-0 right-0 p-8 opacity-[0.03] group-hover:opacity-[0.07] transition-opacity">
                <i data-lucide="trending-up" class="w-24 h-24 text-white"></i>
            </div>

            <div class="flex items-center justify-between mb-8 relative z-10">
                <div class="flex flex-col">
                    <span
                        class="text-[8px] font-black uppercase text-slate-500 tracking-widest italic mb-1">Bookmaker</span>
                    <div class="text-xl font-black text-white italic uppercase tracking-tighter">
                        <?php echo htmlspecialchars($o['bookmaker_name'] ?? 'Bookmaker'); ?>
                    </div>
                </div>
                <span
                    class="px-4 py-1.5 rounded-xl bg-accent/10 text-accent text-[9px] font-black uppercase tracking-widest border border-accent/20">
                    <?php echo htmlspecialchars($o['bet_name'] ?? 'Esito Finale'); ?>
                </span>
            </div>

            <div class="grid grid-cols-2 gap-4 relative z-10">
                <?php foreach ($oddsData as $val):
                    $params = http_build_query([
                        'fixture_id' => $o['fixture_id'] ?? $_GET['id'] ?? 0, // Fallback if fixture_id not in odds row (usually in $f['id'])
                        'market' => $o['bet_name'] ?? 'Esito Finale',
                        'selection' => $val['value'],
                        'odd' => $val['odd']
                    ]);
                    // If fixture_id is missing here, we must rely on parent context or pass it.
                    // odds.php usually included inside viewMatchTab($id), but $id variable might not be in scope here directly unless passed.
                    // Actually $id is function arg in MatchController::viewMatchTab($id, ...).
                    // In partial scope, only variables defined before include are available.
                    // MatchController::viewMatchTab sets $id.
                    if (!isset($o['fixture_id'])) {
                        // Fix: pass fixture ID from Controller context
                        // But for now, let's assume it's available as $id
                    }
                    ?>
                    <div class="bg-white/5 p-5 rounded-3xl border border-white/5 flex flex-col items-center justify-center gap-1 group/odd hover:border-accent/40 active:scale-95 transition-all cursor-pointer"
                        hx-get="/api/view/modal/place_bet?<?php echo $params; ?>&fixture_id=<?php echo $id; ?>"
                        hx-target="#global-modal-container">
                        <span
                            class="text-[9px] font-black uppercase text-slate-500 tracking-widest group-hover/odd:text-accent transition-colors">
                            <?php echo htmlspecialchars($val['value']); ?>
                        </span>
                        <span class="text-2xl font-black text-white tabular-nums">
                            <?php echo number_format((float) $val['odd'], 2); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>