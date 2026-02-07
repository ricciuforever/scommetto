<?php
// app/Views/partials/match/analysis.php

if (!$data) {
    echo '<div class="glass p-10 text-center font-black text-slate-500 uppercase italic">Pronostico non disponibile.</div>';
    return;
}

$perc = $data['percent'] ?? [];
$comp = $data['comparison'] ?? [];
$metrics = [
    ['label' => 'Forma', 'key' => 'form'],
    ['label' => 'Attacco', 'key' => 'attaching'],
    ['label' => 'Difesa', 'key' => 'defensive'],
    ['label' => 'Probabilità Gol', 'key' => 'poisson_distribution']
];
?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-10 animate-fade-in">
    <div class="space-y-8">
        <div class="glass p-10 rounded-[48px] border-white/5 bg-accent/5">
            <div
                class="text-[9px] font-black text-accent uppercase tracking-[0.2em] mb-4 italic flex items-center gap-2">
                <i data-lucide="brain-circuit" class="w-4 h-4"></i> Algorithmic Prediction
            </div>
            <div class="text-3xl font-black text-white italic tracking-tight uppercase leading-tight mb-8">
                <?php echo htmlspecialchars($data['advice']); ?>
            </div>

            <div class="grid grid-cols-3 gap-6">
                <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                    <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">1 (Home)</span>
                    <span class="text-3xl font-black text-white tabular-nums">
                        <?php echo $perc['home'] ?? '0%'; ?>
                    </span>
                </div>
                <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                    <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">X (Draw)</span>
                    <span class="text-3xl font-black text-white tabular-nums">
                        <?php echo $perc['draw'] ?? '0%'; ?>
                    </span>
                </div>
                <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                    <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">2 (Away)</span>
                    <span class="text-3xl font-black text-white tabular-nums">
                        <?php echo $perc['away'] ?? '0%'; ?>
                    </span>
                </div>
            </div>
        </div>

        <button
            class="w-full py-6 rounded-3xl bg-accent text-white font-black uppercase tracking-widest italic shadow-2xl shadow-accent/20 hover:scale-[1.01] transition-all flex items-center justify-center gap-3"
            onclick="analyzeMatch(<?php echo $id; ?>)">
            <i data-lucide="zap" class="w-5 h-5"></i>
            Analizza con Gemini AI Live
        </button>
    </div>

    <div class="glass p-10 rounded-[48px] border-white/5 space-y-8">
        <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] mb-2 italic">Data Insights</h3>
        <div class="space-y-6">
            <?php foreach ($metrics as $m):
                $val = $comp[$m['key']] ?? ['home' => '50%', 'away' => '50%'];
                $h = (int) $val['home'];
                $a = (int) $val['away'];
                ?>
                <div class="space-y-2">
                    <div
                        class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <span>
                            <?php echo $h; ?>%
                        </span>
                        <span class="text-white italic font-bold">
                            <?php echo $m['label']; ?>
                        </span>
                        <span>
                            <?php echo $a; ?>%
                        </span>
                    </div>
                    <div class="h-1.5 bg-white/5 rounded-full overflow-hidden flex">
                        <div class="h-full bg-accent" style="width: <?php echo $h; ?>%"></div>
                        <div class="h-full bg-slate-600" style="width: <?php echo $a; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>