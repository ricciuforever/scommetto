<?php
// app/Views/partials/modals/bet_details.php
// HTMX Modal for Bet Details

$statusColor = $bet['status'] === 'won' ? 'text-success' : ($bet['status'] === 'lost' ? 'text-danger' : 'text-warning');
$profit = 0;
if ($bet['status'] === 'won')
    $profit = $bet['stake'] * ($bet['odds'] - 1);
elseif ($bet['status'] === 'lost')
    $profit = -$bet['stake'];
?>

<div id="bet-details-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-lg rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative">
        <button onclick="document.getElementById('bet-details-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10">
            <h3 class="text-3xl font-black mb-8 tracking-tight text-white uppercase italic">Dettaglio <span
                    class="text-accent">Scommessa</span></h3>

            <div class="space-y-6">
                <div class="glass p-6 rounded-3xl border-white/5">
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Evento</div>
                    <div class="text-xl font-black italic uppercase text-white">
                        <?php echo htmlspecialchars($bet['match_name']); ?>
                    </div>
                    <div class="text-xs font-bold text-slate-500 uppercase mt-1">
                        <?php echo date('d M Y H:i', strtotime($bet['timestamp'])); ?>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="glass p-5 rounded-3xl border-white/5">
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Mercato</div>
                        <div class="text-sm font-black italic uppercase text-white">
                            <?php echo htmlspecialchars($bet['market']); ?>
                        </div>
                    </div>
                    <div class="glass p-5 rounded-3xl border-white/5">
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Quota</div>
                        <div class="text-xl font-black italic uppercase text-accent">
                            <?php echo $bet['odds']; ?>
                        </div>
                    </div>
                </div>

                <div class="glass p-6 rounded-3xl border-white/5 flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Stato</div>
                        <div class="text-xl font-black italic uppercase <?php echo $statusColor; ?>">
                            <?php echo $bet['status']; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">P/L</div>
                        <div
                            class="text-2xl font-black italic uppercase <?php echo $profit >= 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo ($profit >= 0 ? '+' : '') . number_format($profit, 2); ?>€
                        </div>
                    </div>
                </div>

                <?php if (!empty($bet['notes'])): ?>
                    <div class="glass p-6 rounded-3xl border-white/5">
                        <div class="text-[10px] font-bold text-accent uppercase tracking-widest mb-2">Analisi Tecnica Gemini
                        </div>
                        <div class="text-sm italic text-slate-300 bg-black/20 p-4 rounded-xl max-h-48 overflow-y-auto leading-relaxed">
                            <?php echo nl2br(htmlspecialchars($bet['notes'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>