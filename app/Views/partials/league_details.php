<?php
// app/Views/partials/league_details.php

if (!$league) {
    echo '<div class="glass p-20 text-center font-black text-slate-500 uppercase italic">Competizione non trovata.</div>';
    return;
}
?>

<div class="animate-fade-in">
    <div class="mb-12 flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div class="flex items-center gap-6">
            <div class="w-24 h-24 glass p-4 rounded-[32px] border-white/5 flex items-center justify-center">
                <img src="<?php echo $league['logo']; ?>" class="max-w-full max-h-full object-contain">
            </div>
            <div>
                <div class="text-[10px] font-black uppercase tracking-[0.3em] text-accent mb-2 italic">Competizione
                </div>
                <h1 class="text-5xl font-black italic uppercase tracking-tighter text-white">
                    <?php echo $league['name']; ?>
                </h1>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic mt-2">
                    <?php echo $league['country_name'] ?? $league['country']; ?>
                </p>
            </div>
        </div>

        <button onclick="navigate('leagues')"
            class="px-8 py-4 rounded-2xl bg-white/5 hover:bg-white/10 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-all border border-white/5 flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Torna alla Lista
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
        <!-- Standings -->
        <div class="lg:col-span-2 space-y-8">
            <div class="glass rounded-[48px] border-white/5 overflow-hidden">
                <div class="p-8 border-b border-white/5 bg-white/5">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 italic">Classifica</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-[10px] font-black uppercase tracking-widest text-slate-500 border-b border-white/5">
                                <th class="py-6 px-8">#</th>
                                <th class="py-6 px-8">Squadra</th>
                                <th class="py-6 px-4 text-center">P</th>
                                <th class="py-6 px-4 text-center">V</th>
                                <th class="py-6 px-4 text-center">P</th>
                                <th class="py-6 px-4 text-center">S</th>
                                <th class="py-6 px-4 text-center">DR</th>
                                <th class="py-6 px-8 text-center text-white">Pts</th>
                                <th class="py-6 px-8">Forma</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php foreach ($standings as $row):
                                $form = str_split($row['form'] ?? '');
                                ?>
                                <tr class="hover:bg-white/5 transition-colors cursor-pointer group"
                                    onclick="navigate('team', '<?php echo $row['team_id']; ?>')">
                                    <td class="py-6 px-8 font-black text-accent tabular-nums">
                                        <?php echo $row['rank']; ?>
                                    </td>
                                    <td class="py-6 px-8">
                                        <div class="flex items-center gap-4">
                                            <img src="<?php echo $row['team_logo']; ?>" class="w-8 h-8 object-contain">
                                            <span
                                                class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors">
                                                <?php echo $row['team_name']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="py-6 px-4 text-center font-bold tabular-nums text-slate-400">
                                        <?php echo $row['played']; ?>
                                    </td>
                                    <td class="py-6 px-4 text-center font-bold tabular-nums text-slate-400">
                                        <?php echo $row['win']; ?>
                                    </td>
                                    <td class="py-6 px-4 text-center font-bold tabular-nums text-slate-400">
                                        <?php echo $row['draw']; ?>
                                    </td>
                                    <td class="py-6 px-4 text-center font-bold tabular-nums text-slate-400">
                                        <?php echo $row['lose']; ?>
                                    </td>
                                    <td class="py-6 px-4 text-center font-bold tabular-nums text-slate-400">
                                        <?php echo $row['goals_diff']; ?>
                                    </td>
                                    <td class="py-6 px-8 text-center font-black tabular-nums text-xl text-white">
                                        <?php echo $row['points']; ?>
                                    </td>
                                    <td class="py-6 px-8">
                                        <div class="flex gap-1.5">
                                            <?php foreach ($form as $f):
                                                $color = 'bg-slate-500';
                                                if ($f === 'W')
                                                    $color = 'bg-success';
                                                if ($f === 'L')
                                                    $color = 'bg-danger';
                                                if ($f === 'D')
                                                    $color = 'bg-warning';
                                                ?>
                                                <span class="w-2 h-2 rounded-full <?php echo $color; ?>"
                                                    title="<?php echo $f; ?>"></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Top Stats Column -->
        <aside class="space-y-10">
            <?php
            $sections = [
                ['title' => 'Capocannonieri', 'key' => 'scorers', 'icon' => 'zap', 'metric' => 'goals'],
                ['title' => 'Top Assistmen', 'key' => 'assists', 'icon' => 'star', 'metric' => 'assists']
            ];
            foreach ($sections as $sec):
                $list = $topStats[$sec['key']]['stats_json'] ?? [];
                ?>
                <div class="glass p-10 rounded-[48px] border-white/5">
                    <h3
                        class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 mb-8 flex items-center gap-2 italic">
                        <i data-lucide="<?php echo $sec['icon']; ?>" class="w-4 h-4 text-accent"></i>
                        <?php echo $sec['title']; ?>
                    </h3>
                    <div class="space-y-6">
                        <?php if (empty($list)): ?>
                            <div class="text-[10px] font-bold text-slate-600 italic uppercase">Dati non disponibili</div>
                        <?php else: ?>
                            <?php foreach (array_slice($list, 0, 5) as $idx => $p): ?>
                                <div class="flex items-center gap-4 group cursor-pointer"
                                    onclick="navigate('player', '<?php echo $p['player']['id']; ?>')">
                                    <span
                                        class="text-2xl font-black italic text-slate-800 group-hover:text-accent/20 transition-colors tabular-nums w-8">
                                        <?php echo $idx + 1; ?>
                                    </span>
                                    <div
                                        class="w-12 h-12 rounded-2xl bg-white/5 overflow-hidden border border-white/5 p-1 shrink-0">
                                        <img src="<?php echo $p['player']['photo']; ?>"
                                            class="w-full h-full object-cover rounded-xl">
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors truncate">
                                            <?php echo $p['player']['name']; ?>
                                        </div>
                                        <div class="text-[9px] font-bold text-slate-500 uppercase truncate">
                                            <?php echo $p['statistics'][0]['team']['name']; ?>
                                        </div>
                                    </div>
                                    <div class="text-3xl font-black italic tabular-nums text-accent">
                                        <?php echo $sec['key'] === 'scorers' ? $p['statistics'][0]['goals']['total'] : $p['statistics'][0]['goals']['assists']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </aside>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();
</script>