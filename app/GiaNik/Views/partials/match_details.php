<?php
// app/GiaNik/Views/partials/match_details.php
$events = $events ?? [];
$details = $details ?? null;
$stats = $stats ?? [];

// In MySQL model, keys are flat
$fixtureId = $details['id'] ?? null;
$venueName = $details['venue_name'] ?? null;
$venueCity = $details['venue_city'] ?? null;
$homeId = $details['team_home_id'] ?? null;
$awayId = $details['team_away_id'] ?? null;

// Timeline events: Goals, Cards, Var, Subst
$timelineEvents = array_filter($events, function ($e) {
    return in_array(strtolower($e['type']), ['goal', 'card', 'var', 'subst']);
});

// Calculate timeline range
$matchElapsed = (int) ($details['elapsed'] ?? 0);
$maxMinutes = max($matchElapsed, 90);
$displayMax = $maxMinutes > 90 ? $maxMinutes + 5 : 95;
?>

<div class="flex flex-col gap-6">

    <!-- Match Timeline (Replaces Probability Bar) -->
    <div class="flex flex-col gap-3">
        <div class="flex justify-between items-center px-1">
            <span class="text-[9px] font-black uppercase text-slate-500 tracking-[0.2em]">Match Timeline</span>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-accent"></div>
                    <span class="text-[8px] font-black text-slate-500 uppercase">Casa</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <div class="w-1.5 h-1.5 rounded-full bg-indigo-500"></div>
                    <span class="text-[8px] font-black text-slate-500 uppercase">Trasferta</span>
                </div>
            </div>
        </div>

        <div
            class="relative w-full h-24 bg-white/[0.02] rounded-[32px] border border-white/5 overflow-hidden backdrop-blur-sm group/timeline">
            <!-- Halftime divider -->
            <div class="absolute left-1/2 top-0 bottom-0 w-px bg-white/10 z-10"></div>
            <!-- Center horizontal line (The actual timeline bar) -->
            <div class="absolute left-4 right-4 top-1/2 h-1 bg-white/5 rounded-full -translate-y-1/2 overflow-hidden">
                <div class="h-full bg-accent/20 transition-all duration-1000"
                    style="width: <?php echo min(100, ($matchElapsed / $displayMax) * 100); ?>%"></div>
            </div>

            <!-- Minute Labels -->
            <div class="absolute left-4 top-1/2 -translate-y-1/2 -ml-6 text-[8px] font-black text-slate-700">0'</div>
            <div
                class="absolute left-1/2 top-2 -translate-x-1/2 text-[7px] font-black text-slate-700 tracking-[0.3em] uppercase">
                HT</div>
            <div class="absolute right-4 top-1/2 -translate-y-1/2 -mr-8 text-[8px] font-black text-slate-700">
                <?php echo $displayMax; ?>'</div>

            <!-- Event Icons -->
            <?php foreach ($timelineEvents as $e):
                $min = (int) $e['time']['elapsed'];
                $extra = (int) ($e['time']['extra'] ?? 0);
                $displayMin = $min . ($extra ? "+$extra" : "");
                // Scale min to fits into 4..96% (considering 4px margins)
                $pos = 4 + (($min / $displayMax) * 92);

                $isHome = ($e['team']['id'] == $homeId);
                $type = str_replace(' ', '', strtolower($e['type']));
                $detail = strtolower($e['detail'] ?? '');

                $icon = '⚽';
                $iconColor = 'text-white';

                if ($type === 'card') {
                    if (strpos($detail, 'red') !== false) {
                        $icon = '🟥';
                        $iconColor = 'text-danger';
                    } else {
                        $icon = '🟨';
                        $iconColor = 'text-warning';
                    }
                } elseif ($type === 'subst') {
                    $icon = '🔄';
                    $iconColor = 'text-accent';
                } elseif ($type === 'var') {
                    $icon = '🖥️';
                    $iconColor = 'text-indigo-400';
                }

                $dotColor = $isHome ? 'bg-accent' : 'bg-indigo-400';
                ?>
                    <div class="absolute flex flex-col items-center group/event z-20 transition-all hover:z-30"
                    style="left: <?php echo $pos; ?>%; <?php echo $isHome ? 'bottom: 50%' : 'top: 50%'; ?>; transform: translateX(-50%);">

                    <!-- Hover Popup -->
                    <div
                        class="opacity-0 group-hover/event:opacity-100 absolute z-50 transition-all duration-200 pointer-events-none <?php echo $isHome ? 'bottom-full mb-4 translate-y-2 group-hover/event:translate-y-0' : 'top-full mt-4 -translate-y-2 group-hover/event:translate-y-0'; ?>">
                        <div
                            class="bg-slate-900/95 border border-white/10 p-2.5 rounded-[20px] shadow-2xl backdrop-blur-xl flex items-center gap-3 whitespace-nowrap">
                            <div class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center text-lg">
                             <?php echo $icon; ?></div>
                            <div class="flex flex-col">
                                <span
                                    class="text-[10px] font-black text-white uppercase leading-none mb-1"><?php echo htmlspecialchars($e['player']['name']); ?></span>
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-[7px] font-black text-slate-500 uppercase tracking-widest"><?php echo $displayMin; ?>'</span>
                                    <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                                    <span
                                        class="text-[7px] font-black <?php echo $iconColor; ?> uppercase tracking-widest"><?php echo htmlspecialchars($e['detail'] ?? $e['type']); ?></span>
                                </div>
                            </div>
                        </div>
                        <!-- Arrow -->
                        <div
                            class="absolute left-1/2 -translate-x-1/2 w-3 h-3 bg-slate-900 rotate-45 border border-white/10 <?php echo $isHome ? 'top-full -mt-1.5 border-t-0 border-l-0' : 'bottom-full -mb-1.5 border-b-0 border-r-0'; ?>">
                        </div>
                    </div>

                    <?php if ($isHome): ?>
                        <span
                            class="text-[12px] mb-1 drop-shadow-lg scale-100 group-hover/event:scale-125 transition-transform"><?php echo $icon; ?></span>
                        <div
                            class="w-2.5 h-2.5 rounded-full <?php echo $dotColor; ?> border-2 border-slate-900 shadow-lg ring-1 ring-white/10 transition-all group-hover/event:ring-accent/50">
                        </div>
                    <?php else: ?>
                        <div
                            class="w-2.5 h-2.5 rounded-full <?php echo $dotColor; ?> border-2 border-slate-900 shadow-lg ring-1 ring-white/10 transition-all group-hover/event:ring-indigo-400/50">
                        </div>
                        <span
                            class="text-[12px] mt-1 drop-shadow-lg scale-100 group-hover/event:scale-125 transition-transform"><?php echo $icon; ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Stadium & Info -->
    <div
        class="flex items-center justify-between text-[9px] font-black uppercase tracking-[0.15em] text-slate-500 border-t border-white/5 pt-5">
        <div class="flex items-center gap-8">
            <?php if ($venueName): ?>
                <div class="flex items-center gap-2.5 group">
                    <div
                        class="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center transition-colors group-hover:bg-accent/10">
                        <i data-lucide="map-pin"
                            class="w-3 h-3 text-slate-600 transition-colors group-hover:text-accent"></i>
                    </div>
                    <span class="group-hover:text-slate-300 transition-colors"><?php echo $venueName; ?>,
                        <?php echo $venueCity; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($details && isset($details['referee'])): ?>
                <div class="flex items-center gap-2.5 group">
                    <div
                        class="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center transition-colors group-hover:bg-accent/10">
                        <i data-lucide="user" class="w-3 h-3 text-slate-600 transition-colors group-hover:text-accent"></i>
                    </div>
                    <span class="group-hover:text-slate-300 transition-colors">Ref:
                        <?php echo $details['referee']; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="flex items-center gap-8">
            <?php
            foreach ($stats as $s) {
                $teamName = $s['team']['name'] ?? '';
                $possession = null;
                foreach ($s['statistics'] as $stat) {
                    if ($stat['type'] === 'Ball Possession')
                        $possession = $stat['value'];
                }
                if ($possession !== null) {
                    $isHomeStat = ($s['team']['id'] == $homeId);
                    $colorClass = $isHomeStat ? 'text-accent' : 'text-indigo-400';
                    $bgClass = $isHomeStat ? 'bg-accent/5 border-accent/10' : 'bg-indigo-400/5 border-indigo-400/10';
                    echo "<div class='flex items-center gap-3 px-3 py-1.5 rounded-xl $bgClass border'>
                            <span class='opacity-40 text-[8px]'>$teamName</span> 
                            <span class='font-black $colorClass text-xs leading-none'>$possession</span>
                          </div>";
                }
            }
            ?>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>