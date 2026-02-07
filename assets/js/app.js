// assets/js/app.js

// Global State
let liveMatches = [];
let matchStates = {};
let pinnedMatches = new Set();
const notificationSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');
notificationSound.volume = 0.5;
let currentView = 'dashboard';
let historyData = [];

// Global Filter State
let selectedCountry = localStorage.getItem('selected_country') || 'all';
let selectedBookmaker = localStorage.getItem('selected_bookmaker') || 'all';
let allFilterData = { countries: [], bookmakers: [] };

const countryFlags = {
    'Italy': '🇮🇹', 'England': '🏴󠁧󠁢󠁥󠁮󠁧󠁿', 'Spain': '🇪🇸', 'Germany': '🇩🇪',
    'France': '🇫🇷', 'Brazil': '🇧🇷', 'Argentina': '🇦🇷',
    'Belgium': '🇧🇪', 'Netherlands': '🇳🇱', 'Portugal': '🇵🇹', 'Turkey': '🇹🇷',
    'USA': '🇺🇸', 'Japan': '🇯🇵', 'Saudi Arabia': '🇸🇦', 'International': '🌍'
};

// Init UI Elements
const viewContainer = document.getElementById('view-container');
const viewTitle = document.getElementById('view-title');
const viewLoader = document.getElementById('view-loader');

// --- ROUTER ---

function handleRouting() {
    const hash = window.location.hash.substring(1) || 'dashboard';
    const [view, id] = hash.split('/');

    currentView = view;
    updateNavLinks(view);
    renderView(view, id);
}

function updateNavLinks(activeView) {
    document.querySelectorAll('.nav-link, nav a').forEach(link => {
        if (link.dataset.view === activeView) {
            link.classList.add('active-nav');
            link.classList.remove('text-slate-500');
        } else {
            link.classList.remove('active-nav');
            if (link.tagName === 'A' && !link.classList.contains('nav-link')) {
                link.classList.add('text-slate-500');
            }
        }
    });
}

async function renderView(view, id) {
    showLoader(true);
    viewContainer.innerHTML = '';

    switch (view) {
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
            renderDashboard();
    }

    showLoader(false);
    if (window.lucide) lucide.createIcons();
}

function showLoader(show) {
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
    // Stats Summary Grid
    const statsHtml = `
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-10" id="stats-summary">
            <!-- Populated by updateStats() -->
        </div>

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

    await fetchLive();
    updateStatsSummary();
    renderDashboardMatches();
    renderDashboardHistory();
    renderDashboardPredictions();
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

async function renderLeagues(leagueId) {
    if (leagueId) {
        return await renderLeagueDetails(leagueId);
    }

    const res = await fetch('/api/leagues');
    const leagues = await res.json();

    // Estrai nazioni uniche per il filtro
    const countries = [...new Set(leagues.map(l => l.country_name || l.country || 'International'))].sort();

    let html = `
        <div class="mb-12 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-black italic uppercase tracking-tighter mb-2">Competizioni</h1>
                <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic">Esplora le leghe sincronizzate nel database di Scommetto.AI</p>
            </div>
            
            <div class="flex items-center gap-4 bg-white/5 p-2 rounded-2xl border border-white/10 group focus-within:border-accent/80 transition-all min-w-[240px]">
                <div class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-slate-500 group-focus-within:text-accent transition-colors">
                    <i data-lucide="filter" class="w-5 h-5"></i>
                </div>
                <select id="country-filter" class="flex-1 bg-transparent border-none text-sm font-black uppercase italic text-white focus:ring-0 cursor-pointer pr-10 appearance-none">
                    <option value="all" class="bg-slate-900">Tutte le Nazioni</option>
                    ${countries.map(c => `<option value="${c}" class="bg-slate-900">${c}</option>`).join('')}
                </select>
            </div>
        </div>

        <div id="leagues-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            ${leagues.map(l => `
                <div class="glass p-6 rounded-[32px] border-white/5 hover:border-accent/30 transition-all cursor-pointer group league-card active-card" data-country="${l.country_name || l.country || 'International'}" onclick="window.location.hash = 'leagues/${l.id}'">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-white rounded-2xl p-2 flex items-center justify-center shadow-lg border border-white/10 overflow-hidden">
                            <img src="${l.logo}" class="max-w-full max-h-full object-contain group-hover:scale-110 transition-transform">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-black uppercase italic text-white group-hover:text-accent transition-colors truncate">${l.name}</h3>
                            <p class="text-[10px] font-bold text-slate-500 tracking-widest uppercase">${l.country_name || l.country || 'International'}</p>
                        </div>
                        <i data-lucide="chevron-right" class="w-5 h-5 ml-auto text-slate-500 opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
                    </div>
                </div>
            `).join('')}
        </div>
    `;

    viewContainer.innerHTML = html;
    if (window.lucide) lucide.createIcons();

    const filter = document.getElementById('country-filter');

    // Funzione per applicare il filtro
    const applyFilter = (val) => {
        const cards = document.querySelectorAll('.league-card');
        cards.forEach(card => {
            if (val === 'all' || card.getAttribute('data-country') === val) {
                card.classList.remove('hidden');
                card.classList.add('active-card');
            } else {
                card.classList.add('hidden');
                card.classList.remove('active-card');
            }
        });
    };

    // Recupera e applica filtro salvato
    const savedCountry = localStorage.getItem('selected_country') || 'all';
    if (filter) {
        filter.value = savedCountry;
        applyFilter(savedCountry);

        filter.onchange = (e) => {
            const val = e.target.value;
            localStorage.setItem('selected_country', val);
            applyFilter(val);
        };
    }
}

async function renderLeagueTopStats(leagueId) {
    const container = document.getElementById('league-top-stats');
    try {
        const res = await fetch(`/api/leagues/stats/${leagueId}`);
        const data = await res.json();

        const categories = [
            { title: 'Marcatori', key: 'scorers', icon: 'zap' },
            { title: 'Assist', key: 'assists', icon: 'share-2' },
            { title: 'Gialli', key: 'yellow_cards', icon: 'award' },
            { title: 'Rossi', key: 'red_cards', icon: 'shield-alert' }
        ];

        categories.forEach(cat => {
            const list = data[cat.key]?.stats_json || [];
            if (!list.length) return;

            const card = document.createElement('div');
            card.className = "glass p-6 rounded-[32px] border-white/5";
            card.innerHTML = `
                <div class="flex items-center gap-2 mb-6">
                    <i data-lucide="${cat.icon}" class="w-4 h-4 text-accent"></i>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 italic">${cat.title}</span>
                </div>
                <div class="space-y-4">
                    ${list.slice(0, 5).map((p, i) => `
                        <div class="flex items-center justify-between group cursor-pointer" onclick="window.location.hash = 'player/${p.player.id}'">
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] font-black text-slate-500">${i + 1}</span>
                                <span class="text-xs font-black uppercase italic group-hover:text-accent transition-colors">${p.player.name}</span>
                            </div>
                            <span class="text-xs font-black tabular-nums text-white">${p.statistics[0].goals.total || p.statistics[0].cards.yellow || p.statistics[0].cards.red || 0}</span>
                        </div>
                    `).join('')}
                </div>
            `;
            container.appendChild(card);
        });
        if (window.lucide) lucide.createIcons();
    } catch (e) { console.error("Error rendering top stats", e); }
}

async function renderLeagueDetails(leagueId) {
    const res = await fetch(`/api/standings/${leagueId}`);
    const standings = await res.json();

    if (standings.error) {
        viewContainer.innerHTML = `<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">${standings.error}</div>`;
        return;
    }

    let html = `
        <div class="mb-8 flex items-center gap-4">
             <button onclick="window.history.back()" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
             </button>
             <h2 class="text-3xl font-black italic uppercase tracking-tight">Classifica ${standings[0]?.league_name || ''}</h2>
        </div>

        <div class="glass rounded-[40px] border-white/5 overflow-hidden shadow-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="border-b border-white/10 text-slate-500 uppercase text-[10px] font-black tracking-widest bg-white/5">
                            <th class="py-6 px-6">Pos</th>
                            <th class="py-6 px-6">Squadra</th>
                            <th class="py-6 px-6 text-center">P</th>
                            <th class="py-6 px-6 text-center">V</th>
                            <th class="py-6 px-6 text-center">N</th>
                            <th class="py-6 px-6 text-center">P</th>
                            <th class="py-6 px-6 text-center">+/-</th>
                            <th class="py-6 px-6 text-center font-bold text-white">PTS</th>
                            <th class="py-6 px-6">Ultime 5</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
    `;

    standings.forEach(row => {
        const formHtml = (row.form || '').split('').map(f => {
            let color = 'bg-slate-500';
            if (f === 'W') color = 'bg-success';
            if (f === 'L') color = 'bg-danger';
            if (f === 'D') color = 'bg-warning';
            return `<span class="w-2 h-2 rounded-full ${color}" title="${f}"></span>`;
        }).join('');

        html += `
            <tr class="hover:bg-white/5 transition-colors cursor-pointer" onclick="window.location.hash = 'team/${row.team_id}'">
                <td class="py-5 px-6 font-black text-accent tabular-nums">${row.rank}</td>
                <td class="py-5 px-6">
                    <div class="flex items-center gap-4">
                        <img src="${row.team_logo}" class="w-8 h-8 object-contain">
                        <span class="font-black uppercase italic tracking-tight">${row.team_name}</span>
                    </div>
                </td>
                <td class="py-5 px-6 text-center font-bold tabular-nums opacity-60">${row.played}</td>
                <td class="py-5 px-6 text-center font-bold tabular-nums opacity-60">${row.win}</td>
                <td class="py-5 px-6 text-center font-bold tabular-nums opacity-60">${row.draw}</td>
                <td class="py-5 px-6 text-center font-bold tabular-nums opacity-60">${row.lose}</td>
                <td class="py-5 px-6 text-center font-bold tabular-nums opacity-60">${row.goals_diff}</td>
                <td class="py-5 px-6 text-center font-black tabular-nums text-lg text-white">${row.points}</td>
                <td class="py-5 px-6">
                    <div class="flex gap-1.5">${formHtml}</div>
                </td>
            </tr>
        `;
    });

    html += `</tbody></table></div></div>

    <div class="mt-12 grid grid-cols-1 md:grid-cols-2 gap-10" id="league-stats-container">
        <div class="glass p-10 rounded-[48px] border-white/5 animate-pulse">
            <div class="h-6 bg-white/5 rounded w-1/3 mb-6"></div>
            <div class="space-y-4">
                <div class="h-10 bg-white/5 rounded"></div>
                <div class="h-10 bg-white/5 rounded"></div>
                <div class="h-10 bg-white/5 rounded"></div>
            </div>
        </div>
    </div>`;

    viewContainer.innerHTML = html;
    await renderLeagueStats(leagueId);
}

async function renderLeagueStats(leagueId) {
    const container = document.getElementById('league-stats-container');
    if (!container) return;

    try {
        const res = await fetch(`/api/leagues/stats/${leagueId}`);
        const data = await res.json();

        let html = '';
        const sections = [
            { title: 'Top Scorers', key: 'scorers', icon: 'zap' },
            { title: 'Top Assists', key: 'assists', icon: 'star' }
        ];

        sections.forEach(sec => {
            const list = data[sec.key]?.stats_json || [];
            html += `
                <div class="glass p-8 rounded-[40px] border-white/5">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 mb-8 flex items-center gap-2 italic">
                        <i data-lucide="${sec.icon}" class="w-4 h-4 text-accent"></i> ${sec.title}
                    </h3>
                    <div class="space-y-6">
                        ${list.slice(0, 5).map((p, idx) => `
                            <div class="flex items-center gap-4 group cursor-pointer" onclick="window.location.hash = 'player/${p.player.id}'">
                                <span class="text-xl font-black italic opacity-20 tabular-nums">${idx + 1}</span>
                                <div class="w-10 h-10 rounded-2xl bg-white/5 overflow-hidden border border-white/5 p-1">
                                    <img src="${p.player.photo}" class="w-full h-full object-cover rounded-xl">
                                </div>
                                <div>
                                    <div class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors">${p.player.name}</div>
                                    <div class="text-[9px] font-bold text-slate-500 uppercase">${p.statistics[0].team.name}</div>
                                </div>
                                <div class="ml-auto text-2xl font-black italic tabular-nums text-accent">
                                    ${sec.key === 'scorers' ? p.statistics[0].goals.total : p.statistics[0].goals.assists}
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html || '<div class="col-span-2 text-center text-slate-500 font-bold uppercase italic">Statistiche non ancora sincronizzate.</div>';
        if (window.lucide) lucide.createIcons();
    } catch (e) { console.error("Error rendering league stats", e); }
}

async function renderPredictions() {
    const res = await fetch('/api/predictions/all');
    const predictions = await res.json();

    if (predictions.error || !predictions.length) {
        viewContainer.innerHTML = `<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Nessun pronostico generato recentemente.</div>`;
        return;
    }

    const filtered = predictions.filter(p => {
        const matchesCountry = selectedCountry === 'all' || (p.country_name || 'International') === selectedCountry;
        // As predictions might not have bookmaker info directly yet, we filter primarily by country
        return matchesCountry;
    });

    let html = `
        <div class="mb-12">
            <h1 class="text-4xl font-black italic uppercase tracking-tighter mb-2">Pronostici AI</h1>
            <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic">Suggerimenti algoritmici basati su performance e big data per ${selectedCountry === 'all' ? 'tutte le nazioni' : selectedCountry}.</p>
        </div>

        <div id="predictions-grid" class="grid grid-cols-1 md:grid-cols-2 gap-8">
            ${filtered.length > 0 ? filtered.map(p => {
        const dateStr = new Date(p.date).toLocaleString('it-IT', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        return `
                    <div class="glass p-8 rounded-[40px] border-white/5 hover:border-accent/30 transition-all group relative overflow-hidden prediction-card active-card">
                        <div class="absolute top-0 right-0 p-8 opacity-5 group-hover:opacity-10 transition-all scale-150">
                            <i data-lucide="brain-circuit" class="w-20 h-20"></i>
                        </div>

                        <div class="flex items-center justify-between mb-6">
                            <span class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 italic">${p.league_name}</span>
                            <span class="text-[10px] font-black uppercase text-accent tracking-widest">${dateStr}</span>
                        </div>

                        <div class="flex items-center justify-between gap-4 mb-8">
                            <div class="flex-1 flex flex-col items-center gap-3">
                                 <img src="${p.home_logo}" class="w-12 h-12 object-contain">
                                 <span class="text-xs font-black uppercase text-center truncate w-full">${p.home_name}</span>
                            </div>
                            <div class="flex flex-col items-center">
                                <span class="text-2xl font-black italic opacity-20">VS</span>
                            </div>
                            <div class="flex-1 flex flex-col items-center gap-3">
                                 <img src="${p.away_logo}" class="w-12 h-12 object-contain">
                                 <span class="text-xs font-black uppercase text-center truncate w-full">${p.away_name}</span>
                            </div>
                        </div>

                        <div class="bg-accent/10 border border-accent/20 p-5 rounded-3xl mb-6">
                            <div class="text-[9px] font-black text-accent uppercase tracking-widest mb-1">AI Suggestion</div>
                            <div class="text-lg font-black text-white italic uppercase">${p.advice}</div>
                        </div>

                        <button class="w-full py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-[10px] uppercase tracking-widest transition-all" onclick="window.location.hash = 'match/${p.fixture_id}'">
                            Apri Match Center
                        </button>
                    </div>
                `;
    }).join('') : `<div class="col-span-2 glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Nessun pronostico trovato per i filtri attuali.</div>`}
        </div>
    `;

    viewContainer.innerHTML = html;
    if (window.lucide) lucide.createIcons();
}

async function renderTracker() {
    viewContainer.innerHTML = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10" id="tracker-stats-summary"></div>
        <div class="glass rounded-[40px] border-white/5 overflow-hidden divide-y divide-white/5" id="tracker-history"></div>
    `;

    await fetchHistory();
    updateTrackerSummary();
    renderFullHistory();
}

async function renderMatchCenter(fixtureId) {
    const res = await fetch(`/api/match/${fixtureId}`);
    const data = await res.json();

    if (data.error) {
        viewContainer.innerHTML = `<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">${data.error}</div>`;
        return;
    }

    const f = data.fixture;
    const dateStr = new Date(f.date).toLocaleString('it-IT', { weekday: 'short', day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });

    let html = `
        <div class="mb-8 flex items-center gap-4">
             <button onclick="window.location.hash = 'dashboard'" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
             </button>
             <div class="flex flex-col">
                <h2 class="text-2xl font-black italic uppercase tracking-tight leading-none">${f.team_home_name} VS ${f.team_away_name}</h2>
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em]">${f.league_name} | ${dateStr}</span>
             </div>
        </div>

        <div class="glass rounded-[48px] border-white/5 overflow-hidden mb-10">
            <div class="p-10 md:p-16 flex flex-col md:flex-row items-center justify-between gap-12 bg-gradient-to-br from-accent/5 to-transparent">
                <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group" onclick="window.location.hash = 'team/${f.team_home_id}'">
                    <div class="w-24 h-24 md:w-32 md:h-32 glass rounded-[48px] p-6 flex items-center justify-center group-hover:scale-110 transition-transform border-white/10">
                        <img src="${f.team_home_logo}" class="w-full h-full object-contain">
                    </div>
                    <span class="text-xl font-black uppercase italic tracking-tight text-center group-hover:text-accent transition-colors">${f.team_home_name}</span>
                </div>

                <div class="flex flex-col items-center gap-4">
                    <div class="text-6xl md:text-8xl font-black tracking-tighter text-white tabular-nums flex items-center gap-4">
                        ${f.score_home ?? 0} <span class="text-accent/20 font-light">-</span> ${f.score_away ?? 0}
                    </div>
                    <div class="px-6 py-2 rounded-2xl bg-white/5 border border-white/10 flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full ${f.status_short === 'NS' ? 'bg-slate-500' : 'bg-danger animate-pulse'}"></div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-300">${f.status_long}</span>
                        ${f.elapsed ? `<span class="text-[10px] font-black text-accent">${f.elapsed}'</span>` : ''}
                    </div>
                </div>

                <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group" onclick="window.location.hash = 'team/${f.team_away_id}'">
                    <div class="w-24 h-24 md:w-32 md:h-32 glass rounded-[48px] p-6 flex items-center justify-center group-hover:scale-110 transition-transform border-white/10">
                        <img src="${f.team_away_logo}" class="w-full h-full object-contain">
                    </div>
                    <span class="text-xl font-black uppercase italic tracking-tight text-center group-hover:text-accent transition-colors">${f.team_away_name}</span>
                </div>
            </div>

            <nav class="flex border-t border-white/5 overflow-x-auto no-scrollbar">
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all text-accent border-b-2 border-accent" data-tab="analysis">Intelligence</button>
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all text-slate-500" data-tab="info">Info</button>
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all text-slate-500" data-tab="odds">Quote</button>
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all text-slate-500" data-tab="lineups">Formazioni</button>
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all text-slate-500" data-tab="stats">Statistiche</button>
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic hover:bg-white/5 transition-all text-slate-500" data-tab="h2h">Testa a Testa</button>
            </nav>
        </div>

        <div id="match-view-content" class="min-h-[400px]"></div>
    `;
    viewContainer.innerHTML = html;

    // Tab logic
    const tabs = document.querySelectorAll('[data-tab]');
    tabs.forEach(t => {
        t.onclick = () => {
            tabs.forEach(btn => {
                btn.classList.remove('text-accent', 'border-b-2', 'border-accent');
                btn.classList.add('text-slate-500');
            });
            t.classList.add('text-accent', 'border-b-2', 'border-accent');
            t.classList.remove('text-slate-500');
            renderMatchTab(t.dataset.tab, fixtureId, data);
        };
    });

    // Default tab
    renderMatchTab('analysis', fixtureId, data);
}

async function renderMatchTab(tab, fixtureId, matchData) {
    const container = document.getElementById('match-view-content');
    container.innerHTML = '<div class="flex justify-center py-20"><i data-lucide="loader-2" class="w-8 h-8 text-accent rotator"></i></div>';
    if (window.lucide) lucide.createIcons();

    switch (tab) {
        case 'analysis':
            await renderMatchAnalysis(fixtureId);
            break;
        case 'lineups':
            renderMatchLineups(matchData.lineups, matchData.injuries);
            break;
        case 'stats':
            renderMatchStats(matchData.statistics);
            break;
        case 'h2h':
            renderMatchH2H(matchData.h2h);
            break;
        case 'odds':
            renderMatchOdds(matchData.odds);
            break;
        case 'info':
            renderMatchInfo(matchData.fixture);
            break;
    }
    if (window.lucide) lucide.createIcons();
}

async function renderMatchAnalysis(id) {
    const container = document.getElementById('match-view-content');
    try {
        const res = await fetch(`/api/predictions/${id}`);
        const data = await res.json();
        if (data.error) { container.innerHTML = `<div class="glass p-10 text-center font-bold text-slate-500 uppercase">${data.error}</div>`; return; }

        const comp = data.comparison || {};
        const perc = data.percent || {};
        const metrics = [
            { label: 'Forma', key: 'form' }, { label: 'Attacco', key: 'attaching' },
            { label: 'Difesa', key: 'defensive' }, { label: 'Probabilità Gol', key: 'poisson_distribution' }
        ];

        let metricsHtml = metrics.map(m => {
            const val = comp[m.key] || { home: '50%', away: '50%' };
            const h = parseInt(val.home); const a = parseInt(val.away);
            return `
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <span>${h}%</span> <span class="text-white italic font-bold">${m.label}</span> <span>${a}%</span>
                    </div>
                    <div class="h-1.5 bg-white/5 rounded-full overflow-hidden flex">
                        <div class="h-full bg-accent" style="width: ${h}%"></div>
                        <div class="h-full bg-success/50" style="width: ${a}%"></div>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <div class="space-y-8">
                    <div class="glass p-10 rounded-[48px] border-white/5 bg-accent/5">
                        <div class="text-[9px] font-black text-accent uppercase tracking-[0.2em] mb-4 italic flex items-center gap-2">
                            <i data-lucide="brain-circuit" class="w-4 h-4"></i> Algorithmic Prediction
                        </div>
                        <div class="text-3xl font-black text-white italic tracking-tight uppercase leading-tight mb-8">${data.advice}</div>

                        <div class="grid grid-cols-3 gap-6">
                            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                                <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">1 (Home)</span>
                                <span class="text-3xl font-black text-white tabular-nums">${perc.home}</span>
                            </div>
                            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                                <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">X (Draw)</span>
                                <span class="text-3xl font-black text-white tabular-nums">${perc.draw}</span>
                            </div>
                            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                                <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">2 (Away)</span>
                                <span class="text-3xl font-black text-white tabular-nums">${perc.away}</span>
                            </div>
                        </div>
                    </div>

                    <button class="w-full py-6 rounded-3xl bg-accent text-white font-black uppercase tracking-widest italic shadow-2xl shadow-accent/20 hover:scale-[1.01] transition-all" onclick="analyzeMatch(${id})">
                        Analizza con Gemini AI Live
                    </button>
                </div>

                <div class="glass p-10 rounded-[48px] border-white/5 space-y-8">
                    <h3 class="text-[10px] font-black text-white uppercase tracking-[0.2em] mb-2 italic">Data Insights</h3>
                    ${metricsHtml}
                </div>
            </div>
        `;
    } catch (e) { container.innerHTML = "Dati analisi non disponibili."; }
}

function renderMatchLineups(lineups, injuries) {
    const container = document.getElementById('match-view-content');
    if (!lineups || !lineups.length) {
        container.innerHTML = '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Formazioni non ancora disponibili.</div>';
        return;
    }

    let html = `<div class="grid grid-cols-1 md:grid-cols-2 gap-10">`;
    lineups.forEach(l => {
        html += `
            <div class="glass p-8 rounded-[40px] border-white/5">
                <div class="flex items-center justify-between mb-8">
                     <h3 class="text-xl font-black italic uppercase italic text-white">${l.team_name}</h3>
                     <span class="px-4 py-1.5 rounded-xl bg-accent/10 text-accent text-[10px] font-black uppercase tracking-widest border border-accent/20">${l.formation}</span>
                </div>

                <div class="space-y-4">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-4 italic">Titolari (Starting XI)</div>
                    ${(l.start_xi_json || []).map(p => `
                        <div class="flex items-center gap-4 group cursor-pointer" onclick="window.location.hash = 'player/${p.player.id}'">
                            <span class="w-6 text-accent font-black tabular-nums italic">${p.player.number || '-'}</span>
                            <span class="font-black uppercase italic tracking-tight text-white group-hover:text-accent transition-colors">${p.player.name}</span>
                            <span class="text-[9px] font-bold text-slate-500 uppercase ml-auto">${p.player.pos}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    });
    html += `</div>`;
    container.innerHTML = html;
}

function renderMatchInfo(f) {
    const container = document.getElementById('match-view-content');
    let html = `
        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="glass p-10 rounded-[48px] border-white/5 space-y-8">
                <div>
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-4 italic flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-accent"></i> Stadio
                    </h3>
                    <div class="text-2xl font-black text-white italic uppercase mb-2">${f.venue_name || 'N/A'}</div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">${f.venue_city || ''}</div>
                </div>

                <div>
                    <h3 class="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-4 italic flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-accent"></i> Match Info
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center border-b border-white/5 pb-4">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Campionato</span>
                            <span class="text-xs font-black text-white uppercase italic">${f.league_name}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-white/5 pb-4">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Round</span>
                            <span class="text-xs font-black text-white uppercase italic">${f.round}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-white/5 pb-4">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Status</span>
                            <span class="text-xs font-black text-white uppercase italic">${f.status_long}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="glass p-10 rounded-[48px] border-white/5 bg-accent/5 flex flex-col items-center justify-center text-center">
                <i data-lucide="calendar" class="w-12 h-12 text-accent mb-6"></i>
                <div class="text-[10px] font-black uppercase tracking-[0.2em] text-accent mb-2 italic">Data e Ora</div>
                <div class="text-4xl font-black text-white italic tracking-tighter uppercase leading-none mb-4">
                    ${new Date(f.date).toLocaleDateString('it-IT', { day: '2-digit', month: 'long' })}
                </div>
                <div class="text-2xl font-black text-slate-400 tabular-nums uppercase italic">
                    ${new Date(f.date).toLocaleTimeString('it-IT', { hour: '2-digit', minute: '2-digit' })}
                </div>
            </div>
        </div>
    `;
    container.innerHTML = html;
    if (window.lucide) lucide.createIcons();
}

function renderMatchOdds(odds) {
    const container = document.getElementById('match-view-content');
    if (!odds || !odds.length) {
        container.innerHTML = '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Quote non disponibili per questo match.</div>';
        return;
    }

    let html = `<div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">`;

    odds.forEach(o => {
        const oddsData = JSON.parse(o.odds_json) || [];
        html += `
            <div class="glass p-8 rounded-[40px] border-white/5 relative overflow-hidden">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex flex-col">
                        <span class="text-[8px] font-black uppercase text-slate-500 tracking-widest italic mb-1">Bookmaker</span>
                        <div class="text-lg font-black text-white italic uppercase">${o.bookmaker_name || 'Bookmaker'}</div>
                    </div>
                    <div class="px-4 py-1.5 rounded-xl bg-accent/10 text-accent text-[9px] font-black uppercase tracking-widest border border-accent/20">${o.bet_name || 'Mercato'}</div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    ${oddsData.map(val => `
                        <div class="bg-white/5 p-4 rounded-2xl border border-white/5 flex flex-col items-center justify-center gap-1 group hover:border-accent/50 transition-all cursor-pointer">
                            <span class="text-[9px] font-black uppercase text-slate-500 tracking-widest group-hover:text-accent transition-colors">${val.value}</span>
                            <span class="text-xl font-black text-white tabular-nums">${val.odd}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    });

    html += `</div>`;
    container.innerHTML = html;
}

function renderMatchStats(stats) {
    const container = document.getElementById('match-view-content');
    if (!stats || !stats.length) {
        container.innerHTML = '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Statistiche live non disponibili.</div>';
        return;
    }

    // Simplified stats display
    let html = `<div class="glass p-10 rounded-[40px] border-white/5 max-w-2xl mx-auto space-y-8">`;

    // Group stats by type
    const s1 = typeof stats[0].stats_json === 'string' ? JSON.parse(stats[0].stats_json) : (stats[0].stats_json || []);
    const s2 = typeof stats[1].stats_json === 'string' ? JSON.parse(stats[1].stats_json) : (stats[1].stats_json || []);

    s1.forEach((st, idx) => {
        const v1 = st.value || 0;
        const v2 = s2[idx]?.value || 0;
        const p1 = (parseInt(v1) + parseInt(v2)) > 0 ? (parseInt(v1) / (parseInt(v1) + parseInt(v2)) * 100) : 50;

        html += `
            <div class="space-y-2">
                <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                    <span class="text-white font-black">${v1}</span>
                    <span class="italic opacity-60">${st.type}</span>
                    <span class="text-white font-black">${v2}</span>
                </div>
                <div class="h-1.5 bg-white/5 rounded-full overflow-hidden flex">
                    <div class="h-full bg-accent" style="width: ${p1}%"></div>
                    <div class="h-full bg-white/10" style="width: ${100 - p1}%"></div>
                </div>
            </div>
        `;
    });

    html += `</div>`;
    container.innerHTML = html;
}

function renderMatchH2H(h2h) {
    const container = document.getElementById('match-view-content');
    if (!h2h || !h2h.h2h_json) {
        container.innerHTML = '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Dati storici non disponibili.</div>';
        return;
    }

    const matches = h2h.h2h_json || [];
    let html = `<div class="max-w-4xl mx-auto glass rounded-[40px] border-white/5 overflow-hidden divide-y divide-white/5">`;

    matches.slice(0, 10).forEach(m => {
        const date = new Date(m.fixture.date).toLocaleDateString();
        html += `
            <div class="p-8 flex items-center justify-between group hover:bg-white/5 transition-all">
                <div class="flex-1 text-right">
                    <span class="font-black uppercase italic tracking-tight text-white">${m.teams.home.name}</span>
                </div>
                <div class="flex flex-col items-center gap-1 mx-8">
                    <div class="text-2xl font-black tabular-nums text-accent">${m.goals.home} - ${m.goals.away}</div>
                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">${date}</span>
                </div>
                <div class="flex-1 text-left">
                    <span class="font-black uppercase italic tracking-tight text-white">${m.teams.away.name}</span>
                </div>
            </div>
        `;
    });

    html += `</div>`;
    container.innerHTML = html;
}

async function renderTeamProfile(teamId) {
    const res = await fetch(`/api/team/${teamId}`);
    const data = await res.json();

    if (data.error) {
        viewContainer.innerHTML = `<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">${data.error}</div>`;
        return;
    }

    const t = data.team;
    const c = data.coach;
    const squad = data.squad || [];
    const stats = data.statistics;

    let html = `
        <div class="mb-8 flex items-center gap-4">
             <button onclick="window.history.back()" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
             </button>
             <h2 class="text-3xl font-black italic uppercase tracking-tight">Profilo Squadra</h2>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="flex flex-col md:flex-row items-center gap-8 mb-12">
                <div class="w-40 h-40 bg-white rounded-[48px] p-8 shadow-2xl border border-white/10 flex items-center justify-center">
                    <img src="${t.logo}" class="w-full h-full object-contain">
                </div>
                <div class="text-center md:text-left">
                    <h1 class="text-6xl font-black tracking-tighter text-white uppercase italic mb-2">${t.name}</h1>
                    <div class="flex flex-wrap justify-center md:justify-start gap-4">
                        <span class="text-xs font-black uppercase tracking-widest text-slate-500">${t.country}</span>
                        <span class="text-xs font-black uppercase tracking-widest text-slate-500">•</span>
                        <span class="text-xs font-black uppercase tracking-widest text-slate-500">Founded: ${t.founded}</span>
                    </div>
                </div>
            </div>

                ${stats ? `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-12">
                    <div class="glass p-6 rounded-3xl border-white/5 text-center">
                        <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Giocate</span>
                        <span class="text-3xl font-black text-white tabular-nums">${stats.played}</span>
                    </div>
                    <div class="glass p-6 rounded-3xl border-white/5 text-center">
                        <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Vinte</span>
                        <span class="text-3xl font-black text-success tabular-nums">${stats.wins}</span>
                    </div>
                    <div class="glass p-6 rounded-3xl border-white/5 text-center">
                        <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Pareggi</span>
                        <span class="text-3xl font-black text-warning tabular-nums">${stats.draws}</span>
                    </div>
                    <div class="glass p-6 rounded-3xl border-white/5 text-center">
                        <span class="text-[9px] font-black uppercase text-slate-500 block mb-1">Perse</span>
                        <span class="text-3xl font-black text-danger tabular-nums">${stats.losses}</span>
                    </div>
                </div>
                ` : ''}

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
                    <div class="glass p-8 rounded-[40px] border-white/5">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 mb-6 flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-4 h-4 text-accent"></i> Stadio
                    </h3>
                    <div class="text-2xl font-black text-white italic uppercase mb-2">${t.venue_name}</div>
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">Capacità: ${t.venue_capacity}</div>
                </div>

                <div class="glass p-8 rounded-[40px] border-white/5">
                    <h3 class="text-xs font-black uppercase tracking-[0.2em] text-slate-500 mb-6 flex items-center gap-2">
                        <i data-lucide="user" class="w-4 h-4 text-accent"></i> Allenatore
                    </h3>
                    ${c ? `
                    <div class="flex items-center gap-4">
                        <img src="${c.photo}" class="w-16 h-16 rounded-3xl border border-accent/20 object-cover">
                        <div>
                            <div class="text-2xl font-black text-white italic uppercase">${c.name}</div>
                            <div class="text-xs font-bold text-slate-500 uppercase tracking-widest">${c.nationality} | ${c.age} anni</div>
                        </div>
                    </div>
                    ` : '<div class="text-slate-500 italic font-bold uppercase">Coach non disponibile</div>'}
                </div>
            </div>

            <div class="glass rounded-[48px] border-white/5 overflow-hidden">
                <div class="p-8 border-b border-white/5 bg-white/5 flex items-center justify-between">
                    <h3 class="text-xl font-black italic uppercase tracking-tight">Rosa Squadra</h3>
                    <span class="bg-accent/10 text-accent px-4 py-1 rounded-xl text-[10px] font-black uppercase tracking-widest">${squad.length} Giocatori</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="text-slate-500 uppercase text-[9px] font-black tracking-widest">
                                <th class="py-4 px-8">#</th>
                                <th class="py-4 px-8">Giocatore</th>
                                <th class="py-4 px-8">Posizione</th>
                                <th class="py-4 px-8 text-right">Azione</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            ${squad.map(p => `
                                <tr class="hover:bg-white/5 transition-colors group">
                                    <td class="py-4 px-8 font-black text-accent tabular-nums">${p.number || '-'}</td>
                                    <td class="py-4 px-8">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-white/5 overflow-hidden border border-white/5">
                                                <img src="${p.photo}" class="w-full h-full object-cover">
                                            </div>
                                            <span class="font-black uppercase italic tracking-tight text-white">${p.name}</span>
                                        </div>
                                    </td>
                                    <td class="py-4 px-8 font-bold text-slate-500 uppercase text-[10px] tracking-widest">${p.position}</td>
                                    <td class="py-4 px-8 text-right">
                                        <button class="px-4 py-2 rounded-xl bg-white/5 group-hover:bg-accent group-hover:text-white transition-all text-[9px] font-black uppercase tracking-widest border border-white/5" onclick="window.location.hash = 'player/${p.id}'">Dettagli</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    `;
    viewContainer.innerHTML = html;
}

async function renderPlayerProfile(playerId) {
    const res = await fetch(`/api/player/${playerId}`);
    const data = await res.json();

    if (!data || data.error) {
        viewContainer.innerHTML = `<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">${data.error || "Dati giocatore non disponibili."}</div>`;
        return;
    }

    const p = data.player;
    const stats = data.statistics;

    let html = `
        <div class="mb-8 flex items-center gap-4">
             <button onclick="window.history.back()" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
             </button>
             <h2 class="text-3xl font-black italic uppercase tracking-tight">Profilo Giocatore</h2>
        </div>

        <div class="max-w-4xl mx-auto">
            <div class="glass p-12 rounded-[56px] border-white/5 mb-12 relative overflow-hidden">
                <div class="absolute top-0 right-0 p-12 opacity-5 scale-150">
                    <i data-lucide="user" class="w-64 h-64"></i>
                </div>

                <div class="flex flex-col md:flex-row items-center gap-12 relative z-10">
                    <div class="w-56 h-56 rounded-[56px] border-4 border-accent shadow-2xl overflow-hidden p-2 bg-slate-800">
                        <img src="${p.photo}" class="w-full h-full object-cover rounded-[48px]">
                    </div>
                    <div class="text-center md:text-left flex-1">
                        <div class="bg-accent/10 text-accent px-4 py-1.5 rounded-2xl text-[10px] font-black uppercase tracking-widest border border-accent/20 inline-block mb-4 italic">${p.nationality}</div>
                        <h1 class="text-6xl font-black tracking-tighter text-white uppercase italic mb-6 leading-none">${p.name}</h1>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-8">
                            <div>
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Età</span>
                                <span class="text-2xl font-black text-white tabular-nums italic">${p.age}</span>
                            </div>
                            <div>
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Altezza</span>
                                <span class="text-2xl font-black text-white tabular-nums italic">${p.height || '-'}</span>
                            </div>
                            <div>
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Peso</span>
                                <span class="text-2xl font-black text-white tabular-nums italic">${p.weight || '-'}</span>
                            </div>
                            <div>
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Infortunato</span>
                                <span class="text-2xl font-black ${p.injured ? 'text-danger' : 'text-success'} italic uppercase tracking-tighter">${p.injured ? 'Sì' : 'No'}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            ${stats ? `
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-12">
                 <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <span class="text-[9px] font-black uppercase text-slate-500 block mb-2">Presenze</span>
                    <div class="text-4xl font-black text-white italic tabular-nums">${stats.stats_json[0]?.games?.appearences || 0}</div>
                 </div>
                 <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <span class="text-[9px] font-black uppercase text-slate-500 block mb-2">Gol</span>
                    <div class="text-4xl font-black text-success italic tabular-nums">${stats.stats_json[0]?.goals?.total || 0}</div>
                 </div>
                 <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <span class="text-[9px] font-black uppercase text-slate-500 block mb-2">Assist</span>
                    <div class="text-4xl font-black text-accent italic tabular-nums">${stats.stats_json[0]?.goals?.assists || 0}</div>
                 </div>
                 <div class="glass p-8 rounded-[40px] border-white/5 text-center">
                    <span class="text-[9px] font-black uppercase text-slate-500 block mb-2">Rating</span>
                    <div class="text-4xl font-black text-warning italic tabular-nums">${stats.stats_json[0]?.games?.rating || '-'}</div>
                 </div>
            </div>
            ` : `
            <div class="glass p-12 rounded-[48px] border-white/5 text-center">
                 <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic">Sincronizzazione statistiche dettagliate in corso...</p>
            </div>
            `}

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="glass rounded-[48px] border-white/5 overflow-hidden">
                    <div class="p-8 border-b border-white/5 bg-white/5 flex items-center justify-between">
                        <h3 class="text-xl font-black italic uppercase tracking-tight">Trasferimenti</h3>
                        <i data-lucide="repeat" class="w-5 h-5 text-accent"></i>
                    </div>
                    <div class="p-8 space-y-6">
                        ${data.transfers?.length ? data.transfers.map(t => `
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-sm font-black text-white italic uppercase">${t.type || 'Transfer'}</span>
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">${new Date(t.transfer_date).toLocaleDateString()}</span>
                                </div>
                                <div class="w-10 h-10 rounded-full bg-accent/10 border border-accent/20 flex items-center justify-center">
                                    <i data-lucide="arrow-right-left" class="w-4 h-4 text-accent"></i>
                                </div>
                            </div>
                        `).join('') : '<p class="text-slate-500 italic text-sm">Nessun trasferimento registrato.</p>'}
                    </div>
                </div>

                <div class="glass rounded-[48px] border-white/5 overflow-hidden">
                    <div class="p-8 border-b border-white/5 bg-white/5 flex items-center justify-between">
                        <h3 class="text-xl font-black italic uppercase tracking-tight">Palmarès</h3>
                        <i data-lucide="award" class="w-5 h-5 text-warning"></i>
                    </div>
                    <div class="p-8 space-y-6">
                        ${data.trophies?.length ? data.trophies.map(t => `
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-sm font-black text-white italic uppercase">${t.league}</span>
                                    <span class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">${t.season} | ${t.place}</span>
                                </div>
                                <span class="text-[10px] font-black text-warning uppercase">${t.country}</span>
                            </div>
                        `).join('') : '<p class="text-slate-500 italic text-sm">Nessun trofeo registrato.</p>'}
                    </div>
                </div>
            </div>
        </div>
    `;
    viewContainer.innerHTML = html;
}

// --- FILTERS & MODALS ---

async function fetchFilterData() {
    try {
        const res = await fetch('/api/filters');
        allFilterData = await res.json();
    } catch (e) { console.error("Error fetching filters", e); }
}

function openCountryModal() {
    const modal = document.getElementById('country-modal');
    const list = document.getElementById('country-list');
    modal.classList.remove('hidden');

    const allNations = `
        <div onclick="setCountry('all')" class="p-4 rounded-3xl border border-white/5 hover:border-accent bg-white/5 transition-all cursor-pointer flex flex-col items-center gap-2 ${selectedCountry === 'all' ? 'border-accent bg-accent/10' : ''}">
            <div class="w-10 h-10 rounded-full bg-accent/20 flex items-center justify-center">
                <i data-lucide="globe" class="w-6 h-6 text-accent"></i>
            </div>
            <span class="text-[10px] font-black uppercase tracking-tighter text-center">Tutte le Nazioni</span>
        </div>
    `;

    list.innerHTML = allNations + allFilterData.countries.map(c => `
        <div onclick="setCountry('${c.name}')" class="p-4 rounded-3xl border border-white/5 hover:border-accent bg-white/5 transition-all cursor-pointer flex flex-col items-center gap-2 ${selectedCountry === c.name ? 'border-accent bg-accent/10' : ''}">
            <div class="w-10 h-10 rounded-lg overflow-hidden border border-white/10">
                <img src="${c.flag}" class="w-full h-full object-cover">
            </div>
            <span class="text-[10px] font-black uppercase tracking-tighter text-center">${c.name}</span>
        </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

function setCountry(name) {
    selectedCountry = name;
    localStorage.setItem('selected_country', name);

    const label = document.getElementById('selected-country-name');
    const flagPlaceholder = document.getElementById('selected-country-flag');

    if (name === 'all') {
        label.textContent = 'Tutte le Nazioni';
        flagPlaceholder.innerHTML = '<i data-lucide="globe" class="w-4 h-4 text-accent"></i>';
    } else {
        const c = allFilterData.countries.find(x => x.name === name);
        label.textContent = name;
        if (c && c.flag) {
            flagPlaceholder.innerHTML = `<img src="${c.flag}" class="w-5 h-5 rounded-sm object-cover">`;
        } else {
            flagPlaceholder.textContent = '🏳️';
        }
    }

    closeCountryModal();
    if (window.lucide) lucide.createIcons();
    refreshAllViews();
}

function closeCountryModal() { document.getElementById('country-modal').classList.add('hidden'); }

function openBookmakerModal() {
    const modal = document.getElementById('bookmaker-modal');
    const list = document.getElementById('bookmaker-list');
    modal.classList.remove('hidden');

    const bookies = [{ id: 'all', name: 'Tutti i Bookmaker' }, ...allFilterData.bookmakers];

    list.innerHTML = bookies.map(b => `
        <div onclick="setBookmaker('${b.id}', '${b.name}')" class="p-5 rounded-2xl border border-white/5 hover:border-accent bg-white/5 transition-all cursor-pointer flex items-center justify-between ${selectedBookmaker === b.id.toString() ? 'border-accent bg-accent/10' : ''}">
            <div class="flex items-center gap-3">
                <i data-lucide="landmark" class="w-4 h-4 text-accent"></i>
                <span class="font-black uppercase italic text-sm text-balance">${b.name}</span>
            </div>
            ${b.managed_matches ? `<span class="text-[9px] font-bold text-slate-500 uppercase">${b.managed_matches} match</span>` : ''}
        </div>
    `).join('');
    if (window.lucide) lucide.createIcons();
}

function setBookmaker(id, name) {
    selectedBookmaker = id.toString();
    localStorage.setItem('selected_bookmaker', selectedBookmaker);
    document.getElementById('selected-bookmaker-name').textContent = id === 'all' ? 'Tutti i Book' : name;
    closeBookmakerModal();
    refreshAllViews();
}

function closeBookmakerModal() { document.getElementById('bookmaker-modal').classList.add('hidden'); }

function refreshAllViews() {
    updateStatsSummary();
    if (currentView === 'dashboard') {
        renderDashboardMatches();
        renderDashboardHistory();
        renderDashboardPredictions();
    } else if (currentView === 'predictions') {
        renderPredictions();
    } else if (currentView === 'leagues') {
        renderLeagues();
    } else if (currentView === 'tracker') {
        renderTracker();
    }
}

// --- SHARED DATA FETCHERS & UPDATERS ---

async function fetchLive() {
    try {
        const res = await fetch('/api/live');
        const data = await res.json();
        liveMatches = data.response || [];

        // Update match states for notifications
        liveMatches.forEach(m => {
            const id = m.fixture.id;
            const prevState = matchStates[id];
            const currentEventsCount = (m.events || []).filter(ev => ev.type !== 'subst').length;

            if (prevState) {
                const goalsChanged = m.goals.home !== prevState.goals.home || m.goals.away !== prevState.goals.away;
                if (goalsChanged) {
                    pinnedMatches.add(id);
                    notificationSound.play().catch(() => { });
                    setTimeout(() => pinnedMatches.delete(id), 10000);
                }
            }
            matchStates[id] = { goals: { home: m.goals.home, away: m.goals.away }, eventsCount: currentEventsCount };
        });
    } catch (e) { console.error("Error fetching live data", e); }
}

async function fetchHistory() {
    try {
        const res = await fetch("/api/history");
        const data = await res.json();
        historyData = Array.isArray(data) ? data : [];
    } catch (e) {
        console.error("Error fetching history", e);
        historyData = [];
    }
}

function updateStatsSummary() {
    const summary = calculateStats();
    const container = document.getElementById('stats-summary');
    if (!container) return;

    const countryLabel = selectedCountry === 'all' ? 'Tutte' : selectedCountry;
    const bookmakerLabel = selectedBookmaker === 'all' ? 'Tutti i Book' : (allFilterData.bookmakers.find(b => b.id.toString() === selectedBookmaker)?.name || 'Filtro');

    container.innerHTML = `
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1">${summary.currentPortfolio.toFixed(2)}€</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Portafoglio</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1">${summary.winCount}W - ${summary.lossCount}L</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Filtro: ${countryLabel}</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1 ${summary.netProfit >= 0 ? 'text-success' : 'text-danger'}">${summary.netProfit >= 0 ? '+' : ''}${summary.netProfit.toFixed(2)}€</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Profitto Netto</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1">${summary.liveCount}</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Live Now</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1 text-warning">${summary.pendingCount}</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">In Sospeso</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1 text-success">${summary.roi.toFixed(1)}%</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">ROI | ${bookmakerLabel}</span>
        </div>
    `;
}

function calculateStats() {
    const data = Array.isArray(historyData) ? historyData : [];

    let pendingCount = 0; let winCount = 0; let lossCount = 0; let totalStake = 0; let netProfit = 0;
    const startingPortfolio = 100;

    // Global profit (ALWAYS based on ALL bets for portfolio)
    let globalNetProfit = 0;
    data.forEach(bet => {
        const stake = parseFloat(bet.stake) || 0;
        const odds = parseFloat(bet.odds) || 0;
        const status = (bet.status || "").toLowerCase();
        if (status === "won") globalNetProfit += stake * (odds - 1);
        else if (status === "lost") globalNetProfit -= stake;
    });

    // Filtered stats for other blocks
    const filteredHistory = data.filter(bet => {
        const countryName = bet.country || bet.country_name || 'International';
        const matchesCountry = selectedCountry === 'all' || countryName === selectedCountry;
        const matchesBookie = selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === selectedBookmaker;
        return matchesCountry && matchesBookie;
    });

    filteredHistory.forEach(bet => {
        const stake = parseFloat(bet.stake) || 0;
        const odds = parseFloat(bet.odds) || 0;
        const status = (bet.status || "").toLowerCase();

        if (status === "pending") pendingCount++;
        else if (status === "won") { winCount++; totalStake += stake; netProfit += stake * (odds - 1); }
        else if (status === "lost") { lossCount++; totalStake += stake; netProfit -= stake; }
    });

    const liveCount = liveMatches.filter(m => {
        const countryName = m.league.country || m.league.country_name;
        const matchesCountry = selectedCountry === 'all' || countryName === selectedCountry;
        const matchesBookie = selectedBookmaker === 'all' || (m.available_bookmakers || []).includes(parseInt(selectedBookmaker));
        return matchesCountry && matchesBookie;
    }).length;

    return {
        pendingCount, winCount, lossCount, netProfit, liveCount,
        roi: totalStake > 0 ? (netProfit / totalStake) * 100 : 0,
        currentPortfolio: startingPortfolio + globalNetProfit
    };
}

function renderDashboardMatches() {
    const container = document.getElementById('live-matches-list');
    if (!container) return;

    const filteredMatches = liveMatches.filter(m => {
        const countryName = m.league.country || m.league.country_name;
        const matchesCountry = selectedCountry === 'all' || countryName === selectedCountry;
        const matchesBookie = selectedBookmaker === 'all' || (m.available_bookmakers || []).includes(parseInt(selectedBookmaker));
        return matchesCountry && matchesBookie;
    });

    if (filteredMatches.length === 0) {
        const msg = selectedCountry === 'all' && selectedBookmaker === 'all' ? 'nessun match live' : `nessun match live per ${selectedCountry === 'all' ? 'Tutte le Nazioni' : selectedCountry}`;
        container.innerHTML = `<div class="glass p-10 rounded-[32px] text-center text-slate-500 font-black italic uppercase tracking-widest">${msg}</div>`;
        return;
    }

    filteredMatches.forEach(m => {
        const card = document.createElement('div');
        card.className = "glass rounded-[40px] p-8 border-white/5 hover:border-accent/30 transition-all group cursor-pointer";
        card.onclick = () => window.location.hash = `match/${m.fixture.id}`;
        card.innerHTML = `
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3 opacity-60">
                    <img src="${m.league.logo}" class="w-5 h-5 object-contain">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] italic">${m.league.name}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">${m.fixture.status.elapsed}'</span>
                    <div class="w-2 h-2 bg-danger rounded-full animate-ping"></div>
                </div>
            </div>
            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-8 mb-4 text-center">
                <div class="flex flex-col items-center gap-3">
                    <img src="${m.teams.home.logo}" class="w-16 h-16 object-contain">
                    <span class="text-sm font-black uppercase tracking-tight">${m.teams.home.name}</span>
                </div>
                <div class="text-5xl font-black italic tracking-tighter text-white">
                    ${m.goals.home} - ${m.goals.away}
                </div>
                <div class="flex flex-col items-center gap-3">
                    <img src="${m.teams.away.logo}" class="w-16 h-16 object-contain">
                    <span class="text-sm font-black uppercase tracking-tight">${m.teams.away.name}</span>
                </div>
            </div>
        `;
        container.appendChild(card);
    });
}

function renderDashboardHistory() {
    const container = document.getElementById('dashboard-history');
    if (!container) return;

    if (historyData.length === 0) {
        container.innerHTML = '<div class="p-10 text-center text-slate-500 font-bold text-xs">Nessuna attività recente.</div>';
        return;
    }

    historyData.slice(0, 5).forEach(h => {
        const item = document.createElement('div');
        item.className = "p-6 hover:bg-white/5 cursor-pointer transition-all";
        item.onclick = () => showBetDetails(h);
        item.innerHTML = `
            <div class="flex justify-between items-start mb-1">
                <span class="font-black text-xs uppercase tracking-tight italic truncate max-w-[120px] text-white">${h.match_name}</span>
                <span class="text-[9px] font-black uppercase italic ${h.status === 'won' ? 'text-success' : h.status === 'lost' ? 'text-danger' : 'text-warning'}">${h.status}</span>
            </div>
            <div class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">${h.market} @ ${h.odds}</div>
        `;
        container.appendChild(item);
    });
}

// --- UTILS ---

async function showIntelligenceInPage(id) {
    const container = document.getElementById('match-center-content');
    try {
        const res = await fetch(`/api/predictions/${id}`);
        const data = await res.json();

        if (data.error) {
            container.innerHTML = `<div class="glass p-10 text-center font-bold text-slate-500 uppercase">${data.error}</div>`;
            return;
        }

        const comp = data.comparison || {};
        const perc = data.percent || {};
        const metrics = [
            { label: 'Forma', key: 'form' }, { label: 'Attacco', key: 'attaching' },
            { label: 'Difesa', key: 'defensive' }, { label: 'Probabilità Gol', key: 'poisson_distribution' },
            { label: 'H2H', key: 'h2h' }, { label: 'Gol', key: 'goals' }
        ];

        let metricsHtml = metrics.map(m => {
            const val = comp[m.key] || { home: '50%', away: '50%' };
            const h = parseInt(val.home); const a = parseInt(val.away);
            return `
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <span>${h}%</span> <span class="text-white italic">${m.label}</span> <span>${a}%</span>
                    </div>
                    <div class="h-2 bg-white/5 rounded-full overflow-hidden flex">
                        <div class="h-full bg-accent" style="width: ${h}%"></div>
                        <div class="h-full bg-success/50" style="width: ${a}%"></div>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
                <div class="space-y-8">
                    <div class="glass p-10 rounded-[48px] border-white/5 bg-accent/5">
                        <div class="text-[10px] font-black text-accent uppercase tracking-[0.2em] mb-4 italic">Algorithmic Advice</div>
                        <div class="text-4xl font-black text-white italic tracking-tight uppercase leading-tight mb-8">${data.advice}</div>

                        <div class="grid grid-cols-3 gap-6">
                            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                                <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">Casa</span>
                                <span class="text-3xl font-black text-white tabular-nums">${perc.home}</span>
                            </div>
                            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                                <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">Pareggio</span>
                                <span class="text-3xl font-black text-white tabular-nums">${perc.draw}</span>
                            </div>
                            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 text-center">
                                <span class="text-[9px] font-black text-slate-500 uppercase block mb-1">Ospite</span>
                                <span class="text-3xl font-black text-white tabular-nums">${perc.away}</span>
                            </div>
                        </div>
                    </div>

                    <button class="w-full py-6 rounded-3xl bg-accent text-white font-black uppercase tracking-widest italic shadow-2xl shadow-accent/20 hover:scale-[1.02] transition-all" onclick="analyzeMatch(${id})">
                        Analizza con Gemini AI Intelligence
                    </button>
                </div>

                <div class="glass p-10 rounded-[48px] border-white/5">
                    <h3 class="text-xs font-black text-white uppercase tracking-[0.2em] mb-10 text-center italic">Advanced Metrics Comparison</h3>
                    <div class="space-y-8">${metricsHtml}</div>
                </div>
            </div>
        `;
    } catch (e) { container.innerHTML = "Errore durante il caricamento delle statistiche."; }
}

async function showBetDetails(bet) {
    // Reuse existing modal logic
    const modal = document.getElementById("analysis-modal");
    const body = document.getElementById("modal-body");
    const btn = document.getElementById("place-bet-btn");
    const confInd = document.getElementById('confidence-indicator');

    modal.classList.remove('hidden');
    btn.classList.add('hidden');
    confInd.classList.add('hidden');

    const profit = bet.status === "won" ? (parseFloat(bet.stake) * (parseFloat(bet.odds) - 1)) : (bet.status === "lost" ? -parseFloat(bet.stake) : 0);

    body.innerHTML = `
        <div class="mb-10">
            <h2 class="text-4xl font-black italic uppercase tracking-tighter text-white mb-2">${bet.match_name}</h2>
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">${new Date(bet.timestamp).toLocaleString()}</div>
        </div>

        <div class="bg-white/5 p-8 rounded-[40px] border border-white/5 mb-8">
            <div class="grid grid-cols-2 gap-8 mb-8">
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Mercato</span>
                    <span class="text-xl font-black text-accent uppercase italic">${bet.market}</span>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Quota</span>
                    <span class="text-3xl font-black text-white tabular-nums">${bet.odds}</span>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Status</span>
                    <span class="text-xl font-black uppercase italic ${bet.status === 'won' ? 'text-success' : bet.status === 'lost' ? 'text-danger' : 'text-warning'}">${bet.status}</span>
                </div>
                <div>
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-1">Profitto/Perdita</span>
                    <span class="text-3xl font-black tabular-nums ${profit >= 0 ? 'text-success' : 'text-danger'}">${profit >= 0 ? '+' : ''}${profit.toFixed(2)}€</span>
                </div>
            </div>

            ${bet.advice ? `
                <div class="pt-8 border-t border-white/5">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-3 italic">AI Analysis</span>
                    <div class="text-sm leading-relaxed text-slate-400 font-medium italic">${bet.advice}</div>
                </div>
            ` : ''}
        </div>

        <div class="flex gap-4">
            <button class="flex-1 py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-[10px] uppercase tracking-widest transition-all" onclick="closeModal()">
                Chiudi
            </button>
            <button class="flex-1 py-4 rounded-2xl bg-danger/10 hover:bg-danger/20 border border-danger/20 text-danger font-black text-[10px] uppercase tracking-widest transition-all" onclick="deleteBet(${bet.id})">
                Elimina Scommessa
            </button>
        </div>
    `;
    if (window.lucide) lucide.createIcons();
}

async function deleteBet(id) {
    if (!confirm('Sei sicuro di voler eliminare questa scommessa?')) return;
    try {
        const res = await fetch(`/api/bets/delete/${id}`);
        const result = await res.json();
        if (result.status === 'success') {
            closeModal();
            await fetchHistory();
            if (currentView === 'tracker') renderTracker();
            else if (currentView === 'dashboard') updateStatsSummary();
        }
    } catch (e) { console.error("Error deleting bet", e); }
}

async function deduplicateBets() {
    try {
        const res = await fetch('/api/bets/deduplicate');
        const result = await res.json();
        if (result.status === 'success') {
            await fetchHistory();
            if (currentView === 'tracker') renderTracker();
            else if (currentView === 'dashboard') updateStatsSummary();
        }
    } catch (e) { console.error("Error deduplicating bets", e); }
}

async function analyzeMatch(id) {
    // Reuse existing modal logic but adapted
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const btn = document.getElementById('place-bet-btn');
    const confInd = document.getElementById('confidence-indicator');
    const confVal = document.getElementById('confidence-val');
    const confBar = document.getElementById('confidence-bar');

    modal.classList.remove('hidden');
    body.innerHTML = '<div class="text-center py-20"><i data-lucide="loader-2" class="w-12 h-12 text-accent rotator mx-auto mb-4"></i><p class="font-black uppercase italic text-xs tracking-widest">Gemini AI Analysis in progress...</p></div>';
    btn.classList.add('hidden');
    confInd.classList.add('hidden');
    if (window.lucide) lucide.createIcons();

    try {
        const res = await fetch(`/api/analyze/${id}`);
        const data = await res.json();
        if (data.error) { body.innerHTML = `<div class="text-danger font-black text-center py-20 uppercase italic">${data.error}</div>`; return; }

        const prediction = data.prediction;
        let betData = null; let displayHtml = prediction;
        const jsonMatch = prediction.match(/```json\n?([\s\S]*?)\n?```/i);
        if (jsonMatch) { try { betData = JSON.parse(jsonMatch[1]); displayHtml = prediction.replace(/```json[\s\S]*?```/i, ''); } catch (e) { } }

        body.innerHTML = `
            <div class="max-h-[500px] overflow-y-auto pr-4 custom-scrollbar">
                <div class="text-slate-400 leading-relaxed font-medium mb-10 text-sm italic">${displayHtml}</div>
                ${betData ? `
                    <div class="bg-accent/10 p-8 rounded-[40px] border border-accent/20 relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-8 opacity-10">
                            <i data-lucide="zap" class="w-12 h-12"></i>
                        </div>
                        <div class="text-[9px] font-black text-accent uppercase tracking-widest mb-2 italic">Consiglio AI Premium</div>
                        <div class="text-3xl font-black text-white italic uppercase tracking-tighter mb-8 leading-none">${betData.advice}</div>
                        <div class="grid grid-cols-2 gap-8">
                            <div>
                                <span class="text-[8px] font-black uppercase tracking-widest text-slate-500 block mb-1">Mercato</span>
                                <span class="text-lg font-black text-white uppercase italic">${betData.market}</span>
                            </div>
                            <div>
                                <span class="text-[8px] font-black uppercase tracking-widest text-slate-500 block mb-1">Quota</span>
                                <span class="text-2xl font-black text-white tabular-nums">${betData.odds}</span>
                            </div>
                            <div>
                                <span class="text-[8px] font-black uppercase tracking-widest text-slate-500 block mb-1">Stake</span>
                                <span class="text-2xl font-black text-success tabular-nums">${betData.stake}€</span>
                            </div>
                            <div>
                                <span class="text-[8px] font-black uppercase tracking-widest text-slate-500 block mb-1">Urgenza</span>
                                <span class="text-lg font-black text-warning uppercase italic">${betData.urgency}</span>
                            </div>
                        </div>
                    </div>
                ` : '<div class="bg-warning/10 p-6 rounded-3xl border border-warning/20 text-warning font-black uppercase italic text-xs">AI Confidence too low for automatic betting.</div>'}
            </div>
        `;
        if (betData) {
            btn.classList.remove('hidden');
            btn.onclick = () => placeBet(id, data.match, betData);
            if (betData.confidence) {
                confInd.classList.remove('hidden');
                confVal.textContent = betData.confidence + '%';
                confBar.style.width = betData.confidence + '%';
            }
        }
        if (window.lucide) lucide.createIcons();
    } catch (e) { body.innerHTML = "Errore durante l'analisi."; }
}

async function placeBet(fixture_id, match, betData) {
    try {
        const matchName = typeof match === 'string' ? match : `${match.teams.home.name} vs ${match.teams.away.name}`;

        let payload = { fixture_id, match: matchName, ...betData };
        if (selectedBookmaker !== 'all') {
            payload.bookmaker_id = parseInt(selectedBookmaker);
            const bookie = allFilterData.bookmakers.find(b => b.id.toString() === selectedBookmaker);
            if (bookie) payload.bookmaker_name = bookie.name;
        }

        const res = await fetch('/api/place_bet', {
            method: 'POST',
            body: JSON.stringify(payload)
        });
        const result = await res.json();
        if (result.status === 'success') { closeModal(); fetchHistory(); if (currentView === 'dashboard') updateStatsSummary(); }
    } catch (e) { alert("Errore nell'invio della scommessa."); }
}

function closeModal() { document.getElementById('analysis-modal').classList.add('hidden'); }

async function renderTracker() {
    viewContainer.innerHTML = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10" id="tracker-stats-summary"></div>
        <div class="glass rounded-[40px] border-white/5 overflow-hidden divide-y divide-white/5" id="tracker-history"></div>
    `;

    await fetchHistory();
    updateTrackerSummary();
    renderFullHistory();
}

function updateTrackerSummary() {
    const summary = calculateStats();
    const container = document.getElementById('tracker-stats-summary');
    if (!container) return;

    container.innerHTML = `
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">Net Balance</span>
            <div class="text-4xl font-black italic tracking-tighter ${summary.netProfit >= 0 ? 'text-success' : 'text-danger'}">${summary.netProfit >= 0 ? '+' : ''}${summary.netProfit.toFixed(2)}€</div>
        </div>
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">ROI</span>
            <div class="text-4xl font-black italic tracking-tighter text-accent">${summary.roi.toFixed(1)}%</div>
        </div>
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">Success Rate</span>
            <div class="text-4xl font-black italic tracking-tighter text-white">${summary.winCount + summary.lossCount > 0 ? ((summary.winCount / (summary.winCount + summary.lossCount)) * 100).toFixed(0) : 0}%</div>
        </div>
        <div class="glass p-8 rounded-[40px] border-white/5">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 block mb-2">Bankroll</span>
            <div class="text-4xl font-black italic tracking-tighter text-white">${summary.currentPortfolio.toFixed(2)}€</div>
        </div>
    `;
}

function renderFullHistory() {
    const container = document.getElementById('tracker-history');
    if (!container) return;

    const data = Array.isArray(historyData) ? historyData : [];
    const filtered = data.filter(bet => {
        const matchesCountry = selectedCountry === 'all' || bet.country === selectedCountry;
        const matchesBookie = selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === selectedBookmaker;
        return matchesCountry && matchesBookie;
    });

    if (filtered.length === 0) {
        container.innerHTML = '<div class="p-20 text-center text-slate-500 font-black uppercase italic">Nessuna scommessa trovata per i filtri selezionati.</div>';
        return;
    }

    filtered.forEach(h => {
        const item = document.createElement('div');
        item.className = "p-8 hover:bg-white/5 cursor-pointer transition-all flex items-center justify-between group";
        item.onclick = () => showBetDetails(h);
        item.innerHTML = `
            <div class="flex items-center gap-6">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center border ${h.status === 'won' ? 'border-success bg-success/10 text-success' : h.status === 'lost' ? 'border-danger bg-danger/10 text-danger' : 'border-warning bg-warning/10 text-warning'}">
                    <i data-lucide="${h.status === 'won' ? 'check-circle' : h.status === 'lost' ? 'x-circle' : 'clock'}" class="w-6 h-6"></i>
                </div>
                <div>
                    <div class="text-xl font-black italic uppercase tracking-tight text-white group-hover:text-accent transition-colors">${h.match_name}</div>
                    <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">${h.market} @ ${h.odds} | ${new Date(h.timestamp).toLocaleDateString()}</div>
                </div>
            </div>
            <div class="text-right">
                <div class="text-2xl font-black italic tracking-tighter ${h.status === 'won' ? 'text-success' : h.status === 'lost' ? 'text-danger' : 'text-slate-500'}">
                    ${h.status === 'won' ? '+' + (h.stake * (h.odds - 1)).toFixed(2) : (h.status === 'lost' ? '-' + h.stake : '0.00')}€
                </div>
                <div class="text-[9px] font-black uppercase tracking-widest text-slate-500">Puntata: ${h.stake}€</div>
            </div>
        `;
        container.appendChild(item);
    });
    if (window.lucide) lucide.createIcons();
}

// --- SHARED DATA FETCHERS & UPDATERS ---

async function fetchUsage() {
    try {
        const res = await fetch('/api/usage');
        const data = await res.json();
        if (data) {
            const usageVal = document.getElementById('usage-val');
            const limitVal = document.getElementById('limit-val');
            if (usageVal) usageVal.textContent = data.requests_used || 0;
            if (limitVal) limitVal.textContent = data.requests_limit || 75000;
        }
    } catch (e) { console.error("Error fetching API usage", e); }
}

// --- GLOBAL INIT ---

window.addEventListener('hashchange', handleRouting);

async function init() {
    await fetchFilterData();
    // Update header labels from localStorage
    const label = document.getElementById('selected-country-name');
    const flagPlaceholder = document.getElementById('selected-country-flag');

    if (selectedCountry === 'all') {
        label.textContent = 'Tutte le Nazioni';
        flagPlaceholder.innerHTML = '<i data-lucide="globe" class="w-4 h-4 text-accent"></i>';
    } else {
        const c = allFilterData.countries.find(x => x.name === selectedCountry);
        label.textContent = selectedCountry;
        if (c && c.flag) {
            flagPlaceholder.innerHTML = `<img src="${c.flag}" class="w-5 h-5 rounded-sm object-cover">`;
        }
    }

    if (selectedBookmaker !== 'all') {
        const bookie = allFilterData.bookmakers.find(b => b.id.toString() === selectedBookmaker);
        if (bookie) document.getElementById('selected-bookmaker-name').textContent = bookie.name;
    }

    if (window.lucide) lucide.createIcons();
    await fetchUsage();
    await fetchHistory();
    handleRouting();

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

// Theme Toggle
const themeToggle = document.getElementById('theme-toggle');
const htmlElement = document.documentElement;
if (themeToggle) {
    themeToggle.onclick = () => {
        const isDark = htmlElement.classList.toggle('dark');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        if (window.lucide) lucide.createIcons();
    };
}
if (localStorage.getItem('theme') === 'light') htmlElement.classList.remove('dark');

init();
