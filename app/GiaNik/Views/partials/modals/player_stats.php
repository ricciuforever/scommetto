<?php
// app/GiaNik/Views/partials/modals/player_stats.php
$player = $player ?? null;
if (!$player) return;

$p = $player['player'];
$stats = $player['statistics'][0] ?? [];
$team = $player['team'] ?? [];

// Helper to get stat value
$getStat = function($path) use ($stats) {
    $keys = explode('.', $path);
    $val = $stats;
    foreach ($keys as $key) {
        if (!isset($val[$key])) return 0;
        $val = $val[$key];
    }
    return $val ?: 0;
};
?>

<div id="player-details-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
         onclick="event.stopPropagation()">

        <button onclick="document.getElementById('player-details-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10">
            <div class="flex items-center gap-6 mb-8">
                <div class="relative">
                    <div class="w-24 h-24 rounded-full bg-accent/20 border-4 border-white/10 overflow-hidden">
                        <img src="<?php echo $p['photo']; ?>" class="w-full h-full object-cover" alt="">
                    </div>
                    <?php if($team['logo'] ?? null): ?>
                    <div class="absolute -bottom-2 -right-2 w-10 h-10 rounded-xl bg-white p-1.5 shadow-xl border border-white/10">
                        <img src="<?php echo $team['logo']; ?>" class="w-full h-full object-contain" alt="">
                    </div>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="text-3xl font-black tracking-tight text-white uppercase italic leading-none"><?php echo $p['name']; ?></h3>
                    <p class="text-slate-500 text-xs font-black uppercase tracking-widest mt-2">
                        <?php echo $stats['games']['position'] ?? 'N/A'; ?> • <?php echo $p['nationality']; ?> • <?php echo $p['age']; ?> anni
                    </p>
                    <div class="mt-2 text-[10px] font-black uppercase text-accent bg-accent/10 px-3 py-1 rounded-full inline-block border border-accent/20">
                        Rating: <?php echo $stats['games']['rating'] ?? 'N/A'; ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="glass p-4 rounded-3xl border-white/5 text-center">
                    <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">GOL</div>
                    <div class="text-2xl font-black text-white"><?php echo $getStat('goals.total'); ?></div>
                </div>
                <div class="glass p-4 rounded-3xl border-white/5 text-center">
                    <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">ASSIST</div>
                    <div class="text-2xl font-black text-white"><?php echo $getStat('goals.assists'); ?></div>
                </div>
                <div class="glass p-4 rounded-3xl border-white/5 text-center">
                    <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">MINUTI</div>
                    <div class="text-2xl font-black text-white"><?php echo $getStat('games.minutes'); ?>'</div>
                </div>
            </div>

            <div class="glass p-6 rounded-[32px] border-white/5">
                <h4 class="text-[10px] font-black text-accent uppercase tracking-widest mb-4 border-b border-white/5 pb-2">Live Statistics</h4>
                <div class="grid grid-cols-2 gap-y-4 gap-x-8">
                    <div class="flex justify-between items-center">
                        <span class="text-[9px] font-bold text-slate-500 uppercase">Tiri (In porta)</span>
                        <span class="text-xs font-black text-white"><?php echo $getStat('shots.total'); ?> (<?php echo $getStat('shots.on'); ?>)</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[9px] font-bold text-slate-500 uppercase">Passaggi Totali</span>
                        <span class="text-xs font-black text-white"><?php echo $getStat('passes.total'); ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[9px] font-bold text-slate-500 uppercase">Precisione</span>
                        <span class="text-xs font-black text-white"><?php echo $getStat('passes.accuracy'); ?>%</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-[9px] font-bold text-slate-500 uppercase">Dribbling</span>
                        <span class="text-xs font-black text-white"><?php echo $getStat('dribbles.success'); ?>/<?php echo $getStat('dribbles.attempts'); ?></span>
                    </div>
                    <div class="flex justify-between items-center border-t border-white/5 pt-2 mt-2 col-span-2">
                        <span class="text-[9px] font-bold text-slate-500 uppercase">Contrasti Vinti</span>
                        <span class="text-xs font-black text-white"><?php echo $getStat('tackles.interceptions'); ?></span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-3">
                <div class="flex items-center gap-1.5 px-3 py-1.5 bg-yellow-500/10 rounded-xl border border-yellow-500/20">
                    <span class="text-xs">🟨</span>
                    <span class="text-[10px] font-black text-yellow-500 uppercase"><?php echo $getStat('cards.yellow'); ?></span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 bg-red-500/10 rounded-xl border border-red-500/20">
                    <span class="text-xs">🟥</span>
                    <span class="text-[10px] font-black text-red-500 uppercase"><?php echo $getStat('cards.red'); ?></span>
                </div>
            </div>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>
