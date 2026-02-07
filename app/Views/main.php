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
                <i data-lucide="layout-dashboard" class="text-white w-6 h-6"></i>
            </div>
            <div class="text-2xl font-black tracking-tighter uppercase italic">
                Scommetto<span class="text-accent">.AI</span>
            </div>
        </div>

        <nav class="flex-1 px-4 py-4 space-y-2">
            <a href="#dashboard"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-2xl transition-all hover:bg-white/5 font-bold text-sm active-nav"
                data-view="dashboard">
                <i data-lucide="home" class="w-5 h-5"></i> Dashboard
            </a>
            <a href="#leagues"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-2xl transition-all hover:bg-white/5 font-bold text-sm"
                data-view="leagues">
                <i data-lucide="trophy" class="w-5 h-5"></i> Competizioni
            </a>
            <a href="#predictions"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-2xl transition-all hover:bg-white/5 font-bold text-sm"
                data-view="predictions">
                <i data-lucide="brain-circuit" class="w-5 h-5"></i> Pronostici
            </a>
            <a href="#tracker"
                class="nav-link flex items-center gap-3 px-4 py-3 rounded-2xl transition-all hover:bg-white/5 font-bold text-sm"
                data-view="tracker">
                <i data-lucide="line-chart" class="w-5 h-5"></i> Tracker
            </a>
        </nav>

        <div class="p-4 border-t border-white/10">
            <div class="flex flex-col gap-1 p-4 rounded-2xl bg-white/5 border border-white/5">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">API Status</span>
                <div class="text-sm font-black"><span id="usage-val" class="text-accent">...</span> / <span
                        id="limit-val">75000</span></div>
            </div>
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
                <!-- Selectors -->
                <div class="flex items-center gap-2 mr-4">
                    <!-- Country Selector -->
                    <button id="country-selector" onclick="openCountryModal()"
                        class="h-10 px-4 rounded-xl bg-white/5 hover:bg-white/10 flex items-center gap-2 transition-all border border-white/5 group">
                        <span id="selected-country-flag" class="text-lg">🇮🇹</span>
                        <span id="selected-country-name"
                            class="text-[10px] font-black uppercase tracking-tighter text-slate-400 group-hover:text-white transition-colors">Italy</span>
                        <i data-lucide="chevron-down" class="w-3 h-3 text-slate-500"></i>
                    </button>

                    <!-- Bookmaker Selector -->
                    <button id="bookmaker-selector" onclick="openBookmakerModal()"
                        class="h-10 px-4 rounded-xl bg-white/5 hover:bg-white/10 flex items-center gap-2 transition-all border border-white/5 group">
                        <i data-lucide="landmark" class="w-4 h-4 text-accent"></i>
                        <span id="selected-bookmaker-name"
                            class="text-[10px] font-black uppercase tracking-tighter text-slate-400 group-hover:text-white transition-colors">Tutti
                            i Book</span>
                        <i data-lucide="chevron-down" class="w-3 h-3 text-slate-500"></i>
                    </button>
                </div>

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
            <!-- Loader -->
            <div id="view-loader" class="flex items-center justify-center h-full py-20 hidden">
                <i data-lucide="loader-2" class="w-10 h-10 text-accent rotator"></i>
            </div>
            <!-- Dynamic Content -->
            <div id="view-container"></div>
        </main>
    </div>

    <!-- Bottom Navigation Mobile -->
    <nav
        class="lg:hidden fixed bottom-0 left-0 right-0 glass border-t border-white/10 px-6 py-3 flex justify-between items-center z-50">
        <a href="#dashboard" class="flex flex-col items-center gap-1 text-accent" data-view="dashboard">
            <i data-lucide="home" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Home</span>
        </a>
        <a href="#leagues" class="flex flex-col items-center gap-1 text-slate-500" data-view="leagues">
            <i data-lucide="trophy" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Leghe</span>
        </a>
        <a href="#predictions" class="flex flex-col items-center gap-1 text-slate-500" data-view="predictions">
            <i data-lucide="brain-circuit" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">AI Tips</span>
        </a>
        <a href="#tracker" class="flex flex-col items-center gap-1 text-slate-500" data-view="tracker">
            <i data-lucide="line-chart" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Tracker</span>
        </a>
    </nav>

    <!-- Analysis Modal (Global) -->
    <div id="analysis-modal"
        class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl">
        <div
            class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative">
            <button onclick="closeModal()"
                class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <div class="p-10">
                <h3 id="modal-title" class="text-3xl font-black mb-8 tracking-tight text-white uppercase italic">
                    Scommetto<span class="text-accent">.AI</span> Intelligence</h3>
                <div id="modal-body" class="mb-10 text-slate-400 leading-relaxed font-medium text-sm">
                    Auto-Generating intelligence...
                </div>
                <div class="flex flex-col sm:flex-row justify-between items-center gap-6">
                    <div id="confidence-indicator"
                        class="hidden flex items-center gap-3 bg-accent/10 px-5 py-3 rounded-2xl border border-accent/20 w-full sm:w-auto">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">AI
                                Confidence</span>
                            <span id="confidence-val" class="text-lg font-black text-accent leading-none">90%</span>
                        </div>
                        <div class="flex-1 h-2 bg-white/10 rounded-full overflow-hidden min-w-[80px]">
                            <div id="confidence-bar" class="h-full bg-accent transition-all duration-1000"
                                style="width: 90%"></div>
                        </div>
                    </div>
                    <div id="modal-footer-actions" class="flex items-center gap-4 w-full sm:w-auto justify-end">
                        <button onclick="closeModal()"
                            class="px-8 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest text-slate-500 hover:text-white transition-colors">Chiudi</button>
                        <button id="place-bet-btn"
                            class="hidden bg-accent hover:bg-sky-500 text-white px-10 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest transition-all shadow-xl shadow-accent/30 hover:scale-105">Conferma
                            Giocata</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Country Selection Modal -->
    <div id="country-modal"
        class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl">
        <div
            class="bg-slate-900 w-full max-w-4xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative max-h-[90vh] flex flex-col">
            <div class="p-8 border-b border-white/5 flex justify-between items-center">
                <h3 class="text-2xl font-black tracking-tight text-white uppercase italic">Seleziona Nazione</h3>
                <button onclick="closeCountryModal()"
                    class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="country-list"
                class="p-8 overflow-y-auto grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <!-- Data injected by JS -->
            </div>
        </div>
    </div>

    <!-- Bookmaker Selection Modal -->
    <div id="bookmaker-modal"
        class="hidden fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl">
        <div
            class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative max-h-[90vh] flex flex-col">
            <div class="p-8 border-b border-white/5 flex justify-between items-center">
                <h3 class="text-2xl font-black tracking-tight text-white uppercase italic">Filtra per Bookmaker</h3>
                <button onclick="closeBookmakerModal()"
                    class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div id="bookmaker-list" class="p-8 overflow-y-auto space-y-3">
                <!-- Data injected by JS -->
            </div>
        </div>
    </div>

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
    </style>

    <script src="/assets/js/app.js?v=<?php echo time(); ?>"></script>
    <script>
        // Init Lucide
        lucide.createIcons();
    </script>
</body>

</html>