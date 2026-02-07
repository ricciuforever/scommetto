<?php
// app/Views/partials/team.php
// HTMX Partial for Team Profile

$t = $team;
$c = $coach;
$squad = $squad ?? [];
$stats = $stats ?? null;
?>

<div id="team-view-content" class="animate-fade-in max-w-5xl mx-auto">
    <!-- Back Button -->
    <div class="mb-8 flex items-center gap-4">
        <button onclick="window.history.back()"
            class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10 text-white">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </button>
        <h2 class="text-3xl font-black italic uppercase tracking-tight text-white">Profilo Squadra</h2>
    </div>

    <!-- Team Info Header -->
    <div class="flex flex-col md:flex-row items-center gap-8 mb-12">
        <div
            class="w-40 h-40 bg-white rounded-[48px] p-8 shadow-2xl border border-white/10 flex items-center justify-center shrink-0">
            <img src="<?php echo $t['logo']; ?>" class="w-full h-full object-contain">
        </div>
        <div class="text-center md:text-left">
            <h1 class="text-6xl font-black tracking-tighter text-white uppercase italic mb-2">
                <?php echo htmlspecialchars($t['name']); ?>
            </h1>
            <div class="flex flex-wrap justify-center md:justify-start gap-4">
                <span class="text-xs font-black uppercase tracking-widest text-slate-500">
                    <?php echo htmlspecialchars($t['country']); ?>
                </span>
                <span class="text-xs font-black uppercase tracking-widest text-slate-500">•</span>
                <span class="text-xs font-black uppercase tracking-widest text-slate-500">Fondato nel:
                    <?php echo $t['founded']; ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($stats): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
            <div class="glass p-6 rounded-3xl border-white/5 text-center">
                <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Giocate</span>
                <span class="text-3xl font-black text-white tabular-nums">
                    <?php echo $stats['played']; ?>
                </span>
            </div>
            <div class="glass p-6 rounded-3xl border-white/5 text-center">
                <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Vinte</span>
                <span class="text-3xl font-black text-success tabular-nums">
                    <?php echo $stats['wins']; ?>
                </span>
            </div>
            <div class="glass p-6 rounded-3xl border-white/5 text-center">
                <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Pareggi</span>
                <span class="text-3xl font-black text-warning tabular-nums">
                    <?php echo $stats['draws']; ?>
                </span>
            </div>
            <div class="glass p-6 rounded-3xl border-white/5 text-center">
                <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Perse</span>
                <span class="text-3xl font-black text-danger tabular-nums">
                    <?php echo $stats['losses']; ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
        <!-- Venue -->
        <div class="glass p-8 rounded-[40px] border-white/5 bg-accent/5">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 mb-6 flex items-center gap-2">
                <i data-lucide="map-pin" class="w-4 h-4 text-accent"></i> Stadio
            </h3>
            <div class="text-2xl font-black text-white italic uppercase mb-2">
                <?php echo htmlspecialchars($t['venue_name'] ?? 'N/A'); ?>
            </div>
            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">Capacità:
                <?php echo number_format($t['venue_capacity'] ?? 0); ?> spettatori
            </div>
        </div>

        <!-- Coach -->
        <div class="glass p-8 rounded-[40px] border-white/5">
            <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 mb-6 flex items-center gap-2">
                <i data-lucide="user" class="w-4 h-4 text-accent"></i> Allenatore
            </h3>
            <?php if ($c): ?>
                <div class="flex items-center gap-4">
                    <img src="<?php echo $c['photo']; ?>" class="w-16 h-16 rounded-3xl border border-accent/20 object-cover"
                        onerror="this.src='https://media.api-sports.io/football/players/1.png'">
                    <div>
                        <div class="text-2xl font-black text-white italic uppercase">
                            <?php echo htmlspecialchars($c['name']); ?>
                        </div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">
                            <?php echo htmlspecialchars($c['nationality']); ?> |
                            <?php echo $c['age']; ?> anni
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-slate-500 italic font-bold uppercase">Coach non disponibile</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Squad Table -->
    <div class="glass rounded-[48px] border-white/5 overflow-hidden shadow-2xl mb-20">
        <div class="p-8 border-b border-white/5 bg-white/5 flex items-center justify-between">
            <h3 class="text-2xl font-black italic uppercase tracking-tight text-white">Rosa Squadra</h3>
            <span
                class="bg-accent/10 text-accent px-4 py-1 rounded-xl text-[10px] font-black uppercase tracking-widest border border-accent/20">
                <?php echo count($squad); ?> Giocatori
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="text-slate-500 uppercase text-[9px] font-black tracking-widest">
                        <th class="py-6 px-8">#</th>
                        <th class="py-6 px-8">Giocatore</th>
                        <th class="py-6 px-8">Posizione</th>
                        <th class="py-6 px-8 text-right">Azione</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($squad)): ?>
                        <tr>
                            <td colspan="4" class="py-20 text-center text-slate-500 italic font-bold uppercase">Nessun
                                giocatore
                                in rosa trovato</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($squad as $p): ?>
                            <tr class="hover:bg-white/5 transition-colors group">
                                <td class="py-5 px-8 font-black text-accent tabular-nums">
                                    <?php echo $p['number'] ?? '-'; ?>
                                </td>
                                <td class="py-5 px-8">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-white/5 overflow-hidden border border-white/10">
                                            <img src="<?php echo $p['photo']; ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div
                                            class="font-bold text-white uppercase italic group-hover:text-accent transition-colors">
                                            <?php echo htmlspecialchars($p['name']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-5 px-8 font-bold text-slate-400 uppercase tracking-widest text-[10px]">
                                    <?php echo htmlspecialchars($p['position']); ?>
                                </td>
                                <td class="py-5 px-8 text-right">
                                    <button onclick="navigate('player', '<?php echo $p['id']; ?>')"
                                        class="px-5 py-2 rounded-xl bg-white/5 hover:bg-white/10 text-white font-black uppercase text-[9px] tracking-widest border border-white/5 transition-all">
                                        Dettagli
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>