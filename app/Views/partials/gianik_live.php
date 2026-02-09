<?php
// app/Views/partials/gianik_live.php
$groupedMatches = $groupedMatches ?? [];
$account = $account ?? ['available' => 0, 'exposure' => 0];
$orders = $orders ?? [];
$history = $history ?? [];

$sportKeys = array_keys($groupedMatches);
$activeSport = $sportKeys[0] ?? null;

// Translation & Icons maps remain same
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
    <div class="flex items-center gap-6">
        <div>
            <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Disponibile</span>
            <div class="text-lg font-black tabular-nums text-white leading-none">€<?php echo number_format($account['available'], 2); ?></div>
        </div>
        <div>
            <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Esposizione</span>
            <div class="text-lg font-black tabular-nums text-warning leading-none">€<?php echo number_format($account['exposure'], 2); ?></div>
        </div>
        <div>
            <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Ordini</span>
            <div class="text-lg font-black tabular-nums text-accent leading-none"><?php echo count($orders); ?></div>
        </div>
    </div>
    <!-- Active Orders Ticker -->
    <?php if (!empty($orders)): ?>
    <div class="flex items-center gap-3 overflow-hidden">
        <?php foreach (array_slice($orders, 0, 2) as $order): ?>
            <div class="text-[10px] bg-white/5 rounded px-2 py-1 flex items-center gap-2">
                <span class="font-black text-white"><?php echo $order['betId']; ?></span>
                <span class="text-slate-400"><?php echo $order['side']; ?> @ <?php echo $order['priceSize']['price']; ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Settled Bets Section -->
<?php if (!empty($history)): ?>
<div class="mb-10">
    <h2 class="text-xl font-black italic uppercase tracking-tight text-white mb-4">Ultime Giocate Concluse</h2>
    <div class="glass rounded-[32px] border-white/5 divide-y divide-white/5 overflow-hidden">
        <?php foreach (array_slice($history, 0, 10) as $bet):
            $profit = (float)($bet['profit'] ?? 0);
            $isWin = $profit > 0;
            $isLoss = $profit < 0;
            $statusClass = $isWin ? 'text-success' : ($isLoss ? 'text-danger' : 'text-slate-500');
        ?>
            <div class="p-4 flex items-center justify-between group hover:bg-white/5 transition-all">
                <div>
                    <div class="text-[10px] font-black italic uppercase text-white truncate max-w-[250px]">
                        Market: <?php echo $bet['marketId']; ?>
                    </div>
                    <div class="text-[8px] font-bold text-slate-500 uppercase">
                        Scommessa: <?php echo $bet['betId']; ?> | Quota: <?php echo $bet['lastMatchedPrice']; ?>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-[10px] font-black italic uppercase <?php echo $statusClass; ?>">
                        <?php echo $profit >= 0 ? '+' : ''; echo number_format($profit, 2); ?>€
                    </div>
                    <div class="text-[8px] font-bold text-slate-500 uppercase"><?php echo date('d/m/y H:i', strtotime($bet['settledDate'])); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($groupedMatches)): ?>
    <div class="text-center py-10">
        <span class="text-slate-500 text-sm font-bold uppercase">Nessun evento live</span>
    </div>
<?php else: ?>

    <!-- Simple Tabs -->
    <div class="flex border-b border-white/10 mb-6 overflow-x-auto">
        <?php foreach ($groupedMatches as $sport => $matches): 
            $isActive = $sport === $activeSport;
            $translated = $translationMap[$sport] ?? $sport;
            $count = count($matches);
        ?>
            <button 
                onclick="switchTab('<?php echo preg_replace('/[^a-zA-Z0-9]/', '', $sport); ?>', this)"
                class="tab-btn px-4 py-3 text-xs font-black uppercase tracking-wider border-b-2 transition-colors <?php echo $isActive ? 'border-accent text-white' : 'border-transparent text-slate-500 hover:text-slate-300'; ?>">
                <?php echo $translated; ?> <span class="opacity-50 ml-1"><?php echo $count; ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Content Sections (Table View) -->
    <?php foreach ($groupedMatches as $sport => $matches): 
        $isActive = $sport === $activeSport;
        $sportId = preg_replace('/[^a-zA-Z0-9]/', '', $sport);
    ?>
        <div id="content-<?php echo $sportId; ?>" class="sport-content <?php echo $isActive ? '' : 'hidden'; ?>">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[9px] text-slate-500 uppercase tracking-widest border-b border-white/5">
                            <th class="py-3 pl-2 font-black w-1/3">Evento</th>
                            <th class="py-3 text-center font-black w-24">Punteggio</th>
                            <th class="py-3 text-center font-black w-24">Volume</th>
                            <th class="py-3 px-4 font-black">Quote</th>
                            <th class="py-3 pr-2 text-right font-black w-16">Analisi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        <?php foreach ($matches as $m): 
                            $eventId = $m['event_id'] ?? 0;
                            $runners = $m['runners'] ?? [];
                            
                            // Map prices
                            $mappedRunners = [];
                            if (isset($m['prices'])) {
                                foreach($runners as $runner) {
                                    $data = ['name' => $runner['name'], 'back' => null];
                                    foreach($m['prices'] as $p) {
                                        if ($p['selectionId'] == $runner['selectionId']) {
                                            $data['back'] = $p['ex']['availableToBack'][0]['price'] ?? null;
                                            break;
                                        }
                                    }
                                    $mappedRunners[] = $data;
                                }
                            } else {
                                foreach($runners as $runner) {
                                    $mappedRunners[] = ['name' => $runner['name'], 'back' => $runner['back'] ?? null];
                                }
                            }
                        ?>
                            <tr class="group hover:bg-white/5 transition-colors border-b border-white/5">
                                <!-- Event Name -->
                                <td class="py-3 pl-2">
                                    <div class="flex flex-col">
                                        <a href="/match/<?php echo $eventId; ?>" class="font-bold text-white uppercase group-hover:text-accent truncate transition-colors">
                                            <?php echo $m['event']; ?>
                                        </a>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-[9px] text-slate-500 uppercase tracking-wide truncate max-w-[150px]"><?php echo $m['competition'] ?? $sport; ?></span>
                                            <?php if (isset($m['elapsed'])): ?>
                                                <span class="text-[9px] text-success font-black uppercase"><?php echo $m['elapsed']; ?>'</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <!-- Score -->
                                <td class="py-3 text-center">
                                    <?php if (isset($m['score']) || (isset($m['score_home']) && isset($m['score_away']))): ?>
                                        <span class="text-xs font-black text-white bg-white/10 px-2 py-0.5 rounded">
                                            <?php echo $m['score'] ?? ($m['score_home'] . '-' . $m['score_away']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] text-slate-600">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Volume -->
                                <td class="py-3 text-center">
                                    <span class="text-[10px] font-bold text-slate-500 tabular-nums">€<?php echo number_format($m['totalMatched'] ?? 0, 0, ',', '.'); ?></span>
                                </td>

                                <!-- Odds -->
                                <td class="py-3 px-4">
                                    <div class="flex gap-2 justify-start items-center overflow-x-auto scrollbar-none max-w-[300px]">
                                        <?php 
                                        foreach (array_slice($mappedRunners, 0, 3) as $runner):
                                            $name = $runner['name'] === 'The Draw' ? 'X' : $runner['name'];
                                            $back = $runner['back'] ?? '-';
                                        ?>
                                            <div class="flex flex-col items-center min-w-[40px]">
                                                <span class="text-[8px] text-slate-500 uppercase truncate max-w-[40px] mb-0.5"><?php echo $name; ?></span>
                                                <div class="bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded px-1.5 py-0.5 text-[10px] font-black w-full text-center">
                                                    <?php echo $back; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </td>

                                <!-- Action -->
                                <td class="py-3 pr-2 text-right">
                                    <button 
                                        hx-get="/api/gianik/analyze/<?php echo $m['marketId']; ?>"
                                        hx-target="#global-modal-container"
                                        class="p-2 rounded-lg text-accent hover:bg-accent/10 transition-colors">
                                        <i data-lucide="brain-circuit" class="w-4 h-4"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>

<?php endif; ?>

<script>
    if (window.lucide) lucide.createIcons();

    window.switchTab = function(sportId, btn) {
        // Reset tabs
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('border-accent', 'text-white');
            b.classList.add('border-transparent', 'text-slate-500');
        });
        // Activate button
        btn.classList.remove('border-transparent', 'text-slate-500');
        btn.classList.add('border-accent', 'text-white');

        // Toggle Content
        document.querySelectorAll('.sport-content').forEach(c => c.classList.add('hidden'));
        const target = document.getElementById('content-' + sportId);
        if (target) {
            target.classList.remove('hidden');
            if (window.lucide) lucide.createIcons();
        }
    }
</script>