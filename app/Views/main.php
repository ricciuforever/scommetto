<!DOCTYPE html>
<html lang="it" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scommetto PRO - Live Intelligence</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="icon" href="data:,">
    <script src="https://cdn.tailwindcss.com?minify=true"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        darkbg: '#0f172a',
                        accent: '#38bdf8',
                        success: '#10b981',
                        danger: '#ef4444',
                        warning: '#f59e0b',
                    },
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body
    class="bg-slate-50 dark:bg-darkbg text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-300 font-sans flex">

    <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
        <div class="absolute -top-[10%] -right-[10%] w-[40%] h-[40%] bg-accent/10 blur-[120px] rounded-full"></div>
        <div class="absolute -bottom-[10%] -left-[10%] w-[40%] h-[40%] bg-indigo-500/10 blur-[120px] rounded-full">
        </div>
    </div>

    <!-- Sidebar Desktop -->
    <aside class="hidden lg:flex flex-col w-64 h-screen sticky top-0 border-r border-white/10 glass z-50">
        <div class="p-6 flex items-center gap-3">
            <div class="w-10 h-10 bg-accent rounded-xl flex items-center justify-center shadow-lg shadow-accent/20">
                <i data-lucide="zap" class="text-white w-6 h-6"></i>
            </div>
            <div class="text-2xl font-black tracking-tighter uppercase italic">
                Scommetto<span class="text-accent">.AI</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-4 space-y-2">
            <a href="/dashboard"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-2xl transition-all hover:bg-white/5 font-bold text-sm active-nav"
                data-view="dashboard">
                <i data-lucide="home" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="/predictions"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-2xl transition-all hover:bg-white/5 font-bold text-sm"
                data-view="predictions">
                <i data-lucide="brain-circuit" class="w-5 h-5"></i> Pronostici
            </a>
            <a href="/tracker"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-2xl transition-all hover:bg-white/5 font-bold text-sm"
                data-view="tracker">
                <i data-lucide="landmark" class="w-5 h-5"></i> Betfair Account
            </a>
        </nav>

        <div class="p-4 border-t border-white/10 space-y-4">
            <div class="flex flex-col gap-1 p-4 rounded-2xl bg-white/5 border border-white/5">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Betfair Status</span>
                <div class="text-sm font-black"><span class="text-success">CONNECTED</span></div>
            </div>

            <!-- Modalità e Reset nascosti perché richiedono Auth -->
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5" id="settings-status-card">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-1">Modalità
                    Attuale</span>
                <?php
                $stmSettings = json_decode(file_get_contents(\App\Config\Config::DATA_PATH . 'settings.json') ?: '{"simulation_mode":true}', true);
                $isSim = $stmSettings['simulation_mode'] ?? true;
                ?>
                <div id="current-mode-display"
                    class="text-xs font-black uppercase italic <?php echo $isSim ? 'text-accent' : 'text-danger animate-pulse'; ?> transition-colors">
                    <?php echo $isSim ? 'SCOMMESSE SIMULATE' : 'DENARO REALE ATTIVO'; ?>
                </div>
            </div>

            <a href="/settings"
                class="w-full py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-[10px] font-black uppercase tracking-widest transition-all border border-white/5 flex items-center justify-center gap-2 cursor-pointer">
                <i data-lucide="settings" class="w-4 h-4"></i> Admin Panel
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header Mobile / Top Bar -->
        <header
            class="sticky top-0 z-40 glass border-b border-white/10 px-6 py-4 flex justify-between items-center lg:mb-0">
            <div class="flex lg:hidden items-center gap-3">
                <div class="text-xl font-black tracking-tighter uppercase italic">
                    Scommetto<span class="text-accent">.AI</span>
                </div>
            </div>
            <div class="hidden lg:block">
                <!-- Breadcrumbs or Search could go here -->
                <div id="view-title"
                    class="text-xl font-black tracking-tight uppercase tracking-widest text-slate-500 text-[10px]">
                    Dashboard</div>
            </div>
            <div class="flex items-center gap-4">

                <button id="theme-toggle"
                    class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all border border-white/5">
                    <i data-lucide="sun" class="hidden dark:block w-5 h-5 text-yellow-400"></i>
                    <i data-lucide="moon" class="block dark:hidden w-5 h-5 text-slate-600"></i>
                </button>
                <div
                    class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center relative">
                    <i data-lucide="bell" class="w-5 h-5 text-accent"></i>
                    <span
                        class="absolute top-2 right-2 w-2 h-2 bg-danger rounded-full ring-2 ring-darkbg animate-pulse"></span>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main id="main-content" class="flex-1 p-6 pb-24 lg:pb-6 max-w-7xl mx-auto w-full">
            <!-- Multi-Sport Selectors -->
            <div id="sport-selectors" class="flex gap-3 mb-8 overflow-x-auto no-scrollbar pb-2">
                <!-- Dynamically populated -->
            </div>

            <!-- Loader (only used for non-HTMX transitions) -->
            <div id="view-loader" class="flex items-center justify-center h-full py-20 hidden">
                <i data-lucide="loader-2" class="w-10 h-10 text-accent rotator"></i>
            </div>

            <!-- HTMX Container for Dynamic Content -->
            <?php
            $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $initialApi = '/api/view/dashboard';
            $initialTitle = 'Dashboard Intelligence';

            if (strpos($currentPath, '/leagues') !== false) {
                $initialApi = '/api/view/leagues';
                $initialTitle = 'Competizioni';
            } elseif (strpos($currentPath, '/predictions') !== false) {
                $initialApi = '/api/view/predictions';
                $initialTitle = 'Pronostici AI';
            } elseif (strpos($currentPath, '/tracker') !== false) {
                $initialApi = '/api/view/tracker';
                $initialTitle = 'Tracker Scommesse';
            } elseif (preg_match('#^/match/(\d+)$#', $currentPath, $m)) {
                $initialApi = "/api/view/match/{$m[1]}";
                $initialTitle = 'Analisi Match';
            } elseif (preg_match('#^/team/(\d+)$#', $currentPath, $m)) {
                $initialApi = "/api/view/team/{$m[1]}";
                $initialTitle = 'Profilo Squadra';
            } elseif (preg_match('#^/player/(\d+)$#', $currentPath, $m)) {
                $initialApi = "/api/view/player/{$m[1]}";
                $initialTitle = 'Dettagli Giocatore';
            }
            ?>
            <script>document.getElementById('view-title').textContent = '<?php echo $initialTitle; ?>';</script>

            <div id="htmx-container" hx-get="<?php echo $initialApi; ?>" hx-trigger="load" hx-target="#htmx-container"
                hx-swap="innerHTML">
                <!-- Initial placeholder while loading -->
                <div class="flex items-center justify-center py-20">
                    <i data-lucide="loader-2" class="w-10 h-10 text-accent rotator"></i>
                </div>
            </div>

            <!-- Legacy Container for non-dashboard views (Leagues, Tracker, etc.) -->
            <div id="view-container" class="hidden"></div>
        </main>
    </div>

    <!-- Bottom Navigation Mobile -->
    <nav
        class="lg:hidden fixed bottom-0 left-0 right-0 glass border-t border-white/10 px-6 py-3 flex justify-between items-center z-50">
        <a href="/dashboard" class="flex flex-col items-center gap-1 text-accent" data-view="dashboard">
            <i data-lucide="home" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Home</span>
        </a>
        <a href="/leagues" class="flex flex-col items-center gap-1 text-slate-500" data-view="leagues">
            <i data-lucide="trophy" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Leghe</span>
        </a>
        <a href="/predictions" class="flex flex-col items-center gap-1 text-slate-500" data-view="predictions">
            <i data-lucide="brain-circuit" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">AI Tips</span>
        </a>
        <a href="/tracker" class="flex flex-col items-center gap-1 text-slate-500" data-view="tracker">
            <i data-lucide="line-chart" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Tracker</span>
        </a>
    </nav>

    <!-- Global Modal Container -->
    <div id="global-modal-container"></div>





    <style>
        .active-nav {
            @apply bg-accent text-white shadow-lg shadow-accent/20 !important;
        }

        .nav-link:hover:not(.active-nav) {
            @apply translate-x-1;
        }

        .glass {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
        }

        /* Custom Dashboard Selects */
        .dash-filter-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%2338bdf8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1rem;
            padding-right: 2.5rem;
        }

        .pinned-match {
            @apply ring-2 ring-accent shadow-2xl shadow-accent/20 scale-[1.01];
            animation: pulse-ring 2s infinite;
        }

        @keyframes pulse-ring {
            0% {
                box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(56, 189, 248, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(56, 189, 248, 0);
            }
        }
    </style>

    <script>
        // Minimal JS for Theme Toggle & basic UI
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;

        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
            });
        }

        // Re-init Icons on HTMX swap
        document.body.addEventListener('htmx:afterSwap', function (evt) {
            if (window.lucide) lucide.createIcons();
        });

        // Initial Icons
        if (window.lucide) lucide.createIcons();
    </script>
</body>

</html>