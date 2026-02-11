<?php
// app/GiaNik/Views/partials/gianik_live.php
$groupedMatches = $groupedMatches ?? [];
$account = $account ?? ['available' => 0, 'exposure' => 0];
$offset = $offset ?? 0;
$limit = $limit ?? 10;
$hasMore = $hasMore ?? false;
$allMatches = $allMatches ?? [];
?>

<style>
    @keyframes pulse-slow {

        0%,
        100% {
            opacity: 1;
            transform: scale(1);
        }

        50% {
            opacity: 0.8;
            transform: scale(1.02);
        }
    }

    .animate-pulse-slow {
        animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
</style>

<?php if ($offset === 0): ?>
    <div class="glass px-6 py-3 rounded-xl border-white/5 flex flex-wrap items-center justify-between gap-4 mb-6">
        <div class="flex items-center gap-8">
            <div class="flex items-center gap-4">
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Disponibile</span>
                    <div class="text-lg font-black tabular-nums text-white leading-none">€
                        <?php echo number_format($account['available'], 2); ?>
                    </div>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase text-slate-500 tracking-wider">Real Esposizione</span>
                    <div class="text-lg font-black tabular-nums text-warning leading-none">€
                        <?php echo number_format($account['exposure'], 2); ?>
                    </div>
                </div>
            </div>
            <?php if (($operationalMode ?? 'virtual') !== 'real'): ?>
                <div class="h-8 w-px bg-white/10 mx-2"></div>
                <div class="flex items-center gap-4">
                    <div>
                        <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual Disponibile</span>
                        <div class="text-lg font-black tabular-nums text-white leading-none">€
                            <?php echo number_format($virtualAccount['available'] ?? 0, 2); ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-[9px] font-black uppercase text-accent/50 tracking-wider">Virtual In Gioco</span>
                        <div class="text-lg font-black tabular-nums text-accent leading-none">€
                            <?php echo number_format($virtualAccount['exposure'] ?? 0, 2); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($allMatches)): ?>
        <div class="text-center py-10">
            <span class="text-slate-500 text-sm font-bold uppercase">Nessun evento disponibile</span>
        </div>
    <?php else: ?>
        <div id="gianik-matches-list" class="animate-fade-in space-y-3 px-2">
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($allMatches)): ?>
        <?php foreach ($allMatches as $m):
            $marketId = $m['marketId'];
            $fixtureId = $m['fixture_id'] ?? null;
            $isJustUpdated = ($m['just_updated'] ?? false) && (time() - $m['just_updated'] <= 15);
            ?>
            <div id="match-card-<?php echo str_replace('.', '-', $marketId); ?>"
                class="glass p-4 rounded-[32px] border <?php echo $isJustUpdated ? 'border-accent shadow-[0_0_20px_rgba(var(--accent-rgb),0.3)] animate-pulse' : 'border-white/5'; ?> hover:border-accent/20 transition-all group flex flex-col gap-4 relative">

                <?php if ($m['has_active_real_bet'] ?? false): ?>
                    <div class="absolute -top-2 -right-2 z-10">
                        <div
                            class="bg-accent px-3 py-1 rounded-full shadow-lg border border-white/20 flex items-center gap-1.5 animate-bounce-slow">
                            <i data-lucide="zap" class="w-3 h-3 text-white"></i>
                            <span class="text-[9px] font-black uppercase text-white tracking-widest">Active Bet</span>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="flex items-center gap-6">
                    <div class="flex-1 flex items-center justify-between">
                        <div class="flex items-center gap-4 flex-1 justify-end cursor-pointer hover:opacity-80 transition-opacity"
                            <?php if ($m['home_id'] ?? null): ?> hx-get="/api/gianik/team-details?teamId=
                    <?php echo $m['home_id']; ?>&leagueId=
                    <?php echo $m['league_id'] ?? ''; ?>&season=
                    <?php echo $m['season'] ?? ''; ?>" hx-target="#global-modal-container"
                            <?php endif; ?>>
                            <span class="text-xs font-black uppercase text-white truncate text-right max-w-[150px]">
                                <?php echo $m['home_name'] ?? 'Unknown'; ?>
                            </span>
                            <?php if ($m['home_logo'] ?? null): ?>
                                <img src="<?php echo $m['home_logo']; ?>"
                                    class="w-10 h-10 object-contain rounded-full bg-white/5 p-1 border border-white/5" alt="">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0"><i
                                        data-lucide="shield" class="w-5 h-5 text-slate-600"></i></div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-col items-center min-w-[140px] px-2 text-center">
                            <div class="flex items-center gap-1 mb-1">
                                <?php if ($m['flag'] ?? null): ?> <img src="<?php echo $m['flag']; ?>"
                                        class="w-3 h-2 object-cover rounded-sm" alt="">
                                <?php endif; ?>
                                <span class="text-[8px] font-black text-slate-500 uppercase tracking-widest">
                                    <?php echo $m['country'] ?? ''; ?>
                                </span>
                            </div>
                            <div class="text-[9px] font-black text-accent uppercase tracking-widest mb-1 leading-tight">
                                <?php echo $m['competition'] ?? ''; ?>
                            </div>
                            <div
                                class="bg-white/10 px-3 py-1 rounded-xl border border-white/5 font-black text-lg tabular-nums text-white">
                                <?php echo $m['score'] ?: '0-0'; ?>
                            </div>
                            <div class="text-[9px] font-black uppercase text-slate-500 mt-1">
                                <?php echo $m['status_label'] ?? 'LIVE'; ?>
                            </div>
                        </div>

                        <div class="flex items-center gap-4 flex-1 justify-start cursor-pointer hover:opacity-80 transition-opacity"
                            <?php if ($m['away_id'] ?? null): ?> hx-get="/api/gianik/team-details?teamId=
                    <?php echo $m['away_id']; ?>&leagueId=
                    <?php echo $m['league_id'] ?? ''; ?>&season=
                    <?php echo $m['season'] ?? ''; ?>" hx-target="#global-modal-container"
                            <?php endif; ?>>
                            <?php if ($m['away_logo'] ?? null): ?>
                                <img src="<?php echo $m['away_logo']; ?>"
                                    class="w-10 h-10 object-contain rounded-full bg-white/5 p-1 border border-white/5" alt="">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-white/5 flex items-center justify-center shrink-0"><i
                                        data-lucide="shield" class="w-5 h-5 text-slate-600"></i></div>
                            <?php endif; ?>
                            <span class="text-xs font-black uppercase text-white truncate max-w-[150px]">
                                <?php echo $m['away_name'] ?? 'Unknown'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 border-l border-white/5 pl-4 ml-2">
                        <button hx-get="/api/gianik/analyze?marketId=<?php echo $marketId; ?>"
                            hx-target="#global-modal-container"
                            class="px-3 py-2 bg-accent/10 hover:bg-accent/20 text-accent rounded-xl text-[9px] font-black uppercase transition-all flex items-center gap-1.5 border border-accent/10">
                            <i data-lucide="brain-circuit" class="w-3.5 h-3.5"></i> ANALISI
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($hasMore): ?>
            <div id="gianik-lazy-sentinel" hx-get="/api/gianik/live?offset=<?php echo $offset + $limit; ?>"
                hx-trigger="revealed" hx-swap="afterend" class="flex items-center justify-center p-8 opacity-50">
                <div class="flex items-center gap-3">
                    <i data-lucide="loader-2" class="w-5 h-5 animate-spin text-accent"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Altro...</span>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($offset === 0 && !empty($allMatches)): ?>
    </div>
<?php endif; ?>

<script>
    if (window.lucide) lucide.createIcons();
</script>