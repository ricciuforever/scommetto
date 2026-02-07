<?php
// app/Views/partials/match/events.php

if (empty($events)) {
    echo '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Nessun evento registrato.</div>';
    return;
}
?>

<div
    class="max-w-3xl mx-auto space-y-6 relative before:absolute before:inset-y-0 before:left-1/2 before:w-px before:bg-white/10 py-10 animate-fade-in">
    <?php foreach ($events as $ev):
        // Normalize event structure (db vs api)
        $teamId = $ev['team_id'] ?? null;
        $isHome = $teamId == $homeId;
        $icon = 'info';
        $color = 'slate-500';
        $type = $ev['type'];
        $detail = $ev['detail'];
        $player = $ev['player_name'] ?? 'Giocatore';
        $time = $ev['time_elapsed'];

        if ($type === 'Goal') {
            $icon = 'trophy';
            $color = 'accent';
        } elseif ($type === 'Card') {
            $icon = 'alert-triangle';
            $color = (strpos($detail, 'Yellow') !== false) ? 'warning' : 'danger';
        } elseif ($type === 'subst') {
            $icon = 'refresh-cw';
            $color = 'success';
        } elseif ($type === 'Var') {
            $icon = 'camera';
            $color = 'white';
        }
        ?>
        <div class="flex items-center justify-between group">
            <div class="flex-1 text-right pr-8 <?php echo $isHome ? '' : 'invisible'; ?>">
                <?php if ($isHome): ?>
                    <div
                        class="inline-block glass px-6 py-4 rounded-2xl border-white/5 hover:border-<?php echo $color; ?>/30 transition-colors group/card">
                        <div class="flex items-center gap-3 mb-1 justify-end">
                            <span class="text-[10px] font-black uppercase text-white tracking-widest">
                                <?php echo htmlspecialchars($player); ?>
                            </span>
                            <i data-lucide="<?php echo $icon; ?>" class="w-3 h-3 text-<?php echo $color; ?>"></i>
                        </div>
                        <div class="text-[8px] font-bold text-slate-500 uppercase tracking-widest">
                            <?php echo htmlspecialchars($detail); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div
                class="relative z-10 w-10 h-10 rounded-full glass border border-white/10 flex items-center justify-center text-xs font-black text-white tabular-nums bg-slate-900 shadow-xl shadow-black/50 group-hover:scale-110 transition-transform cursor-default ring-4 ring-slate-900 shrink-0">
                <?php echo $time; ?>'
            </div>

            <div class="flex-1 pl-8 <?php echo !$isHome ? '' : 'invisible'; ?>">
                <?php if (!$isHome): ?>
                    <div
                        class="inline-block glass px-6 py-4 rounded-2xl border-white/5 hover:border-<?php echo $color; ?>/30 transition-colors group/card">
                        <div class="flex items-center gap-3 mb-1">
                            <i data-lucide="<?php echo $icon; ?>" class="w-3 h-3 text-<?php echo $color; ?>"></i>
                            <span class="text-[10px] font-black uppercase text-white tracking-widest">
                                <?php echo htmlspecialchars($player); ?>
                            </span>
                        </div>
                        <div class="text-[8px] font-bold text-slate-500 uppercase tracking-widest">
                            <?php echo htmlspecialchars($detail); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>