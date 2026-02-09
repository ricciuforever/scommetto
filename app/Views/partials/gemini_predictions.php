<?php
// app/Views/partials/gemini_predictions.php
?>
<div class="animate-fade-in">
    <div class="flex items-center justify-between mb-10">
        <div>
            <h1 class="text-4xl font-black italic tracking-tighter uppercase text-white">
                Pronostici <span class="text-accent">Gemini</span>
            </h1>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mt-2">
                Analisi AI sui mercati Betfair più liquidi (Prossime 48 ore)
            </p>
        </div>
        <?php if (isset($timestamp)): ?>
            <div class="text-right">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-600 block">Ultimo Aggiornamento</span>
                <span class="text-xs font-bold text-slate-400"><?php echo date('d/m/Y H:i', $timestamp); ?></span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (empty($predictions)): ?>
        <div class="glass rounded-[40px] p-20 text-center border-white/5">
            <div class="w-20 h-20 bg-white/5 rounded-3xl flex items-center justify-center mx-auto mb-6">
                <i data-lucide="brain-circuit" class="w-10 h-10 text-slate-600"></i>
            </div>
            <h3 class="text-2xl font-black text-white uppercase italic mb-2">Nessun Pronostico</h3>
            <p class="text-slate-500 max-w-md mx-auto">
                L'intelligenza artificiale non ha ancora generato pronostici per i prossimi eventi.
                Il sistema analizza i mercati ogni ora.
            </p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php foreach ($predictions as $p): ?>
                <div class="glass rounded-[40px] border-white/5 overflow-hidden group hover:border-accent/30 transition-all flex flex-col">
                    <div class="p-8 flex-1">
                        <div class="flex justify-between items-start mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-accent/10 flex items-center justify-center">
                                    <i data-lucide="trophy" class="w-5 h-5 text-accent"></i>
                                </div>
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-accent block">
                                        <?php echo htmlspecialchars($p['competition'] ?? $p['sport'] ?? 'Competizione'); ?>
                                    </span>
                                    <span class="text-xs font-bold text-slate-500 italic">
                                        <?php echo htmlspecialchars($p['sport'] ?? 'Sport'); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 block mb-1">Affidabilità</span>
                                <div class="text-xl font-black italic text-success"><?php echo $p['confidence'] ?? 0; ?>%</div>
                            </div>
                        </div>

                        <h2 class="text-2xl font-black italic tracking-tight text-white mb-4 uppercase">
                            <?php echo htmlspecialchars($p['event'] ?? 'Evento'); ?>
                        </h2>

                        <div class="bg-white/5 rounded-2xl p-4 mb-6 border border-white/5">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 block mb-1">Suggerimento</span>
                                    <span class="text-lg font-black italic text-white uppercase"><?php echo htmlspecialchars($p['advice'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 block mb-1">Quota</span>
                                    <span class="text-lg font-black italic text-accent">@<?php echo number_format($p['odds'] ?? 0, 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 block">Analisi Tecnica Gemini</span>
                            <p class="text-sm text-slate-400 italic leading-relaxed">
                                "<?php echo htmlspecialchars($p['motivation'] ?? 'Nessuna motivazione fornita.'); ?>"
                            </p>
                        </div>
                    </div>

                    <div class="p-6 bg-white/5 border-t border-white/5 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="bar-chart-3" class="w-4 h-4 text-slate-500"></i>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                Volume: <?php echo number_format($p['totalMatched'] ?? 0); ?>€
                            </span>
                        </div>
                        <button class="bg-accent/10 hover:bg-accent text-accent hover:text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">
                            Vedi Mercato
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>
