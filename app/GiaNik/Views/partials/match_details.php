<?php
// app/GiaNik/Views/partials/match_details.php
$events = $events ?? [];
$details = $details ?? null;
$stats = $stats ?? [];

$fixtureId = $details['fixture']['id'] ?? null;
$venue = $details['fixture']['venue'] ?? null;
?>

<div class="flex flex-col gap-4">
    <!-- Predictions Graphic -->
    <?php if ($predictions && isset($predictions['percent'])): ?>
        <div class="flex flex-col gap-1.5 mb-2">
            <div class="flex justify-between text-[8px] font-black uppercase text-slate-500 tracking-widest">
                <span>Probabilità: Home <?php echo $predictions['percent']['home']; ?></span>
                <span>Draw <?php echo $predictions['percent']['draw']; ?></span>
                <span>Away <?php echo $predictions['percent']['away']; ?></span>
            </div>
            <div class="flex h-1.5 w-full rounded-full overflow-hidden bg-white/5 border border-white/5">
                <div class="bg-accent h-full" style="width: <?php echo $predictions['percent']['home']; ?>"></div>
                <div class="bg-slate-500 h-full" style="width: <?php echo $predictions['percent']['draw']; ?>"></div>
                <div class="bg-indigo-500 h-full" style="width: <?php echo $predictions['percent']['away']; ?>"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Match Facts (Events) -->
    <div class="flex flex-wrap items-center gap-3">
        <span class="text-[9px] font-black uppercase text-slate-500 tracking-widest mr-2">Fatti Salienti:</span>
        <?php if (empty($events)): ?>
            <span class="text-[9px] font-bold text-slate-600 uppercase">Nessun evento registrato</span>
        <?php else: ?>
            <?php
            // Sort events by time and show only Goal, Card, Var, Subst
            $importantTypes = ['goal', 'card', 'var', 'subst'];
            $filteredEvents = array_filter($events, function ($e) use ($importantTypes) {
                return in_array(strtolower($e['type']), $importantTypes);
            });
            // Limit to last 8 important events
            $displayEvents = array_slice($filteredEvents, -8);

            foreach ($displayEvents as $e):
                $type = strtolower($e['type']);
                $detail = strtolower($e['detail'] ?? '');
                $icon = '⚽';
                $colorClass = 'text-white';

                if ($type === 'card') {
                    $icon = '🟨';
                    if (strpos($detail, 'red') !== false)
                        $icon = '🟥';
                    $colorClass = 'text-warning';
                } elseif ($type === 'subst') {
                    $icon = '🔄';
                    $colorClass = 'text-accent';
                } elseif ($type === 'var') {
                    $icon = '🖥️';
                    $colorClass = 'text-indigo-400';
                }
                ?>
                <div class="flex items-center gap-1.5 bg-white/5 px-2 py-1 rounded-lg border border-white/5 cursor-pointer hover:bg-white/10 transition-all"
                    onclick="openPlayerModal(<?php echo $e['player']['id']; ?>, <?php echo $fixtureId; ?>)">
                    <span class="text-[10px]"><?php echo $icon; ?></span>
                    <span class="text-[9px] font-black uppercase <?php echo $colorClass; ?>">
                        <?php echo $e['time']['elapsed']; ?>' <?php echo $e['player']['name']; ?>
                    </span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Stadium & Info -->
    <div class="flex items-center justify-between text-[9px] font-black uppercase tracking-widest text-slate-500">
        <div class="flex items-center gap-4">
            <?php if ($venue && $venue['name']): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-3 h-3 text-slate-600"></i>
                    <span><?php echo $venue['name']; ?>, <?php echo $venue['city']; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($details && isset($details['fixture']['referee'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="user" class="w-3 h-3 text-slate-600"></i>
                    <span>Ref: <?php echo $details['fixture']['referee']; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-4">
            <?php
            // Simple possession or shots stat if available
            foreach ($stats as $s) {
                $teamName = $s['team']['name'];
                $possession = 'N/A';
                foreach ($s['statistics'] as $stat) {
                    if ($stat['type'] === 'Ball Possession')
                        $possession = $stat['value'];
                }
                echo "<span>$teamName: $possession</span>";
            }
            ?>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>