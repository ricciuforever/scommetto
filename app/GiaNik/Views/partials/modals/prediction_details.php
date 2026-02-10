<?php
// app/GiaNik/Views/partials/modals/prediction_details.php
$prediction = $prediction ?? [];
$comparison = $comparison ?? [];
$details = $details ?? [];
?>

<div id="gianik-prediction-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
        onclick="event.stopPropagation()">

        <button onclick="document.getElementById('gianik-prediction-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10">
            <div class="flex items-center gap-3 mb-8">
                <div
                    class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <span class="text-2xl">🔮</span>
                </div>
                <div>
                    <h3 class="text-3xl font-black tracking-tight text-white uppercase italic leading-none">AI <span
                            class="text-indigo-400">Predictions</span></h3>
                    <p class="text-slate-500 text-[9px] font-black uppercase tracking-widest mt-1">Crystal Ball
                        Statistical Analysis</p>
                </div>
            </div>

            <div class="space-y-6">
                <!-- Match Header -->
                <div class="glass p-6 rounded-3xl border-white/5 flex items-center justify-between">
                    <div class="flex-1 text-center">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Home</div>
                        <div class="text-lg font-black text-white truncate">
                            <?php echo $details['teams']['home']['name'] ?? 'Home'; ?>
                        </div>
                    </div>
                    <div class="px-6 text-2xl font-black text-indigo-400 italic">VS</div>
                    <div class="flex-1 text-center">
                        <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Away</div>
                        <div class="text-lg font-black text-white truncate">
                            <?php echo $details['teams']['away']['name'] ?? 'Away'; ?>
                        </div>
                    </div>
                </div>

                <?php
                // Detection for real data
                $hasPercent = !empty($prediction['percent']) && ($prediction['percent']['home'] !== '33.3%' || $prediction['percent']['away'] !== '33.3%');
                $hasAdvice = !empty($prediction['advice']) && !str_contains(strtolower($prediction['advice']), 'no predictions') && !str_contains(strtolower($prediction['advice']), 'nessun consiglio');
                // For comparison, check if we have any key other than 'total' or if 'total' is > 0
                $hasComparison = false;
                if (!empty($comparison)) {
                    foreach (['form', 'att', 'def', 'h2h', 'goals'] as $k) {
                        if (isset($comparison[$k]) && (float) ($comparison[$k]['home'] ?? 0) > 0) {
                            $hasComparison = true;
                            break;
                        }
                    }
                }
                ?>

                <!-- Probabilities -->
                <?php if ($hasPercent): ?>
                    <div class="glass p-6 rounded-3xl border-white/5">
                        <h4 class="text-xs font-black text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i data-lucide="trending-up" class="w-4 h-4 text-accent"></i>
                            Probabilità Esito (1X2)
                        </h4>
                        <?php
                        $pH = $prediction['percent']['home'] ?? '33.3%';
                        $pD = $prediction['percent']['draw'] ?? '33.4%';
                        $pA = $prediction['percent']['away'] ?? '33.3%';
                        ?>
                        <div class="flex h-3 w-full bg-white/5 rounded-full overflow-hidden border border-white/5 mb-3">
                            <div class="bg-accent h-full" style="width: <?php echo $pH; ?>"
                                title="Home: <?php echo $pH; ?>"></div>
                            <div class="bg-white/20 h-full" style="width: <?php echo $pD; ?>"
                                title="Draw: <?php echo $pD; ?>"></div>
                            <div class="bg-danger h-full" style="width: <?php echo $pA; ?>"
                                title="Away: <?php echo $pA; ?>"></div>
                        </div>
                        <div class="flex justify-between text-[10px] font-black uppercase tracking-tighter">
                            <div class="flex flex-col">
                                <span class="text-slate-500">Home</span>
                                <span class="text-accent text-lg"><?php echo $pH; ?></span>
                            </div>
                            <div class="flex flex-col text-center">
                                <span class="text-slate-500">Draw</span>
                                <span class="text-slate-300 text-lg"><?php echo $pD; ?></span>
                            </div>
                            <div class="flex flex-col text-right">
                                <span class="text-slate-500">Away</span>
                                <span class="text-danger text-lg"><?php echo $pA; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Advice & Under/Over -->
                <?php if ($hasAdvice): ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-indigo-500/10 border border-indigo-500/20 p-6 rounded-3xl">
                            <span
                                class="text-[10px] font-black text-indigo-400 uppercase tracking-widest block mb-2">Advice</span>
                            <p class="text-sm font-black text-white leading-tight italic">
                                "<?php echo $prediction['advice']; ?>"
                            </p>
                        </div>
                        <?php if (!empty($prediction['under_over']) && $prediction['under_over'] !== 'N/D'): ?>
                            <div class="glass p-6 rounded-3xl border-white/5">
                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-1">Under /
                                    Over</span>
                                <div class="text-2xl font-black text-white"><?php echo $prediction['under_over']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Comparison Stats -->
                <?php if ($hasComparison): ?>
                    <div class="glass p-6 rounded-3xl border-white/5">
                        <h4 class="text-xs font-black text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i data-lucide="bar-chart-2" class="w-4 h-4 text-warning"></i>
                            Comparazione Statistica
                        </h4>
                        <div class="space-y-3">
                            <?php
                            $stats = [
                                'Forma' => 'form',
                                'Attacco' => 'att',
                                'Difesa' => 'def',
                                'H2H' => 'h2h',
                                'Goal' => 'goals',
                                'Totale' => 'total'
                            ];
                            foreach ($stats as $label => $key):
                                if (!isset($comparison[$key]))
                                    continue;
                                $hVal = (float) ($comparison[$key]['home'] ?? 0);
                                $aVal = (float) ($comparison[$key]['away'] ?? 0);
                                $sum = $hVal + $aVal;
                                if ($sum <= 0)
                                    continue;
                                $hPerc = ($hVal / $sum) * 100;
                                ?>
                                <div class="flex flex-col gap-1">
                                    <div
                                        class="flex justify-between text-[8px] font-black uppercase text-slate-500 tracking-widest">
                                        <span><?php echo $comparison[$key]['home'] ?? '0%'; ?></span>
                                        <span><?php echo $label; ?></span>
                                        <span><?php echo $comparison[$key]['away'] ?? '0%'; ?></span>
                                    </div>
                                    <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden flex">
                                        <div class="bg-accent h-full" style="width: <?php echo $hPerc; ?>%"></div>
                                        <div class="bg-danger h-full" style="width: <?php echo 100 - $hPerc; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$hasPercent && !$hasAdvice && !$hasComparison): ?>
                    <div class="glass p-12 rounded-[32px] border-white/5 text-center flex flex-col items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-white/5 flex items-center justify-center">
                            <i data-lucide="database-zap" class="w-8 h-8 text-slate-600"></i>
                        </div>
                        <div>
                            <p class="text-white font-black uppercase italic tracking-wider">Dati non disponibili</p>
                            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest mt-1">L'IA sta
                                elaborando le statistiche del match</p>
                        </div>
                    </div>
                <?php endif; ?>

                <button onclick="document.getElementById('gianik-prediction-modal').remove()"
                    class="w-full py-4 rounded-2xl bg-white/5 hover:bg-white/10 text-white font-black uppercase tracking-widest text-xs transition-all border border-white/5">
                    Chiudi Analisi
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>