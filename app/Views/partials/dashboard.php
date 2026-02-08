<?php
// app/Views/partials/dashboard.php
use App\Config\Config;

// Data passed from Controller or fetched here if direct include (fallback)
$liveMatches = $liveMatches ?? [];
$predictions = $predictions ?? [];
$history = $history ?? [];
$account = $account ?? ['available' => 0, 'exposure' => 0, 'wallet' => 0];

// Stats Calculation
$totalProfit = 0;
$wonBets = 0;
$lostBets = 0;
foreach ($history as $h) {
    if ($h['status'] === 'won') {
        $totalProfit += $h['stake'] * ($h['odds'] - 1);
        $wonBets++;
    } elseif ($h['status'] === 'lost') {
        $totalProfit -= $h['stake'];
        $lostBets++;
    }
}
?>

<!-- Auto-refresh dashboard every 15s -->
<div hx-get="/api/view/dashboard" hx-trigger="every 15s" hx-swap="outerHTML">

    <!-- Account Stats Summary -->
    <?php if (!empty($activeSports)): ?>
        <div class="flex gap-4 mb-8 overflow-x-auto no-scrollbar pb-2">
            <?php 
            $icons = [
                'soccer' => 'trophy',
                'tennis' => 'circle-dot',
                'basket' => 'dribbble',
                'volley' => 'activity',
                'hockey' => 'snowflake',
                'rugby' => 'citrus',
                'golf'   => 'flag-triangle-right',
                'motor'  => 'car-front',
                'cycl'   => 'bike'
            ];
            $currentSport = $selectedSport ?? 'all';
            ?>
            <button
                class="<?php echo $currentSport === 'all' ? 'bg-accent text-white shadow-lg shadow-accent/20 scale-[1.02]' : 'bg-slate-800 text-slate-500 hover:bg-slate-700 hover:text-white'; ?> px-6 py-4 rounded-2xl flex items-center justify-between gap-4 min-w-[140px] transition-all bg-white/5 border border-white/5 shrink-0"
                hx-get="/api/view/dashboard?sport=all" hx-target="#htmx-container" hx-push-url="true">
                <div class="flex items-center gap-3">
                    <i data-lucide="layout-grid" class="w-5 h-5"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest">Tutti</span>
                </div>
                <span class="<?php echo $currentSport === 'all' ? 'bg-white/20 text-white' : 'bg-slate-900/50 text-slate-500'; ?> text-[10px] font-bold px-2 py-1 rounded-lg"><?php echo isset($allMatches) ? count($allMatches) : count($liveMatches); ?></span>
            </button>
            <?php foreach ($activeSports as $sport => $count):
                $icon = 'activity';
                foreach($icons as $key => $val) {
                    if(stripos($sport, $key) !== false) {
                        $icon = $val;
                        break;
                    }
                }
                $isActive = (strtolower($currentSport) === strtolower($sport));
                ?>
                <button
                    class="<?php echo $isActive ? 'bg-accent text-white shadow-lg shadow-accent/20 scale-[1.02]' : 'bg-slate-800 text-slate-500 hover:bg-slate-700 hover:text-white'; ?> px-6 py-4 rounded-2xl flex items-center justify-between gap-4 min-w-[140px] transition-all bg-white/5 border border-white/5 group shrink-0"
                    hx-get="/api/view/dashboard?sport=<?php echo urlencode($sport); ?>" hx-target="#htmx-container" hx-push-url="true">
                    <div class="flex items-center gap-3">
                        <i data-lucide="<?php echo $icon; ?>" class="w-5 h-5 <?php echo $isActive ? 'text-white' : 'group-hover:text-accent'; ?> transition-colors"></i>
                        <span
                            class="text-[10px] font-black uppercase tracking-widest truncate max-w-[80px]"><?php echo $sport; ?></span>
                    </div>
                    <span class="<?php echo $isActive ? 'bg-white/20 text-white' : 'bg-slate-900/50 text-slate-500 group-hover:bg-accent/20 group-hover:text-accent'; ?> text-[10px] font-bold px-2 py-1 rounded-lg transition-colors"><?php echo $count; ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
        <div class="glass p-6 rounded-[32px] border-white/5">
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Disponibile</div>
            <div class="text-2xl font-black tabular-nums text-white">
                €<?php echo number_format($account['available'], 2); ?>
            </div>
        </div>
        <div class="glass p-6 rounded-[32px] border-white/5">
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">In Gioco</div>
            <div class="flex items-baseline gap-2">
                <div class="text-2xl font-black tabular-nums text-warning">
                    €<?php echo number_format($account['exposure'], 2); ?>
                </div>
                <?php if (isset($openBetsCount) && $openBetsCount > 0): ?>
                    <span
                        class="text-[10px] font-bold text-slate-500 bg-white/5 px-2 py-0.5 rounded-lg border border-white/5">
                        <?php echo $openBetsCount; ?> attive
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="glass p-6 rounded-[32px] border-white/5">
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Profitto Netto</div>
            <div
                class="text-2xl font-black tabular-nums <?php echo $totalProfit >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo ($totalProfit >= 0 ? '+' : '') . number_format($totalProfit, 2); ?>€
            </div>
        </div>
        <div class="glass p-6 rounded-[32px] border-white/5">
            <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">Portafoglio Totale</div>
            <div class="text-2xl font-black tabular-nums text-accent">
                €<?php echo number_format($account['wallet'], 2); ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Live & Upcoming Matches -->
        <div class="lg:col-span-2 space-y-8">
            <div class="flex items-center justify-between">
                <h2 class="text-4xl font-black italic uppercase tracking-tighter text-white leading-none">Live Now <span
                        class="text-accent">.</span></h2>
                <div
                    class="px-4 py-2 bg-accent/10 text-accent rounded-2xl text-[10px] font-black uppercase tracking-widest border border-accent/20">
                    <?php echo count($liveMatches); ?> Active
                </div>
            </div>

            <div class="space-y-4">
                <?php if (empty($liveMatches)): ?>
                    <div class="glass p-20 text-center font-black uppercase italic text-slate-500 rounded-[40px]">Nessun
                        match live al momento.</div>
                <?php else: ?>
                    <?php foreach ($liveMatches as $m): ?>
                        <div
                            class="glass p-6 rounded-[32px] border-white/5 hover:border-accent/30 transition-all group relative overflow-hidden">
                            <!-- Background Sport Icon -->
                            <div class="absolute -right-4 -bottom-4 opacity-5 rotate-12 pointer-events-none">
                                <i data-lucide="trophy" class="w-32 h-32"></i>
                            </div>

                            <div class="relative z-10">
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="px-2 py-0.5 rounded bg-danger/10 text-danger text-[9px] font-black uppercase tracking-widest border border-danger/20 animate-pulse">LIVE</span>
                                        <span
                                            class="text-[9px] font-bold text-slate-500 uppercase tracking-widest"><?php echo $m['sport']; ?>
                                            | <?php echo $m['market']; ?></span>
                                    </div>
                                    <div class="text-[9px] font-black text-slate-500 uppercase tracking-widest">
                                        Vol: €<?php echo number_format($m['totalMatched'], 0); ?>
                                    </div>
                                </div>

                                <h3 class="text-xl font-black italic uppercase text-white mb-6 tracking-tight">
                                    <?php echo $m['event']; ?>
                                </h3>

                                <div class="grid grid-cols-<?php echo count($m['runners']); ?> gap-3">
                                    <?php foreach ($m['runners'] as $r): ?>
                                        <div
                                            class="bg-white/5 p-3 rounded-2xl text-center border border-white/5 group-hover:border-accent/20 transition-all">
                                            <div class="text-[9px] font-bold text-slate-400 uppercase truncate mb-1">
                                                <?php echo $r['name']; ?>
                                            </div>
                                            <div class="grid grid-cols-2 gap-1">
                                                <div class="bg-accent/10 rounded-lg py-1">
                                                    <span
                                                        class="block text-xs font-black text-accent"><?php echo $r['back'] ?? '-'; ?></span>
                                                </div>
                                                <div class="bg-danger/10 rounded-lg py-1">
                                                    <span
                                                        class="block text-xs font-black text-danger"><?php echo $r['lay'] ?? '-'; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sidebar: Predictions & Recent Activity -->
        <aside class="space-y-8">
            <!-- Predictions -->
            <div>
                <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Hot Predictions</h2>
                <div class="space-y-4">
                    <?php if (empty($predictions)): ?>
                        <div
                            class="glass p-8 rounded-3xl text-center text-slate-500 font-bold text-[10px] uppercase italic">
                            Nessun consiglio disponibile</div>
                    <?php else: ?>
                        <?php foreach (array_slice($predictions, 0, 5) as $p): ?>
                            <div class="glass p-6 rounded-3xl border-white/5 hover:border-accent/30 transition-all group cursor-pointer"
                                hx-get="/api/view/match/<?php echo is_array($p['event']) ? $p['event']['id'] : $p['fixture_id']; ?>"
                                hx-target="#htmx-container"
                                hx-push-url="/match/<?php echo is_array($p['event']) ? $p['event']['id'] : $p['fixture_id']; ?>">
                                <div class="flex items-center justify-between mb-4">
                                    <span
                                        class="text-[8px] font-black uppercase text-slate-500 tracking-widest"><?php echo $p['competition']['name'] ?? $p['sport']; ?></span>
                                    <i data-lucide="brain-circuit" class="w-3 h-3 text-accent"></i>
                                </div>
                                <div class="text-[10px] font-black text-white italic uppercase mb-2 truncate">
                                    <?php echo is_array($p['event']) ? $p['event']['name'] : $p['event']; ?>
                                </div>
                                <div class="bg-accent/10 p-3 rounded-xl border border-accent/20">
                                    <div class="flex justify-between items-center">
                                        <span
                                            class="text-[9px] font-bold text-accent uppercase tracking-widest"><?php echo $p['advice']; ?></span>
                                        <span class="text-xs font-black text-white"><?php echo $p['odds']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div>
                <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Recent Activity</h2>
                <div class="glass rounded-[32px] border-white/5 divide-y divide-white/5 overflow-hidden">
                    <?php if (empty($history)): ?>
                        <div class="p-10 text-center text-slate-500 font-bold text-[10px] uppercase italic">Nessuna attività
                            recente</div>
                    <?php else: ?>
                        <?php foreach (array_slice($history, 0, 5) as $h): ?>
                            <div class="p-4 flex items-center justify-between group hover:bg-white/5 transition-all cursor-pointer"
                                hx-get="/api/view/modal/bet/<?php echo $h['id']; ?>" hx-target="#global-modal-container">
                                <div>
                                    <div class="text-[10px] font-black italic uppercase text-white truncate max-w-[120px]">
                                        <?php echo $h['match_name']; ?>
                                    </div>
                                    <div class="text-[8px] font-bold text-slate-500 uppercase"><?php echo $h['market']; ?></div>
                                </div>
                                <div class="text-right">
                                    <div
                                        class="text-[10px] font-black italic uppercase <?php echo $h['status'] === 'won' ? 'text-success' : ($h['status'] === 'lost' ? 'text-danger' : 'text-warning'); ?>">
                                        <?php echo $h['status']; ?>
                                    </div>
                                    <div class="text-[8px] font-bold text-white tabular-nums">€<?php echo $h['stake']; ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
    <script>
        if (window.lucide) lucide.createIcons();
    </script>
</div>