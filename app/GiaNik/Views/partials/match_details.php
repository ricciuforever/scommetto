<?php
// app/GiaNik/Views/partials/match_details.php
$events = $events ?? [];
$details = $details ?? null;
$stats = $stats ?? [];

$fixtureId = $details['fixture']['id'] ?? ($details['id'] ?? null);
$homeId = $details['team_home_id'] ?? null;
$awayId = $details['team_away_id'] ?? null;
$venue = $details['fixture']['venue'] ?? null;
?>

<div class="flex flex-col gap-6 py-2">
    <!-- Predictions Graphic -->
    <?php if ($predictions && isset($predictions['percent'])): ?>
        <div class="flex flex-col gap-1.5 px-2">
            <div class="flex justify-between text-[8px] font-black uppercase text-slate-600 tracking-widest">
                <span>Home <?php echo $predictions['percent']['home']; ?></span>
                <span>Draw <?php echo $predictions['percent']['draw']; ?></span>
                <span>Away <?php echo $predictions['percent']['away']; ?></span>
            </div>
            <div class="flex h-1 w-full rounded-full overflow-hidden bg-white/5">
                <div class="bg-accent h-full shadow-[0_0_8px_rgba(var(--accent-rgb),0.4)]" style="width: <?php echo $predictions['percent']['home']; ?>"></div>
                <div class="bg-slate-700 h-full" style="width: <?php echo $predictions['percent']['draw']; ?>"></div>
                <div class="bg-indigo-600 h-full shadow-[0_0_8px_rgba(79,70,229,0.4)]" style="width: <?php echo $predictions['percent']['away']; ?>"></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Match Timeline -->
    <div class="relative px-2">
        <div class="flex items-center justify-between text-[8px] font-black uppercase text-slate-600 tracking-widest mb-4 px-1">
            <span>Inizio</span>
            <span>45'</span>
            <span>Fine</span>
        </div>

        <div class="relative h-12 flex items-center">
            <!-- Central Track -->
            <div class="absolute left-0 right-0 h-0.5 bg-white/10 rounded-full"></div>

            <!-- Midfield Mark -->
            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-0.5 h-3 bg-white/20"></div>

            <!-- Events -->
            <?php
            $importantTypes = ['goal', 'card', 'var', 'subst'];
            foreach ($events as $e):
                $type = strtolower($e['type'] ?? '');
                if (!in_array($type, $importantTypes)) continue;

                $minute = (int)($e['time']['elapsed'] ?? 0);
                $extra = (int)($e['time']['extra'] ?? 0);
                // Position relative to 95 minutes (to allow for some extra time display)
                $pos = min(100, (($minute + $extra) / 95) * 100);

                $isHome = ($e['team']['id'] ?? ($e['team_id'] ?? null)) == $homeId;

                $icon = '⚽';
                if ($type === 'card') {
                    $icon = '🟨';
                    if (strpos(strtolower($e['detail'] ?? ''), 'red') !== false) $icon = '🟥';
                } elseif ($type === 'subst') {
                    $icon = '🔄';
                } elseif ($type === 'var') {
                    $icon = '🖥️';
                }
            ?>
                <div class="absolute group/ev" style="left: <?php echo $pos; ?>%; <?php echo $isHome ? 'bottom: 55%;' : 'top: 55%;'; ?> transform: translateX(-50%);">
                    <div class="cursor-pointer transition-transform hover:scale-125 relative">
                        <span class="text-[10px] drop-shadow-lg"><?php echo $icon; ?></span>

                        <!-- Tooltip (Simple) -->
                        <div class="absolute <?php echo $isHome ? 'bottom-full mb-2' : 'top-full mt-2'; ?> left-1/2 -translate-x-1/2 hidden group-hover/ev:block z-20">
                            <div class="bg-slate-900 border border-white/10 rounded-lg px-2 py-1 whitespace-nowrap shadow-2xl">
                                <div class="text-[9px] font-black text-white uppercase italic">
                                    <?php echo $minute; ?><?php echo $extra ? "+$extra" : ""; ?>' <?php echo $e['player']['name'] ?? ''; ?>
                                </div>
                                <div class="text-[7px] font-bold text-slate-500 uppercase">
                                    <?php echo $e['detail'] ?? ''; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Stadium & Info -->
    <div class="flex items-center justify-between px-2 text-[8px] font-black uppercase tracking-widest text-slate-600">
        <div class="flex items-center gap-4">
            <?php if ($venue && !empty($venue['name'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="map-pin" class="w-3 h-3 text-slate-700"></i>
                    <span><?php echo $venue['name']; ?>, <?php echo $venue['city']; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($details && !empty($details['fixture']['referee'])): ?>
                <div class="flex items-center gap-1.5">
                    <i data-lucide="user" class="w-3 h-3 text-slate-700"></i>
                    <span>Ref: <?php echo $details['fixture']['referee']; ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats & Info -->
    <div class="flex items-center justify-between px-2 pt-2 border-t border-white/5">
        <div class="flex items-center gap-6">
             <!-- Simplified stats display -->
             <?php
             $homePos = 50; $awayPos = 50;
             $homeShots = 0; $awayShots = 0;
             foreach ($stats as $s) {
                 $isHomeStat = ($s['team_id'] ?? ($s['team']['id'] ?? null)) == $homeId;
                 $statArr = $s['stats_json'] ?? $s['statistics'] ?? [];
                 if (is_string($statArr)) $statArr = json_decode($statArr, true);

                 foreach ($statArr as $st) {
                     if ($st['type'] === 'Ball Possession') {
                         $val = (int)str_replace('%', '', $st['value']);
                         if ($isHomeStat) $homePos = $val; else $awayPos = $val;
                     }
                     if ($st['type'] === 'Total Shots') {
                         $val = (int)$st['value'];
                         if ($isHomeStat) $homeShots = $val; else $awayShots = $val;
                     }
                 }
             }
             ?>
             <div class="flex items-center gap-4">
                 <div class="flex flex-col items-center">
                     <span class="text-[8px] font-black text-slate-600 uppercase tracking-tighter">Possesso</span>
                     <span class="text-[10px] font-black text-white italic"><?php echo $homePos; ?>% - <?php echo $awayPos; ?>%</span>
                 </div>
                 <div class="flex flex-col items-center">
                     <span class="text-[8px] font-black text-slate-600 uppercase tracking-tighter">Tiri</span>
                     <span class="text-[10px] font-black text-white italic"><?php echo $homeShots; ?> - <?php echo $awayShots; ?></span>
                 </div>
             </div>
        </div>

        <div class="flex items-center gap-2">
            <button hx-get="/api/gianik/predictions?fixtureId=<?php echo $fixtureId; ?>"
                    hx-target="#global-modal-container"
                    class="p-2 rounded-lg bg-white/5 hover:bg-white/10 transition-all text-slate-400 hover:text-white"
                    title="Predictions">
                <i data-lucide="line-chart" class="w-3 h-3"></i>
            </button>
            <button hx-get="/gianik/stats-modal/<?php echo $fixtureId; ?>"
                    hx-target="#global-modal-container"
                    class="p-2 rounded-lg bg-white/5 hover:bg-white/10 transition-all text-slate-400 hover:text-white"
                    title="Statistiche">
                <i data-lucide="bar-chart-3" class="w-3 h-3"></i>
            </button>
            <button hx-get="/gianik/lineups-modal/<?php echo $fixtureId; ?>"
                    hx-target="#global-modal-container"
                    class="p-2 rounded-lg bg-white/5 hover:bg-white/10 transition-all text-slate-400 hover:text-white"
                    title="Formazioni">
                <i data-lucide="users" class="w-3 h-3"></i>
            </button>
            <button hx-get="/gianik/h2h-modal/<?php echo $fixtureId; ?>"
                    hx-target="#global-modal-container"
                    class="p-2 rounded-lg bg-white/5 hover:bg-white/10 transition-all text-slate-400 hover:text-white"
                    title="H2H">
                <i data-lucide="history" class="w-3 h-3"></i>
            </button>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>
