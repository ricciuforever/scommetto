<?php
// app/GiaNik/Views/partials/modals/stats.php
$stats = $stats ?? [];
$details = $details ?? null;
?>

<div id="stats-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
        onclick="event.stopPropagation()">

        <div class="p-8 border-b border-white/5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="p-4 bg-blue-500/10 rounded-2xl border border-blue-500/10">
                    <i data-lucide="bar-chart-2" class="w-6 h-6 text-blue-400"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-white uppercase italic">Live Statistics</h3>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">
                        <?php echo $details['teams']['home']['name']; ?> vs
                        <?php echo $details['teams']['away']['name']; ?>
                    </p>
                </div>
            </div>
            <button onclick="document.getElementById('stats-modal').remove()"
                class="w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="p-8 max-h-[70vh] overflow-y-auto">
            <div class="grid grid-cols-2 gap-12">
                <!-- Home Stats -->
                <div>
                    <div class="flex items-center gap-3 mb-6">
                        <img src="<?php echo $details['teams']['home']['logo']; ?>" class="w-8 h-8 object-contain">
                        <span class="text-xs font-black text-white uppercase italic">
                            <?php echo $details['teams']['home']['name']; ?>
                        </span>
                    </div>
                    <div id="home-stats-container" class="flex flex-col gap-4">
                        <?php
                        $homeStats = array_filter($stats, fn($s) => ($s['team_id'] ?? ($s['team']['id'] ?? null)) == $details['teams']['home']['id']);
                        $homeStats = reset($homeStats);
                        $homeStatsArr = $homeStats['stats_json'] ?? $homeStats['statistics'] ?? [];
                        foreach ($homeStatsArr as $s): ?>
                            <div class="flex justify-between items-center py-2 border-b border-white/5">
                                <span class="text-[10px] text-slate-500 font-bold uppercase">
                                    <?php echo $s['type'] ?? 'N/A'; ?>
                                </span>
                                <span class="text-sm font-black text-white">
                                    <?php echo $s['value'] ?: '0'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Away Stats -->
                <div>
                    <div class="flex items-center gap-3 mb-6 justify-end text-right">
                        <span class="text-xs font-black text-white uppercase italic">
                            <?php echo $details['teams']['away']['name']; ?>
                        </span>
                        <img src="<?php echo $details['teams']['away']['logo']; ?>" class="w-8 h-8 object-contain">
                    </div>
                    <div id="away-stats-container" class="flex flex-col gap-4 text-right">
                        <?php
                        $awayStats = array_filter($stats, fn($s) => ($s['team_id'] ?? ($s['team']['id'] ?? null)) == $details['teams']['away']['id']);
                        $awayStats = reset($awayStats);
                        $awayStatsArr = $awayStats['stats_json'] ?? $awayStats['statistics'] ?? [];
                        foreach ($awayStatsArr as $s): ?>
                            <div class="flex justify-between items-center py-2 border-b border-white/5">
                                <span class="text-sm font-black text-white">
                                    <?php echo $s['value'] ?: '0'; ?>
                                </span>
                                <span class="text-[10px] text-slate-500 font-bold uppercase">
                                    <?php echo $s['type'] ?? 'N/A'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>