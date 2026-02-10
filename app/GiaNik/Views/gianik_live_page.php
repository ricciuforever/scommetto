<?php
// app/GiaNik/Views/gianik_live_page.php
$pageTitle = "GiaNik Live - App within App";
// We still use the main layout for now as it provides React/Tailwind/HTMX
require __DIR__ . '/../../Views/layout/top.php';
?>

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
                Betfair Multi-Sport Dashboard (Real-Time) - SEZIONE INDIPENDENTE
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
                    ATTIVO (30s)
                </span>
            </div>
            <button hx-get="/api/gianik/live" hx-target="#gianik-live-container"
                class="w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center border border-white/5 transition-all text-slate-400 hover:text-white">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
            </button>
        </div>
    </div>

    <!-- Container per il caricamento asincrono -->
    <div id="gianik-live-container" hx-get="/api/gianik/live" hx-trigger="load, every 30s" class="min-h-[400px]">
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
    <div id="recent-bets-container" hx-get="/api/gianik/recent-bets" hx-trigger="load, every 30s"
        class="flex-1 overflow-hidden">
        <div class="flex items-center justify-center p-20">
            <i data-lucide="loader-2" class="w-6 h-6 text-slate-500 animate-spin"></i>
        </div>
    </div>
</aside>

<script src="/js/modals.js"></script>
<script>
    // Modal helpers
    async function openMatchDetailsModal(fixtureId) {
        try {
            const response = await fetch(`/gianik/match-details-modal/${fixtureId}`);
            const html = await response.text();

            const container = document.createElement('div');
            container.innerHTML = html;
            document.body.appendChild(container.firstElementChild);

            if (typeof loadMatchData === 'function') {
                loadMatchData(fixtureId);
            }

            if (window.lucide) lucide.createIcons();
        } catch (error) {
            console.error('Error opening match details modal:', error);
        }
    }

    let matchData = null;
    function switchMatchTab(tab) {
        document.querySelectorAll('.match-tab').forEach(t => {
            t.classList.remove('bg-accent', 'text-white');
            t.classList.add('text-slate-500');
        });
        const activeTab = document.getElementById('tab-' + tab);
        if (activeTab) {
            activeTab.classList.add('bg-accent', 'text-white');
            activeTab.classList.remove('text-slate-500');
        }

        document.querySelectorAll('.match-tab-content').forEach(c => c.classList.add('hidden'));
        const activeContent = document.getElementById('content-' + tab);
        if (activeContent) activeContent.classList.remove('hidden');
    }

    async function loadMatchData(fixtureId) {
        try {
            const response = await fetch(`/api/match-details/${fixtureId}`);
            matchData = await response.json();

            // Update header
            const hLogo = document.getElementById('match-home-logo');
            if (hLogo) hLogo.src = matchData.fixture.team_home_logo;
            const aLogo = document.getElementById('match-away-logo');
            if (aLogo) aLogo.src = matchData.fixture.team_away_logo;

            const hName = document.getElementById('match-home-name');
            if (hName) hName.textContent = matchData.fixture.team_home_name;
            const aName = document.getElementById('match-away-name');
            if (aName) aName.textContent = matchData.fixture.team_away_name;

            const score = document.getElementById('match-score');
            if (score) score.textContent = `${matchData.fixture.score_home || 0}-${matchData.fixture.score_away || 0}`;

            const status = document.getElementById('match-status');
            if (status) status.textContent = matchData.fixture.status_short + (matchData.fixture.elapsed ? ` ${matchData.fixture.elapsed}'` : '');

            const league = document.getElementById('match-league');
            if (league) league.textContent = matchData.fixture.league_name;

            const date = document.getElementById('match-date');
            if (date) date.textContent = new Date(matchData.fixture.date).toLocaleString('it-IT');

            // Render tabs
            renderMatchStats(matchData.statistics);
            renderEvents(matchData.events);
        } catch (error) {
            console.error('Error loading match data:', error);
        }
    }

    function renderMatchStats(stats) {
        const homeStats = stats.find(s => s.team_id == matchData.fixture.team_home_id);
        const awayStats = stats.find(s => s.team_id == matchData.fixture.team_away_id);
        if (!homeStats || !awayStats) return;

        const statTypes = ['Ball Possession', 'Total Shots', 'Shots on Goal', 'Corner Kicks', 'Fouls', 'Yellow Cards', 'Total passes', 'Passes %'];
        const renderStat = (stat, containerId) => {
            const container = document.getElementById(containerId);
            if (!container) return;
            const statsJson = JSON.parse(stat.stats_json || '[]');
            let html = '';
            statTypes.forEach(type => {
                const item = statsJson.find(s => s.type === type);
                const value = item?.value || 0;
                html += `
                    <div class="flex justify-between items-center py-2 border-b border-white/5">
                        <span class="text-xs text-slate-500 font-bold uppercase">${type}</span>
                        <span class="text-sm font-black text-white">${value}</span>
                    </div>`;
            });
            container.innerHTML = html;
        };
        renderStat(homeStats, 'home-stats');
        renderStat(awayStats, 'away-stats');
    }

    function renderEvents(events) {
        const container = document.getElementById('events-timeline');
        if (!container) return;
        if (!events || events.length === 0) {
            container.innerHTML = '<div class="text-center text-slate-500 py-8">Nessun evento registrato</div>';
            return;
        }

        let html = '';
        events.forEach(event => {
            const icon = event.type === 'Goal' ? '⚽' : event.type === 'Card' ? (event.detail === 'Yellow Card' ? '🟨' : '🟥') : '🔄';
            html += `
                <div class="glass p-4 rounded-2xl border-white/5 flex items-center gap-4 cursor-pointer hover:bg-white/5 transition-all" 
                     onclick="openPlayerModal(${event.player_id || event.player.id}, ${matchData.fixture.id || matchData.fixture.fixture_id})">
                    <div class="text-2xl">${icon}</div>
                    <div class="flex-1">
                        <div class="text-sm font-black text-white">${event.detail}</div>
                        <div class="text-xs text-slate-500 mt-1">${event.player_name || event.player.name}</div>
                    </div>
                    <div class="text-accent font-black text-sm">${event.time_elapsed || event.time.elapsed}'</div>
                </div>`;
        });
        container.innerHTML = html;
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

    // GiaNik mode
    window.gianikMode = 'virtual';

    window.openBetDetails = function (id) {
        htmx.ajax('GET', '/api/gianik/bet/' + id, {
            target: '#global-modal-container',
            swap: 'innerHTML'
        });
    }

    function setGiaNikMode(mode) {
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
    }
</script>

<?php require __DIR__ . '/../../Views/layout/bottom.php'; ?>