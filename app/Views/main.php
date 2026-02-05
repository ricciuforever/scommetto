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
    <script src="https://cdn.tailwindcss.com"></script>
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
    class="bg-slate-50 dark:bg-darkbg text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-300 font-sans">

    <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
        <div class="absolute -top-[10%] -right-[10%] w-[40%] h-[40%] bg-accent/10 blur-[120px] rounded-full"></div>
        <div class="absolute -bottom-[10%] -left-[10%] w-[40%] h-[40%] bg-indigo-500/10 blur-[120px] rounded-full">
        </div>
    </div>

    <header
        class="sticky top-0 z-50 glass border-b border-white/10 px-6 py-4 flex justify-between items-center mb-8 text-white">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-accent rounded-xl flex items-center justify-center shadow-lg shadow-accent/20">
                <i data-lucide="layout-dashboard" class="text-white w-6 h-6"></i>
            </div>
            <div class="text-2xl font-black tracking-tighter uppercase italic">
                Scommetto<span class="text-accent">.AI</span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <div class="hidden md:flex flex-col items-end">
                <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">API Status</span>
                <div class="text-sm font-black"><span id="usage-val" class="text-accent">...</span> / 7500 <span
                        class="text-slate-500">Credits</span></div>
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

    <main class="max-w-7xl mx-auto p-6">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-10">
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i data-lucide="wallet" class="w-10 h-10"></i>
                </div>
                <span class="block text-2xl font-black mb-1" id="portfolio-val">100€</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Portafoglio</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i data-lucide="swords" class="w-10 h-10"></i>
                </div>
                <span class="block text-2xl font-black mb-1" id="win-loss-count">0W - 0L</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Performance</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i data-lucide="trending-up" class="w-10 h-10"></i>
                </div>
                <span class="block text-2xl font-black mb-1" id="profit-val">+0.00€</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Profitto Netto</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i data-lucide="radio" class="w-10 h-10 text-danger"></i>
                </div>
                <span class="block text-2xl font-black mb-1" id="active-matches-count">0</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Live Now</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity">
                    <i data-lucide="timer" class="w-10 h-10 text-warning"></i>
                </div>
                <span class="block text-2xl font-black mb-1" id="pending-bets-count">0</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">In Sospeso</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <div
                    class="absolute top-0 right-0 p-3 opacity-10 group-hover:opacity-20 transition-opacity text-success">
                    <i data-lucide="bar-chart-3" class="w-10 h-10"></i>
                </div>
                <span class="block text-2xl font-black mb-1 text-success" id="roi-val">0%</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">ROI Stimato</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-10">
            <div class="lg:col-span-3">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
                    <h2 class="text-2xl font-black tracking-tight flex items-center gap-3">
                        <span class="w-2 h-8 bg-accent rounded-full"></span>
                        Eventi in Diretta
                    </h2>
                    <div id="league-filters" class="flex flex-wrap gap-2">
                    </div>
                </div>
                <div id="live-matches-container" class="space-y-6">
                </div>
            </div>
            <aside class="space-y-8">
                <div>
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-black tracking-tight">Attività Recente</h2>
                        <button id="sync-btn"
                            class="text-accent hover:text-white hover:bg-accent p-2 rounded-xl transition-all border border-accent/20">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="glass rounded-[32px] border-white/5 divide-y divide-white/5 overflow-hidden">
                        <div id="history-container"></div>
                        <div class="p-6">
                            <button id="deep-sync-btn"
                                class="w-full bg-slate-100 dark:bg-white/5 hover:bg-accent/10 hover:text-accent p-4 rounded-[20px] transition-all border border-transparent hover:border-accent/20 font-black text-[10px] uppercase tracking-widest flex items-center justify-center gap-3">
                                <i data-lucide="database" class="w-4 h-4 text-accent"></i>
                                Deep Sync Intelligence
                            </button>
                        </div>
                    </div>
                </div>
                <div
                    class="bg-gradient-to-br from-indigo-600 to-accent p-6 rounded-[32px] text-white shadow-xl shadow-indigo-500/20">
                    <div
                        class="w-12 h-12 bg-white/20 rounded-2xl flex items-center justify-center mb-4 backdrop-blur-md">
                        <i data-lucide="zap" class="w-6 h-6 text-white"></i>
                    </div>
                    <h3 class="font-black text-lg mb-2 tracking-tight">Potenza AI</h3>
                    <p class="text-white/80 text-sm leading-relaxed mb-4 font-medium">L'algoritmo analizza migliaia di
                        dati per darti i consigli migliori in tempo reale.</p>
                    <button
                        class="w-full bg-white text-indigo-600 font-black py-3 rounded-2xl text-xs uppercase tracking-widest hover:scale-105 transition-transform">Scopri
                        di più</button>
                </div>
            </aside>
        </div>
    </main>

    <div id="analysis-modal"
        class="hidden fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl">
        <div
            class="bg-slate-900 w-full max-w-2xl rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative">
            <button onclick="closeModal()"
                class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
            <div class="p-10">
                <h3 id="modal-title" class="text-3xl font-black mb-8 tracking-tight text-white">Analisi Prossima Giocata
                </h3>
                <div id="modal-body" class="mb-10 text-slate-400 leading-relaxed font-medium">
                    Auto-Generating intelligence...
                </div>
                <div class="flex justify-end items-center gap-4">
                    <button onclick="closeModal()"
                        class="px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest text-slate-500 hover:text-white transition-colors">Chiudi</button>
                    <div id="confidence-indicator"
                        class="hidden flex items-center gap-3 bg-accent/10 px-5 py-3 rounded-2xl border border-accent/20">
                        <div class="flex flex-col">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">AI
                                Confidence</span>
                            <span id="confidence-val" class="text-lg font-black text-accent leading-none">90%</span>
                        </div>
                        <div class="flex-1 h-2 bg-white/10 rounded-full overflow-hidden">
                            <div id="confidence-bar" class="h-full bg-accent transition-all duration-1000"
                                style="width: 90%"></div>
                        </div>
                    </div>
                    <button id="place-bet-btn"
                        class="hidden bg-accent hover:bg-sky-500 text-white px-10 py-4 rounded-2xl font-black text-xs uppercase tracking-widest transition-all shadow-xl shadow-accent/30 hover:scale-105">Conferma
                        Giocata</button>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/app.js"></script>
</body>

</html>