<?php
// app/GiaNik/Views/partials/modals/bet_details.php
$bet = $bet ?? [];
?>

<div id="bet-details-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
         onclick="event.stopPropagation()">

        <button onclick="document.getElementById('bet-details-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-12 h-12 rounded-2xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                    <i data-lucide="zap" class="w-7 h-7"></i>
                </div>
                <div>
                    <h3 class="text-3xl font-black tracking-tight text-white uppercase italic leading-none">Dettaglio <span class="text-accent">Giocata</span></h3>
                    <p class="text-slate-500 text-[9px] font-black uppercase tracking-widest mt-1">GiaNik Autonomous Intelligence</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Status Badge -->
                <div class="flex justify-center">
                    <?php
                        $status = $bet['status'];
                        $badgeClass = 'bg-slate-500/10 text-slate-500 border-slate-500/20';
                        if ($status === 'won') $badgeClass = 'bg-success/10 text-success border-success/20';
                        if ($status === 'lost') $badgeClass = 'bg-danger/10 text-danger border-danger/20';
                    ?>
                    <div class="px-6 py-2 rounded-full border <?php echo $badgeClass; ?> text-xs font-black uppercase tracking-widest">
                        Status: <?php echo $status; ?>
                    </div>
                </div>

                <div class="glass p-6 rounded-3xl border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1"><?php echo htmlspecialchars($bet['sport']); ?></div>
                    <div class="text-xl font-black italic uppercase text-white">
                        <?php echo htmlspecialchars($bet['event_name']); ?>
                    </div>
                </div>

                <div class="glass p-5 rounded-3xl border-white/5 flex items-center justify-between">
                     <div>
                        <div class="text-[8px] font-black text-slate-500 uppercase tracking-[.2em] mb-1">Mercato</div>
                        <div class="text-sm font-black italic uppercase text-indigo-400">
                            <?php echo htmlspecialchars($bet['market_name'] ?? 'Esito Finale'); ?>
                        </div>
                     </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="glass p-5 rounded-3xl border-white/5">
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Runner Scelto</div>
                        <div class="text-lg font-black italic uppercase text-accent">
                            <?php echo htmlspecialchars($bet['runner_name']); ?>
                        </div>
                    </div>
                    <div class="glass p-5 rounded-3xl border-white/5">
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Quota & Stake</div>
                        <div class="text-xl font-black italic uppercase text-white">
                            @ <?php echo number_format($bet['odds'], 2); ?> <span class="text-slate-500 text-sm ml-2">/ <?php echo $bet['stake']; ?>€</span>
                        </div>
                    </div>
                </div>

                <?php if ($bet['profit'] != 0): ?>
                <div class="glass p-6 rounded-3xl border-white/5 flex items-center justify-between">
                     <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">P&L Finale</span>
                     <span class="text-2xl font-black italic <?php echo $bet['profit'] > 0 ? 'text-success' : 'text-danger'; ?>">
                        <?php echo ($bet['profit'] > 0 ? '+' : '') . number_format($bet['profit'], 2); ?>€
                     </span>
                </div>
                <?php endif; ?>

                <div class="glass p-6 rounded-3xl border-white/5">
                    <div class="text-[10px] font-black text-accent uppercase tracking-widest mb-3">Analisi Tecnica Gemini</div>
                    <div class="text-sm italic text-slate-300 bg-black/20 p-5 rounded-2xl max-h-64 overflow-y-auto leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($bet['motivation'])); ?>
                    </div>
                </div>

                <div class="text-[8px] text-center text-slate-600 font-bold uppercase tracking-widest">
                    ID: <?php echo $bet['market_id']; ?> • Registrata il <?php echo date('d/m/Y H:i', strtotime($bet['created_at'] . ' UTC')); ?>
                </div>
            </div>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>
