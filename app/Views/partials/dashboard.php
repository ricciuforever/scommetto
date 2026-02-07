<?php
// app/Views/partials/dashboard.php
// This fragment is loaded via HTMX to refresh the dashboard content
?>

<?php
// Extract countries and leagues for filters from all live matches
$countries = [];
$leaguesMap = [];
foreach ($allLiveMatches ?? [] as $m) {
    $c = $m['league']['country'] ?? $m['league']['country_name'] ?? 'International';
    if (!in_array($c, $countries))
        $countries[] = $c;

    if ($selectedCountry === 'all' || $c === $selectedCountry) {
        $leaguesMap[$m['league']['id']] = $m['league']['name'];
    }
}
sort($countries);
asort($leaguesMap);

$selectedCountry = $selectedCountry ?? 'all';
$selectedLeague = $selectedLeague ?? 'all';
?>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-10" id="stats-summary">
    <!-- Populated by JS updateStatsSummary() -->
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8" id="dashboard-content">
    <!-- Live Matches Column -->
    <div class="lg:col-span-2 space-y-6" id="live-matches-list">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <h2 class="text-4xl font-black italic uppercase tracking-tighter text-white leading-none">Live Now <span
                    class="text-accent">.</span></h2>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Dashboard Local Filters -->
                <div class="flex items-center gap-2">
                    <select id="dash-country-filter" name="country"
                        hx-get="/api/view/dashboard" hx-target="#htmx-container" hx-include="[name='league']"
                        hx-trigger="change"
                        onchange="updateSelectedCountry(this.value)"
                        class="dash-filter-select bg-white/5 border border-white/5 rounded-2xl px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400 focus:border-accent/50 outline-none transition-all cursor-pointer">
                        <option value="all">Tutte le Nazioni</option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $selectedCountry === $c ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select id="dash-league-filter" name="league"
                        hx-get="/api/view/dashboard" hx-target="#htmx-container" hx-include="[name='country']"
                        hx-trigger="change"
                        onchange="updateSelectedLeague(this.value)"
                        class="dash-filter-select bg-white/5 border border-white/5 rounded-2xl px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-400 focus:border-accent/50 outline-none transition-all cursor-pointer">
                        <option value="all">Tutti i Campionati</option>
                        <?php foreach ($leaguesMap as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo (string) $selectedLeague === (string) $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="h-8 w-px bg-white/5 mx-2 hidden md:block"></div>

                <span
                    class="px-4 py-2 bg-accent/10 text-accent rounded-2xl text-[10px] font-black uppercase tracking-widest border border-accent/20">
                    <span id="live-active-count"><?php echo count($liveMatches ?? []); ?></span> Active
                </span>
            </div>
        </div>

        <?php if (empty($liveMatches)): ?>
            <div class="glass p-12 rounded-[40px] text-center border-white/5 flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mb-6">
                    <i data-lucide="calendar-off" class="w-8 h-8 text-slate-500"></i>
                </div>
                <h3 class="text-xl font-black text-white uppercase italic tracking-tight mb-2">Nessun Match Live</h3>
                <p class="text-slate-400 font-medium text-sm max-w-md mx-auto">Non ci sono partite in corso che
                    corrispondono ai tuoi filtri.</p>
            </div>
        <?php else: ?>
            <?php foreach ($liveMatches as $match):
                $isPinned = in_array($match['fixture']['id'], $pinnedMatches ?? []);
                $homeName = $match['teams']['home']['name'];
                $awayName = $match['teams']['away']['name'];
                $scoreHome = $match['goals']['home'] ?? 0;
                $scoreAway = $match['goals']['away'] ?? 0;
                $elapsed = $match['fixture']['status']['elapsed'] ?? 0;
                $statusShort = $match['fixture']['status']['short'];

                $period = '';
                if (in_array($statusShort, ['1H', 'HT', '2H', 'ET', 'P']))
                    $period = $statusShort;
                else if ($elapsed <= 45)
                    $period = '1H';
                else
                    $period = '2H';

                $events = $match['events'] ?? [];
                usort($events, function ($a, $b) {
                    return $b['time']['elapsed'] - $a['time']['elapsed'];
                });
                $recentEvents = array_slice($events, 0, 3);
                ?>

                <div class="glass rounded-[40px] p-8 border-white/5 hover:border-accent/30 transition-all group cursor-pointer relative overflow-hidden mb-6 <?php echo $isPinned ? 'pinned-match' : ''; ?>"
                    onclick="navigate('match', '<?php echo $match['fixture']['id']; ?>')">

                    <!-- Header: League + Country -->
                    <div class="flex items-center justify-between mb-8 border-b border-white/5 pb-4">
                        <div class="flex items-center gap-3 opacity-80">
                            <img src="<?php echo $match['league']['flag'] ?? $match['league']['logo']; ?>"
                                class="w-5 h-5 rounded-full object-cover">
                            <div class="flex flex-col">
                                <span class="text-[10px] font-black uppercase tracking-[0.2em] italic text-white">
                                    <?php echo $match['league']['name']; ?>
                                </span>
                                <span class="text-[8px] font-bold uppercase tracking-widest text-slate-500">
                                    <?php echo $match['league']['country'] ?? 'International'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-full border border-white/10">
                            <span class="text-[10px] font-black text-accent uppercase tracking-widest">
                                <?php echo $period; ?>
                            </span>
                            <div class="h-3 w-px bg-white/10"></div>
                            <span class="text-[10px] font-black text-white uppercase tracking-widest">
                                <?php echo $elapsed; ?>'
                            </span>
                            <div class="w-1.5 h-1.5 bg-danger rounded-full animate-pulse shadow-[0_0_10px_red]"></div>
                        </div>
                    </div>

                    <!-- Match Score & Teams -->
                    <div class="flex items-center justify-between gap-4 mb-8">
                        <!-- Home -->
                        <div class="flex flex-col items-center gap-4 flex-1 group/team cursor-pointer"
                            onclick="event.stopPropagation(); navigate('team', '<?php echo $match['teams']['home']['id']; ?>')">
                            <div
                                class="w-16 h-16 p-2 glass rounded-2xl flex items-center justify-center group-hover/team:border-accent/50 transition-all relative">
                                <img src="<?php echo $match['teams']['home']['logo']; ?>"
                                    class="w-full h-full object-contain drop-shadow-2xl">
                                <div
                                    class="absolute -bottom-2 bg-slate-900 border border-white/10 px-2 rounded text-[8px] font-black uppercase text-slate-500 group-hover/team:text-accent transition-colors">
                                    Profilo</div>
                            </div>
                            <span
                                class="text-xs font-black uppercase tracking-tight text-center leading-tight flex items-center gap-2">
                                <?php echo htmlspecialchars($homeName); ?> <i data-lucide="chevron-right"
                                    class="w-3 h-3 text-slate-600 shrink-0"></i>
                            </span>
                        </div>

                        <!-- Score -->
                        <div
                            class="text-5xl md:text-6xl font-black italic tracking-tighter text-white tabular-nums flex flex-col items-center">
                            <span>
                                <?php echo $scoreHome; ?> -
                                <?php echo $scoreAway; ?>
                            </span>
                            <span class="text-[9px] font-bold text-slate-500 tracking-widest uppercase mt-2 opacity-50">Live
                                Score</span>
                        </div>

                        <!-- Away -->
                        <div class="flex flex-col items-center gap-4 flex-1 group/team cursor-pointer"
                            onclick="event.stopPropagation(); navigate('team', '<?php echo $match['teams']['away']['id']; ?>')">
                            <div
                                class="w-16 h-16 p-2 glass rounded-2xl flex items-center justify-center group-hover/team:border-accent/50 transition-all relative">
                                <img src="<?php echo $match['teams']['away']['logo']; ?>"
                                    class="w-full h-full object-contain drop-shadow-2xl">
                                <div
                                    class="absolute -bottom-2 bg-slate-900 border border-white/10 px-2 rounded text-[8px] font-black uppercase text-slate-500 group-hover/team:text-accent transition-colors">
                                    Profilo</div>
                            </div>
                            <span
                                class="text-xs font-black uppercase tracking-tight text-center leading-tight flex items-center gap-2">
                                <i data-lucide="chevron-left" class="w-3 h-3 text-slate-600 shrink-0"></i>
                                <?php echo htmlspecialchars($awayName); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Bottom Section: Timeline & Actions -->
                    <div class="flex flex-col md:flex-row items-center justify-between gap-6 pt-6 border-t border-white/5">
                        <!-- Left: Timeline -->
                        <div class="flex-1 min-w-0 w-full">
                            <?php if (!empty($recentEvents)): ?>
                                <div class="flex items-center gap-3 overflow-x-auto no-scrollbar mask-linear-fade pr-4">
                                    <?php foreach ($recentEvents as $ev):
                                        $icon = 'info';
                                        $color = 'slate-500';
                                        if ($ev['type'] === 'Goal') {
                                            $icon = 'trophy';
                                            $color = 'text-accent';
                                        } elseif ($ev['type'] === 'Card' && $ev['detail'] === 'Yellow Card') {
                                            $icon = 'alert-triangle';
                                            $color = 'text-warning';
                                        } elseif ($ev['type'] === 'Card') {
                                            $icon = 'alert-octagon';
                                            $color = 'text-danger';
                                        }
                                        ?>
                                        <div
                                            class="flex items-center gap-1.5 shrink-0 bg-white/5 px-3 py-1.5 rounded-xl border border-white/5">
                                            <span class="text-[9px] font-black text-slate-400">
                                                <?php echo $ev['time']['elapsed']; ?>'
                                            </span>
                                            <i data-lucide="<?php echo $icon; ?>" class="w-3 h-3 <?php echo $color; ?>"></i>
                                            <span
                                                class="text-[9px] font-bold text-white uppercase truncate max-w-[80px] hover:text-accent cursor-pointer transition-colors"
                                                onclick="event.stopPropagation(); navigate('player', '<?php echo $ev['player']['id']; ?>')">
                                                <?php echo $ev['player']['name']; ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-[9px] font-bold text-slate-600 italic">Nessun evento recente</div>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Buttons -->
                        <div class="flex items-center gap-3 shrink-0">
                            <button
                                onclick="event.stopPropagation(); navigate('match', '<?php echo $match['fixture']['id']; ?>')"
                                class="p-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white border border-white/5 transition-all group/btn"
                                title="Dettagli">
                                <i data-lucide="arrow-right"
                                    class="w-4 h-4 group-hover/btn:translate-x-1 transition-transform"></i>
                            </button>

                            <button onclick="event.stopPropagation(); openStatsModal(<?php echo $match['fixture']['id']; ?>)"
                                class="px-5 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/5 transition-all font-black uppercase text-[9px] tracking-widest flex items-center gap-2">
                                <i data-lucide="bar-chart-2" class="w-3 h-3"></i> Stats & Predictions
                            </button>

                            <button onclick="event.stopPropagation(); analyzeMatch(<?php echo $match['fixture']['id']; ?>)"
                                class="px-5 py-3 rounded-xl bg-accent text-white shadow-lg shadow-accent/20 hover:scale-[1.02] active:scale-95 transition-all font-black uppercase text-[9px] tracking-widest flex items-center gap-2">
                                <i data-lucide="sparkles" class="w-3 h-3"></i> AI Analysis
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($upcomingMatches)): ?>
            <div class="col-span-full border-t border-white/5 my-8 pt-4 text-[10px] font-black uppercase tracking-widest text-slate-500 flex items-center gap-4">
                <div class="h-px bg-white/10 flex-1"></div>PROSSIME 24 ORE<div class="h-px bg-white/10 flex-1"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($upcomingMatches as $m): ?>
                    <div onclick="navigate('match', '<?php echo $m['fixture_id']; ?>')"
                        class="glass p-6 rounded-3xl border-white/5 hover:border-accent/30 transition-all cursor-pointer group relative overflow-hidden h-full">
                        <div class="absolute top-0 right-0 p-4 opacity-50"><i data-lucide="calendar-clock"
                                class="w-4 h-4 text-slate-500"></i></div>
                        <div class="flex items-center gap-2 mb-4">
                            <span class="text-[8px] font-black uppercase text-accent tracking-widest truncate max-w-[150px]">
                                <?php echo $m['league_name']; ?>
                            </span>
                            <span class="text-[8px] font-bold text-slate-500">
                                <?php echo date('H:i', strtotime($m['date'])); ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between gap-4">
                            <div class="flex flex-col items-center gap-2 flex-1 min-w-0">
                                <img src="<?php echo $m['home_logo']; ?>" class="w-8 h-8 object-contain">
                                <span class="text-[9px] font-black uppercase text-center leading-tight truncate w-full">
                                    <?php echo htmlspecialchars($m['home_name']); ?>
                                </span>
                            </div>
                            <div class="text-[10px] font-black text-slate-600 italic">VS</div>
                            <div class="flex flex-col items-center gap-2 flex-1 min-w-0">
                                <img src="<?php echo $m['away_logo']; ?>" class="w-8 h-8 object-contain">
                                <span class="text-[9px] font-black uppercase text-center leading-tight truncate w-full">
                                    <?php echo htmlspecialchars($m['away_name']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mt-4 pt-4 border-t border-white/5">
                            <button onclick="event.stopPropagation(); analyzeMatch(<?php echo $m['fixture_id']; ?>)"
                                class="text-[8px] font-black uppercase tracking-widest text-accent hover:text-white transition-colors">AI
                                Forecast</button>
                            <button onclick="event.stopPropagation(); navigate('match', <?php echo $m['fixture_id']; ?>)"
                                class="text-[8px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-colors">Pronostico</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar / Stats Column -->
    <aside class="space-y-8">
        <div>
            <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Hot Predictions</h2>
            <div id="dashboard-predictions-php" class="space-y-4">
                <?php if (empty($hotPredictions)): ?>
                    <div class="text-[10px] font-bold text-slate-500 italic uppercase">Nessun pronostico hot.</div>
                <?php else: ?>
                    <?php foreach ($hotPredictions as $p): ?>
                        <div class="glass p-5 rounded-[24px] border-white/5 cursor-pointer hover:border-accent/30 transition-all group"
                            onclick="navigate('match', '<?php echo $p['fixture_id']; ?>')">
                            <div class="flex items-center justify-between mb-4">
                                <span
                                    class="text-[8px] font-black uppercase tracking-widest text-slate-500 italic truncate max-w-[100px]"><?php echo $p['league_name']; ?></span>
                                <span
                                    class="text-[8px] font-black uppercase text-accent tracking-widest"><?php echo date('d M', strtotime($p['date'])); ?></span>
                            </div>
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <img src="<?php echo $p['home_logo']; ?>" class="w-8 h-8 object-contain">
                                <span class="text-[9px] font-black uppercase text-slate-500 text-center flex-1">VS</span>
                                <img src="<?php echo $p['away_logo']; ?>" class="w-8 h-8 object-contain">
                            </div>
                            <div class="bg-accent/10 border border-accent/20 p-3 rounded-xl text-center">
                                <div class="text-[7px] font-black text-accent uppercase tracking-widest mb-1 opacity-80">AI
                                    Suggestion</div>
                                <div class="text-[10px] font-black text-white italic uppercase leading-tight">
                                    <?php echo $p['advice']; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Recent Activity</h2>
            <div class="glass rounded-[32px] border-white/5 divide-y divide-white/5 overflow-hidden"
                id="dashboard-history-php">
                <?php if (empty($recentActivity)): ?>
                    <div class="p-6 text-[10px] font-bold text-slate-500 italic uppercase text-center">Nessuna attività
                        recente.</div>
                <?php else: ?>
                    <?php foreach ($recentActivity as $bet):
                        $statusClass = $bet['status'] === 'won' ? 'text-success' : ($bet['status'] === 'lost' ? 'text-danger' : 'text-warning');
                        ?>
                        <div class="p-5 hover:bg-white/5 cursor-pointer transition-all flex flex-col gap-2 group"
                            onclick='showBetDetails(<?php echo json_encode($bet); ?>)'>
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[8px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[120px]"><?php echo $bet['match_name']; ?></span>
                                <span
                                    class="text-[8px] font-black uppercase <?php echo $statusClass; ?>"><?php echo $bet['status']; ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-black italic text-white group-hover:text-accent transition-colors truncate max-w-[150px]"><?php echo $bet['market']; ?></span>
                                <span class="text-xs font-black tabular-nums text-white">@ <?php echo $bet['odds']; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>
<script>
    if (window.lucide) lucide.createIcons();
    if (typeof updateStatsSummary === 'function') updateStatsSummary();
</script>