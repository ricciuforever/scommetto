<?php
// app/GiaNik/Views/gianik_live_page.php
$pageTitle = "GiaNik Live - App within App";
// We still use the main layout for now as it provides React/Tailwind/HTMX
require __DIR__ . '/../../Views/layout/top.php';
?>
<link rel="icon" href="https://cdn.jsdelivr.net/gh/GiaNik/assets/favicon.ico" type="image/x-icon">

<style>
    main#main-content {
        max-width: 95% !important;
        padding-left: 2rem;
        padding-right: 2rem;
    }
</style>

<div class="flex flex-col gap-6 mr-72"> <!-- Added margin for sidebar -->
    <!-- Hidden auto-processing trigger -->
    <div hx-get="/api/gianik/auto-process" hx-trigger="every 120s" class="hidden"></div>

    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-2 h-2 bg-accent rounded-full animate-ping"></span>
                <span class="text-[8px] font-black uppercase text-accent tracking-[.2em]">Autonomous Agent Active</span>
            </div>
            <h1 class="text-4xl font-black italic uppercase tracking-tighter text-white leading-none">
                GiaNik Live <span class="text-accent">.</span>
            </h1>
            <p class="text-slate-500 text-xs font-bold uppercase tracking-widest mt-2">
                Betfair Multi-Sport Dashboard (Prossime 24 Ore) - SEZIONE INDIPENDENTE
            </p>
        </div>
        <div class="flex items-center gap-4">
            <!-- Mode Switcher -->
            <div class="flex bg-white/5 p-1 rounded-xl border border-white/5">
                <button onclick="setGiaNikMode('virtual')" id="mode-virtual"
                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition-all bg-accent text-white">Virtual</button>
                <button onclick="setGiaNikMode('real')" id="mode-real"
                    class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase transition-all text-slate-500">Real</button>
            </div>

            <div class="flex flex-col items-end">
                <span class="text-[10px] font-black uppercase text-slate-500">Auto-Refresh</span>
                <span class="text-xs font-bold text-success flex items-center gap-1.5">
                    <span class="w-2 h-2 bg-success rounded-full animate-pulse"></span>
                    ATTIVO (60s)
                </span>
            </div>

            <!-- Brain Dashboard Link -->
            <a href="/gianik/brain" title="GiaNik Brain"
                class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center border border-white/5 transition-all text-slate-400 hover:text-white">
                <i data-lucide="brain" class="w-5 h-5 text-purple-400"></i>
            </a>

            <!-- Sound Toggle -->
            <button onclick="toggleGiaNikSound()" id="sound-toggle"
                class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center border border-white/5 transition-all text-slate-400 hover:text-white">
                <i data-lucide="volume-2" id="sound-icon" class="w-5 h-5 text-success"></i>
            </button>

            <button hx-get="/api/gianik/live" hx-target="#gianik-live-container"
                class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center border border-white/5 transition-all text-slate-400 hover:text-white">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
            </button>
        </div>
    </div>

    <!-- Container per il caricamento asincrono -->
    <div id="gianik-live-container" hx-get="/api/gianik/live" hx-trigger="load, every 60s" class="min-h-[400px]">
        <div class="flex items-center justify-center p-20">
            <div class="flex flex-col items-center gap-4">
                <i data-lucide="loader-2" class="w-12 h-12 text-accent animate-spin"></i>
                <span class="text-xs font-black uppercase tracking-widest text-slate-500">Inizializzazione Dati
                    Betfair Diretti...</span>
            </div>
        </div>
    </div>
    <div id="global-modal-container"></div>
</div>

<!-- GiaNik Bets Sidebar -->
<aside class="fixed top-[73px] right-0 bottom-0 w-72 border-l border-white/10 glass z-30 flex flex-col">
    <div id="recent-bets-container" hx-get="/api/gianik/recent-bets" hx-trigger="load, every 60s"
        class="flex-1 overflow-hidden">
        <div class="flex items-center justify-center p-20">
            <i data-lucide="loader-2" class="w-6 h-6 text-slate-500 animate-spin"></i>
        </div>
    </div>
</aside>

<script src="/js/modals.js"></script>
<script>
    // Modal helpers
    async function openPlayerModal(playerId, fixtureId = null) {
        closeAllModals(); // Close other modals first if needed
        try {
            const url = fixtureId
                ? `/gianik/player-modal/${playerId}?fixture=${fixtureId}`
                : `/gianik/player-modal/${playerId}`;

            const response = await fetch(url);
            const html = await response.text();

            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);

            if (window.lucide) lucide.createIcons();
        } catch (error) {
            console.error('Error opening player modal:', error);
        }
    }

    async function openTeamModal(teamId) {
        try {
            const response = await fetch(`/gianik/team-modal/${teamId}`);
            const html = await response.text();

            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);

            if (window.lucide) lucide.createIcons();
        } catch (error) {
            console.error('Error opening team modal:', error);
        }
    }

    function closeAllModals() {
        document.querySelectorAll('[id$="-modal"]').forEach(modal => {
            modal.remove();
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    // GiaNik mode initial state
    window.gianikMode = 'virtual';

    // Auto-fetch mode on load
    async function initGiaNikMode() {
        try {
            const res = await fetch('/api/gianik/get-mode');
            const data = await res.json();
            if (data.status === 'success') {
                setGiaNikMode(data.mode, false); // false = don't save back to server
            }
        } catch (e) {
            console.error('Error fetching Gianik mode:', e);
        }
    }
    initGiaNikMode();

    window.openBetDetails = function (id) {
        htmx.ajax('GET', '/api/gianik/bet/' + id, {
            target: '#global-modal-container',
            swap: 'innerHTML'
        });
    }

    function setGiaNikMode(mode, saveToServer = true) {
        window.gianikMode = mode;
        const vBtn = document.getElementById('mode-virtual');
        const rBtn = document.getElementById('mode-real');

        if (mode === 'virtual') {
            vBtn.classList.add('bg-accent', 'text-white');
            vBtn.classList.remove('text-slate-500');
            rBtn.classList.remove('bg-accent', 'text-white');
            rBtn.classList.add('text-slate-500');
        } else {
            rBtn.classList.add('bg-accent', 'text-white');
            rBtn.classList.remove('text-slate-500');
            vBtn.classList.remove('bg-accent', 'text-white');
            vBtn.classList.add('text-slate-500');
        }

        if (saveToServer) {
            fetch('/api/gianik/set-mode', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mode: mode })
            }).then(r => r.json()).then(data => {
                console.log('Operational mode updated:', data.mode);
            });
        }
    }

    window.isGiaNikSoundEnabled = true;
    function toggleGiaNikSound() {
        window.isGiaNikSoundEnabled = !window.isGiaNikSoundEnabled;
        const icon = document.getElementById('sound-icon');
        const btn = document.getElementById('sound-toggle');

        if (window.isGiaNikSoundEnabled) {
            // Prime the audio context
            const silent = new Audio('https://cdn.jsdelivr.net/gh/GiaNik/assets/notification.mp3');
            silent.volume = 0;
            silent.play().catch(() => { });

            icon.setAttribute('data-lucide', 'volume-2');
            icon.classList.remove('text-danger');
            icon.classList.add('text-success');
        } else {
            icon.setAttribute('data-lucide', 'volume-x');
            icon.classList.remove('text-success');
            icon.classList.add('text-danger');
        }
        if (window.lucide) lucide.createIcons();
    }
</script>

<?php require __DIR__ . '/../../Views/layout/bottom.php'; ?>