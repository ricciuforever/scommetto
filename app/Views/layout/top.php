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
    <link rel="icon" href="https://cdn.jsdelivr.net/gh/GiaNik/assets/favicon.ico" type="image/x-icon">

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
    <script src="https://unpkg.com/alpinejs" defer></script>

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

        .htmx-indicator {
            opacity: 0;
            transition: opacity 200ms ease-in;
            pointer-events: none;
        }

        .htmx-request .htmx-indicator,
        .htmx-request.htmx-indicator {
            opacity: 1;
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
    class="bg-darkbg text-slate-100 min-h-screen font-sans flex flex-col lg:flex-row">

    <div class="fixed inset-0 pointer-events-none overflow-hidden -z-10">
        <div class="absolute -top-[10%] -right-[10%] w-[40%] h-[40%] bg-accent/10 blur-[120px] rounded-full"></div>
        <div class="absolute -bottom-[10%] -left-[10%] w-[40%] h-[40%] bg-indigo-500/10 blur-[120px] rounded-full">
        </div>
    </div>

    <!-- Sidebar Desktop -->
    <aside id="sidebar" class="hidden lg:flex flex-col w-64 h-screen sticky top-0 border-r border-white/10 glass z-50 transition-all duration-300">
        <div class="p-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-accent rounded-xl flex items-center justify-center shadow-lg shadow-accent/20">
                    <i data-lucide="zap" class="text-white w-6 h-6"></i>
                </div>
                <div class="text-2xl font-black tracking-tighter uppercase italic">
                    Scommetto<span class="text-accent">.AI</span>
                </div>
            </div>
            <button onclick="toggleMobileMenu()" class="lg:hidden text-white">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>
        </div>

        <div class="px-4 py-2 flex-1 overflow-y-auto no-scrollbar">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-4 px-4">Menu Principale</span>
            <nav class="space-y-1">
                <a href="/"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/5 transition-all group <?= $_SERVER['REQUEST_URI'] === '/' ? 'border-l-2 border-indigo-500/50 bg-indigo-500/5' : '' ?>">
                    <i data-lucide="home" class="w-4 h-4 text-indigo-400 transition-colors"></i>
                    <span class="text-xs font-black text-white transition-colors uppercase italic tracking-tighter leading-none">Home</span>
                </a>
                <a href="/gianik-live"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/5 transition-all group <?= strpos($_SERVER['REQUEST_URI'], 'gianik-live') !== false ? 'border-l-2 border-accent/50 bg-accent/5' : '' ?>">
                    <i data-lucide="zap" class="w-4 h-4 text-accent transition-colors"></i>
                    <span class="text-xs font-black text-white transition-colors uppercase italic tracking-tighter leading-none">Gianik Live</span>
                </a>
                <a href="/gianik/brain"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/5 transition-all group <?= strpos($_SERVER['REQUEST_URI'], 'gianik/brain') !== false ? 'border-l-2 border-purple-500/50 bg-purple-500/5' : '' ?>">
                    <i data-lucide="brain" class="w-4 h-4 text-purple-400 transition-colors"></i>
                    <span class="text-xs font-black text-white transition-colors uppercase italic tracking-tighter leading-none">Performance Gianik</span>
                </a>
                <a href="/dio"
                    class="flex items-center gap-3 px-4 py-2.5 rounded-xl hover:bg-white/5 transition-all group <?= strpos($_SERVER['REQUEST_URI'], 'dio') !== false ? 'border-l-2 border-indigo-500/50 bg-indigo-500/5' : '' ?>">
                    <i data-lucide="eye" class="w-4 h-4 text-indigo-400 transition-colors"></i>
                    <span class="text-xs font-black text-white transition-colors uppercase italic tracking-tighter leading-none">Performance Dio</span>
                </a>
            </nav>
        </div>

        <div class="p-4 border-t border-white/10">
            <a href="/admin"
                class="w-full py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white text-[10px] font-black uppercase tracking-widest transition-all border border-white/5 flex items-center justify-center gap-2">
                <i data-lucide="shield-check" class="w-4 h-4"></i> Admin Panel
            </a>
        </div>
    </aside>

    <!-- Mobile Header -->
    <header class="lg:hidden sticky top-0 z-40 glass border-b border-white/10 px-6 py-4 flex justify-between items-center shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-accent rounded-lg flex items-center justify-center shadow-lg shadow-accent/20">
                <i data-lucide="zap" class="text-white w-5 h-5"></i>
            </div>
            <div class="text-xl font-black tracking-tighter uppercase italic">
                Scommetto<span class="text-accent">.AI</span>
            </div>
        </div>
        <div class="flex items-center gap-4">
            <button onclick="toggleMobileMenu()" class="text-white">
                <i data-lucide="menu" class="w-6 h-6"></i>
            </button>
        </div>
    </header>

    <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
        <!-- Header Desktop (Solo desktop) -->
        <header class="hidden lg:flex sticky top-0 z-40 glass border-b border-white/10 px-8 py-4 justify-between items-center shrink-0">
            <div class="flex items-center gap-8 ml-4">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">
                    SISTEMA MVCS <span class="text-accent">V4.0</span>
                </span>
            </div>
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-accent/10 border border-accent/20 flex items-center justify-center relative">
                    <i data-lucide="bell" class="w-5 h-5 text-accent"></i>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-danger rounded-full ring-2 ring-darkbg animate-pulse"></span>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main id="main-content" class="flex-1 p-4 lg:p-8 max-w-7xl mx-auto w-full overflow-y-auto no-scrollbar">

        <script>
            function toggleMobileMenu() {
                const sidebar = document.getElementById('sidebar');
                if (sidebar.classList.contains('hidden')) {
                    sidebar.classList.remove('hidden');
                    sidebar.classList.add('fixed', 'inset-0', 'w-full', 'h-full');
                } else {
                    sidebar.classList.add('hidden');
                    sidebar.classList.remove('fixed', 'inset-0', 'w-full', 'h-full');
                }
            }
        </script>