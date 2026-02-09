<?php
// app/GiaNik/Views/partials/gianik_live.php
$groupedMatches = $groupedMatches ?? [];
$account = $account ?? ['available' => 0, 'exposure' => 0];

$sportKeys = array_keys($groupedMatches);
$activeSport = $sportKeys[0] ?? null;

$translationMap = [
    'Soccer' => 'Calcio',
    'Football' => 'Calcio',
    'Tennis' => 'Tennis',
    'Basketball' => 'Basket',
    'Volleyball' => 'Pallavolo',
    'Cricket' => 'Cricket',
    'Ice Hockey' => 'Hockey',
    'American Football' => 'Football',
    'Rugby Union' => 'Rugby',
    'Rugby League' => 'Rugby',
    'Golf' => 'Golf',
    'Cycling' => 'Ciclismo',
    'Motor Sport' => 'Motori',
    'Darts' => 'Freccette',
    'Snooker' => 'Snooker',
    'Boxing' => 'Pugilato',
    'Mixed Martial Arts' => 'MMA',
    'Horse Racing' => 'Ippica',
    'Greyhounds' => 'Levrieri'
];
?>

<!-- Account Summary (Compact Bar) -->
<div class="glass px-6 py-3 rounded-xl border-white/5 flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-8">
        <!-- Real Betfair Account -->
        <div class="flex items-center gap-4">
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Disponibile</span>
                <div class="text-lg font-black tabular-nums text-white leading-none">€<?php echo number_format($account['available'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Esposizione</span>
                <div class="text-lg font-black tabular-nums text-warning leading-none">€<?php echo number_format($account['exposure'], 2); ?></div>
            </div>
        </div>

        <div class="h-8 w-px bg-white/10 mx-2"></div>

        <!-- Virtual GiaNik Account -->
        <div class="flex items-center gap-4">
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Disponibile</span>
                <div class="text-lg font-black tabular-nums text-white leading-none">€<?php echo number_format($virtualAccount['available'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual In Gioco</span>
                <div class="text-lg font-black tabular-nums text-accent leading-none">€<?php echo number_format($virtualAccount['exposure'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Totale</span>
                <div class="text-lg font-black tabular-nums text-slate-400 leading-none">€<?php echo number_format($virtualAccount['total'], 2); ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($groupedMatches)): ?>
    <div class="text-center py-10">
        <span class="text-slate-500 text-sm font-bold uppercase">Nessun evento live disponibile</span>
    </div>
<?php else: ?>

    <!-- Sport Switcher Tabs -->
    <div class="flex border-b border-white/10 mb-6 overflow-x-auto no-scrollbar scroll-smooth">
        <?php foreach ($groupedMatches as $sport => $matches):
            $isActive = $sport === $activeSport;
            $translated = $translationMap[$sport] ?? $sport;
            $count = count($matches);
            $sportId = preg_replace('/[^a-zA-Z0-9]/', '', $sport);
        ?>
            <button
                onclick="switchTab('<?php echo $sportId; ?>', this)"
                class="tab-btn px-6 py-4 text-xs font-black uppercase tracking-widest border-b-2 transition-all flex-shrink-0 flex items-center gap-2 <?php echo $isActive ? 'border-accent text-white bg-white/5' : 'border-transparent text-slate-500 hover:text-slate-300'; ?>">
                <?php echo $translated; ?>
                <span class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] opacity-70"><?php echo $count; ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Content Sections -->
    <?php foreach ($groupedMatches as $sport => $matches):
        $isActive = $sport === $activeSport;
        $sportId = preg_replace('/[^a-zA-Z0-9]/', '', $sport);
    ?>
        <div id="content-<?php echo $sportId; ?>" class="sport-content <?php echo $isActive ? 'animate-fade-in' : 'hidden'; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($matches as $m):
                    $marketId = $m['marketId'];
                    $runners = $m['runners'] ?? [];
                ?>
                    <div class="glass p-5 rounded-[24px] border border-white/5 hover:border-accent/20 transition-all group relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-3 flex flex-col items-end gap-1">
                             <span class="text-[8px] font-black uppercase text-slate-500 opacity-50"><?php echo $m['marketId']; ?></span>
                             <span class="px-1.5 py-0.5 rounded bg-success/10 text-success text-[8px] font-black uppercase tracking-widest border border-success/20">
                                <?php echo $m['status_label']; ?>
                             </span>
                             <?php if ($m['has_api_data']): ?>
                                 <span class="px-1.5 py-0.5 rounded bg-accent/10 text-accent text-[8px] font-black uppercase tracking-widest border border-accent/20">
                                    Analisi Potenziata
                                 </span>
                             <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <div class="text-[10px] font-black text-accent uppercase tracking-widest mb-1 truncate">
                                <?php echo $m['competition']; ?>
                            </div>
                            <h3 class="text-sm font-black italic uppercase text-white leading-tight mb-2 group-hover:text-accent transition-colors">
                                <?php echo $m['event']; ?>
                            </h3>
                            <div class="flex items-center justify-between">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter">Matched: <span class="text-white">€<?php echo number_format($m['totalMatched'], 0, ',', '.'); ?></span></span>
                                <?php if ($m['score']): ?>
                                    <span class="text-[10px] font-black text-white bg-white/10 px-2 py-0.5 rounded"><?php echo $m['score']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="space-y-2 mb-6">
                            <?php foreach (array_slice($runners, 0, 3) as $runner): ?>
                                <div class="flex items-center justify-between bg-white/5 rounded-xl p-2 border border-white/5">
                                    <span class="text-[10px] font-bold text-slate-300 uppercase truncate max-w-[140px]">
                                        <?php echo $runner['name'] === 'The Draw' ? 'Pareggio' : $runner['name']; ?>
                                    </span>
                                    <div class="flex gap-1">
                                        <div class="bg-indigo-500/20 text-indigo-400 px-2 py-1 rounded text-[10px] font-black min-w-[40px] text-center">
                                            <?php echo $runner['back']; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="flex gap-2">
                             <button
                                hx-get="/api/gianik/analyze/<?php echo $marketId; ?>"
                                hx-target="#global-modal-container"
                                class="flex-1 py-3 bg-accent/10 hover:bg-accent/20 text-accent rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center justify-center gap-2">
                                <i data-lucide="brain-circuit" class="w-4 h-4"></i> Analisi IA
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<script>
    if (window.lucide) lucide.createIcons();

    window.switchTab = function(sportId, btn) {
        // Reset tabs
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('border-accent', 'text-white', 'bg-white/5');
            b.classList.add('border-transparent', 'text-slate-500');
        });
        // Activate button
        btn.classList.remove('border-transparent', 'text-slate-500');
        btn.classList.add('border-accent', 'text-white', 'bg-white/5');

        // Toggle Content
        document.querySelectorAll('.sport-content').forEach(c => c.classList.add('hidden'));
        const target = document.getElementById('content-' + sportId);
        if (target) {
            target.classList.remove('hidden');
            if (window.lucide) lucide.createIcons();
        }
    }
</script>
