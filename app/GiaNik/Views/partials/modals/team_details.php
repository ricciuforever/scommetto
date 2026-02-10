<?php
// app/GiaNik/Views/partials/modals/team_details.php
$team = $team ?? null;
if (!$team)
    return;
$t = $team['team'];
$v = $team['venue'];
$coach = $team['coach'];
$squad = $team['squad'] ?? [];
$standing = $team['standing'] ?? null;
?>

<div id="team-details-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-4xl max-h-[90vh] rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative flex flex-col"
        onclick="event.stopPropagation()">

        <!-- Header -->
        <div class="relative h-48 shrink-0">
            <!-- Stadium Background -->
            <?php if ($v['image']): ?>
                <div class="absolute inset-0">
                    <img src="<?php echo $v['image']; ?>" class="w-full h-full object-cover opacity-30" alt="">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/60 to-transparent"></div>
                </div>
            <?php else: ?>
                <div class="absolute inset-0 bg-slate-800/50"></div>
            <?php endif; ?>

            <button onclick="document.getElementById('team-details-modal').remove()"
                class="absolute top-6 right-6 w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-20">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>

            <div class="absolute bottom-6 left-10 flex items-end gap-6 z-10">
                <div
                    class="w-24 h-24 rounded-3xl bg-slate-900 p-4 border border-white/10 flex items-center justify-center overflow-hidden shadow-2xl">
                    <img src="<?php echo $t['logo']; ?>" class="w-full h-full object-contain" alt="">
                </div>
                <div class="mb-2">
                    <h3
                        class="text-4xl font-black tracking-tight text-white uppercase italic leading-none drop-shadow-lg">
                        <?php echo $t['name']; ?></h3>
                    <p class="text-slate-400 text-xs font-black uppercase tracking-widest mt-2">
                        <?php echo $t['country']; ?> • Fondato nel <?php echo $t['founded']; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-10 custom-scrollbar">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">

                <!-- Info Column -->
                <div class="md:col-span-1 space-y-6">
                    <!-- Standing Widget -->
                    <?php if ($standing): ?>
                        <div class="glass p-6 rounded-[32px] border-white/5 bg-accent/5">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="trending-up" class="w-4 h-4 text-accent"></i>
                                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Classifica
                                    </h4>
                                </div>
                                <span
                                    class="text-[10px] font-black text-slate-500"><?php echo $standing['group'] ?? 'League'; ?></span>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="text-5xl font-black text-white leading-none italic">
                                    #<?php echo $standing['rank']; ?></div>
                                <div>
                                    <div class="text-xl font-black text-accent"><?php echo $standing['points']; ?> PT</div>
                                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter">
                                        G:<?php echo $standing['played']; ?> | V:<?php echo $standing['win']; ?> |
                                        P:<?php echo $standing['lose']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if ($standing['form']): ?>
                                <div class="flex items-center gap-1 mt-4">
                                    <?php
                                    $form = str_split($standing['form']);
                                    foreach ($form as $res):
                                        $color = $res === 'W' ? 'bg-success' : ($res === 'L' ? 'bg-danger' : 'bg-slate-600');
                                        ?>
                                        <span
                                            class="w-4 h-4 rounded-md <?php echo $color; ?> text-[8px] flex items-center justify-center font-black text-white"><?php echo $res; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Coach Widget -->
                    <?php if ($coach): ?>
                        <div class="glass p-6 rounded-[32px] border-white/5">
                            <div class="flex items-center gap-2 mb-4">
                                <i data-lucide="user" class="w-4 h-4 text-accent"></i>
                                <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Allenatore</h4>
                            </div>
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-xl bg-slate-800 border border-white/5 overflow-hidden">
                                    <img src="<?php echo $coach['photo']; ?>" class="w-full h-full object-cover" alt="">
                                </div>
                                <div>
                                    <div class="text-sm font-black text-white uppercase italic">
                                        <?php echo $coach['name']; ?></div>
                                    <div class="text-[10px] font-bold text-slate-500 uppercase">
                                        <?php echo $coach['nationality']; ?> • <?php echo $coach['age']; ?> anni</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Venue Details -->
                    <div class="glass p-6 rounded-[32px] border-white/5">
                        <div class="flex items-center gap-2 mb-4">
                            <i data-lucide="map-pin" class="w-4 h-4 text-accent"></i>
                            <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-400">Stadio</h4>
                        </div>
                        <div class="text-sm font-black text-white uppercase italic"><?php echo $v['name']; ?></div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase"><?php echo $v['city']; ?></div>
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <div class="text-[8px] font-black text-slate-600 uppercase">Capacità</div>
                                <div class="text-xs font-black text-white">
                                    <?php echo number_format($v['capacity'] ?? 0, 0, ',', '.'); ?></div>
                            </div>
                            <div>
                                <div class="text-[8px] font-black text-slate-600 uppercase">Superficie</div>
                                <div class="text-xs font-black text-white"><?php echo $v['surface'] ?: 'Erba'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Squad Column -->
                <div class="md:col-span-2 space-y-8">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-black text-white uppercase italic tracking-tighter">Rosa Giocatori</h4>
                        <span
                            class="text-[10px] font-black text-slate-500 uppercase tracking-widest"><?php echo count($squad); ?>
                            Elementi</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <?php
                        // Simple sorting by position if needed, or just follow API order
                        foreach ($squad as $p):
                            ?>
                            <div
                                class="group flex items-center gap-3 p-3 rounded-2xl bg-white/5 border border-white/5 hover:bg-white/10 hover:border-accent/20 transition-all cursor-pointer"
                                hx-get="/api/gianik/player-details?playerId=<?php echo $p['id']; ?>&fixtureId=<?php echo $team['fixtureId'] ?? ''; ?>"
                                hx-target="#global-modal-container">
                                <div
                                    class="w-10 h-10 rounded-xl bg-slate-800 border border-white/10 overflow-hidden shrink-0">
                                    <img src="<?php echo $p['photo']; ?>" class="w-full h-full object-cover" alt="">
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-[10px] font-black text-accent w-5"><?php echo $p['number'] ?: '-'; ?></span>
                                        <span
                                            class="text-xs font-black text-white uppercase truncate italic"><?php echo $p['name']; ?></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span
                                            class="text-[9px] font-bold text-slate-500 uppercase"><?php echo $p['position']; ?></span>
                                        <span
                                            class="text-[9px] font-black text-slate-400 group-hover:text-accent transition-colors"><?php echo $p['age']; ?>
                                            anni</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
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