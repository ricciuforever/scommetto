<?php
// app/GiaNik/Views/brain.php
// Layout base
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <title>GiaNik Brain v2.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="bg-slate-900 text-gray-100 font-sans antialiased">

<div class="container mx-auto p-4">

    <div class="flex justify-between items-center mb-8">
        <div class="flex items-center gap-4">
            <div class="bg-purple-600 p-3 rounded-full shadow-lg shadow-purple-500/30">
                <i class="fa-solid fa-brain text-2xl text-white"></i>
            </div>
            <div>
                <h1 class="text-3xl font-bold tracking-tight">GiaNik <span class="text-purple-400">Brain</span></h1>
                <p class="text-slate-400 text-sm">Neural Performance Monitoring & Optimization</p>
            </div>
        </div>
        <a href="/gianik" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 rounded-lg text-sm transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> Torna al Terminale
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="glass-panel p-6 rounded-xl relative overflow-hidden">
            <div class="absolute top-0 right-0 p-4 opacity-10"><i class="fa-solid fa-chart-line text-6xl"></i></div>
            <h3 class="text-slate-400 text-sm uppercase font-semibold">ROI Globale</h3>
            <p class="text-3xl font-bold mt-2 <?php echo $global['roi'] >= 0 ? 'text-green-400' : 'text-red-400'; ?>">
                <?php echo ($global['roi'] > 0 ? '+' : '') . number_format($global['roi'], 2); ?>%
            </p>
            <p class="text-xs text-slate-500 mt-1">su <?php echo number_format($global['total_stake'], 2); ?>€ investiti</p>
        </div>

        <div class="glass-panel p-6 rounded-xl">
            <h3 class="text-slate-400 text-sm uppercase font-semibold">Net Profit</h3>
            <p class="text-3xl font-bold mt-2 <?php echo $global['total_profit'] >= 0 ? 'text-green-400' : 'text-red-400'; ?>">
                <?php echo number_format($global['total_profit'], 2); ?>€
            </p>
        </div>

        <div class="glass-panel p-6 rounded-xl">
            <h3 class="text-slate-400 text-sm uppercase font-semibold">Win Rate</h3>
            <div class="flex items-end gap-2">
                <p class="text-3xl font-bold mt-2 text-blue-400"><?php echo number_format($global['win_rate'], 1); ?>%</p>
                <span class="text-sm text-slate-500 mb-1">/ <?php echo $global['total_bets']; ?> bets</span>
            </div>
            <div class="w-full bg-slate-700 h-1.5 mt-3 rounded-full overflow-hidden">
                <div class="bg-blue-500 h-full" style="width: <?php echo $global['win_rate']; ?>%"></div>
            </div>
        </div>

        <div class="glass-panel p-6 rounded-xl border-purple-500/30 border">
            <h3 class="text-purple-400 text-sm uppercase font-semibold">Cortex Status</h3>
            <p class="text-xl font-bold mt-2 text-white">
                <i class="fa-solid fa-check-circle text-green-500 mr-2"></i> Active
            </p>
            <p class="text-xs text-slate-400 mt-2">Gatekeeper: <strong>ON</strong> (-15% threshold)</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-1">
            <div class="glass-panel rounded-xl p-5 border-l-4 border-red-500 h-full">
                <h2 class="text-xl font-bold mb-4 flex items-center text-red-400">
                    <i class="fa-solid fa-ban mr-2"></i> Blacklist (Gatekeeper)
                </h2>
                <p class="text-xs text-slate-400 mb-4">GiaNik bloccherà automaticamente le scommesse su questi contesti.</p>

                <?php if (empty($blacklist)): ?>
                    <div class="text-center py-8 text-slate-500 italic">Nessun blocco attivo. Ottimo lavoro!</div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($blacklist as $item): ?>
                        <div class="bg-slate-800/50 p-3 rounded border border-red-900/30 flex justify-between items-center">
                            <div>
                                <span class="text-xs font-bold text-slate-500 block"><?php echo $item['context_type']; ?></span>
                                <span class="font-bold text-red-200"><?php echo $item['context_id']; ?></span>
                            </div>
                            <div class="text-right">
                                <span class="block text-red-500 font-bold"><?php echo number_format($item['roi'], 1); ?>% ROI</span>
                                <span class="text-xs text-slate-600"><?php echo $item['total_bets']; ?> bets</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">

            <div class="glass-panel rounded-xl p-5">
                <h2 class="text-xl font-bold mb-4 flex items-center text-green-400">
                    <i class="fa-solid fa-trophy mr-2"></i> Top Leagues
                </h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-slate-300">
                        <thead class="text-xs uppercase bg-slate-800 text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Lega</th>
                                <th class="px-4 py-3 text-center">Bets</th>
                                <th class="px-4 py-3 text-center">Win%</th>
                                <th class="px-4 py-3 text-right">Profit</th>
                                <th class="px-4 py-3 text-right">ROI</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topLeagues as $league):
                                $winRate = ($league['total_bets'] > 0) ? ($league['wins'] / $league['total_bets']) * 100 : 0;
                            ?>
                            <tr class="border-b border-slate-700 hover:bg-slate-800/50 transition">
                                <td class="px-4 py-3 font-medium text-white"><?php echo $league['context_id']; ?></td>
                                <td class="px-4 py-3 text-center"><?php echo $league['total_bets']; ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="w-16 bg-slate-700 h-1.5 rounded-full inline-block align-middle mr-2">
                                        <div class="bg-green-500 h-full rounded-full" style="width: <?php echo $winRate; ?>%"></div>
                                    </div>
                                    <span class="text-[10px] text-slate-500"><?php echo round($winRate, 0); ?>%</span>
                                </td>
                                <td class="px-4 py-3 text-right text-green-400">+<?php echo number_format($league['profit_loss'], 2); ?>€</td>
                                <td class="px-4 py-3 text-right font-bold text-green-300">+<?php echo number_format($league['roi'], 1); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="glass-panel rounded-xl p-5">
                <h2 class="text-xl font-bold mb-4 flex items-center text-blue-400">
                    <i class="fa-solid fa-shirt mr-2"></i> Top Teams
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <?php foreach ($topTeams as $team): ?>
                    <div class="bg-slate-800 p-3 rounded flex items-center gap-3 border border-slate-700 hover:border-blue-500/50 transition">
                        <?php if(!empty($team['logo'])): ?>
                            <img src="<?php echo $team['logo']; ?>" class="w-8 h-8 object-contain">
                        <?php else: ?>
                            <div class="w-8 h-8 bg-slate-700 rounded-full flex items-center justify-center text-xs font-bold">
                                <?php echo substr($team['context_id'], 0, 2); ?>
                            </div>
                        <?php endif; ?>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-bold truncate text-white" title="<?php echo $team['context_id']; ?>"><?php echo $team['context_id']; ?></p>
                            <p class="text-xs text-green-400">+<?php echo number_format($team['profit_loss'], 2); ?>€</p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-bold bg-blue-900 text-blue-300 px-1.5 py-0.5 rounded">
                                <?php echo number_format($team['roi'], 0); ?>%
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>

    <?php if (!empty($lessons)): ?>
    <div class="glass-panel rounded-xl p-5 mt-6 border-l-4 border-yellow-500">
        <h2 class="text-xl font-bold mb-4 flex items-center text-yellow-400">
            <i class="fa-solid fa-lightbulb mr-2"></i> Ultime Lezioni Apprese (Post-Mortem)
        </h2>
        <div class="space-y-4">
            <?php foreach ($lessons as $lesson): ?>
            <div class="bg-slate-800/50 p-4 rounded-lg">
                <p class="text-sm text-slate-300 italic">"<?php echo htmlspecialchars($lesson['lesson_text']); ?>"</p>
                <div class="mt-2 flex justify-between text-xs text-slate-500">
                    <span>Context: <?php echo !empty($lesson['match_context']) ? $lesson['match_context'] : $lesson['entity_id']; ?></span>
                    <span><?php echo date('d/m/Y H:i', strtotime($lesson['created_at'])); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
