<?php
// app/Views/partials/modals/gianik_analysis.php
$analysis = $analysis ?? [];
$reasoning = $reasoning ?? '';
$event = $event ?? [];
?>

<div id="gianik-analysis-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
         onclick="event.stopPropagation()">
        <button onclick="document.getElementById('gianik-analysis-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                    <i data-lucide="brain-circuit" class="w-7 h-7"></i>
                </div>
                <div>
                    <h3 class="text-3xl font-black tracking-tight text-white uppercase italic leading-none">Analisi <span class="text-accent">GiaNik</span></h3>
                    <p class="text-slate-500 text-[9px] font-black uppercase tracking-widest mt-1">Intelligenza Artificiale Betfair</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Event Header -->
                <div class="glass p-6 rounded-3xl border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($event['sport'] ?? 'Sport'); ?> - <?php echo htmlspecialchars($event['competition'] ?? ''); ?></div>
                    <div class="text-xl font-black italic uppercase text-white">
                        <?php echo htmlspecialchars($event['event'] ?? 'Evento'); ?>
                    </div>
                </div>

                <?php if (!empty($analysis)): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="glass p-5 rounded-3xl border-white/5">
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Consiglio</div>
                            <div class="text-lg font-black italic uppercase text-accent">
                                <?php echo htmlspecialchars($analysis['advice'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="glass p-5 rounded-3xl border-white/5">
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Quota Suggerita</div>
                            <div class="text-xl font-black italic uppercase text-white">
                                @ <?php echo htmlspecialchars($analysis['odds'] ?? '0.00'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="glass p-6 rounded-3xl border-indigo-500/20 bg-indigo-500/5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Confidence & Sentiment</div>
                            <div class="px-2 py-0.5 rounded bg-indigo-500/20 text-indigo-400 text-[10px] font-black italic">
                                <?php echo htmlspecialchars($analysis['confidence'] ?? 0); ?>%
                            </div>
                        </div>
                        <div class="text-sm font-bold text-white italic">
                            "<?php echo htmlspecialchars($analysis['sentiment'] ?? 'Analisi neutrale'); ?>"
                        </div>
                    </div>
                <?php endif; ?>

                <div class="glass p-6 rounded-3xl border-white/5">
                    <div class="text-[10px] font-black text-accent uppercase tracking-widest mb-3">Motivazione Tecnica AI</div>
                    <div class="text-sm italic text-slate-300 bg-black/20 p-5 rounded-2xl max-h-64 overflow-y-auto leading-relaxed">
                        <?php
                            $motivation = $analysis['motivation'] ?? $reasoning;
                            echo nl2br(htmlspecialchars($motivation));
                        ?>
                    </div>
                </div>

                <div class="pt-4 flex gap-4">
                     <button onclick="document.getElementById('gianik-analysis-modal').remove()" class="flex-1 py-4 rounded-2xl bg-white/5 hover:bg-white/10 text-white font-black uppercase tracking-widest text-xs transition-all border border-white/5">
                        Chiudi
                     </button>
                </div>
            </div>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>
