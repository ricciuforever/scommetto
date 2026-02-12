<?php
// app/GiaNik/Views/gianik_live_page.php
$pageTitle = "GiaNik Live - App within App";
// We still use the main layout for now as it provides React/Tailwind/HTMX
require __DIR__ . '/../../Views/layout/top.php';
?>

<div class="flex flex-col gap-6 mr-72"> <!-- Added margin for sidebar -->
    <!-- Hidden auto-processing trigger -->
    <div hx-get="/api/gianik/auto-process" hx-trigger="every 120s" class="hidden"></div>

    <!-- Sidebar Left Extra Content Trigger (Matches Scartati) -->
    <div id="skipped-matches-portal" hx-get="/api/gianik/skipped-matches" hx-trigger="load, every 120s" hx-target="#skipped-matches-container" class="hidden"></div>

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
            <!-- Auto-Refresh Info Only -->
            <div class="flex items-center gap-4 bg-white/5 px-4 py-2 rounded-xl border border-white/5">
                <div class="flex flex-col items-end">
                    <span class="text-[10px] font-black uppercase text-slate-500">Auto-Refresh</span>
                    <span class="text-xs font-bold text-success flex items-center gap-1.5">
                        <span class="w-2 h-2 bg-success rounded-full animate-pulse"></span>
                        ATTIVO (60s)
                    </span>
                </div>
            </div>
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
    async function openMatchStatsModal(fixtureId) {
        try {
            const response = await fetch(`/gianik/stats-modal/${fixtureId}`);
            const html = await response.text();
            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);
            if (window.lucide) lucide.createIcons();
        } catch (error) {
            console.error('Error opening stats modal:', error);
        }
    }

    async function openMatchLineupsModal(fixtureId) {
        try {
            const response = await fetch(`/gianik/lineups-modal/${fixtureId}`);
            const html = await response.text();
            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);
            if (window.lucide) lucide.createIcons();
        } catch (error) {
            console.error('Error opening lineups modal:', error);
        }
    }

    async function openMatchH2HModal(fixtureId) {
        try {
            const response = await fetch(`/gianik/h2h-modal/${fixtureId}`);
            const html = await response.text();
            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);
            if (window.lucide) lucide.createIcons();
        } catch (error) {
            console.error('Error opening H2H modal:', error);
        }
    }



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

    // GiaNik mode is always REAL
    window.gianikMode = 'real';

    window.openBetDetails = function (id) {
        htmx.ajax('GET', '/api/gianik/bet/' + id, {
            target: '#global-modal-container',
            swap: 'innerHTML'
        });
    }
</script>

<!-- Script per iniettare i match scartati nella sidebar principale -->
<script>
    document.addEventListener('htmx:afterOnLoad', function(evt) {
        if (evt.detail.target.id === 'skipped-matches-container') {
            const portal = document.getElementById('skipped-matches-container');
            const target = document.getElementById('left-sidebar-extra-content');
            if (portal && target) {
                target.innerHTML = portal.innerHTML;
                if (window.lucide) lucide.createIcons();
            }
        }
    });
</script>

<div id="skipped-matches-container" class="hidden"></div>

<?php require __DIR__ . '/../../Views/layout/bottom.php'; ?>