<?php
// app/Views/partials/dashboard.php
// This fragment is loaded via HTMX to refresh the dashboard content
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8" id="dashboard-content">
    <!-- Live Matches Column -->
    <div class="lg:col-span-2 space-y-6">
        <div class="flex items-center justify-between mb-8">
            <h2 class="text-3xl font-black italic uppercase tracking-tighter text-white">Live Now <span
                    class="text-accent">.</span></h2>
            <div class="flex gap-2">
                <span
                    class="px-3 py-1 bg-accent/20 text-accent rounded-lg text-[10px] font-black uppercase tracking-widest animate-pulse">
                    <?php echo count($liveMatches ?? []); ?> Active
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
                    return $b['time']['elapsed'] - $a['time']['elapsed']; });
                $recentEvents = array_slice($events, 0, 3);
                ?>

                <div class="glass rounded-[40px] p-8 border-white/5 hover:border-accent/30 transition-all group cursor-pointer relative overflow-hidden mb-6 <?php echo $isPinned ? 'pinned-match' : ''; ?>"
                    onclick="window.location.hash = 'match/<?php echo $match['fixture']['id']; ?>'">

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
                            onclick="event.stopPropagation(); openLineupModal(<?php echo $match['fixture']['id']; ?>, <?php echo $match['teams']['home']['id']; ?>)">
                            <div
                                class="w-16 h-16 p-2 glass rounded-2xl flex items-center justify-center group-hover/team:border-accent/50 transition-all relative">
                                <img src="<?php echo $match['teams']['home']['logo']; ?>"
                                    class="w-full h-full object-contain drop-shadow-2xl">
                                <div
                                    class="absolute -bottom-2 bg-slate-900 border border-white/10 px-2 rounded text-[8px] font-black uppercase text-slate-500 group-hover/team:text-accent transition-colors">
                                    Lineup</div>
                            </div>
                            <span
                                class="text-xs font-black uppercase tracking-tight text-center leading-tight flex items-center gap-2">
                                <?php echo $homeName; ?> <i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i>
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
                            onclick="event.stopPropagation(); openLineupModal(<?php echo $match['fixture']['id']; ?>, <?php echo $match['teams']['away']['id']; ?>)">
                            <div
                                class="w-16 h-16 p-2 glass rounded-2xl flex items-center justify-center group-hover/team:border-accent/50 transition-all relative">
                                <img src="<?php echo $match['teams']['away']['logo']; ?>"
                                    class="w-full h-full object-contain drop-shadow-2xl">
                                <div
                                    class="absolute -bottom-2 bg-slate-900 border border-white/10 px-2 rounded text-[8px] font-black uppercase text-slate-500 group-hover/team:text-accent transition-colors">
                                    Lineup</div>
                            </div>
                            <span
                                class="text-xs font-black uppercase tracking-tight text-center leading-tight flex items-center gap-2">
                                <i data-lucide="chevron-left" class="w-3 h-3 text-slate-600"></i>
                                <?php echo $awayName; ?>
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
                                                onclick="event.stopPropagation(); window.location.hash='player/<?php echo $ev['player']['id']; ?>'">
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
                                onclick="event.stopPropagation(); window.location.hash='match/<?php echo $match['fixture']['id']; ?>'"
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
    </div>

    <!-- Sidebar / Stats Column -->
    <div class="space-y-6">
        <div class="glass p-8 rounded-[40px] border-white/5">
            <h3 class="text-xl font-black italic uppercase tracking-tighter text-white mb-6">Market Trends</h3>
            <div class="space-y-4">
                <div class="bg-white/5 p-4 rounded-2xl border border-white/5 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-accent/20 flex items-center justify-center text-accent">
                        <i data-lucide="trending-up" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Top Market</div>
                        <div class="text-white font-black italic">Over 2.5 Goals</div>
                    </div>
                </div>
                <div class="bg-white/5 p-4 rounded-2xl border border-white/5 flex items-center gap-4">
                    <div class="w-10 h-10 rounded-full bg-success/20 flex items-center justify-center text-success">
                        <i data-lucide="activity" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">High Volatility
                        </div>
                        <div class="text-white font-black italic">Premier League</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    if (window.lucide) lucide.createIcons();
    updateStatsSummary(); // Update sidebar stats if function exists
</script>