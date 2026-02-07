<?php
// app/Views/partials/match.php
// HTMX Partial for Match Center

if (isset($error)) {
    echo '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">' . htmlspecialchars($error) . '</div>';
    return;
}

$f = $fixture;
$dateStr = date('D d M H:i', strtotime($f['date']));
$statusClass = $f['status_short'] === 'NS' ? 'bg-slate-500' : 'bg-danger animate-pulse';
?>

<div id="match-center-view" class="animate-fade-in">
    <!-- Header Back + Title -->
    <div class="mb-8 flex items-center gap-4">
        <button onclick="window.location.hash = 'dashboard'"
            class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </button>
        <div class="flex flex-col">
            <h2 class="text-2xl font-black italic uppercase tracking-tight leading-none">
                <?php echo htmlspecialchars($f['team_home_name']); ?> VS
                <?php echo htmlspecialchars($f['team_away_name']); ?>
            </h2>
            <span class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em]">
                <?php echo htmlspecialchars($f['league_name']); ?> |
                <?php echo $dateStr; ?>
            </span>
        </div>
    </div>

    <!-- Match Scoreboard -->
    <div class="glass rounded-[48px] border-white/5 overflow-hidden mb-10">
        <div
            class="p-10 md:p-16 flex flex-col md:flex-row items-center justify-between gap-12 bg-gradient-to-br from-accent/5 to-transparent">
            <!-- Home Team -->
            <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group"
                onclick="window.location.hash = 'team/<?php echo $f['team_home_id']; ?>'">
                <div
                    class="w-24 h-24 md:w-32 md:h-32 glass rounded-[48px] p-6 flex items-center justify-center group-hover:scale-110 transition-transform border-white/10">
                    <img src="<?php echo $f['team_home_logo']; ?>" class="w-full h-full object-contain">
                </div>
                <span
                    class="text-xl font-black uppercase italic tracking-tight text-center group-hover:text-accent transition-colors">
                    <?php echo htmlspecialchars($f['team_home_name']); ?>
                </span>
            </div>

            <!-- Score / Status -->
            <div class="flex flex-col items-center gap-4">
                <div
                    class="text-6xl md:text-8xl font-black tracking-tighter text-white tabular-nums flex items-center gap-4">
                    <?php echo $f['score_home'] ?? 0; ?>
                    <span class="text-accent/20 font-light">-</span>
                    <?php echo $f['score_away'] ?? 0; ?>
                </div>
                <div class="px-6 py-2 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full <?php echo $statusClass; ?>"></div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-300">
                        <?php echo $f['status_long']; ?>
                    </span>
                    <?php if ($f['elapsed']): ?>
                        <span class="text-[10px] font-black text-accent">
                            <?php echo $f['elapsed']; ?>'
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Away Team -->
            <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group"
                onclick="window.location.hash = 'team/<?php echo $f['team_away_id']; ?>'">
                <div
                    class="w-24 h-24 md:w-32 md:h-32 glass rounded-[48px] p-6 flex items-center justify-center group-hover:scale-110 transition-transform border-white/10">
                    <img src="<?php echo $f['team_away_logo']; ?>" class="w-full h-full object-contain">
                </div>
                <span
                    class="text-xl font-black uppercase italic tracking-tight text-center group-hover:text-accent transition-colors">
                    <?php echo htmlspecialchars($f['team_away_name']); ?>
                </span>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <nav class="flex border-t border-white/5 overflow-x-auto no-scrollbar" hx-target="#match-tab-content"
            hx-push-url="false">
            <?php
            $tabs = [
                'analysis' => 'Intelligence',
                'events' => 'Eventi',
                'info' => 'Info',
                'odds' => 'Quote',
                'lineups' => 'Formazioni',
                'stats' => 'Statistiche',
                'h2h' => 'Testa a Testa'
            ];
            foreach ($tabs as $key => $label):
                $activeClass = $key === 'analysis' ? 'text-accent border-b-2 border-accent' : 'text-slate-500';
                ?>
                <button hx-get="/api/view/match/<?php echo $f['id']; ?>/tab/<?php echo $key; ?>" hx-trigger="click"
                    hx-swap="innerHTML"
                    class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all <?php echo $activeClass; ?>"
                    onclick="switchMatchTabUI(this)">
                    <?php echo $label; ?>
                </button>
            <?php endforeach; ?>
        </nav>
    </div>

    <!-- Tab Content Area -->
    <div id="match-tab-content" class="min-h-[400px] transition-all"
        hx-get="/api/view/match/<?php echo $f['id']; ?>/tab/analysis" hx-trigger="load">
        <div class="flex flex-col items-center justify-center py-20 gap-4">
            <i data-lucide="loader-2" class="w-10 h-10 text-accent rotator"></i>
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 animate-pulse">Syncing Match
                Intelligence...</span>
        </div>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();

    function switchMatchTabUI(btn) {
        // UI Only: update active classes
        const nav = btn.parentElement;
        Array.from(nav.children).forEach(c => {
            c.classList.remove('text-accent', 'border-b-2', 'border-accent');
            c.classList.add('text-slate-500');
        });
        btn.classList.add('text-accent', 'border-b-2', 'border-accent');
        btn.classList.remove('text-slate-500');
    }
</script>