<?php
// app/GiaNik/Views/partials/modals/match_trend.php
$fixture = $fixture ?? [];
$statistics = $statistics ?? [];
$events = $events ?? [];
$momentum = $momentum ?? '';
?>

<div id="match-trend-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative flex flex-col max-h-[90vh]"
        onclick="event.stopPropagation()">

        <div class="p-8 border-b border-white/5 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
                    <i data-lucide="line-chart" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-white uppercase italic leading-none">Andamento <span class="text-indigo-400">Match</span></h3>
                    <p class="text-slate-500 text-[9px] font-black uppercase tracking-widest mt-1"><?php echo htmlspecialchars($fixture['team_home_name'] . ' vs ' . $fixture['team_away_name']); ?></p>
                </div>
            </div>
            <button onclick="document.getElementById('match-trend-modal').remove()"
                class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto p-8 space-y-6">
            <!-- Momentum / Intensity Index -->
            <div class="glass p-6 rounded-3xl border-indigo-500/20 bg-indigo-500/5">
                <div class="flex items-center gap-2 mb-4">
                    <i data-lucide="zap" class="w-4 h-4 text-accent"></i>
                    <h4 class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Momentum & Intensity Index</h4>
                </div>
                <div class="text-sm italic text-slate-300 whitespace-pre-line leading-relaxed">
                    <?php echo !empty($momentum) ? htmlspecialchars($momentum) : 'Dati momentum non ancora sufficienti per questo match.'; ?>
                </div>
            </div>

            <!-- Statistics Grid -->
            <?php if (!empty($statistics)): ?>
                <div class="grid grid-cols-2 gap-4">
                    <?php foreach ($statistics as $teamStats): ?>
                        <div class="glass p-5 rounded-3xl border-white/5 bg-white/[0.02]">
                            <div class="flex items-center gap-2 mb-4">
                                <img src="<?php echo $teamStats['team_logo']; ?>" class="w-5 h-5 object-contain" alt="">
                                <h4 class="text-[10px] font-black text-white uppercase truncate"><?php echo htmlspecialchars($teamStats['team_name']); ?></h4>
                            </div>
                            <div class="space-y-2">
                                <?php
                                $stats = is_string($teamStats['stats_json']) ? json_decode($teamStats['stats_json'], true) : ($teamStats['stats_json'] ?? []);
                                foreach ($stats as $s):
                                    if (in_array($s['type'], ['Total Shots', 'Shots on Goal', 'Corner Kicks', 'Ball Possession', 'Dangerous Attacks'])):
                                ?>
                                    <div class="flex justify-between items-center py-1.5 border-b border-white/5 last:border-0">
                                        <span class="text-[9px] text-slate-500 font-bold uppercase tracking-tighter"><?php echo $s['type']; ?></span>
                                        <span class="text-xs font-black text-white"><?php echo $s['value'] ?: '0'; ?></span>
                                    </div>
                                <?php
                                    endif;
                                endforeach;
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Timeline / Events -->
            <?php if (!empty($events)): ?>
                <div class="glass p-6 rounded-3xl border-white/5">
                    <div class="flex items-center gap-2 mb-4">
                        <i data-lucide="list-todo" class="w-4 h-4 text-slate-500"></i>
                        <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Timeline Eventi</h4>
                    </div>
                    <div class="space-y-3">
                        <?php foreach (array_reverse($events) as $e): ?>
                            <?php
                            $icon = 'circle'; $color = 'text-slate-500';
                            if ($e['type'] === 'Goal') { $icon = 'star'; $color = 'text-success'; }
                            if ($e['type'] === 'Card') {
                                $icon = 'square';
                                $color = strpos(strtolower($e['detail'] ?? ''), 'red') !== false ? 'text-danger' : 'text-warning';
                            }
                            ?>
                            <div class="flex items-center gap-4 p-3 rounded-2xl bg-white/5 border border-white/5">
                                <div class="text-xs font-black text-slate-500 w-8 shrink-0"><?php echo $e['time']['elapsed']; ?>'</div>
                                <div class="<?php echo $color; ?> shrink-0"><i data-lucide="<?php echo $icon; ?>" class="w-4 h-4"></i></div>
                                <div class="flex-1">
                                    <div class="text-[10px] font-black text-white uppercase"><?php echo htmlspecialchars($e['detail']); ?></div>
                                    <div class="text-[8px] font-bold text-slate-500 uppercase"><?php echo htmlspecialchars($e['player']['name'] ?? ''); ?></div>
                                </div>
                                <?php if (isset($e['team']['logo'])): ?>
                                    <img src="<?php echo $e['team']['logo']; ?>" class="w-4 h-4 object-contain opacity-50" alt="">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        if (window.lucide) lucide.createIcons();
    </script>
</div>