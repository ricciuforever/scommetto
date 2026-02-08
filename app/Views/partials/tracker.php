<?php
// app/Views/partials/tracker.php
// HTMX Partial for Tracker

$status = $_GET['status'] ?? 'all';
$summary = $statsSummary ?? ['netProfit' => 0, 'roi' => 0, 'winCount' => 0, 'lossCount' => 0, 'currentPortfolio' => 0];

// Helper for filtering classes (mimicking JS logic)
function getStatusClass($s)
{
    return $s === 'won' ? 'text-success' : ($s === 'lost' ? 'text-danger' : 'text-warning');
}
function getBorderClass($s)
{
    return $s === 'won' ? 'border-success bg-success/10 text-success' : ($s === 'lost' ? 'border-danger bg-danger/10 text-danger' : 'border-warning bg-warning/10 text-warning');
}
function getIcon($s)
{
    return $s === 'won' ? 'check-circle' : ($s === 'lost' ? 'x-circle' : 'clock');
}
?>

<div id="tracker-view-content" class="animate-fade-in">
    <!-- Tracker Summary Metrics -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10" id="tracker-stats-summary-php">
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">Available
                Balance</span>
            <div
                class="text-4xl font-black italic tracking-tighter <?php echo $summary['available_balance'] >= 0 ? 'text-white' : 'text-danger'; ?>">
                <?php echo number_format($summary['available_balance'], 2); ?>€
            </div>
        </div>
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">Portfolio</span>
            <div class="text-4xl font-black italic tracking-tighter text-white">
                <?php echo number_format($summary['currentPortfolio'], 2); ?>€
            </div>
        </div>
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">Net Balance</span>
            <div
                class="text-4xl font-black italic tracking-tighter <?php echo $summary['netProfit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?php echo ($summary['netProfit'] >= 0 ? '+' : '') . number_format($summary['netProfit'], 2); ?>€
            </div>
        </div>
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">ROI</span>
            <div class="text-4xl font-black italic tracking-tighter text-accent">
                <?php echo number_format($summary['roi'], 1); ?>%
            </div>
        </div>
    </div>


    <!-- Filter Buttons -->
    <div class="flex gap-4 mb-8 overflow-x-auto pb-2 no-scrollbar">
        <?php foreach (['all' => 'Tutte', 'won' => 'Vinte', 'lost' => 'Perse', 'pending' => 'In Corso'] as $k => $label):
            $isActive = ($status === $k);
            $bgClass = $isActive ? 'bg-accent text-white' : 'bg-white/5 text-slate-500 hover:bg-white/10';
            ?>
            <button
                class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap <?php echo $bgClass; ?>"
                hx-get="/api/view/tracker?status=<?php echo $k; ?>" hx-target="#htmx-container" hx-push-url="false">
                <?php echo $label; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- History List -->
    <div class="glass rounded-[40px] border-white/5 overflow-hidden divide-y divide-white/5" id="tracker-history-php">
        <?php if (empty($bets)): ?>
            <div class="p-20 text-center text-slate-500 font-black uppercase italic">
                Nessuna scommessa trovata per i filtri selezionati.
            </div>
        <?php else: ?>
            <?php foreach ($bets as $bet):
                $profit = 0;
                if ($bet['status'] === 'won') {
                    $profit = $bet['stake'] * ($bet['odds'] - 1);
                } elseif ($bet['status'] === 'lost') {
                    $profit = -$bet['stake'];
                }
                $statusClass = getStatusClass($bet['status']);
                $borderClass = getBorderClass($bet['status']);
                $icon = getIcon($bet['status']);
                ?>
                <div class="p-8 hover:bg-white/5 cursor-pointer transition-all flex items-center justify-between group"
                    hx-get="/api/view/modal/bet/<?php echo $bet['id']; ?>" hx-target="#global-modal-container">
                    <!-- Reuse existing JS modal function for now -->
                    <div class="flex items-center gap-6">
                        <div class="w-12 h-12 rounded-2xl flex items-center justify-center border <?php echo $borderClass; ?>">
                            <i data-lucide="<?php echo $icon; ?>" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <div
                                class="text-xl font-black italic uppercase tracking-tight text-white group-hover:text-accent transition-colors">
                                <?php echo htmlspecialchars($bet['match_name']); ?>
                            </div>
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                <?php echo htmlspecialchars($bet['market']); ?> @
                                <?php echo $bet['odds']; ?> |
                                <?php echo date('d/m/Y', strtotime($bet['timestamp'])); ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-black italic tracking-tighter <?php echo $statusClass; ?>">
                            <?php echo ($bet['status'] === 'won' ? '+' : ($bet['status'] === 'lost' ? '-' : '')); ?>
                            <?php echo number_format(abs($profit), 2); ?>€
                        </div>
                        <div class="text-[9px] font-black uppercase tracking-widest text-slate-500">
                            Puntata:
                            <?php echo $bet['stake']; ?>€
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();

    // Inline JS to handle filter clicks -> triggers HTMX reload with query params
    function setTrackerFilterPHP(status) {
        const htmxContainer = document.getElementById('htmx-container');
        if (htmxContainer) {
            htmxContainer.setAttribute('hx-get', `/api/view/tracker?status=${status}`);
            htmx.trigger('#htmx-container', 'load');
        }
    }
</script>