<?php
// app/Views/partials/predictions.php
// HTMX Partial for AI Predictions

$selectedCountry = $_GET['country'] ?? 'all';
?>

<div id="predictions-view-content" class="animate-fade-in">
    <div class="mb-12">
        <h1 class="text-4xl font-black italic uppercase tracking-tighter mb-2 text-white">Pronostici AI</h1>
        <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic">
            Suggerimenti algoritmici basati su performance e big data per
            <?php echo $selectedCountry === 'all' ? 'tutte le nazioni' : htmlspecialchars($selectedCountry); ?>.
        </p>
    </div>

    <div id="predictions-grid" class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <?php if (empty($predictions)): ?>
            <div class="col-span-2 glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">
                Nessun pronostico trovato per i filtri attuali.
            </div>
        <?php else: ?>
            <?php foreach ($predictions as $p):
                $dateStr = date('d M H:i', strtotime($p['date']));
                // Filter by country if needed (though typically done in controller)
                if ($selectedCountry !== 'all' && ($p['country_name'] ?? 'International') !== $selectedCountry)
                    continue;
                ?>
                <div
                    class="glass p-8 rounded-[40px] border-white/5 hover:border-accent/30 transition-all group relative overflow-hidden prediction-card active-card">
                    <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-all scale-150">
                        <i data-lucide="brain-circuit" class="w-20 h-20"></i>
                    </div>

                    <div class="flex items-center justify-between mb-6">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 italic">
                            <?php echo $p['league_name']; ?>
                        </span>
                        <span class="text-[10px] font-black uppercase text-accent tracking-widest">
                            <?php echo $dateStr; ?>
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-4 mb-8">
                        <div class="flex-1 flex flex-col items-center gap-3">
                            <img src="<?php echo $p['home_logo']; ?>" class="w-12 h-12 object-contain">
                            <span class="text-xs font-black uppercase text-center truncate w-full">
                                <?php echo $p['home_name']; ?>
                            </span>
                        </div>
                        <div class="flex flex-col items-center">
                            <span class="text-2xl font-black italic opacity-20">VS</span>
                        </div>
                        <div class="flex-1 flex flex-col items-center gap-3">
                            <img src="<?php echo $p['away_logo']; ?>" class="w-12 h-12 object-contain">
                            <span class="text-xs font-black uppercase text-center truncate w-full">
                                <?php echo $p['away_name']; ?>
                            </span>
                        </div>
                    </div>

                    <div class="bg-accent/10 border border-accent/20 p-5 rounded-3xl mb-6">
                        <div class="text-[9px] font-black text-accent uppercase tracking-widest mb-1">AI Suggestion</div>
                        <div class="text-lg font-black text-white italic uppercase">
                            <?php echo $p['advice']; ?>
                        </div>
                    </div>

                    <button
                        class="w-full py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-[10px] uppercase tracking-widest transition-all"
                        onclick="window.location.hash = 'match/<?php echo $p['fixture_id']; ?>'">
                        Apri Match Center
                    </button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>