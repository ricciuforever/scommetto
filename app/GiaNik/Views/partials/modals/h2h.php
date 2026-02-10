<?php
// app/GiaNik/Views/partials/modals/h2h.php
$h2h = $h2h ?? [];
$details = $details ?? null;
?>

<div id="h2h-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative"
        onclick="event.stopPropagation()">

        <div class="p-8 border-b border-white/5 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="p-4 bg-amber-500/10 rounded-2xl border border-amber-500/10">
                    <i data-lucide="arrow-left-right" class="w-6 h-6 text-amber-400"></i>
                </div>
                <div>
                    <h3 class="text-xl font-black text-white uppercase italic">H2H & Precedenti</h3>
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">
                        Scontri diretti storici
                    </p>
                </div>
            </div>
            <button onclick="document.getElementById('h2h-modal').remove()"
                class="w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="p-8 max-h-[70vh] overflow-y-auto">
            <?php if (empty($h2h)): ?>
                <div class="text-center py-20">
                    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Dati H2H non disponibili</p>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-3">
                    <?php foreach ($h2h as $match):
                        $home = $match['teams']['home'];
                        $away = $match['teams']['away'];
                        $score = $match['goals']['home'] . ' - ' . $match['goals']['away'];
                        $date = date('d/m/Y', strtotime($match['fixture']['date']));
                        ?>
                        <div
                            class="glass p-4 rounded-3xl border-transparent hover:border-white/10 transition-all flex items-center justify-between">
                            <div class="flex items-center gap-4 flex-1">
                                <div class="flex flex-col items-center gap-1 w-20 shrink-0">
                                    <img src="<?php echo $home['logo']; ?>" class="w-8 h-8 object-contain">
                                    <span class="text-[9px] font-black text-white uppercase truncate text-center w-full">
                                        <?php echo $home['name']; ?>
                                    </span>
                                </div>

                                <div class="flex flex-col items-center flex-1">
                                    <span class="text-xs font-black text-white mb-1 tracking-tighter">
                                        <?php echo $score; ?>
                                    </span>
                                    <span class="text-[8px] font-bold text-slate-500 uppercase">
                                        <?php echo $date; ?>
                                    </span>
                                </div>

                                <div class="flex flex-col items-center gap-1 w-20 shrink-0">
                                    <img src="<?php echo $away['logo']; ?>" class="w-8 h-8 object-contain">
                                    <span class="text-[9px] font-black text-white uppercase truncate text-center w-full">
                                        <?php echo $away['name']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ml-6 px-3 py-1 bg-white/5 rounded-full border border-white/5 shrink-0">
                                <span class="text-[8px] font-black text-slate-500 uppercase">
                                    <?php echo $match['league']['name']; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>