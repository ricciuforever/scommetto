<?php
// app/GiaNik/Views/partials/modals/player_stats.php
$player = $player ?? null;
if (!$player)
    return;

$p = $player['player'];
$statistics = $player['statistics'] ?? [];
$stats = $statistics[0] ?? [];
$team = $player['team'] ?? [];
$career = $player['career'] ?? [];
$trophies = $player['trophies']['trophies'] ?? [];
$transfers = $player['transfers']['transfers'] ?? [];
$sidelined = $player['sidelined']['sidelined'] ?? [];

// Helper to get stat value
$getStat = function ($path) use ($stats) {
    $keys = explode('.', $path);
    $val = $stats;
    foreach ($keys as $key) {
        if (!isset($val[$key]))
            return 0;
        $val = $val[$key];
    }
    return $val ?: 0;
};
?>

<div id="player-details-modal"
    class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-4xl max-h-[90vh] rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative flex flex-col"
        onclick="event.stopPropagation()">

        <!-- Header -->
        <div class="relative h-40 shrink-0 bg-gradient-to-r from-accent/20 to-slate-900">
            <button onclick="document.getElementById('player-details-modal').remove()"
                class="absolute top-6 right-6 w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-20">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <div class="absolute bottom-6 left-10 flex items-center gap-6 z-10">
                <div class="relative">
                    <div
                        class="w-24 h-24 rounded-full bg-slate-800 border-4 border-slate-900 overflow-hidden shadow-2xl">
                        <img src="<?php echo $p['photo']; ?>" class="w-full h-full object-cover" alt="">
                    </div>
                    <?php if ($team['logo'] ?? null): ?>
                        <div class="absolute -bottom-1 -right-1 w-8 h-8 rounded-lg bg-white p-1 shadow-lg">
                            <img src="<?php echo $team['logo']; ?>" class="w-full h-full object-contain" alt="">
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-3xl font-black tracking-tight text-white uppercase italic leading-none">
                        <?php echo $p['name']; ?>
                    </h3>
                    <div class="flex items-center gap-3 mt-2">
                        <span class="text-slate-400 text-xs font-black uppercase tracking-widest">
                            <?php echo $stats['games']['position'] ?? 'N/A'; ?> • <?php echo $p['nationality']; ?> •
                            <?php echo $p['age']; ?> anni
                        </span>
                        <div
                            class="text-[9px] font-black uppercase text-accent bg-accent/10 px-2 py-0.5 rounded-full border border-accent/20">
                            Rating: <?php echo $stats['games']['rating'] ?? 'N/A'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-10 custom-scrollbar bg-slate-900">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                <!-- Left: Stats & Market -->
                <div class="md:col-span-1 space-y-6">
                    <!-- Seasonal Card -->
                    <div class="glass p-6 rounded-[32px] border-white/5 bg-accent/5">
                        <div
                            class="text-[10px] font-black text-accent uppercase tracking-widest mb-4 flex items-center gap-2">
                            <i data-lucide="bar-chart-3" class="w-3 h-3"></i> Stagione Corrente
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-3 rounded-2xl bg-white/5">
                                <div class="text-[8px] font-bold text-slate-500 uppercase">Gol</div>
                                <div class="text-xl font-black text-white"><?php echo $getStat('goals.total'); ?></div>
                            </div>
                            <div class="text-center p-3 rounded-2xl bg-white/5">
                                <div class="text-[8px] font-bold text-slate-500 uppercase">Assist</div>
                                <div class="text-xl font-black text-white"><?php echo $getStat('goals.assists'); ?>
                                </div>
                            </div>
                            <div class="text-center p-3 rounded-2xl bg-white/5">
                                <div class="text-[8px] font-bold text-slate-500 uppercase">Minuti</div>
                                <div class="text-xl font-black text-white"><?php echo $getStat('games.minutes'); ?>'
                                </div>
                            </div>
                            <div class="text-center p-3 rounded-2xl bg-white/5">
                                <div class="text-[8px] font-bold text-slate-500 uppercase">Rating</div>
                                <div class="text-xl font-black text-accent">
                                    <?php echo $getStat('games.rating') ?: '-'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-center gap-2">
                            <div
                                class="px-3 py-1 bg-yellow-500/10 rounded-lg border border-yellow-500/20 text-[10px] font-black text-yellow-500">
                                🟨 <?php echo $getStat('cards.yellow'); ?></div>
                            <div
                                class="px-3 py-1 bg-red-500/10 rounded-lg border border-red-500/20 text-[10px] font-black text-red-500">
                                🟥 <?php echo $getStat('cards.red'); ?></div>
                        </div>
                    </div>

                    <!-- Physical / Info -->
                    <?php
                    $hasPhysical = !empty($p['height']) || !empty($p['weight']) || !empty($p['birth_date']);
                    if ($hasPhysical):
                        ?>
                        <div class="glass p-6 rounded-[32px] border-white/5">
                            <div class="space-y-3">
                                <?php if ($p['height']): ?>
                                    <div class="flex justify-between items-center text-[10px] uppercase">
                                        <span class="font-bold text-slate-500">Altezza</span>
                                        <span class="font-black text-white"><?php echo $p['height']; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($p['weight']): ?>
                                    <div class="flex justify-between items-center text-[10px] uppercase">
                                        <span class="font-bold text-slate-500">Peso</span>
                                        <span class="font-black text-white"><?php echo $p['weight']; ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($p['birth_date']): ?>
                                    <div class="flex justify-between items-center text-[10px] uppercase">
                                        <span class="font-bold text-slate-500">Nascita</span>
                                        <span
                                            class="font-black text-white"><?php echo date('d/m/Y', strtotime($p['birth_date'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Trophies -->
                    <?php if ($trophies): ?>
                        <div class="glass p-6 rounded-[32px] border-white/5">
                            <div
                                class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                <i data-lucide="trophy" class="w-3 h-3 text-yellow-500"></i> Palmarès
                            </div>
                            <div class="space-y-3">
                                <?php
                                // Group trophies by name
                                $groupedTrophies = [];
                                foreach ($trophies as $t) {
                                    $name = $t['league'] ?? 'Trophy';
                                    if (!isset($groupedTrophies[$name]))
                                        $groupedTrophies[$name] = 0;
                                    $groupedTrophies[$name]++;
                                }
                                asort($groupedTrophies);
                                $groupedTrophies = array_reverse($groupedTrophies);
                                foreach (array_slice($groupedTrophies, 0, 5) as $name => $count): ?>
                                    <div class="flex items-center justify-between">
                                        <span
                                            class="text-[9px] font-bold text-slate-300 uppercase truncate pr-4"><?php echo $name; ?></span>
                                        <span class="text-xs font-black text-accent italic">x<?php echo $count; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right: Career & Details -->
                <div class="md:col-span-2 space-y-8">
                    <!-- Career Path -->
                    <?php if ($career): ?>
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h4
                                    class="text-sm font-black text-white uppercase italic tracking-widest flex items-center gap-2">
                                    <i data-lucide="history" class="w-4 h-4 text-accent"></i> Carriera
                                </h4>
                            </div>
                            <div class="space-y-3">
                                <?php foreach (array_slice($career, 0, 5) as $c):
                                    $seasons = json_decode($c['seasons_json'] ?? '[]', true);
                                    ?>
                                    <div class="flex items-center gap-4 p-3 rounded-2xl bg-white/5 border border-white/5">
                                        <div class="w-8 h-8 rounded-lg bg-slate-800 p-1 flex items-center justify-center">
                                            <img src="<?php echo $c['team_logo']; ?>" class="w-full h-full object-contain"
                                                alt="">
                                        </div>
                                        <div class="flex-1">
                                            <div class="text-xs font-black text-white uppercase italic">
                                                <?php echo $c['team_name']; ?>
                                            </div>
                                            <div class="text-[9px] font-bold text-slate-500 uppercase">
                                                <?php echo $c['team_country']; ?>
                                            </div>
                                        </div>
                                        <div class="text-[10px] font-black text-accent bg-accent/10 px-2 py-1 rounded-lg">
                                            <?php echo is_array($seasons) ? implode(', ', $seasons) : $seasons; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Transfers -->
                    <?php if ($transfers): ?>
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h4
                                    class="text-sm font-black text-white uppercase italic tracking-widest flex items-center gap-2">
                                    <i data-lucide="arrow-right-left" class="w-4 h-4 text-accent"></i> Trasferimenti Recenti
                                </h4>
                            </div>
                            <div class="grid grid-cols-1 gap-2">
                                <?php foreach (array_slice($transfers, 0, 3) as $tr): ?>
                                    <div
                                        class="flex items-center justify-between p-3 rounded-2xl bg-slate-800/50 border border-white/5">
                                        <div class="flex items-center gap-3">
                                            <div class="text-[10px] font-black text-slate-500 w-16">
                                                <?php echo date('M Y', strtotime($tr['date'])); ?>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="text-[10px] font-bold text-slate-400 uppercase truncate max-w-[80px]"><?php echo $tr['teams']['out']['name']; ?></span>
                                                <i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i>
                                                <span
                                                    class="text-[10px] font-black text-white uppercase truncate max-w-[80px]"><?php echo $tr['teams']['in']['name']; ?></span>
                                            </div>
                                        </div>
                                        <div class="text-[10px] font-black text-success uppercase">
                                            <?php echo $tr['type'] ?: 'Definitivo'; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Detailed Stats -->
                    <?php
                    $statList = [
                        'shots.total',
                        'shots.on',
                        'passes.total',
                        'passes.accuracy',
                        'dribbles.success',
                        'duels.won',
                        'tackles.total',
                        'interceptions'
                    ];
                    $hasStats = false;
                    foreach ($statList as $sl) {
                        if ($getStat($sl) > 0) {
                            $hasStats = true;
                            break;
                        }
                    }

                    if ($hasStats):
                        ?>
                        <div class="glass p-6 rounded-[32px] border-white/5">
                            <h4
                                class="text-[10px] font-black text-accent uppercase tracking-widest mb-4 border-b border-white/5 pb-2">
                                Dettagli Tecnici (Season)</h4>
                            <div class="grid grid-cols-2 gap-y-4 gap-x-12">
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase">Tiri Totali</span>
                                    <span
                                        class="text-xs font-black text-white"><?php echo $getStat('shots.total'); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase">In Porta</span>
                                    <span class="text-xs font-black text-white"><?php echo $getStat('shots.on'); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase">Passaggi</span>
                                    <span
                                        class="text-xs font-black text-white"><?php echo $getStat('passes.total'); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase">Precisione</span>
                                    <span
                                        class="text-xs font-black text-white"><?php echo $getStat('passes.accuracy'); ?>%</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase">Dribbling</span>
                                    <span
                                        class="text-xs font-black text-white"><?php echo $getStat('dribbles.success'); ?>/<?php echo $getStat('dribbles.attempts'); ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[9px] font-bold text-slate-500 uppercase">Duelli Vinti</span>
                                    <span class="text-xs font-black text-white"><?php echo $getStat('duels.won'); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>

    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
        }
    </style>

    <script>if (window.lucide) lucide.createIcons();</script>
</div>