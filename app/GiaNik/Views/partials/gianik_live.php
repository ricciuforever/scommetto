<?php
// app/GiaNik/Views/partials/gianik_live.php
$groupedMatches = $groupedMatches ?? [];
$account = $account ?? ['available' => 0, 'exposure' => 0];

?>

<style>
    @keyframes pulse-slow {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.8;
            transform: scale(1.02);
        }
    }

    .animate-pulse-slow {
        animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<!-- Account Summary (Compact Bar) -->
<div class="glass px-6 py-3 rounded-xl border-white/5 flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-8">
        <!-- Real Betfair Account -->
        <div class="flex items-center gap-4">
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Disponibile</span>
                <div class="text-lg font-black tabular-nums text-white leading-none">
                    €<?php echo number_format($account['available'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Esposizione</span>
                <div class="text-lg font-black tabular-nums text-warning leading-none">
                    €<?php echo number_format($account['exposure'], 2); ?></div>
            </div>
        </div>

        <div class="h-8 w-px bg-white/10 mx-2"></div>

        <!-- Virtual GiaNik Account -->
        <div class="flex items-center gap-4">
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Disponibile</span>
                <div class="text-lg font-black tabular-nums text-white leading-none">
                    €<?php echo number_format($virtualAccount['available'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual In Gioco</span>
                <div class="text-lg font-black tabular-nums text-accent leading-none">
                    €<?php echo number_format($virtualAccount['exposure'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Totale</span>
                <div class="text-lg font-black tabular-nums text-slate-400 leading-none">
                    €<?php echo number_format($virtualAccount['total'], 2); ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($groupedMatches)): ?>
    <div class="text-center py-10">
        <span class="text-slate-500 text-sm font-bold uppercase">Nessun evento live disponibile</span>
    </div>
<?php else: ?>

    <!-- Events Grid -->
    <div class="animate-fade-in">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            $allMatches = [];
            foreach($groupedMatches as $matches) $allMatches = array_merge($allMatches, $matches);
            foreach ($allMatches as $m):
                    $marketId = $m['marketId'];
                    $runners = $m['runners'] ?? [];
                    ?>
                    <div
                        class="glass p-5 rounded-[24px] border border-white/5 hover:border-accent/20 transition-all group relative overflow-hidden">
                        <div class="mb-4">
                            <div class="flex items-center justify-between mb-1">
                                <div class="text-[10px] font-black text-accent uppercase tracking-widest truncate">
                                    <?php echo $m['competition']; ?>
                                </div>
                                <?php if ($m['score']): ?>
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="text-[11px] font-black text-white bg-white/10 px-2 py-0.5 rounded-lg border border-white/5 animate-pulse-slow">
                                            <?php echo $m['score']; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h3
                                class="text-sm font-black italic uppercase text-white leading-tight mb-2 group-hover:text-accent transition-colors flex items-center justify-between gap-2">
                                <span class="truncate"><?php echo $m['event']; ?></span>
                            </h3>

                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">Matched: <span
                                            class="text-white">€<?php echo number_format($m['totalMatched'], 0, ',', '.'); ?></span></span>
                                    <span
                                        class="text-[8px] font-black uppercase text-slate-500 opacity-50"><?php echo $m['marketId']; ?></span>
                                </div>
                                <div class="flex gap-1 items-center">
                                    <?php if ($m['has_api_data']): ?>
                                        <div class="w-1.5 h-1.5 rounded-full bg-success shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                                    <?php endif; ?>
                                    <span
                                        class="px-2 py-0.5 rounded-full bg-white/5 text-slate-400 text-[9px] font-black uppercase tracking-wider border border-white/5">
                                        <?php echo $m['status_label']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1.5 mb-6">
                            <?php foreach (array_slice($runners, 0, 3) as $runner): ?>
                                <div
                                    class="flex items-center justify-between bg-white/[0.03] hover:bg-white/[0.08] rounded-xl p-2 border border-white/5 transition-colors">
                                    <span class="text-[10px] font-bold text-slate-300 uppercase truncate max-w-[140px]">
                                        <?php echo $runner['name'] === 'The Draw' ? 'Pareggio' : $runner['name']; ?>
                                    </span>
                                    <div class="flex gap-1">
                                        <div
                                            class="bg-accent/10 text-accent px-2 py-1 rounded-lg text-[10px] font-black min-w-[42px] text-center border border-accent/20">
                                            <?php echo $runner['back']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex gap-2">
                            <button hx-get="/api/gianik/analyze?marketId=<?php echo $marketId; ?>"
                                hx-target="#global-modal-container"
                                hx-indicator="#indicator-<?php echo str_replace('.', '-', $marketId); ?>"
                                class="flex-1 py-3 bg-accent/10 hover:bg-accent/20 text-accent rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2 relative">
                                <div id="indicator-<?php echo str_replace('.', '-', $marketId); ?>"
                                    class="htmx-indicator absolute inset-0 flex items-center justify-center bg-accent/10 rounded-xl">
                                    <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                </div>
                                <i data-lucide="brain-circuit" class="w-4 h-4"></i> Analisi IA
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
    </div>

<?php endif; ?>

<script>
    if (window.lucide) lucide.createIcons();
</script>