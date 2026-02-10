<?php
// app/GiaNik/Views/partials/gianik_live.php
$groupedMatches = $groupedMatches ?? [];
$account = $account ?? ['available' => 0, 'exposure' => 0];

?>

<style>
    @keyframes pulse-slow {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.8; transform: scale(1.02); }
    }
    .animate-pulse-slow {
        animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<!-- Account Summary (Compact Bar) -->
<div class="glass px-6 py-3 rounded-xl border-white/5 flex flex-wrap items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-8">
        <!-- Real Betfair Account -->
        <div class="flex items-center gap-4">
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Disponibile</span>
                <div class="text-lg font-black tabular-nums text-white leading-none">
                    €<?php echo number_format($account['available'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Esposizione</span>
                <div class="text-lg font-black tabular-nums text-warning leading-none">
                    €<?php echo number_format($account['exposure'], 2); ?></div>
            </div>
        </div>

        <div class="h-8 w-px bg-white/10 mx-2"></div>

        <!-- Virtual GiaNik Account -->
        <div class="flex items-center gap-4">
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Disponibile</span>
                <div class="text-lg font-black tabular-nums text-white leading-none">
                    €<?php echo number_format($virtualAccount['available'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual In Gioco</span>
                <div class="text-lg font-black tabular-nums text-accent leading-none">
                    €<?php echo number_format($virtualAccount['exposure'], 2); ?></div>
            </div>
            <div>
                <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Totale</span>
                <div class="text-lg font-black tabular-nums text-slate-400 leading-none">
                    €<?php echo number_format($virtualAccount['total'], 2); ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($groupedMatches)): ?>
    <div class="text-center py-10">
        <span class="text-slate-500 text-sm font-bold uppercase">Nessun evento live disponibile</span>
    </div>
<?php else: ?>

    <!-- Events List (One per row) -->
    <div class="animate-fade-in space-y-3 px-2">
            <?php
            $allMatches = [];
            foreach($groupedMatches as $matches) $allMatches = array_merge($allMatches, $matches);
            foreach ($allMatches as $m):
                    $marketId = $m['marketId'];
                    $fixtureId = $m['fixture_id'];
                    $homeId = $m['home_id'];
                    $awayId = $m['away_id'];
                    ?>
                    <div class="glass p-4 rounded-[32px] border border-white/5 hover:border-accent/20 transition-all group flex flex-col gap-4">

                        <div class="flex items-center gap-6">
                        <!-- Match Info Section -->
                        <div class="flex-1 flex items-center justify-between">

                            <!-- Home Team -->
                            <div class="flex items-center gap-4 flex-1 justify-end cursor-pointer hover:opacity-80 transition-opacity"
                                 <?php if($homeId): ?> hx-get="/api/gianik/team-details?teamId=<?php echo $homeId; ?>" hx-target="#global-modal-container" <?php endif; ?>>
                                <span class="text-xs font-black uppercase text-white truncate text-right max-w-[200px]">
                                    <?php echo $m['home_name']; ?>
                                </span>
                                <?php if($m['home_logo']): ?>
                                    <div class="w-10 h-10 rounded-full bg-white/5 p-2 border border-white/5 flex items-center justify-center overflow-hidden shrink-0">
                                        <img src="<?php echo $m['home_logo']; ?>" class="w-full h-full object-contain" alt="">
                                    </div>
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0">
                                        <i data-lucide="shield" class="w-5 h-5 text-slate-600"></i>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Center: Score & Competition -->
                            <div class="flex flex-col items-center min-w-[180px] px-4">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <?php if ($m['flag']): ?>
                                        <img src="<?php echo $m['flag']; ?>" class="w-3 h-2 object-cover rounded-sm opacity-80" alt="">
                                    <?php endif; ?>
                                    <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">
                                        <?php echo $m['country'] ?? ''; ?>
                                    </span>
                                </div>
                                <div class="text-[9px] font-black text-accent uppercase tracking-widest mb-1 text-center leading-tight">
                                    <?php echo $m['competition']; ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="bg-white/10 px-4 py-1 rounded-xl border border-white/5 font-black text-xl tabular-nums text-white">
                                        <?php echo $m['score'] ?: '0-0'; ?>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    <?php if ($m['has_api_data']): ?>
                                        <div class="w-1 h-1 rounded-full bg-success shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                                    <?php endif; ?>
                                    <span class="text-[9px] font-black uppercase text-slate-500 tracking-tighter">
                                        <?php echo $m['status_label']; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Away Team -->
                            <div class="flex items-center gap-4 flex-1 justify-start cursor-pointer hover:opacity-80 transition-opacity"
                                 <?php if($awayId): ?> hx-get="/api/gianik/team-details?teamId=<?php echo $awayId; ?>" hx-target="#global-modal-container" <?php endif; ?>>
                                <?php if($m['away_logo']): ?>
                                    <div class="w-10 h-10 rounded-full bg-white/5 p-2 border border-white/5 flex items-center justify-center overflow-hidden shrink-0">
                                        <img src="<?php echo $m['away_logo']; ?>" class="w-full h-full object-contain" alt="">
                                    </div>
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0">
                                        <i data-lucide="shield" class="w-5 h-5 text-slate-600"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="text-xs font-black uppercase text-white truncate max-w-[200px]">
                                    <?php echo $m['away_name']; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Action Section -->
                        <div class="flex items-center gap-4 min-w-[320px] border-l border-white/5 pl-6">
                            <div class="flex flex-col mr-2">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-tighter italic">Matched</span>
                                <span class="text-sm font-black text-white leading-none">€<?php echo number_format($m['totalMatched'], 0, ',', '.'); ?></span>
                            </div>

                            <div class="flex items-center gap-2">
                                <button hx-get="/api/gianik/analyze?marketId=<?php echo $marketId; ?>"
                                    hx-target="#global-modal-container"
                                    hx-indicator="#indicator-<?php echo str_replace('.', '-', $marketId); ?>"
                                    class="px-4 py-3 bg-accent/10 hover:bg-accent/20 text-accent rounded-xl text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2 relative border border-accent/10">
                                    <div id="indicator-<?php echo str_replace('.', '-', $marketId); ?>"
                                        class="htmx-indicator absolute inset-0 flex items-center justify-center bg-accent/10 rounded-xl">
                                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                    </div>
                                    <i data-lucide="brain-circuit" class="w-4 h-4"></i> Analisi IA
                                </button>

                                <button hx-get="/api/fixture-predictions?fixture=<?php echo $fixtureId; ?>"
                                    hx-target="#global-modal-container"
                                    class="p-3 bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-400 rounded-xl border border-indigo-500/10 transition-all"
                                    title="Predictions Crystal Ball">
                                    <span class="text-lg">🔮</span>
                                </button>
                            </div>
                        </div>
                        </div>

                        <!-- Detailed Match Info (Events, Stadium, etc) -->
                        <?php if($fixtureId): ?>
                        <div class="border-t border-white/5 pt-4"
                             hx-get="/api/gianik/match-details?fixtureId=<?php echo $fixtureId; ?>"
                             hx-trigger="load"
                             hx-swap="innerHTML">
                             <div class="flex items-center justify-center py-2 opacity-20">
                                 <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                             </div>
                        </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            </div>

<?php endif; ?>

<script>
    if (window.lucide) lucide.createIcons();
</script>
