<?php
// app/Views/partials/match/lineups.php

if (empty($lineups)) {
    echo '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Formazioni non ancora disponibili.</div>';
    return;
}
?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-10 animate-fade-in">
    <?php foreach ($lineups as $l):
        $startXI = json_decode($l['start_xi_json'] ?? '[]', true);
        $subs = json_decode($l['subs_json'] ?? '[]', true);
        ?>
        <div class="glass p-8 rounded-[40px] border-white/5">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <img src="<?php echo $l['team_logo']; ?>" class="w-8 h-8 object-contain">
                    <h3 class="text-xl font-black italic uppercase text-white">
                        <?php echo htmlspecialchars($l['team_name']); ?>
                    </h3>
                </div>
                <span
                    class="px-4 py-1.5 rounded-xl bg-accent/10 text-accent text-[10px] font-black uppercase tracking-widest border border-accent/20">
                    <?php echo $l['formation']; ?>
                </span>
            </div>

            <div class="space-y-4">
                <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-4 italic">Titolari (Starting
                    XI)</div>
                <div class="grid grid-cols-1 gap-1">
                    <?php foreach ($startXI as $p): ?>
                        <div class="flex items-center gap-4 group cursor-pointer p-2 rounded-xl hover:bg-white/5 transition-all"
                            onclick="window.location.hash = 'player/<?php echo $p['player']['id']; ?>'">
                            <span class="w-6 text-accent font-black tabular-nums italic text-sm">
                                <?php echo $p['player']['number'] ?? '-'; ?>
                            </span>
                            <span
                                class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors text-xs">
                                <?php echo htmlspecialchars($p['player']['name']); ?>
                            </span>
                            <span class="text-[9px] font-bold text-slate-600 uppercase ml-auto tracking-widest">
                                <?php echo $p['player']['pos']; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($subs)): ?>
                    <div class="mt-8 pt-6 border-t border-white/5">
                        <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-4 italic">Sostituzioni
                            (Substitutes)</div>
                        <div class="grid grid-cols-1 gap-1 opacity-60">
                            <?php foreach ($subs as $p): ?>
                                <div class="flex items-center gap-4 group cursor-pointer p-2 rounded-xl hover:bg-white/5 transition-all"
                                    onclick="window.location.hash = 'player/<?php echo $p['player']['id']; ?>'">
                                    <span class="w-6 text-slate-500 font-bold tabular-nums italic text-xs">
                                        <?php echo $p['player']['number'] ?? '-'; ?>
                                    </span>
                                    <span
                                        class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors text-xs">
                                        <?php echo htmlspecialchars($p['player']['name']); ?>
                                    </span>
                                    <span class="text-[8px] font-bold text-slate-600 uppercase ml-auto">
                                        <?php echo $p['player']['pos']; ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
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