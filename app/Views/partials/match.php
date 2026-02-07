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
        <button onclick="navigate('dashboard')"
            class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </button>
        <div>
            <h2 class="text-2xl font-black tracking-tight text-white uppercase italic">Match Center</h2>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-1">Live Intelligence & Analytics
            </p>
        </div>
    </div>

    <div class="glass rounded-[40px] border-white/10 shadow-2xl overflow-hidden relative mb-8">
        <div class="p-8 md:p-12">
            <div class="flex flex-col md:flex-row items-center justify-between gap-12">
                <!-- Home -->
                <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group"
                    onclick="navigate('team', '<?php echo $f['team_home_id']; ?>')">
                    <div
                        class="w-24 h-24 p-4 glass rounded-3xl flex items-center justify-center group-hover:border-accent/50 transition-all shadow-2xl">
                        <img src="<?php echo $f['team_home_logo']; ?>"
                            class="w-full h-full object-contain group-hover:scale-110 transition-transform">
                    </div>
                    <div class="text-center">
                        <h3 class="text-xl font-black text-white uppercase tracking-tight mb-1">
                            <?php echo $f['team_home_name']; ?>
                        </h3>
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Casa</span>
                    </div>
                </div>

                <!-- Match Info -->
                <div class="flex flex-col items-center gap-6">
                    <div class="flex flex-col items-center">
                        <div
                            class="text-[10px] font-black text-accent uppercase tracking-[0.2em] mb-4 bg-accent/10 px-4 py-1.5 rounded-full border border-accent/20">
                            <?php echo $f['status_long']; ?>
                        </div>
                        <div class="flex items-center gap-8">
                            <span class="text-6xl md:text-8xl font-black italic tracking-tighter text-white">
                                <?php echo $f['score_home'] ?? 0; ?>
                                <span class="text-accent opacity-50 px-2">-</span>
                                <?php echo $f['score_away'] ?? 0; ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col items-center gap-2">
                        <span
                            class="text-sm font-black text-white px-4 py-2 bg-white/5 rounded-xl border border-white/10 backdrop-blur-sm">
                            <?php echo $f['elapsed']; ?>'
                        </span>
                    </div>
                </div>

                <!-- Away -->
                <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group"
                    onclick="navigate('team', '<?php echo $f['team_away_id']; ?>')">
                    <div
                        class="w-24 h-24 p-4 glass rounded-3xl flex items-center justify-center group-hover:border-accent/50 transition-all shadow-2xl">
                        <img src="<?php echo $f['team_away_logo']; ?>"
                            class="w-full h-full object-contain group-hover:scale-110 transition-transform">
                    </div>
                    <div class="text-center">
                        <h3 class="text-xl font-black text-white uppercase tracking-tight mb-1">
                            <?php echo $f['team_away_name']; ?></h3>
                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Ospiti</span>
                    </div>
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
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 animate-pulse">Syncing
                    Match
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