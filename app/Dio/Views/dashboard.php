<?php
// app/Dio/Views/dashboard.php
$isEmbedded = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/war-room') !== false;
if (!$isEmbedded) {
    require __DIR__ . '/../../Views/layout/top.php';
}

$formatRome = function ($dateStr, $format = 'd/m H:i:s') {
    if (empty($dateStr))
        return '-';
    try {
        $dt = new DateTime($dateStr, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Europe/Rome'));
        return $dt->format($format);
    } catch (Exception $e) {
        return '-';
    }
};
?>

<div class="space-y-6 <?= $isEmbedded ? 'p-4' : '' ?>">
    <!-- Header -->
    <div
        class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white/5 p-6 rounded-2xl border border-white/10 glass">
        <div class="flex items-center gap-4">
            <div
                class="w-12 h-12 bg-indigo-500 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20">
                <i data-lucide="eye" class="text-white w-7 h-7"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black uppercase italic tracking-tighter leading-none">Modulo <span
                        class="text-indigo-400">Dio</span></h1>
                <div class="flex items-center gap-2 mt-1">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Universal Quantum Trader
                    </p>
                    <span class="w-1.5 h-1.5 bg-success rounded-full animate-pulse"></span>
                    <span class="text-[9px] font-black text-success uppercase tracking-widest">Sistema Autonomo
                        Attivo</span>
                </div>
                <?php if (!empty($lastScan)): ?>
                    <div class="mt-1 flex items-center gap-1.5 opacity-60">
                        <i data-lucide="cpu" class="w-2.5 h-2.5 text-indigo-400"></i>
                        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-tighter">
                            Ultimo Check AI: <?php echo $formatRome($lastScan); ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div class="mt-2 flex items-center gap-2">
                    <?php if (($currentMode ?? 'virtual') === 'real'): ?>
                        <span
                            class="px-2 py-0.5 bg-red-500/20 border border-red-500/50 text-red-500 text-[8px] font-black uppercase tracking-tighter rounded">Real
                            Money Mode</span>
                        <a href="?sync_history=1"
                            class="px-2 py-0.5 bg-indigo-500/20 border border-indigo-500/50 text-indigo-400 text-[8px] font-black uppercase tracking-tighter rounded hover:bg-indigo-500 hover:text-white transition-all">
                            <i data-lucide="refresh-cw" class="w-2 h-2 inline-block mr-1"></i> Sincronizza Storico
                        </a>
                    <?php else: ?>
                        <span
                            class="px-2 py-0.5 bg-blue-500/20 border border-blue-500/50 text-blue-500 text-[8px] font-black uppercase tracking-tighter rounded">Virtual
                            Simulation</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right">
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Ultimo Aggiornamento</p>
                <p class="text-xs font-black text-white"><?php echo $formatRome('now', 'H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <!-- 2. Stats Grid (Reale Portfolio Metrics) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div
            class="bg-white/5 p-4 rounded-xl border border-white/10 glass group hover:border-indigo-500/30 transition-all">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Saldo Totale</span>
            <div class="text-lg font-black text-white"><?php echo number_format($stats['total_balance'], 2); ?>€</div>
        </div>
        <div
            class="bg-white/5 p-4 rounded-xl border border-white/10 glass group hover:border-indigo-400/30 transition-all">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Saldo
                Disponibile</span>
            <div class="text-lg font-black text-indigo-400">
                <?php echo number_format($stats['available_balance'], 2); ?>€
            </div>
        </div>
        <div
            class="bg-white/5 p-4 rounded-xl border border-white/10 glass group hover:border-amber-500/30 transition-all">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Saldo
                Esposizione</span>
            <div class="text-lg font-black text-amber-500"><?php echo number_format($stats['exposure_balance'], 2); ?>€
            </div>
        </div>
        <div
            class="bg-white/5 p-4 rounded-xl border border-white/10 glass group hover:border-success/30 transition-all">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Profitto Totale
                (P&L)</span>
            <div class="text-lg font-black <?php echo $stats['total_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo ($stats['total_profit'] >= 0 ? '+' : '') . number_format($stats['total_profit'], 2); ?>€
            </div>
        </div>
        <div class="bg-white/5 p-4 rounded-xl border border-white/10 glass group hover:border-accent/30 transition-all">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">ROI Reale</span>
            <div class="text-lg font-black text-accent"><?php echo number_format($stats['roi'], 2); ?>%</div>
        </div>
        <div
            class="bg-white/5 p-4 rounded-xl border border-white/10 glass group hover:border-blue-500/30 transition-all">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Win Rate</span>
            <div class="text-lg font-black text-white"><?php echo number_format($stats['win_rate'], 1); ?>%</div>
        </div>
    </div>

    <!-- 3. Equity Curve (Performance Chart) -->
    <div class="bg-white/5 rounded-2xl border border-white/10 glass overflow-hidden flex flex-col">
        <div class="p-4 border-b border-white/10 flex justify-between items-center bg-accent/5">
            <div class="flex items-center gap-2">
                <i data-lucide="trending-up" class="w-4 h-4 text-accent"></i>
                <h2 class="text-sm font-black uppercase tracking-widest">Equity Curve</h2>
            </div>
            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Profit over time</span>
        </div>
        <div class="flex-1 p-4 flex items-center justify-center min-h-[300px]">
            <?php if (empty($performanceHistory) || count($performanceHistory) < 2): ?>
                <div class="text-center">
                    <i data-lucide="bar-chart-3" class="w-8 h-8 text-slate-700 mx-auto mb-2 opacity-50"></i>
                    <p class="text-[10px] font-bold text-slate-600 uppercase tracking-widest">Dati insufficienti per il
                        grafico</p>
                </div>
            <?php else: ?>
                <canvas id="performanceChart"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4. Recent Bets (Ultime Operazioni Quantum) -->
    <div class="bg-white/5 rounded-2xl border border-white/10 glass overflow-hidden">
        <div class="p-4 border-b border-white/10 flex justify-between items-center">
            <h2 class="text-sm font-black uppercase tracking-widest">Ultime Operazioni Quantum</h2>
            <span class="text-[10px] font-bold text-slate-500 uppercase">Live Tracking</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="text-[10px] font-bold text-slate-500 uppercase tracking-widest border-b border-white/10">
                        <th class="px-6 py-4">Data</th>
                        <th class="px-6 py-4">Sport / Evento</th>
                        <th class="px-6 py-4">Mercato / Selezione</th>
                        <th class="px-6 py-4">Quota</th>
                        <th class="px-6 py-4 text-center">Stake</th>
                        <th class="px-6 py-4 text-center">P&L</th>
                        <th class="px-6 py-4 text-center">Stato</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <?php if (empty($recentBets)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-slate-500 text-xs font-bold uppercase">
                                Nessuna operazione registrata</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentBets as $bet): ?>
                            <tr class="hover:bg-white/5 transition-colors group">
                                <td class="px-6 py-4 text-xs font-bold text-slate-400">
                                    <?php echo $formatRome($bet['created_at'], 'H:i'); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-[10px] font-black uppercase text-indigo-400 block mb-0.5">
                                        <?php echo $bet['sport']; ?>
                                    </span>
                                    <span class="text-xs font-bold text-white">
                                        <?php echo $bet['event_name']; ?>
                                        <?php if (!empty($bet['score'])): ?>
                                            <span class="text-yellow-400 ml-1 font-mono"><?php echo $bet['score']; ?></span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-[10px] font-black uppercase text-slate-500 block mb-0.5">
                                        <?php echo $bet['market_name']; ?>
                                    </span>
                                    <span class="text-xs font-bold text-white">
                                        <?php echo $bet['runner_name']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 bg-white/5 border border-white/10 rounded-lg text-xs font-black text-accent">
                                        <?php echo number_format($bet['odds'], 2); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-xs font-bold text-white">
                                    <?php echo number_format($bet['stake'], 2); ?>€
                                </td>
                                <td class="px-6 py-4 text-center text-xs font-bold">
                                    <?php 
                                    <?php 
                                        if ($bet['status'] === 'pending') {
                                            if (isset($bet['live_pnl'])) {
                                                $pnl = (float)$bet['live_pnl'];
                                                $colorClass = $pnl >= 0 ? 'text-emerald-400' : 'text-rose-400';
                                                echo '<div class="flex flex-col items-center leading-none">';
                                                echo '<span class="' . $colorClass . ' font-black text-[11px]">' . ($pnl >= 0 ? '+' : '') . number_format($pnl, 2) . '€</span>';
                                                echo '<span class="text-[9px] text-slate-500 mt-0.5">Live ' . number_format($bet['live_odds'], 2) . '</span>';
                                                echo '</div>';
                                            } else {
                                                echo '<span class="text-slate-600">-</span>';
                                            }
                                        } else {
                                            $pnl = (float)$bet['profit'];
                                            if ($pnl > 0) {
                                                echo '<span class="text-success hover:scale-110 transition-transform cursor-default">+' . number_format($pnl, 2) . '€</span>';
                                            } elseif ($pnl < 0) {
                                                echo '<span class="text-danger hover:scale-110 transition-transform cursor-default">' . number_format($pnl, 2) . '€</span>';
                                            } else {
                                                echo '<span class="text-slate-500">0.00€</span>';
                                            }
                                        }
                                    ?>
                                    ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php if ($bet['status'] === 'pending'): ?>
                                        <span
                                            class="px-2 py-1 bg-warning/10 text-warning text-[10px] font-black uppercase rounded-lg border border-warning/20">Abbinata</span>
                                    <?php elseif ($bet['status'] === 'won'): ?>
                                        <span
                                            class="px-2 py-1 bg-success/10 text-success text-[10px] font-black uppercase rounded-lg border border-success/20">Vinta</span>
                                    <?php else: ?>
                                        <span
                                            class="px-2 py-1 bg-danger/10 text-danger text-[10px] font-black uppercase rounded-lg border border-danger/20">Persa</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. Sport Tabs & Live Events -->
    <?php
    $activeSport = array_key_first($liveSportsData) ?: 'Soccer';
    ?>
    <div class="bg-white/5 rounded-2xl border border-white/10 glass overflow-hidden flex flex-col min-h-[500px]"
        id="dashboard-tabs" x-data="{ activeTab: '<?php echo $activeSport; ?>' }">
        <div class="flex border-b border-white/10 bg-white/5 overflow-x-auto custom-scrollbar">
            <?php foreach ($liveSportsData as $sportName => $data): ?>
                <button @click="activeTab = '<?php echo $sportName; ?>'"
                    :class="activeTab === '<?php echo $sportName; ?>' ? 'bg-indigo-500/20 text-white border-b-2 border-indigo-500' : 'text-slate-500 hover:text-white'"
                    class="px-6 py-4 text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap">
                    <?php echo $sportName; ?> (<?php echo count($data['events']); ?>)
                </button>
            <?php endforeach; ?>
        </div>

        <div class="p-6 flex-1">
            <?php foreach ($liveSportsData as $sportName => $data): ?>
                <div x-show="activeTab === '<?php echo $sportName; ?>'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <?php foreach ($data['events'] as $event): ?>
                        <div
                            class="bg-white/5 p-5 rounded-2xl border border-white/10 hover:border-indigo-500/30 transition-all group flex flex-col justify-between">
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <span
                                        class="text-[10px] font-black uppercase text-indigo-400"><?php echo $sportName; ?></span>
                                    <div class="text-xs text-slate-400 mb-1">
                                        <?php echo $event['event']['countryCode'] ?? 'World'; ?>
                                    </div>
                                    <div class="font-bold text-white mb-2"><?php echo $event['event']['name']; ?></div>

                                    <?php if (isset($event['score'])): ?>
                                        <div class="text-xl font-black text-yellow-400 mb-2 animate-pulse">
                                            <?php echo $event['score']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div
                                    class="bg-danger/10 text-danger px-2 py-1 rounded text-[10px] font-black uppercase flex items-center gap-1 border border-danger/20">
                                    <span class="w-1.5 h-1.5 bg-danger rounded-full animate-pulse"></span>
                                    LIVE
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-between text-xs font-bold text-slate-400 bg-black/20 p-3 rounded-xl border border-white/5">
                                <div class="flex flex-col gap-1">
                                    <span class="text-[9px] uppercase text-slate-500">Inizio</span>
                                    <span
                                        class="text-white"><?php echo date('H:i', strtotime($event['event']['openDate'])); ?></span>
                                </div>
                                <div class="flex flex-col gap-1 text-right">
                                    <span class="text-[9px] uppercase text-slate-500">Exchange ID</span>
                                    <span class="text-white"><?php echo $event['event']['id']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 6. Analisi Autonoma (Thinking Logs) & Live Trace -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Thinking Logs (Background Activity) -->
        <div class="lg:col-span-2 bg-white/5 rounded-2xl border border-white/10 glass overflow-hidden flex flex-col">
            <div class="p-4 border-b border-white/10 flex justify-between items-center bg-indigo-500/5">
                <div class="flex items-center gap-2">
                    <i data-lucide="brain" class="w-4 h-4 text-indigo-400"></i>
                    <h2 class="text-sm font-black uppercase tracking-widest">Analisi Autonoma</h2>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Real-time
                        Thinking</span>
                </div>
            </div>
            <div class="flex-1 max-h-[400px] overflow-y-auto custom-scrollbar">
                <table class="w-full text-left">
                    <tbody class="divide-y divide-white/5">
                        <?php if (empty($recentLogs)): ?>
                            <tr>
                                <td class="px-6 py-10 text-center text-slate-500 text-[10px] uppercase font-bold italic">In
                                    attesa dell'AI...</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="px-6 py-3 text-[10px] font-bold text-slate-500 w-16">
                                        <?php echo $formatRome($log['created_at'], 'H:i'); ?>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-2">
                                            <?php
                                            $actionClass = 'text-slate-500';
                                            $actionLabel = $log['action'];
                                            if ($log['action'] === 'bet') {
                                                $actionClass = 'text-success';
                                                $actionLabel = 'BET';
                                            } elseif ($log['action'] === 'pass') {
                                                $actionClass = 'text-slate-500';
                                                $actionLabel = 'PASS';
                                            } elseif (strpos($log['action'], 'SKIP') === 0) {
                                                $actionClass = 'text-amber-500';
                                                $actionLabel = str_replace('SKIP_', '', $log['action']);
                                            }
                                            ?>
                                            <span
                                                class="text-[10px] font-black uppercase <?php echo $actionClass; ?> transition-all">
                                                <?php echo $actionLabel; ?>
                                            </span>
                                            <span
                                                class="text-[10px] font-bold text-white truncate max-w-[150px]"><?php echo $log['event_name']; ?></span>
                                            <span
                                                class="text-[9px] font-bold text-indigo-400/70 truncate">[<?php echo $log['market_name']; ?>]</span>
                                        </div>
                                        <div class="text-[9px] text-slate-400 italic mt-0.5 line-clamp-1">
                                            "<?php echo $log['motivation']; ?>"</div>
                                    </td>
                                    <td class="px-6 py-3 text-right">
                                        <span
                                            class="text-[10px] font-black text-indigo-400"><?php echo $log['confidence']; ?>%</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- Live Trace Debugger -->
        <div class="bg-white/5 rounded-2xl border border-white/10 glass overflow-hidden flex flex-col">
            <div class="p-4 border-b border-white/10 flex justify-between items-center bg-amber-500/5">
                <div class="flex items-center gap-2">
                    <i data-lucide="terminal" class="w-4 h-4 text-amber-500"></i>
                    <h2 class="text-sm font-black uppercase tracking-widest">Live Trace Debugger</h2>
                </div>
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Last Scan</span>
            </div>
            <div class="p-4 space-y-4 text-[10px] max-h-[400px] overflow-y-auto custom-scrollbar">
                <?php if (empty($lastScanTrace)): ?>
                    <p class="text-slate-500 italic">Nessuna traccia disponibile.</p>
                <?php else: ?>
                    <div class="flex justify-between border-b border-white/5 pb-2">
                        <span class="text-slate-500">Ultimo Check:</span>
                        <span
                            class="text-white font-mono"><?php echo $formatRome($lastScanTrace['timestamp'] ?? 'now', 'H:i:s'); ?></span>
                    </div>

                    <div>
                        <p class="text-indigo-400 font-bold uppercase mb-1">Sport Scansionati</p>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (($lastScanTrace['scanned_sports'] ?? []) as $s):
                                $details = $lastScanTrace['scanned_details'][$s] ?? null;
                                $label = $s . ($details ? " ({$details['events']}/{$details['markets']})" : "");
                                ?>
                                <span
                                    class="px-1.5 py-0.5 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded"><?php echo $label; ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2 bg-black/20 rounded border border-white/5">
                            <p class="text-slate-500 uppercase text-[8px]">Eventi</p>
                            <p class="text-white font-black"><?php echo $lastScanTrace['found_events'] ?? 0; ?></p>
                        </div>
                        <div class="p-2 bg-black/20 rounded border border-white/5">
                            <p class="text-slate-500 uppercase text-[8px]">Mercati</p>
                            <p class="text-white font-black"><?php echo $lastScanTrace['found_markets'] ?? 0; ?></p>
                        </div>
                    </div>

                    <?php if (!empty($lastScanTrace['errors'])): ?>
                        <div class="p-2 bg-danger/10 border border-danger/20 rounded text-danger font-bold">
                            <p class="uppercase text-[8px] mb-1 text-danger/80">Errori Critici</p>
                            <?php foreach ($lastScanTrace['errors'] as $err): ?>
                                <p class="leading-tight">• <?php echo $err; ?></p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <p class="text-amber-400 font-bold uppercase mb-1">Dettagli Skipping</p>
                        <div class="space-y-1 opacity-80">
                            <?php
                            $reasons = array_slice($lastScanTrace['skipped_reasons'] ?? [], 0, 15);
                            if (empty($reasons))
                                echo '<p class="text-slate-600">Nessuno skip registrato.</p>';
                            foreach ($reasons as $reason): ?>
                                <p class="truncate border-l border-white/10 pl-2 py-0.5"><?php echo $reason; ?></p>
                            <?php endforeach; ?>
                            <?php if (count($lastScanTrace['skipped_reasons'] ?? []) > 15): ?>
                                <p class="text-slate-600 italic">... + altri
                                    <?php echo count($lastScanTrace['skipped_reasons']) - 15; ?> skip
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<!-- 7. RAG Brain Memory (Experiences) -->
<div class="bg-white/5 rounded-2xl border border-white/10 glass overflow-hidden">
    <div class="p-4 border-b border-white/10 flex justify-between items-center bg-amber-500/5">
        <div class="flex items-center gap-2">
            <i data-lucide="zap" class="w-4 h-4 text-amber-500"></i>
            <h2 class="text-sm font-black uppercase tracking-widest">Cervello RAG (Memoria di Apprendimento)</h2>
        </div>
        <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Knowledge Base</span>
    </div>
    <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
        <?php if (empty($recentExperiences)): ?>
            <div class="col-span-full py-6 text-center text-slate-600 text-[10px] uppercase font-bold italic">Nessuna
                lezione appresa ancora.</div>
        <?php else: ?>
            <?php foreach ($recentExperiences as $exp): ?>
                <div
                    class="p-3 bg-white/5 rounded-xl border border-white/10 border-l-2 <?php echo $exp['outcome'] === 'won' ? 'border-l-success' : 'border-l-danger'; ?>">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[8px] font-black uppercase text-slate-500"><?php echo $exp['sport']; ?></span>
                        <span
                            class="text-[8px] font-bold text-slate-700"><?php echo date('d/m', strtotime($exp['created_at'])); ?></span>
                    </div>
                    <p class="text-[10px] font-medium text-white italic leading-tight line-clamp-2">
                        "<?php echo $exp['lesson']; ?>"</p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Initialize Performance Chart
    const performanceData = <?php echo json_encode($performanceHistory); ?>;
    if (performanceData.length > 1) {
        const ctx = document.getElementById('performanceChart').getContext('2d');

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: performanceData.map(d => d.t),
                datasets: [
                    {
                        label: 'Baseline (100€)',
                        data: performanceData.map(() => 100),
                        borderColor: 'rgba(255, 255, 255, 0.5)',
                        borderWidth: 1,
                        borderDash: [5, 5],
                        pointRadius: 0,
                        fill: false,
                        order: 1
                    },
                    {
                        label: '<?= ($currentMode ?? 'virtual') === 'real' ? 'Real' : 'Virtual' ?> Bankroll (€)',
                        data: performanceData.map(d => d.v),
                        borderColor: '#6366f1',
                        borderWidth: 3,
                        fill: {
                            target: { value: 100 },
                            above: 'rgba(34, 197, 94, 0.2)',   // Green
                            below: 'rgba(239, 68, 68, 0.2)'    // Red
                        },
                        tension: 0.4,
                        pointRadius: 0,
                        pointHoverRadius: 6,
                        pointHoverBackgroundColor: '#6366f1',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2,
                        order: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 10, weight: 'bold' },
                        bodyFont: { size: 12, weight: 'black' },
                        padding: 12,
                        borderColor: 'rgba(255, 255, 255, 0.1)',
                        borderWidth: 1,
                        displayColors: false,
                        callbacks: {
                            label: function (context) {
                                if (context.dataset.label.includes('Baseline')) return null;
                                return context.parsed.y.toFixed(2) + ' €';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                        ticks: { color: '#64748b', font: { size: 10, weight: 'bold' } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 9, weight: 'bold' }, maxRotation: 0 }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'nearest',
                },
            }
        });
    }

    // Refresh Lucide icons after HTMX content swap
    document.body.addEventListener('htmx:afterOnLoad', function () {
        if (window.lucide) {
            lucide.createIcons();
        }
    });

    // Auto-refresh the dashboard every 60 seconds
    setTimeout(() => {
        window.location.reload();
    }, 60000);
</script>

<?php
if (!$isEmbedded) {
    require __DIR__ . '/../../Views/layout/bottom.php';
}
?>