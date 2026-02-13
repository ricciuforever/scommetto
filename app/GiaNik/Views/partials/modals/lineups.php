<?php
// app/GiaNik/Views/partials/modals/lineups.php
$lineups = $lineups ?? [];
$details = $details ?? null;
?>

<div id="lineups-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-4xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
        onclick="event.stopPropagation()">

        <div class="p-8 border-b border-white/5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="p-4 bg-emerald-500/10 rounded-2xl border border-emerald-500/10">
                    <i data-lucide="users" class="w-6 h-6 text-emerald-400"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-white uppercase italic">Formazioni Live</h3>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">
                        <?php echo $details['teams']['home']['name']; ?> vs
                        <?php echo $details['teams']['away']['name']; ?>
                    </p>
                </div>
            </div>
            <button onclick="document.getElementById('lineups-modal').remove()"
                class="w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="p-8 max-h-[75vh] overflow-y-auto">
            <?php if (empty($lineups)): ?>
                <div class="text-center py-20">
                    <i data-lucide="info" class="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Formazioni non ancora disponibili
                    </p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 gap-12">
                    <?php foreach ($lineups as $l): ?>
                        <div>
                            <div class="flex items-center justify-between mb-6 pb-4 border-b border-white/5">
                                <div class="flex items-center gap-3">
                                    <img src="<?php echo $l['team_logo']; ?>" class="w-10 h-10 object-contain">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-black text-white uppercase italic">
                                            <?php echo $l['team_name']; ?>
                                        </span>
                                        <span class="text-[10px] font-bold text-accent uppercase">
                                            <?php echo $l['formation']; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[8px] font-black text-slate-500 uppercase block">Allenatore</span>
                                    <span class="text-xs font-bold text-white">
                                        <?php echo $l['coach_name'] ?? 'N/A'; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-6">
                                <div>
                                    <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[.2em] mb-4">Titolari
                                    </h4>
                                    <div class="grid grid-cols-1 gap-2">
                                        <?php
                                        $starters = $l['start_xi_json'] ?? $l['start_xi'] ?? $l['startXI'] ?? [];
                                        foreach ($starters as $pxi):
                                            $p = $pxi['player'];
                                            ?>
                                            <div class="flex items-center justify-between p-3 bg-white/5 rounded-2xl border border-white/5 hover:border-white/10 transition-all cursor-pointer"
                                                onclick="openPlayerModal(<?php echo $p['id']; ?>, <?php echo $details['fixture']['id']; ?>)">
                                                <div class="flex items-center gap-3">
                                                    <span class="w-6 text-center text-[10px] font-black text-accent">
                                                        <?php echo $p['number']; ?>
                                                    </span>
                                                    <span class="text-xs font-black text-white uppercase">
                                                        <?php echo $p['name']; ?>
                                                    </span>
                                                </div>
                                                <span class="text-[9px] font-bold text-slate-500 uppercase">
                                                    <?php echo $p['pos']; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[.2em] mb-4">Sostituzioni
                                    </h4>
                                    <div class="grid grid-cols-1 gap-2 opacity-60">
                                        <?php
                                        $subs = $l['substitutes_json'] ?? $l['substitutes'] ?? [];
                                        foreach ($subs as $ps):
                                            $p = $ps['player'];
                                            ?>
                                            <div class="flex items-center justify-between p-2 bg-white/5 rounded-xl border border-white/5"
                                                onclick="openPlayerModal(<?php echo $p['id']; ?>, <?php echo $details['fixture']['id']; ?>)">
                                                <div class="flex items-center gap-3">
                                                    <span class="w-6 text-center text-[10px] font-bold text-slate-500">
                                                        <?php echo $p['number']; ?>
                                                    </span>
                                                    <span class="text-xs font-bold text-slate-300 uppercase">
                                                        <?php echo $p['name']; ?>
                                                    </span>
                                                </div>
                                                <span class="text-[8px] font-bold text-slate-600 uppercase">
                                                    <?php echo $p['pos']; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>