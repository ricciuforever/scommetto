<?php
// app/Views/partials/match/h2h.php

if (empty($h2h)) {
    echo '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Dati storici non disponibili.</div>';
    return;
}
?>

<div
    class="max-w-4xl mx-auto glass rounded-[40px] border-white/5 overflow-hidden divide-y divide-white/5 animate-fade-in shadow-2xl">
    <div class="p-6 bg-white/5 flex items-center justify-between px-10">
        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 italic">Precedenti (Head to Head)</h3>
        <span
            class="text-[9px] font-black uppercase tracking-widest text-accent bg-accent/10 px-3 py-1 rounded-lg border border-accent/20">Ultimi
            5 Match</span>
    </div>

    <?php foreach ($h2h as $m):
        $date = date('d M Y', strtotime($m['date']));
        $homeScore = $m['score_home'] ?? 0;
        $awayScore = $m['score_away'] ?? 0;
        ?>
        <div class="p-8 flex items-center justify-between group hover:bg-white/5 transition-all">
            <div class="flex-1 text-right flex items-center justify-end gap-4">
                <span
                    class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors text-sm">
                    <?php echo htmlspecialchars($m['home_name']); ?>
                </span>
                <img src="<?php echo $m['home_logo']; ?>"
                    class="w-6 h-6 object-contain opacity-50 group-hover:opacity-100 transition-opacity">
            </div>

            <div class="flex flex-col items-center gap-1 mx-12">
                <div class="text-3xl font-black tabular-nums text-white italic tracking-tighter">
                    <?php echo $homeScore; ?> -
                    <?php echo $awayScore; ?>
                </div>
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">
                    <?php echo $date; ?>
                </span>
            </div>

            <div class="flex-1 text-left flex items-center gap-4">
                <img src="<?php echo $m['away_logo']; ?>"
                    class="w-6 h-6 object-contain opacity-50 group-hover:opacity-100 transition-opacity">
                <span
                    class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors text-sm">
                    <?php echo htmlspecialchars($m['away_name']); ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>