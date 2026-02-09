<?php
// app/Views/partials/gianik_live.php
$groupedMatches = $groupedMatches ?? [];

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
    'Mixed Martial Arts' => 'MMA'
];

$iconMap = [
    'Soccer' => 'trophy',
    'Tennis' => 'circle-dot',
    'Basketball' => 'dribbble',
    'American Football' => 'citrus',
    'Boxing' => 'target',
    'Cricket' => 'activity',
    'Cycling' => 'activity',
    'Darts' => 'target',
    'Golf' => 'flag',
    'Ice Hockey' => 'activity',
    'Motor Sport' => 'zap',
    'Rugby League' => 'activity',
    'Rugby Union' => 'activity',
    'Snooker' => 'activity',
    'Volleyball' => 'activity',
];
?>

<?php if (empty($groupedMatches)): ?>
    <div class="glass p-20 rounded-[40px] text-center">
        <div class="flex flex-col items-center gap-4">
            <i data-lucide="alert-circle" class="w-12 h-12 text-slate-500"></i>
            <h2 class="text-2xl font-black italic uppercase text-slate-400">Nessun evento live rilevato</h2>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest">
                Assicurati che il sync live sia in esecuzione o attendi il prossimo aggiornamento.
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="space-y-12">
        <?php foreach ($groupedMatches as $sportName => $matches):
            $translatedSport = $translationMap[$sportName] ?? $sportName;
            $icon = $iconMap[$sportName] ?? 'activity';
        ?>
            <section>
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center text-accent">
                        <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black italic uppercase tracking-tight text-white"><?php echo $translatedSport; ?></h2>
                        <span class="text-[9px] font-black text-slate-500 uppercase tracking-[0.2em]"><?php echo count($matches); ?> EVENTI IN CORSO</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <?php foreach ($matches as $m):
                        $eventId = $m['event_id'] ?? 0;
                        $matchLink = "/match/" . $eventId;
                    ?>
                        <div class="glass p-5 rounded-[28px] border-white/5 hover:border-accent/30 transition-all group relative overflow-hidden">

                            <!-- Header: Competition & Volume -->
                            <div class="flex justify-between items-start mb-4">
                                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[150px]">
                                    <?php echo $m['competition'] ?? 'Live Event'; ?>
                                </span>
                                <div class="flex flex-col items-end">
                                    <span class="text-[8px] font-black text-slate-600 uppercase">Volume</span>
                                    <span class="text-[10px] font-black text-accent tabular-nums">€<?php echo number_format($m['totalMatched'] ?? 0, 0, ',', '.'); ?></span>
                                </div>
                            </div>

                            <!-- Teams / Event Name -->
                            <a href="<?php echo $matchLink; ?>" class="block mb-4 hover:opacity-80 transition-opacity">
                                <h3 class="text-lg font-black italic uppercase text-white leading-tight group-hover:text-accent transition-colors">
                                    <?php echo $m['event']; ?>
                                </h3>
                            </a>

                            <!-- Score if available -->
                            <?php if (isset($m['score']) || (isset($m['score_home']) && isset($m['score_away']))): ?>
                                <div class="flex items-center gap-2 mb-4">
                                    <div class="px-2.5 py-1 rounded-lg bg-white/5 border border-white/10 text-sm font-black tabular-nums text-white italic">
                                        <?php echo $m['score'] ?? ($m['score_home'] . ' - ' . $m['score_away']); ?>
                                    </div>
                                    <?php if (isset($m['elapsed'])): ?>
                                        <span class="text-[9px] font-bold text-success uppercase"><?php echo $m['elapsed']; ?>'</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Market Info -->
                            <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-3 border-b border-white/5 pb-2">
                                <?php echo $m['market']; ?>
                            </div>

                            <!-- Odds Grid (Quick view) -->
                            <div class="grid grid-cols-3 gap-2">
                                <?php
                                // Show first 3 runners max for preview
                                $displayRunners = array_slice($m['runners'], 0, 3);
                                foreach ($displayRunners as $index => $runner):
                                    $runnerName = $runner['name'] ?? 'Runner';
                                    // Shorten common runner names
                                    if ($runnerName === 'The Draw') $runnerName = 'Pareggio';

                                    // Try to find price in prices array
                                    $back = $runner['back'] ?? null;
                                    $lay = $runner['lay'] ?? null;

                                    if (isset($m['prices'])) {
                                        foreach ($m['prices'] as $p) {
                                            if ($p['selectionId'] == ($runner['selectionId'] ?? null)) {
                                                $back = $p['ex']['availableToBack'][0]['price'] ?? $back;
                                                $lay = $p['ex']['availableToLay'][0]['price'] ?? $lay;
                                                break;
                                            }
                                        }
                                    }
                                ?>
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[8px] font-bold text-slate-500 uppercase truncate text-center"><?php echo $runnerName; ?></span>
                                        <div class="grid grid-cols-2 gap-1">
                                            <!-- Back -->
                                            <div class="bg-indigo-500/10 border border-indigo-500/20 rounded-lg p-1.5 text-center">
                                                <span class="text-[10px] font-black text-indigo-400 tabular-nums"><?php echo $back ?: '-'; ?></span>
                                            </div>
                                            <!-- Lay -->
                                            <div class="bg-pink-500/10 border border-pink-500/20 rounded-lg p-1.5 text-center">
                                                <span class="text-[10px] font-black text-pink-400 tabular-nums"><?php echo $lay ?: '-'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Footer Actions -->
                            <div class="mt-5 pt-4 border-t border-white/5 flex justify-between items-center">
                                <span class="px-2 py-0.5 rounded bg-danger/10 text-danger text-[8px] font-black uppercase tracking-widest animate-pulse">LIVE</span>
                                <a href="<?php echo $matchLink; ?>" class="text-[9px] font-black text-slate-400 hover:text-white uppercase tracking-widest flex items-center gap-1 transition-colors">
                                    Analizza <i data-lucide="brain-circuit" class="w-3 h-3"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    if (window.lucide) lucide.createIcons();
</script>
