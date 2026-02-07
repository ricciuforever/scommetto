<?php
// app/Views/partials/match/info.php
?>

<div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 animate-fade-in pb-20">
    <div class="glass p-10 rounded-[48px] border-white/5 space-y-10">
        <div>
            <h3
                class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-6 italic flex items-center gap-2">
                <i data-lucide="map-pin" class="w-4 h-4 text-accent"></i> Stadio
            </h3>
            <div class="text-3xl font-black text-white italic uppercase tracking-tighter mb-2">
                <?php echo htmlspecialchars($fixture['venue_name'] ?? 'N/A'); ?>
            </div>
            <div class="text-xs font-bold text-slate-400 uppercase tracking-widest">
                <?php echo htmlspecialchars($fixture['venue_city'] ?? ''); ?>
            </div>
        </div>

        <div>
            <h3
                class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-6 italic flex items-center gap-2">
                <i data-lucide="info" class="w-4 h-4 text-accent"></i> Dettagli Incontro
            </h3>
            <div class="space-y-5">
                <div class="flex justify-between items-center border-b border-white/5 pb-5">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Campionato</span>
                    <span class="text-xs font-black text-white uppercase italic">
                        <?php echo htmlspecialchars($fixture['league_name']); ?>
                    </span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-5">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Round</span>
                    <span class="text-xs font-black text-white uppercase italic">
                        <?php echo htmlspecialchars($fixture['round']); ?>
                    </span>
                </div>
                <div class="flex justify-between items-center border-b border-white/5 pb-5">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Status</span>
                    <span class="text-xs font-black text-white uppercase italic">
                        <?php echo htmlspecialchars($fixture['status_long']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div
        class="glass p-10 rounded-[48px] border-white/5 bg-accent/5 flex flex-col items-center justify-center text-center relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-accent/10 to-transparent"></div>
        <i data-lucide="calendar" class="w-16 h-16 text-accent mb-8 relative z-10"></i>
        <div class="text-[11px] font-black uppercase tracking-[0.3em] text-accent mb-4 italic relative z-10">Data e Ora
            di Inizio</div>
        <div class="text-5xl font-black text-white italic tracking-tighter uppercase leading-none mb-6 relative z-10">
            <?php echo date('d M Y', strtotime($fixture['date'])); ?>
        </div>
        <div class="text-3xl font-black text-slate-400 tabular-nums uppercase italic relative z-10">
            <?php echo date('H:i', strtotime($fixture['date'])); ?>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>