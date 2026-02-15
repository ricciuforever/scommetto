<?php
$user = $_SESSION['admin_user'];
?>
<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Scommetto - Admin War Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background-color: #0f172a; color: #cbd5e0; font-family: 'Courier New', Courier, monospace; }
        .input-dark { background: #1e293b; border: 1px solid #334155; color: white; padding: 4px 8px; border-radius: 4px; }
        .glass { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.05); }
        .sidebar-link.active { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border-left: 2px solid #3b82f6; }
    </style>
</head>
<body class="h-screen flex overflow-hidden">

<!-- Sidebar Admin -->
<aside class="w-64 bg-gray-900 border-r border-gray-800 flex flex-col shrink-0">
    <div class="p-6 border-b border-gray-800">
        <h1 class="text-xl font-bold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-green-400">
            🛡️ WAR ROOM <span class="text-[10px] text-gray-500 font-normal">v3.0</span>
        </h1>
        <p class="text-[9px] text-gray-500 uppercase font-bold mt-1">Logged as: <?= htmlspecialchars($user['username']) ?></p>
    </div>

    <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
        <a href="/admin/dashboard" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition-all <?= strpos($_SERVER['REQUEST_URI'], '/dashboard') !== false || $_SERVER['REQUEST_URI'] === '/admin' ? 'active' : '' ?>">
            <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
            <span class="text-xs font-bold uppercase tracking-widest">Dashboard</span>
        </a>
        <a href="/admin/war-room" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition-all <?= strpos($_SERVER['REQUEST_URI'], '/war-room') !== false ? 'active' : '' ?>">
            <i data-lucide="database" class="w-4 h-4"></i>
            <span class="text-xs font-bold uppercase tracking-widest">War Room</span>
        </a>
        <a href="/admin/strategy" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition-all <?= strpos($_SERVER['REQUEST_URI'], '/strategy') !== false ? 'active' : '' ?>">
            <i data-lucide="brain-circuit" class="w-4 h-4"></i>
            <span class="text-xs font-bold uppercase tracking-widest">Strategy Lab</span>
        </a>
        <?php if ($user['role'] === 'admin'): ?>
        <a href="/admin/users" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/5 transition-all <?= strpos($_SERVER['REQUEST_URI'], '/users') !== false ? 'active' : '' ?>">
            <i data-lucide="users" class="w-4 h-4"></i>
            <span class="text-xs font-bold uppercase tracking-widest">Gestione Utenti</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="p-4 border-t border-gray-800 space-y-2">
        <a href="/" class="flex items-center gap-3 px-4 py-2 text-xs text-gray-500 hover:text-white transition-all">
            <i data-lucide="external-link" class="w-3 h-3"></i> Torna al Sito
        </a>
        <a href="/admin/logout" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-900/10 hover:bg-red-900/20 text-red-400 text-xs font-bold uppercase tracking-widest transition-all border border-red-900/20">
            <i data-lucide="log-out" class="w-4 h-4"></i> Logout
        </a>
    </div>
</aside>

<main class="flex-1 flex flex-col overflow-hidden">
    <header class="h-16 bg-gray-900/50 border-b border-gray-800 flex items-center justify-between px-8 shrink-0">
        <div class="flex items-center gap-4">
            <h2 class="text-sm font-bold uppercase tracking-widest text-gray-400">
                <?= strtoupper(str_replace(['/admin/', '/admin'], '', $_SERVER['REQUEST_URI']) ?: 'DASHBOARD') ?>
            </h2>
        </div>
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2">
                <span class="text-[10px] text-gray-500 uppercase font-bold">Status:</span>
                <span class="flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-[10px] text-green-500 font-bold uppercase">System Active</span>
                </span>
            </div>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto p-8">
