<?php
// app/GiaNik/Views/partials/modals/team_details.php
$team = $team ?? null;
if (!$team) return;
$t = $team['team'];
$v = $team['venue'];
?>

<div id="team-details-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
         onclick="event.stopPropagation()">

        <button onclick="document.getElementById('team-details-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10">
            <div class="flex items-center gap-6 mb-10">
                <div class="w-24 h-24 rounded-3xl bg-white/5 p-4 border border-white/10 flex items-center justify-center overflow-hidden">
                    <img src="<?php echo $t['logo']; ?>" class="w-full h-full object-contain" alt="">
                </div>
                <div>
                    <h3 class="text-4xl font-black tracking-tight text-white uppercase italic leading-none"><?php echo $t['name']; ?></h3>
                    <p class="text-slate-500 text-sm font-black uppercase tracking-widest mt-2">
                        <?php echo $t['country']; ?> • Fondato nel <?php echo $t['founded']; ?>
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6">
                <!-- Venue Info -->
                <div class="glass p-8 rounded-[32px] border-white/5 col-span-2">
                    <div class="flex items-center gap-3 mb-4">
                        <i data-lucide="map-pin" class="w-6 h-6 text-accent"></i>
                        <h4 class="text-xs font-black uppercase tracking-widest text-slate-400">Stadio & Sede</h4>
                    </div>
                    <div class="flex items-center gap-6">
                        <?php if($v['image']): ?>
                        <div class="w-32 h-20 rounded-2xl overflow-hidden shrink-0 border border-white/10">
                            <img src="<?php echo $v['image']; ?>" class="w-full h-full object-cover" alt="">
                        </div>
                        <?php endif; ?>
                        <div>
                            <div class="text-xl font-black text-white"><?php echo $v['name']; ?></div>
                            <div class="text-sm font-bold text-slate-500 uppercase"><?php echo $v['address']; ?>, <?php echo $v['city']; ?></div>
                            <div class="text-xs font-black text-accent mt-1">Capacità: <?php echo number_format($v['capacity'], 0, ',', '.'); ?> posti</div>
                        </div>
                    </div>
                </div>

                <div class="glass p-6 rounded-[32px] border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Codice</div>
                    <div class="text-xl font-black text-white"><?php echo $t['code'] ?: 'N/A'; ?></div>
                </div>

                <div class="glass p-6 rounded-[32px] border-white/5">
                    <div class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Superficie</div>
                    <div class="text-xl font-black text-white"><?php echo $v['surface'] ?: 'N/A'; ?></div>
                </div>
            </div>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>
