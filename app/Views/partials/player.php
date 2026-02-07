<?php
// app/Views/partials/player.php
// HTMX Partial for Player Profile

$p = $player;
$stats = $statistics ?? null;
$trophies = $trophies ?? [];
$transfers = $transfers ?? [];
?>

<div id="player-view-content" class="animate-fade-in max-w-5xl mx-auto">
    <!-- Back Button -->
    <div class="mb-8 flex items-center gap-4">
        <button onclick="window.history.back()"
            class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10 text-white">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </button>
        <h2 class="text-3xl font-black italic uppercase tracking-tight text-white">Profilo Giocatore</h2>
    </div>

    <!-- Player Header -->
    <div class="flex flex-col md:flex-row items-center gap-10 mb-12">
        <div class="relative group">
            <div
                class="absolute -inset-1 bg-gradient-to-r from-accent to-purple-600 rounded-[48px] blur opacity-25 group-hover:opacity-50 transition duration-1000 group-hover:duration-200">
            </div>
            <div
                class="relative w-48 h-48 bg-slate-900 rounded-[48px] overflow-hidden border border-white/10 shadow-2xl">
                <img src="<?php echo $p['photo']; ?>"
                    class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
            </div>
        </div>
        <div class="text-center md:text-left">
            <h1 class="text-6xl font-black tracking-tighter text-white uppercase italic mb-4">
                <?php echo htmlspecialchars($p['name']); ?>
            </h1>
            <div class="flex flex-wrap justify-center md:justify-start gap-4">
                <div class="flex items-center gap-2 bg-white/5 px-4 py-2 rounded-2xl border border-white/5">
                    <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Nazione</span>
                    <span class="text-xs font-black text-white">
                        <?php echo htmlspecialchars($p['nationality']); ?>
                    </span>
                </div>
                <div class="flex items-center gap-2 bg-white/5 px-4 py-2 rounded-2xl border border-white/5">
                    <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Età</span>
                    <span class="text-xs font-black text-white">
                        <?php echo $p['age']; ?> anni
                    </span>
                </div>
                <div class="flex items-center gap-2 bg-white/5 px-4 py-2 rounded-2xl border border-white/5">
                    <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">Posizione</span>
                    <span
                        class="px-2 py-0.5 rounded bg-accent/20 text-accent text-[10px] font-black uppercase tracking-widest italic border border-accent/30">
                        <?php echo htmlspecialchars($p['position']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($stats): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
            <div class="glass p-8 rounded-[40px] border-white/5 text-center bg-gradient-to-b from-white/5 to-transparent">
                <span class="text-[9px] font-black uppercase text-slate-500 block mb-2 tracking-widest">Presenze</span>
                <span class="text-4xl font-black text-white tabular-nums">
                    <?php echo $stats['appearences'] ?? 0; ?>
                </span>
            </div>
            <div class="glass p-8 rounded-[40px] border-white/5 text-center bg-gradient-to-b from-accent/10 to-transparent">
                <span class="text-[9px] font-black uppercase text-accent block mb-2 tracking-widest italic">Goal</span>
                <span class="text-4xl font-black text-white tabular-nums">
                    <?php echo $stats['goals'] ?? 0; ?>
                </span>
            </div>
            <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                <span class="text-[9px] font-black uppercase text-slate-500 block mb-2 tracking-widest">Assist</span>
                <span class="text-4xl font-black text-white tabular-nums">
                    <?php echo $stats['assists'] ?? 0; ?>
                </span>
            </div>
            <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                <span class="text-[9px] font-black uppercase text-slate-500 block mb-2 tracking-widest">Rating</span>
                <span class="text-4xl font-black text-warning tabular-nums">
                    <?php echo number_format((float) ($stats['rating'] ?? 0), 2); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-20">
        <!-- Trophies -->
        <div class="glass rounded-[48px] border-white/5 overflow-hidden">
            <div class="p-8 border-b border-white/5 bg-white/5 flex items-center justify-between">
                <h3 class="text-xl font-black italic uppercase tracking-tight text-white flex items-center gap-3">
                    <i data-lucide="trophy" class="w-5 h-5 text-warning"></i> Palmarès
                </h3>
            </div>
            <div class="p-8 space-y-4">
                <?php if (empty($trophies)): ?>
                    <p class="text-slate-500 font-bold uppercase italic text-xs">Nessun trofeo registrato.</p>
                <?php else: ?>
                    <?php
                    // Group trophies by name and count
                    $grouped = [];
                    foreach ($trophies as $t) {
                        $key = $t['league'] . ' (' . $t['country'] . ')';
                        if (!isset($grouped[$key]))
                            $grouped[$key] = 0;
                        $grouped[$key]++;
                    }
                    foreach ($grouped as $name => $count): ?>
                        <div class="flex items-center justify-between p-4 rounded-2xl bg-white/5 border border-white/5">
                            <span class="font-black text-white uppercase italic text-xs tracking-tight">
                                <?php echo htmlspecialchars($name); ?>
                            </span>
                            <span
                                class="w-8 h-8 rounded-full bg-warning/20 text-warning flex items-center justify-center font-black text-xs">x
                                <?php echo $count; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Transfers -->
        <div class="glass rounded-[48px] border-white/5 overflow-hidden">
            <div class="p-8 border-b border-white/5 bg-white/5 flex items-center justify-between">
                <h3 class="text-xl font-black italic uppercase tracking-tight text-white flex items-center gap-3">
                    <i data-lucide="repeat" class="w-5 h-5 text-accent"></i> Storia Trasferimenti
                </h3>
            </div>
            <div class="p-8 space-y-4">
                <?php if (empty($transfers)): ?>
                    <p class="text-slate-500 font-bold uppercase italic text-xs">Nessun trasferimento registrato.</p>
                <?php else: ?>
                    <?php foreach (array_slice($transfers, 0, 8) as $tr): ?>
                        <div
                            class="flex items-center gap-4 p-4 rounded-2xl bg-white/5 border border-white/5 group hover:border-accent/30 transition-all">
                            <div class="flex-1">
                                <div class="text-[8px] font-black text-slate-500 uppercase tracking-widest mb-1">
                                    <?php echo date('d M Y', strtotime($tr['date'])); ?>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-black text-white uppercase italic text-xs">
                                        <?php echo htmlspecialchars($tr['team_out_name']); ?>
                                    </span>
                                    <i data-lucide="arrow-right" class="w-3 h-3 text-slate-600"></i>
                                    <span class="font-black text-accent uppercase italic text-xs">
                                        <?php echo htmlspecialchars($tr['team_in_name']); ?>
                                    </span>
                                </div>
                            </div>
                            <div
                                class="bg-white/5 px-3 py-1 rounded-lg text-[9px] font-black text-white uppercase tracking-tighter">
                                <?php echo $tr['type'] === 'Free' ? 'SVINCOLATO' : ($tr['type'] === 'Loan' ? 'PRESTITO' : 'DEFINITIVO'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>