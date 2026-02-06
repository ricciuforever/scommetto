// assets/js/app.js

let liveMatches = [];
let matchStates = {};
let pinnedMatches = new Set();
const notificationSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');
notificationSound.volume = 0.5;
let selectedLeague = 'all';

// UI Elements (Initialized in init)
let viewContainer, viewTitle, viewLoader;

// --- ROUTER ---

function handleRouting() {
    const hash = window.location.hash.substring(1) || 'dashboard';
    const [view, id] = hash.split('/');

    console.log("Routing to:", view, id || "");
    currentView = view;
    updateNavLinks(view);
    renderView(view, id);
}

function updateNavLinks(activeView) {
    document.querySelectorAll('.nav-link, nav a[data-view]').forEach(link => {
        const isMatch = link.dataset.view === activeView;
        if (isMatch) {
            link.classList.add('active-nav');
            link.classList.remove('text-slate-500');
            if (link.tagName === 'A' && !link.classList.contains('nav-link')) {
                 link.classList.add('text-accent');
            }
        } else {
            link.classList.remove('active-nav');
            if (link.tagName === 'A' && !link.classList.contains('nav-link')) {
                 link.classList.add('text-slate-500');
                 link.classList.remove('text-accent');
            }
        }
    });
}

async function renderView(view, id) {
    if (!viewContainer) return;
    showLoader(true);
    viewContainer.innerHTML = '';

    try {
        switch(view) {
            case 'dashboard':
                viewTitle.textContent = 'Dashboard Intelligence';
                await renderDashboard();
                break;
            case 'leagues':
                viewTitle.textContent = 'Competizioni';
                await renderLeagues(id);
                break;
            case 'predictions':
                viewTitle.textContent = 'AI Predictions';
                await renderPredictions();
                break;
            case 'tracker':
                viewTitle.textContent = 'Il Mio Tracker';
                await renderTracker();
                break;
            case 'match':
                viewTitle.textContent = 'Match Center';
                await renderMatchCenter(id);
                break;
            case 'team':
                viewTitle.textContent = 'Profilo Squadra';
                await renderTeamProfile(id);
                break;
            case 'player':
                viewTitle.textContent = 'Profilo Giocatore';
                await renderPlayerProfile(id);
                break;
            default:
                await renderDashboard();
        }
    } catch (e) {
        console.error("View render error:", e);
        viewContainer.innerHTML = `<div class="p-10 text-center text-danger font-bold">Errore durante il caricamento della vista: ${e.message}</div>`;
    }

    showLoader(false);
    if (window.lucide) lucide.createIcons();
}

function showLoader(show) {
    if (!viewLoader || !viewContainer) return;
    if (show) {
        viewLoader.classList.remove('hidden');
        viewContainer.classList.add('hidden');
    } else {
        viewLoader.classList.add('hidden');
        viewContainer.classList.remove('hidden');
    }
}

// --- VIEW RENDERERS ---

async function renderDashboard() {
    const statsHtml = `
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-10" id="stats-summary"></div>
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-10">
            <div class="lg:col-span-3">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
                    <h2 class="text-2xl font-black tracking-tight flex items-center gap-3">
                        <span class="w-2 h-8 bg-accent rounded-full"></span>
                        Live Now
                    </h2>
                </div>
                <div id="live-matches-list" class="space-y-6"></div>
            </div>
            <aside class="space-y-8">
                <div>
                    <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Hot Predictions</h2>
                    <div id="dashboard-predictions" class="space-y-4"></div>
                </div>
                <div>
                    <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Recent Activity</h2>
                    <div class="glass rounded-[32px] border-white/5 divide-y divide-white/5 overflow-hidden" id="dashboard-history"></div>
                </div>
            </aside>
        </div>
    `;
    viewContainer.innerHTML = statsHtml;

    // Non-blocking fetches
    fetchLive().then(() => {
        updateStatsSummary();
        renderDashboardMatches();
    });
    renderDashboardHistory();
    renderDashboardPredictions();
}

async function fetchLive() {
    try {
        const res = await fetch('/api/live');
        const data = await res.json();
        liveMatches = data.response || [];

        // Update match states for notifications
        liveMatches.forEach(m => {
            const id = m.fixture.id;
            const prevState = matchStates[id];

            if (prevState) {
                const goalsChanged = m.goals.home !== prevState.goals.home || m.goals.away !== prevState.goals.away;
                if (goalsChanged) {
                    pinnedMatches.add(id);
                    notificationSound.play().catch(() => {});
                    setTimeout(() => pinnedMatches.delete(id), 10000);
                }
            }
            matchStates[id] = { goals: { home: m.goals.home, away: m.goals.away } };
        });
    } catch (e) {
        console.error("Fetch live error", e);
    }
}

function renderDashboardMatches() {
    const container = document.getElementById('live-matches-list');
    if (!container) return;

    if (liveMatches.length === 0) {
        container.innerHTML = `
            <div class="glass p-12 rounded-[40px] text-center border-white/5">
                <i data-lucide="calendar" class="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">Nessuna partita live al momento</p>
            </div>
        `;
        return;
    }

    container.innerHTML = '';

    // Sort pinned matches to top
    const sorted = [...liveMatches].sort((a, b) => {
        if (pinnedMatches.has(a.fixture.id) && !pinnedMatches.has(b.fixture.id)) return -1;
        if (!pinnedMatches.has(a.fixture.id) && pinnedMatches.has(b.fixture.id)) return 1;
        return 0;
    });

    sorted.forEach(m => {
        const isPinned = pinnedMatches.has(m.fixture.id);
        const card = document.createElement('div');
        card.className = `glass rounded-[40px] border border-white/5 hover:border-accent/30 transition-all overflow-hidden cursor-pointer group relative ${isPinned ? 'ring-2 ring-accent shadow-2xl shadow-accent/20' : ''}`;
        card.onclick = () => window.location.hash = `match/${m.fixture.id}`;

        card.innerHTML = `
            <div class="flex flex-col md:flex-row items-center p-8 gap-8">
                <div class="flex-1 flex items-center justify-between w-full">
                    <div class="flex flex-col items-center gap-3 flex-1 text-center">
                        <img src="${m.teams.home.logo}" class="w-16 h-16 object-contain group-hover:scale-110 transition-transform">
                        <span class="font-black uppercase italic text-sm tracking-tight">${m.teams.home.name}</span>
                    </div>
                    <div class="flex flex-col items-center gap-1 px-6">
                        <div class="text-4xl font-black tabular-nums flex items-center gap-4">
                            <span>${m.goals.home}</span>
                            <span class="text-slate-700">:</span>
                            <span>${m.goals.away}</span>
                        </div>
                        <div class="bg-accent/10 text-accent text-[10px] px-3 py-1 rounded-full font-black uppercase tracking-widest animate-pulse">
                            ${m.fixture.status.elapsed}'
                        </div>
                    </div>
                    <div class="flex flex-col items-center gap-3 flex-1 text-center">
                        <img src="${m.teams.away.logo}" class="w-16 h-16 object-contain group-hover:scale-110 transition-transform">
                        <span class="font-black uppercase italic text-sm tracking-tight">${m.teams.away.name}</span>
                    </div>
                </div>
                <div class="w-px h-12 bg-white/5 hidden md:block"></div>
                <div class="flex gap-4">
                    <button class="w-12 h-12 rounded-2xl bg-white/5 hover:bg-accent hover:text-white flex items-center justify-center transition-all">
                        <i data-lucide="bar-chart-2" class="w-5 h-5"></i>
                    </button>
                    <button class="w-12 h-12 rounded-2xl bg-accent text-white flex items-center justify-center shadow-lg shadow-accent/20">
                        <i data-lucide="brain" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <div class="px-8 py-3 bg-white/5 flex items-center justify-between border-t border-white/5">
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">${m.league.name}</span>
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">${m.fixture.venue.name || ''}</span>
            </div>
        `;
        container.appendChild(card);
    });
    if (window.lucide) lucide.createIcons();
}

async function renderLeagues(leagueId) {
    if (leagueId) {
        return await renderLeagueDetails(leagueId);
    }

    try {
        const res = await fetch('/api/leagues');
        const leagues = await res.json();

        let html = `
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        `;

        leagues.forEach(l => {
            html += `
                <div class="glass p-6 rounded-[32px] border-white/5 hover:border-accent/30 transition-all cursor-pointer group" onclick="window.location.hash = 'leagues/${l.id}'">
                    <div class="flex items-center gap-4">
                        <img src="${l.logo}" class="w-12 h-12 object-contain group-hover:scale-110 transition-transform">
                        <div>
                            <h3 class="font-black uppercase italic">${l.name}</h3>
                            <p class="text-[10px] font-bold text-slate-500 tracking-widest uppercase">${l.country}</p>
                        </div>
                        <i data-lucide="chevron-right" class="w-5 h-5 ml-auto text-slate-500 opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        viewContainer.innerHTML = html;
    } catch (e) {
        console.error("Error fetching leagues", e);
        viewContainer.innerHTML = `<div class="p-10 text-center text-danger font-bold">Errore caricamento leghe</div>`;
    }
}

async function renderLeagueDetails(leagueId) {
    // League Header & Standings
    try {
        const res = await fetch(`/api/leagues/${leagueId}`);
        const league = await res.json();

        let html = `
            <div class="glass p-8 rounded-[40px] border-white/5 mb-10 flex flex-col md:flex-row items-center gap-8">
                <img src="${league.logo}" class="w-24 h-24 object-contain">
                <div>
                    <h1 class="text-4xl font-black uppercase italic tracking-tighter">${league.name}</h1>
                    <p class="text-slate-500 font-bold uppercase tracking-widest">${league.country}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-10">
                <div class="xl:col-span-2">
                    <h2 class="text-2xl font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                        <span class="w-2 h-8 bg-accent rounded-full"></span>
                        Classifica
                    </h2>
                    <div class="glass rounded-[40px] border-white/5 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-white/5">
                                    <tr>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Pos</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Squadra</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">P</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">W</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">D</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">L</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">GF:GS</th>
                                        <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Form</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5" id="standings-body">
                                    <!-- Populated by renderStandings -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div>
                    <h2 class="text-2xl font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                        <span class="w-2 h-8 bg-success rounded-full"></span>
                        Top Stats
                    </h2>
                    <div id="league-top-stats" class="space-y-6"></div>
                </div>
            </div>
        `;
        viewContainer.innerHTML = html;
        renderStandings(league.standings);
        renderLeagueTopStats(leagueId);
    } catch (e) {
        console.error("Error fetching league details", e);
    }
}

function renderStandings(standings) {
    const body = document.getElementById('standings-body');
    if (!body || !standings) return;

    body.innerHTML = '';
    standings.forEach(s => {
        const row = document.createElement('tr');
        row.className = "hover:bg-white/5 transition-colors cursor-pointer";
        row.onclick = () => window.location.hash = `team/${s.team_id}`;
        row.innerHTML = `
            <td class="px-6 py-4 font-black text-sm">${s.rank}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <img src="${s.logo}" class="w-6 h-6 object-contain">
                    <span class="font-bold text-sm">${s.team_name}</span>
                </div>
            </td>
            <td class="px-6 py-4 font-black text-accent text-sm">${s.points}</td>
            <td class="px-6 py-4 text-xs font-bold">${s.played}</td>
            <td class="px-6 py-4 text-xs font-bold">${s.win}</td>
            <td class="px-6 py-4 text-xs font-bold">${s.draw}</td>
            <td class="px-6 py-4 text-xs font-bold">${s.goals_for}:${s.goals_against}</td>
            <td class="px-6 py-4">
                <div class="flex gap-1">
                    ${(s.form || '').split('').map(f => `
                        <span class="w-5 h-5 rounded flex items-center justify-center text-[10px] font-black text-white ${f === 'W' ? 'bg-success' : f === 'D' ? 'bg-warning' : 'bg-danger'}">${f}</span>
                    `).join('')}
                </div>
            </td>
        `;
        body.appendChild(row);
    });
}

async function renderLeagueTopStats(leagueId) {
    if (!leagueId) return;
    const container = document.getElementById('league-top-stats');
    if (!container) return;
    try {
        const res = await fetch('/api/live');
        const data = await res.json();
        const newMatches = data.response || [];

        const categories = [
            { title: 'Marcatori', key: 'scorers', icon: 'zap' },
            { title: 'Assistmen', key: 'assists', icon: 'award' },
            { title: 'Cartellini Gialli', key: 'yellows', icon: 'file-warning' },
            { title: 'Cartellini Rossi', key: 'reds', icon: 'file-x' }
        ];

        container.innerHTML = '';
        categories.forEach(cat => {
            const stats = data[cat.key] || [];
            if (stats.length === 0) return;

            const card = document.createElement('div');
            card.className = "glass p-6 rounded-[32px] border-white/5";
            card.innerHTML = `
                <div class="flex items-center gap-3 mb-4">
                    <i data-lucide="${cat.icon}" class="w-4 h-4 text-accent"></i>
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-500">${cat.title}</h4>
                </div>
                <div class="space-y-3">
                    ${stats.slice(0, 3).map(p => `
                        <div class="flex items-center justify-between group cursor-pointer" onclick="window.location.hash = 'player/${p.player_id}'">
                            <div class="flex items-center gap-3">
                                <img src="${p.photo}" class="w-8 h-8 rounded-full border border-white/10 group-hover:scale-110 transition-transform">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold group-hover:text-accent transition-colors">${p.player_name}</span>
                                    <span class="text-[9px] font-bold text-slate-500 uppercase">${p.team_name}</span>
                                </div>
                            </div>
                            <span class="text-sm font-black italic">${p.value}</span>
                        </div>
                    `).join('')}
                </div>
            `;
            container.appendChild(card);
        });
        if (window.lucide) lucide.createIcons();
    } catch (e) {
        console.error("Error fetching top stats", e);
    }
}

async function renderMatchCenter(fixtureId) {
    try {
        const res = await fetch(`/api/match/${fixtureId}`);
        const data = await res.json();

        if (!data || data.error) {
            viewContainer.innerHTML = `<div class="p-10 text-center text-danger font-bold">Partita non trovata o dati non disponibili</div>`;
            return;
        }

        const match = data.fixture;
        const analysis = data.analysis;
        const odds = data.odds || [];
        const predictions = data.predictions;
        const lineups = data.lineups || [];
        const stats = data.stats || [];
        const h2h = data.h2h || [];

        let html = `
            <div class="glass p-8 md:p-12 rounded-[50px] border-white/5 mb-10 overflow-hidden relative">
                <div class="absolute inset-0 bg-gradient-to-br from-accent/10 to-transparent pointer-events-none opacity-50"></div>
                <div class="relative z-10">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-12">
                        <div class="flex flex-col items-center gap-4 text-center group cursor-pointer" onclick="window.location.hash = 'team/${match.home_id}'">
                            <div class="w-24 h-24 md:w-32 md:h-32 bg-white/5 p-4 rounded-[40px] border border-white/5 flex items-center justify-center group-hover:scale-105 transition-all">
                                <img src="${match.home_logo}" class="w-full h-full object-contain">
                            </div>
                            <h2 class="text-xl md:text-2xl font-black uppercase italic tracking-tight">${match.home_name}</h2>
                        </div>

                        <div class="flex flex-col items-center gap-4">
                            <div class="bg-white/5 px-6 py-2 rounded-full border border-white/10 text-[10px] font-black uppercase tracking-widest text-slate-500">
                                ${match.status_long} ${match.status_short === 'LIVE' ? `- ${match.elapsed}'` : ''}
                            </div>
                            <div class="text-6xl md:text-8xl font-black italic tabular-nums flex items-center gap-8">
                                <span class="${match.goals_home > match.goals_away ? 'text-white' : 'text-white/40'}">${match.goals_home ?? 0}</span>
                                <span class="text-accent">:</span>
                                <span class="${match.goals_away > match.goals_home ? 'text-white' : 'text-white/40'}">${match.goals_away ?? 0}</span>
                            </div>
                            <div class="text-sm font-bold text-slate-500 uppercase tracking-widest">
                                ${new Date(match.date).toLocaleString('it-IT', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'})}
                            </div>
                        </div>

                        <div class="flex flex-col items-center gap-4 text-center group cursor-pointer" onclick="window.location.hash = 'team/${match.away_id}'">
                            <div class="w-24 h-24 md:w-32 md:h-32 bg-white/5 p-4 rounded-[40px] border border-white/5 flex items-center justify-center group-hover:scale-105 transition-all">
                                <img src="${match.away_logo}" class="w-full h-full object-contain">
                            </div>
                            <h2 class="text-xl md:text-2xl font-black uppercase italic tracking-tight">${match.away_name}</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs Nav -->
            <div class="flex overflow-x-auto gap-4 mb-10 pb-2 no-scrollbar">
                <button class="tab-btn active" data-tab="intelligence">Intelligence</button>
                <button class="tab-btn" data-tab="info">Info</button>
                <button class="tab-btn" data-tab="odds">Quote</button>
                <button class="tab-btn" data-tab="lineups">Formazioni</button>
                <button class="tab-btn" data-tab="stats">Statistiche</button>
                <button class="tab-btn" data-tab="h2h">H2H</button>
            </div>

            <div id="tab-content" class="min-h-[400px]">
                <!-- Tab Content will be injected here -->
            </div>
        `;
        viewContainer.innerHTML = html;

        // Tab Switching Logic
        const tabBtns = document.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.onclick = () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                renderTab(btn.dataset.tab, { match, analysis, odds, predictions, lineups, stats, h2h });
            };
        });

        // Default tab
        renderTab('intelligence', { match, analysis, odds, predictions, lineups, stats, h2h });

    } catch (e) {
        console.error("Error fetching match details", e);
    }
}

function renderTab(tabId, data) {
    const container = document.getElementById('tab-content');
    if (!container) return;

    switch(tabId) {
        case 'intelligence':
            renderIntelligenceTab(container, data);
            break;
        case 'info':
            renderInfoTab(container, data);
            break;
        case 'odds':
            renderOddsTab(container, data);
            break;
        case 'lineups':
            renderLineupsTab(container, data);
            break;
        case 'stats':
            renderStatsTab(container, data);
            break;
        case 'h2h':
            renderH2HTab(container, data);
            break;
    }
    if (window.lucide) lucide.createIcons();
}

function renderIntelligenceTab(container, data) {
    const analysis = data.analysis;
    const pred = data.predictions;

    let html = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            <div class="space-y-8">
                <div class="glass p-10 rounded-[40px] border-white/5 relative overflow-hidden">
                    <div class="absolute top-0 right-0 p-6 opacity-10">
                        <i data-lucide="brain-circuit" class="w-32 h-32"></i>
                    </div>
                    <h3 class="text-2xl font-black mb-8 uppercase italic tracking-tight flex items-center gap-3">
                        <span class="w-2 h-8 bg-accent rounded-full"></span>
                        AI Analysis
                    </h3>
                    <div class="prose prose-invert prose-sm max-w-none leading-relaxed text-slate-300 font-medium">
                        ${analysis ? analysis.analysis.replace(/\n/g, '<br>') : 'Analisi in fase di elaborazione...'}
                    </div>
                </div>

                ${pred ? `
                    <div class="glass p-10 rounded-[40px] border-white/5">
                        <h3 class="text-xl font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                             <i data-lucide="sparkles" class="w-5 h-5 text-accent"></i>
                             Consiglio Algoritmico
                        </h3>
                        <div class="bg-accent/10 p-6 rounded-3xl border border-accent/20">
                            <div class="text-accent font-black text-xl italic mb-2 uppercase">${pred.advice}</div>
                            <div class="text-sm font-bold text-slate-400">Winner: ${pred.winner_name || 'N/A'} (Prob: ${pred.winner_comment || 'N/A'})</div>
                        </div>
                    </div>
                ` : ''}
            </div>

            <div class="space-y-8">
                <div class="glass p-10 rounded-[40px] border-white/5">
                    <h3 class="text-xl font-black mb-8 uppercase italic tracking-tight flex items-center gap-3">
                        <span class="w-2 h-8 bg-success rounded-full"></span>
                        Probabilità Live
                    </h3>
                    <div class="space-y-10 py-4">
                        <div class="relative">
                            <div class="flex justify-between mb-4 font-black uppercase italic text-xs tracking-widest">
                                <span>Home Win</span>
                                <span class="text-accent">45%</span>
                            </div>
                            <div class="h-4 bg-white/5 rounded-full overflow-hidden border border-white/5">
                                <div class="h-full bg-accent rounded-full" style="width: 45%"></div>
                            </div>
                        </div>
                        <div class="relative">
                            <div class="flex justify-between mb-4 font-black uppercase italic text-xs tracking-widest">
                                <span>Draw</span>
                                <span class="text-slate-500">30%</span>
                            </div>
                            <div class="h-4 bg-white/5 rounded-full overflow-hidden border border-white/5">
                                <div class="h-full bg-slate-500 rounded-full" style="width: 30%"></div>
                            </div>
                        </div>
                        <div class="relative">
                            <div class="flex justify-between mb-4 font-black uppercase italic text-xs tracking-widest">
                                <span>Away Win</span>
                                <span class="text-danger">25%</span>
                            </div>
                            <div class="h-4 bg-white/5 rounded-full overflow-hidden border border-white/5">
                                <div class="h-full bg-danger rounded-full" style="width: 25%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="glass p-10 rounded-[40px] border-white/5">
                    <h3 class="text-xl font-black mb-6 uppercase italic tracking-tight">AI Confidence</h3>
                    <div class="flex items-center gap-6">
                        <div class="text-5xl font-black italic text-accent">${analysis ? analysis.confidence : 85}%</div>
                        <div class="flex-1 h-3 bg-white/5 rounded-full overflow-hidden border border-white/5">
                            <div class="h-full bg-accent animate-pulse" style="width: ${analysis ? analysis.confidence : 85}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    container.innerHTML = html;
}

function renderInfoTab(container, data) {
    const m = data.match;
    container.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="glass p-8 rounded-[40px] border-white/5">
                <h3 class="text-lg font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                    <i data-lucide="map-pin" class="w-5 h-5 text-accent"></i>
                    Stadio & Luogo
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between border-b border-white/5 pb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase">Stadio</span>
                        <span class="text-xs font-black">${m.venue_name || 'N/A'}</span>
                    </div>
                    <div class="flex justify-between border-b border-white/5 pb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase">Città</span>
                        <span class="text-xs font-black">${m.venue_city || 'N/A'}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs font-bold text-slate-500 uppercase">Arbitro</span>
                        <span class="text-xs font-black">${m.referee || 'N/A'}</span>
                    </div>
                </div>
            </div>
            <div class="glass p-8 rounded-[40px] border-white/5">
                <h3 class="text-lg font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                    <i data-lucide="info" class="w-5 h-5 text-accent"></i>
                    Dettagli Competizione
                </h3>
                <div class="space-y-4">
                    <div class="flex justify-between border-b border-white/5 pb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase">Lega</span>
                        <span class="text-xs font-black">${m.league_name}</span>
                    </div>
                    <div class="flex justify-between border-b border-white/5 pb-3">
                        <span class="text-xs font-bold text-slate-500 uppercase">Stagione</span>
                        <span class="text-xs font-black">${m.season}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-xs font-bold text-slate-500 uppercase">Round</span>
                        <span class="text-xs font-black">${m.round || 'N/A'}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function renderOddsTab(container, data) {
    const odds = data.odds || [];
    if (odds.length === 0) {
        container.innerHTML = `<div class="p-20 glass rounded-[40px] border-white/5 text-center text-slate-500 font-bold uppercase tracking-widest text-xs">Quote non disponibili per questo evento</div>`;
        return;
    }

    let html = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">`;

    // Group by bet type
    const grouped = {};
    odds.forEach(o => {
        if (!grouped[o.bet_name]) grouped[o.bet_name] = [];
        grouped[o.bet_name].push(o);
    });

    for (const [name, values] of Object.entries(grouped)) {
        html += `
            <div class="glass p-8 rounded-[40px] border-white/5">
                <h4 class="text-xs font-black uppercase tracking-widest text-slate-500 mb-6">${name}</h4>
                <div class="grid grid-cols-1 gap-3">
                    ${values.map(v => `
                        <div class="bg-white/5 p-4 rounded-2xl flex justify-between items-center hover:bg-white/10 transition-colors border border-white/5">
                            <span class="text-[10px] font-black uppercase italic">${v.value_name}</span>
                            <span class="text-accent font-black tabular-nums">${v.odd}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }
    html += `</div>`;
    container.innerHTML = html;
}

function renderLineupsTab(container, data) {
    const lineups = data.lineups || [];
    if (lineups.length === 0) {
        container.innerHTML = `<div class="p-20 glass rounded-[40px] border-white/5 text-center text-slate-500 font-bold uppercase tracking-widest text-xs">Formazioni non ancora disponibili (solitamente caricate 45' prima)</div>`;
        return;
    }

    let html = `<div class="grid grid-cols-1 lg:grid-cols-2 gap-10">`;
    lineups.forEach(l => {
        html += `
            <div class="glass p-8 rounded-[40px] border-white/5">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <img src="${l.team_logo}" class="w-10 h-10 object-contain">
                        <h3 class="font-black uppercase italic">${l.team_name}</h3>
                    </div>
                    <div class="bg-accent/10 text-accent px-3 py-1 rounded-full text-[10px] font-black uppercase">${l.formation}</div>
                </div>

                <div class="space-y-4">
                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-4">Start XI</h4>
                    <div class="grid grid-cols-1 gap-2">
                        ${l.start_xi_json.map(p => `
                            <div class="flex items-center justify-between bg-white/5 p-3 rounded-xl border border-white/5">
                                <div class="flex items-center gap-3">
                                    <span class="w-5 text-center text-[10px] font-black text-accent">${p.player.number}</span>
                                    <span class="text-xs font-bold">${p.player.name}</span>
                                </div>
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-tighter">${p.player.pos}</span>
                            </div>
                        `).join('')}
                    </div>

                    <h4 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-8 mb-4">Sostituti</h4>
                    <div class="grid grid-cols-1 gap-2">
                        ${l.substitutes_json.map(p => `
                            <div class="flex items-center justify-between bg-white/5/50 p-2 rounded-xl border border-white/5 text-slate-400">
                                <div class="flex items-center gap-3">
                                    <span class="w-5 text-center text-[10px] font-bold">${p.player.number}</span>
                                    <span class="text-[11px] font-bold">${p.player.name}</span>
                                </div>
                                <span class="text-[8px] font-bold uppercase">${p.player.pos}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    });
    html += `</div>`;
    container.innerHTML = html;
}

function renderStatsTab(container, data) {
    const stats = data.stats || [];
    if (stats.length === 0) {
        container.innerHTML = `<div class="p-20 glass rounded-[40px] border-white/5 text-center text-slate-500 font-bold uppercase tracking-widest text-xs">Statistiche live non disponibili</div>`;
        return;
    }

    // Transform stats into comparable pairs
    const pairs = {};
    stats.forEach(s => {
        if (!pairs[s.type]) pairs[s.type] = { home: 0, away: 0 };
        if (s.team_id === data.match.home_id) pairs[s.type].home = s.value;
        else pairs[s.type].away = s.value;
    });

    let html = `<div class="glass p-10 rounded-[40px] border-white/5 max-w-2xl mx-auto space-y-10">`;
    for (const [type, val] of Object.entries(pairs)) {
        const homeVal = parseFloat(val.home) || 0;
        const awayVal = parseFloat(val.away) || 0;
        const total = homeVal + awayVal || 1;
        const homePerc = (homeVal / total) * 100;

        html += `
            <div>
                <div class="flex justify-between mb-4 font-black uppercase italic text-xs tracking-widest">
                    <span class="${homeVal > awayVal ? 'text-white' : 'text-slate-500'}">${val.home || 0}</span>
                    <span class="text-slate-500 font-bold">${type}</span>
                    <span class="${awayVal > homeVal ? 'text-white' : 'text-slate-500'}">${val.away || 0}</span>
                </div>
                <div class="h-2 bg-white/5 rounded-full overflow-hidden flex border border-white/5">
                    <div class="h-full bg-accent transition-all duration-1000" style="width: ${homePerc}%"></div>
                    <div class="h-full bg-danger transition-all duration-1000" style="width: ${100 - homePerc}%"></div>
                </div>
            </div>
        `;
    }
    html += `</div>`;
    container.innerHTML = html;
}

function renderH2HTab(container, data) {
    const h2h = data.h2h || [];
    if (h2h.length === 0) {
        container.innerHTML = `<div class="p-20 glass rounded-[40px] border-white/5 text-center text-slate-500 font-bold uppercase tracking-widest text-xs">Storico H2H non disponibile</div>`;
        return;
    }

    let html = `<div class="space-y-4 max-w-3xl mx-auto">`;
    h2h.forEach(m => {
        html += `
            <div class="glass p-6 rounded-[32px] border-white/5 flex items-center justify-between hover:bg-white/5 transition-colors cursor-pointer group" onclick="window.location.hash = 'match/${m.id}'">
                <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest w-24">
                    ${new Date(m.date).toLocaleDateString()}
                </div>
                <div class="flex-1 flex items-center justify-center gap-6">
                    <div class="flex items-center gap-3 flex-1 justify-end">
                        <span class="text-xs font-black uppercase italic text-right">${m.home_name}</span>
                        <img src="${m.home_logo}" class="w-6 h-6 object-contain">
                    </div>
                    <div class="bg-white/5 px-4 py-2 rounded-xl font-black text-sm tabular-nums">
                        ${m.goals_home} - ${m.goals_away}
                    </div>
                    <div class="flex items-center gap-3 flex-1">
                        <img src="${m.away_logo}" class="w-6 h-6 object-contain">
                        <span class="text-xs font-black uppercase italic">${m.away_name}</span>
                    </div>
                </div>
                <div class="w-24 text-right">
                    <span class="text-[9px] font-black text-slate-500 uppercase tracking-tighter">${m.league_name}</span>
                </div>
            </div>
        `;
    });
    html += `</div>`;
    container.innerHTML = html;
}

async function renderTeamProfile(teamId) {
    try {
        const res = await fetch(`/api/team/${teamId}`);
        const data = await res.json();

        if (!data || data.error) {
            viewContainer.innerHTML = `<div class="p-10 text-center text-danger font-bold">Squadra non trovata</div>`;
            return;
        }

        const team = data.team;
        const coach = data.coach;
        const stats = data.stats;
        const fixtures = data.fixtures || [];
        const trophies = data.trophies || [];

        let html = `
            <div class="glass p-12 rounded-[50px] border-white/5 mb-10 flex flex-col md:flex-row items-center gap-12 overflow-hidden relative">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-500/10 to-transparent pointer-events-none"></div>
                <img src="${team.logo}" class="w-32 h-32 md:w-48 md:h-48 object-contain relative z-10 drop-shadow-2xl">
                <div class="relative z-10">
                    <h1 class="text-5xl font-black uppercase italic tracking-tighter mb-2">${team.name}</h1>
                    <div class="flex flex-wrap items-center gap-4">
                        <span class="bg-white/5 px-4 py-1 rounded-full border border-white/10 text-[10px] font-black uppercase tracking-widest text-slate-500">
                             ${team.country}
                        </span>
                        <span class="text-sm font-bold text-slate-500 italic">Fondato nel ${team.founded || 'N/A'}</span>
                    </div>
                    <div class="mt-8 flex gap-3">
                        ${trophies.slice(0, 5).map(t => `
                            <div class="w-10 h-10 rounded-xl bg-warning/10 border border-warning/20 flex items-center justify-center text-warning" title="${t.league}">
                                <i data-lucide="trophy" class="w-5 h-5"></i>
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <div class="lg:col-span-2 space-y-10">
                    <section>
                         <h2 class="text-2xl font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                            <span class="w-2 h-8 bg-accent rounded-full"></span>
                            Prossimi Incontri
                        </h2>
                        <div class="space-y-4">
                            ${fixtures.map(f => `
                                <div class="glass p-6 rounded-[32px] border-white/5 flex items-center justify-between hover:bg-white/5 transition-all cursor-pointer group" onclick="window.location.hash = 'match/${f.id}'">
                                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest w-24">
                                        ${new Date(f.date).toLocaleDateString()}
                                    </div>
                                    <div class="flex-1 flex items-center justify-center gap-6">
                                        <div class="flex items-center gap-3 flex-1 justify-end">
                                            <span class="text-xs font-black uppercase italic text-right truncate max-w-[120px]">${f.home_name}</span>
                                            <img src="${f.home_logo}" class="w-6 h-6 object-contain">
                                        </div>
                                        <div class="bg-white/5 px-4 py-2 rounded-xl font-black text-[10px] tracking-widest text-slate-500 uppercase">
                                            VS
                                        </div>
                                        <div class="flex items-center gap-3 flex-1">
                                            <img src="${f.away_logo}" class="w-6 h-6 object-contain">
                                            <span class="text-xs font-black uppercase italic truncate max-w-[120px]">${f.away_name}</span>
                                        </div>
                                    </div>
                                    <div class="w-24 text-right">
                                        <span class="text-[9px] font-black text-accent uppercase tracking-tighter">${f.league_name}</span>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </section>
                </div>

                <aside class="space-y-10">
                    <div class="glass p-8 rounded-[40px] border-white/5">
                        <h3 class="text-xl font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                            <i data-lucide="user" class="w-5 h-5 text-accent"></i>
                            Allenatore
                        </h3>
                        <div class="flex items-center gap-6">
                            <img src="${coach.photo || 'https://media.api-sports.io/football/coaches/placeholder.png'}" class="w-20 h-20 rounded-2xl object-cover border border-white/10">
                            <div>
                                <h4 class="font-black uppercase italic">${coach.name || 'N/A'}</h4>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Età: ${coach.age || 'N/A'}</p>
                                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Naz: ${coach.nationality || 'N/A'}</p>
                            </div>
                        </div>
                    </div>

                    <div class="glass p-8 rounded-[40px] border-white/5">
                        <h3 class="text-xl font-black mb-6 uppercase italic tracking-tight flex items-center gap-3">
                            <i data-lucide="bar-chart-2" class="w-5 h-5 text-success"></i>
                            Stagione Attuale
                        </h3>
                        <div class="space-y-6">
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase">Partite</span>
                                <span class="text-lg font-black italic">${stats.fixtures_played_total || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase">Vinte</span>
                                <span class="text-lg font-black italic text-success">${stats.fixtures_wins_total || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase">Pareggi</span>
                                <span class="text-lg font-black italic text-warning">${stats.fixtures_draws_total || 0}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-xs font-bold text-slate-500 uppercase">Perse</span>
                                <span class="text-lg font-black italic text-danger">${stats.fixtures_loses_total || 0}</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        `;
        viewContainer.innerHTML = html;

    } catch (e) {
        console.error("Error fetching team profile", e);
    }
}

async function renderPlayerProfile(playerId) {
     try {
        const res = await fetch(`/api/player/${playerId}`);
        const data = await res.json();

        if (!data || data.error) {
            viewContainer.innerHTML = `<div class="p-10 text-center text-danger font-bold">Giocatore non trovato</div>`;
            return;
        }

        const p = data.player;
        const stats = data.stats[0] || {};

        let html = `
            <div class="glass p-12 rounded-[50px] border-white/5 mb-10 flex flex-col md:flex-row items-center gap-12 overflow-hidden relative">
                <div class="absolute inset-0 bg-gradient-to-br from-accent/10 to-transparent pointer-events-none"></div>
                <img src="${p.photo}" class="w-48 h-48 rounded-[40px] object-cover relative z-10 shadow-2xl border-4 border-white/10">
                <div class="relative z-10">
                    <div class="bg-accent text-white px-4 py-1 rounded-full text-[10px] font-black uppercase tracking-widest mb-4 inline-block">
                        ${stats.league_name || 'N/A'}
                    </div>
                    <h1 class="text-5xl font-black uppercase italic tracking-tighter mb-2">${p.name}</h1>
                    <div class="flex flex-wrap items-center gap-6">
                        <div class="flex items-center gap-2">
                             <img src="${stats.team_logo}" class="w-6 h-6 object-contain">
                             <span class="text-sm font-black uppercase italic">${stats.team_name}</span>
                        </div>
                        <div class="w-px h-4 bg-white/10"></div>
                        <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">${p.nationality}</span>
                        <div class="w-px h-4 bg-white/10"></div>
                        <span class="text-sm font-bold text-slate-500 uppercase tracking-widest">${p.age} Anni</span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Presenze</div>
                    <div class="text-4xl font-black italic text-white">${stats.games_appearences || 0}</div>
                </div>
                <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Goal</div>
                    <div class="text-4xl font-black italic text-accent">${stats.goals_total || 0}</div>
                </div>
                <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Assist</div>
                    <div class="text-4xl font-black italic text-success">${stats.goals_assists || 0}</div>
                </div>
                <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Media Voto</div>
                    <div class="text-4xl font-black italic text-warning">${stats.games_rating || '0.0'}</div>
                </div>
            </div>
        `;
        viewContainer.innerHTML = html;

    } catch (e) {
        console.error("Error fetching player profile", e);
    }
}

async function renderPredictions() {
    try {
        const res = await fetch('/api/predictions/all');
        const data = await res.json();

        let html = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">`;

        if (data.length === 0) {
            html += `<div class="col-span-full p-20 glass rounded-[40px] text-center text-slate-500 font-bold uppercase tracking-widest text-xs">Nessun pronostico disponibile al momento</div>`;
        }

        data.forEach(p => {
            html += `
                <div class="glass p-8 rounded-[40px] border-white/5 hover:border-accent/30 transition-all cursor-pointer group flex flex-col" onclick="window.location.hash = 'match/${p.fixture_id}'">
                    <div class="flex items-center justify-between mb-6">
                        <span class="text-[10px] font-black uppercase text-slate-500 tracking-widest">${p.league_name}</span>
                        <div class="bg-accent/10 px-3 py-1 rounded-full text-accent text-[9px] font-black uppercase tracking-widest">Conf: ${p.confidence}%</div>
                    </div>
                    <div class="flex items-center justify-between mb-8">
                        <div class="flex flex-col items-center gap-2 flex-1">
                            <img src="${p.home_logo}" class="w-12 h-12 object-contain group-hover:scale-110 transition-transform">
                            <span class="text-[10px] font-black uppercase italic text-center truncate w-full">${p.home_name}</span>
                        </div>
                        <div class="text-xs font-black text-slate-700 mx-4">VS</div>
                        <div class="flex flex-col items-center gap-2 flex-1">
                            <img src="${p.away_logo}" class="w-12 h-12 object-contain group-hover:scale-110 transition-transform">
                            <span class="text-[10px] font-black uppercase italic text-center truncate w-full">${p.away_name}</span>
                        </div>
                    </div>
                    <div class="mt-auto pt-6 border-t border-white/5">
                        <div class="text-accent font-black text-sm italic uppercase mb-1 tracking-tight">${p.advice}</div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase">${p.winner_name ? 'Winner: ' + p.winner_name : 'No winner suggested'}</div>
                    </div>
                </div>
            `;
        });

        html += `</div>`;
        viewContainer.innerHTML = html;
    } catch (e) {
        console.error("Error fetching predictions", e);
    }
}

async function renderTracker() {
    const stats = calculateStats();
    let html = `
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
            <div class="glass p-8 rounded-[40px] border-white/5">
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Giocate</div>
                <div class="text-4xl font-black italic text-white">${stats.total}</div>
            </div>
            <div class="glass p-8 rounded-[40px] border-white/5">
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Win Rate</div>
                <div class="text-4xl font-black italic text-success">${stats.total > 0 ? Math.round((stats.won/stats.total)*100) : 0}%</div>
            </div>
            <div class="glass p-8 rounded-[40px] border-white/5">
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">Net Profit</div>
                <div class="text-4xl font-black italic ${stats.profit >= 0 ? 'text-accent' : 'text-danger'}">${stats.profit.toFixed(2)}€</div>
            </div>
            <div class="glass p-8 rounded-[40px] border-white/5">
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">ROI</div>
                <div class="text-4xl font-black italic text-warning">${stats.roi.toFixed(1)}%</div>
            </div>
        </div>

        <div class="glass rounded-[40px] border-white/5 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-white/5">
                        <tr>
                            <th class="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Data</th>
                            <th class="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Match</th>
                            <th class="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Mercato</th>
                            <th class="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Quota</th>
                            <th class="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Stake</th>
                            <th class="px-8 py-4 text-[10px] font-black uppercase tracking-widest text-slate-500">Risultato</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        ${historyData.map(b => `
                            <tr class="hover:bg-white/5 transition-colors cursor-pointer" onclick="window.location.hash = 'match/${b.fixture_id}'">
                                <td class="px-8 py-5 text-xs font-bold text-slate-500">${new Date(b.created_at).toLocaleDateString()}</td>
                                <td class="px-8 py-5">
                                    <div class="flex items-center gap-3">
                                        <span class="text-xs font-black uppercase italic">${b.home_name} - ${b.away_name}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-5 text-xs font-black text-white italic uppercase">${b.bet_name}: ${b.value_name}</td>
                                <td class="px-8 py-5 font-black text-accent tabular-nums">${b.odd}</td>
                                <td class="px-8 py-5 text-xs font-bold">${b.stake}€</td>
                                <td class="px-8 py-5">
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest ${
                                        b.status === 'won' ? 'bg-success/20 text-success' :
                                        b.status === 'lost' ? 'bg-danger/20 text-danger' :
                                        'bg-warning/20 text-warning'
                                    }">${b.status}</span>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
    viewContainer.innerHTML = html;
}

// --- UTILS ---

async function fetchUsage() {
    try {
        const res = await fetch('/api/usage');
        const data = await res.json();
        document.getElementById('usage-val').textContent = data.current;
        document.getElementById('limit-val').textContent = data.limit;
    } catch (e) {}
}

async function fetchHistory() {
    try {
        const res = await fetch('/api/history');
        historyData = await res.json();
    } catch (e) {}
}

function updateStatsSummary() {
    const container = document.getElementById('stats-summary');
    if (!container) return;

    const stats = calculateStats();
    const items = [
        { label: 'Active Games', val: liveMatches.length, color: 'accent', icon: 'zap' },
        { label: 'Win Rate', val: (stats.total > 0 ? Math.round((stats.won/stats.total)*100) : 0) + '%', color: 'success', icon: 'trending-up' },
        { label: 'ROI', val: stats.roi.toFixed(1) + '%', color: 'warning', icon: 'pie-chart' },
        { label: 'Profit', val: stats.profit.toFixed(1) + '€', color: stats.profit >= 0 ? 'accent' : 'danger', icon: 'dollar-sign' },
        { label: 'Total Bets', val: stats.total, color: 'slate-500', icon: 'list' },
        { label: 'Pending', val: stats.pending, color: 'warning', icon: 'clock' }
    ];

    container.innerHTML = items.map(i => `
        <div class="glass p-4 rounded-3xl border-white/5 flex flex-col items-center text-center">
            <i data-lucide="${i.icon}" class="w-4 h-4 text-${i.color} mb-2"></i>
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">${i.label}</span>
            <span class="text-sm font-black italic text-white">${i.val}</span>
        </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

function calculateStats() {
    const stats = {
        total: historyData.length,
        won: historyData.filter(b => b.status === 'won').length,
        lost: historyData.filter(b => b.status === 'lost').length,
        pending: historyData.filter(b => b.status === 'pending').length,
        profit: 0,
        roi: 0
    };

    let totalStaked = 0;
    historyData.forEach(b => {
        totalStaked += parseFloat(b.stake);
        if (b.status === 'won') {
            stats.profit += (parseFloat(b.stake) * parseFloat(b.odd)) - parseFloat(b.stake);
        } else if (b.status === 'lost') {
            stats.profit -= parseFloat(b.stake);
        }
    });

    if (totalStaked > 0) {
        stats.roi = (stats.profit / totalStaked) * 100;
    }

    return stats;
}

function renderDashboardHistory() {
    const container = document.getElementById('dashboard-history');
    if (!container) return;

    if (historyData.length === 0) {
        container.innerHTML = '<div class="p-8 text-center text-[10px] font-bold text-slate-500 uppercase italic">Nessuna attività recente</div>';
        return;
    }

    container.innerHTML = historyData.slice(0, 5).map(b => `
        <div class="p-5 hover:bg-white/5 transition-all cursor-pointer group" onclick="window.location.hash = 'match/${b.fixture_id}'">
            <div class="flex justify-between items-start mb-2">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">${new Date(b.created_at).toLocaleDateString()}</span>
                <span class="text-[9px] font-black uppercase tracking-widest ${b.status === 'won' ? 'text-success' : b.status === 'lost' ? 'text-danger' : 'text-warning'}">${b.status}</span>
            </div>
            <div class="text-xs font-black uppercase italic group-hover:text-accent transition-colors">${b.home_name} - ${b.away_name}</div>
            <div class="text-[10px] font-bold text-slate-500 mt-1">${b.bet_name}: ${b.value_name} @${b.odd}</div>
        </div>
    `).join('');
}

async function renderDashboardPredictions() {
    const container = document.getElementById('dashboard-predictions');
    if (!container) return;

    try {
        const res = await fetch('/api/predictions/all');
        const data = await res.json();

        if (!data || data.error || !data.length) {
            container.innerHTML = '<div class="glass p-8 rounded-3xl text-center text-slate-500 font-bold text-[10px] uppercase italic">Nessun consiglio disponibile</div>';
            return;
        }

        container.innerHTML = '';
        data.slice(0, 3).forEach(p => {
            const item = document.createElement('div');
            item.className = "glass p-6 rounded-3xl border-white/5 hover:border-accent/30 transition-all cursor-pointer group";
            item.onclick = () => window.location.hash = `match/${p.fixture_id}`;
            item.innerHTML = `
                <div class="flex items-center justify-between mb-4">
                    <span class="text-[8px] font-black uppercase text-slate-500 tracking-widest">${p.league_name}</span>
                    <i data-lucide="brain-circuit" class="w-3 h-3 text-accent"></i>
                </div>
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-[10px] font-black uppercase italic truncate max-w-[80px]">${p.home_name}</span>
                    <span class="text-[8px] opacity-20 italic font-black">VS</span>
                    <span class="text-[10px] font-black uppercase italic truncate max-w-[80px]">${p.away_name}</span>
                </div>
                <div class="text-xs font-black text-white italic uppercase">${p.advice}</div>
            `;
            container.appendChild(item);
        });
        if (window.lucide) lucide.createIcons();
    } catch (e) { console.error("Error fetching dashboard predictions", e); }
}

// --- GLOBAL INIT ---

window.addEventListener('hashchange', handleRouting);

async function init() {
    // Initialize UI Elements
    viewContainer = document.getElementById('view-container');
    viewTitle = document.getElementById('view-title');
    viewLoader = document.getElementById('view-loader');

    if (!viewContainer) {
        console.error("Critical Error: view-container not found!");
        return;
    }

    // Start Routing
    handleRouting();

    // Background fetches
    fetchUsage();
    fetchHistory();

    // Auto-refreshers
    setInterval(fetchUsage, 60000);
    setInterval(fetchHistory, 60000);
    setInterval(async () => {
        if (currentView === 'dashboard') {
            await fetchLive();
            updateStatsSummary();
            renderDashboardMatches();
        }
    }, 60000);
}

if (themeToggle) {
    themeToggle.addEventListener('click', () => {
        if (htmlElement.classList.contains('dark')) {
            htmlElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        } else {
            htmlElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        }
        if (window.lucide) lucide.createIcons();
    });
}

// Start
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
