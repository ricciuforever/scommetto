<?php
// app/Views/layout/top.php
?>
<!DOCTYPE html>
<html lang="it" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Scommetto.AI'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">

    <!-- React & Babel CDN -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <!-- Tailwind CSS CDN -->
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
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .glass {
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(12px);
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .rotator {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }
    </style>
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

        <div class="px-4 py-2 flex-1 overflow-y-auto no-scrollbar">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-4 px-4">Menu
                Principale</span>
            <nav class="space-y-1">
                <a href="/"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/5 transition-all group">
                    <i data-lucide="home" class="w-4 h-4 text-slate-500 group-hover:text-accent transition-colors"></i>
                    <span class="text-xs font-bold text-slate-400 group-hover:text-white transition-colors">Home</span>
                </a>
                <a href="/gemini-bets"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/5 transition-all group">
                    <i data-lucide="history" class="w-4 h-4 text-slate-500 group-hover:text-accent transition-colors"></i>
                    <span class="text-xs font-bold text-slate-400 group-hover:text-white transition-colors">Giocate Gemini</span>
                </a>
                <a href="/gemini-predictions"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/5 transition-all group">
                    <i data-lucide="crystal-ball" class="w-4 h-4 text-slate-500 group-hover:text-accent transition-colors"></i>
                    <span class="text-xs font-bold text-slate-400 group-hover:text-white transition-colors">Pronostici 7gg</span>
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-white/10">
            <a href="/settings"
                class="w-full py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-[10px] font-black uppercase tracking-widest transition-all border border-white/5 flex items-center justify-center gap-2">
                <i data-lucide="settings" class="w-4 h-4"></i> Impostazioni
            </a>
        </div>
    </aside>

    <div class="flex-1 flex flex-col min-h-screen">
        <!-- Header / Top Bar -->
        <header class="sticky top-0 z-40 glass border-b border-white/10 px-6 py-4 flex justify-between items-center">
            <div class="flex lg:hidden items-center gap-3">
                <div class="text-xl font-black tracking-tighter uppercase italic">
                    Scommetto<span class="text-accent">.AI</span>
                </div>
            </div>
            <div class="hidden lg:flex items-center gap-8 ml-8">
                <span class="text-xs font-black uppercase tracking-widest text-slate-500">
                    SISTEMA MVCS <span class="text-accent">V4.0</span>
                </span>
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
        <main id="main-content" class="flex-1 p-6 pb-24 lg:pb-6 max-w-7xl mx-auto w-full overflow-y-auto">