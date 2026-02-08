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
let selectedLeague = localStorage.getItem('selected_league') || 'all';
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

/**
 * Modern HTMX Navigation Helper
 * @param {string} view - 'dashboard', 'leagues', 'match', etc.
 * @param {string|number} id - optional ID
 */
function navigate(view, id = null) {
    let endpoint = `/api/view/${view}`;
    let pushUrl = `/${view}`;

    if (id) {
        endpoint += `/${id}`;
        pushUrl += `/${id}`;
    }

    // Fallback per dashboard
    if (view === 'dashboard') pushUrl = '/dashboard';

    const container = document.getElementById('htmx-container');
    if (container) {
        htmx.ajax('GET', endpoint, { target: '#htmx-container', pushUrl: pushUrl });
    }
}
window.navigate = navigate;

// --- ROUTER (Simplified for HTMX) ---

document.addEventListener('htmx:afterRequest', async function (evt) {
    if (evt.detail.target.id === 'htmx-container') {
        const url = evt.detail.pathInfo.requestPath || window.location.pathname;
        let view = 'dashboard';
        if (url.includes('leagues')) view = 'leagues';
        else if (url.includes('predictions')) view = 'predictions';
        else if (url.includes('tracker')) view = 'tracker';
        else if (url.includes('match')) view = 'match';
        else if (url.includes('team')) view = 'team';
        else if (url.includes('player')) view = 'player';

        currentView = view;
        updateNavLinks(view);
        updateViewTitle(view);

        // Run view-specific JS initialization
        if (view === 'dashboard') {
            // Sync local filter state from the newly loaded HTML
            const countryFilter = document.getElementById('dash-country-filter');
            const leagueFilter = document.getElementById('dash-league-filter');
            if (countryFilter) {
                selectedCountry = countryFilter.value;
                localStorage.setItem('selected_country', selectedCountry);
            }
            if (leagueFilter) {
                selectedLeague = leagueFilter.value;
                localStorage.setItem('selected_league', selectedLeague);
            }

            await fetchLive();
            updateStatsSummary();
            renderDashboardMatches();
        } else if (view === 'tracker') {
            await fetchHistory();
            updateTrackerSummary();
            renderFullHistory();
        } else if (view === 'predictions') {
            if (window.renderPredictions) renderPredictions();
        }

        if (window.lucide) lucide.createIcons();
    }
});

// Sync currentView on history navigation (back/forward)
window.addEventListener('popstate', function () {
    const path = window.location.pathname;
    let view = 'dashboard';
    if (path.includes('leagues')) view = 'leagues';
    else if (path.includes('predictions')) view = 'predictions';
    else if (path.includes('tracker')) view = 'tracker';
    else if (path.includes('match')) view = 'match';
    currentView = view;
    updateNavLinks(view);
    updateViewTitle(view);
});

function updateNavLinks(activeView) {
    document.querySelectorAll('.nav-link, nav a').forEach(link => {
        const view = link.dataset.view;
        if (view === activeView) {
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

function updateViewTitle(view) {
    const titles = {
        'dashboard': 'Dashboard Intelligence',
        'leagues': 'Competizioni',
        'predictions': 'Pronostici AI',
        'tracker': 'Tracker Scommesse',
        'match': 'Analisi Match',
        'team': 'Profilo Squadra',
        'player': 'Dettagli Giocatore'
    };
    if (viewTitle) viewTitle.textContent = titles[view] || 'Scommetto.AI';
}

// Initial call to set active state based on current URL (non-hash)
window.addEventListener('load', () => {
    const path = window.location.pathname;
    let view = 'dashboard';
    if (path.includes('leagues')) view = 'leagues';
    else if (path.includes('predictions')) view = 'predictions';
    else if (path.includes('tracker')) view = 'tracker';
    updateNavLinks(view);
    updateViewTitle(view);
});

// --- END ROUTER ---

function showLoader(show) {
    if (viewLoader) {
        if (show) viewLoader.classList.remove('hidden');
        else viewLoader.classList.add('hidden');
    }
}


// Deprecated or simplified renderDashboard
async function renderDashboard() {
    // Legacy placeholder, now handled by HTMX mostly
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
            item.onclick = () => navigate('match', p.fixture_id);
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
                <div class="glass p-6 rounded-[32px] border-white/5 hover:border-accent/30 transition-all cursor-pointer group league-card active-card" data-country="${l.country_name || l.country || 'International'}" onclick="navigate('leagues', '${l.id}')">
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
                        <div class="flex items-center justify-between group cursor-pointer" onclick="navigate('player', ${p.player.id})">
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
            <tr class="hover:bg-white/5 transition-colors cursor-pointer" onclick="navigate('team', '${row.team_id}')">
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
                            <div class="flex items-center gap-4 group cursor-pointer" onclick="navigate('player', ${p.player.id})">
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

                        <button class="w-full py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-[10px] uppercase tracking-widest transition-all" onclick="navigate('match', '${p.fixture_id}')">
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
             <button onclick="navigate('dashboard')" class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center hover:bg-white/10 transition-all border border-white/10">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
             </button>
             <div class="flex flex-col">
                <h2 class="text-2xl font-black italic uppercase tracking-tight leading-none">${f.team_home_name} VS ${f.team_away_name}</h2>
                <span class="text-[9px] font-bold text-slate-500 uppercase tracking-[0.2em]">${f.league_name} | ${dateStr}</span>
             </div>
        </div>

        <div class="glass rounded-[48px] border-white/5 overflow-hidden mb-10">
            <div class="p-10 md:p-16 flex flex-col md:flex-row items-center justify-between gap-12 bg-gradient-to-br from-accent/5 to-transparent">
                <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group" onclick="navigate('team', '${f.team_home_id}')">
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

                <div class="flex-1 flex flex-col items-center gap-6 cursor-pointer group" onclick="navigate('team', '${f.team_away_id}')">
                    <div class="w-24 h-24 md:w-32 md:h-32 glass rounded-[48px] p-6 flex items-center justify-center group-hover:scale-110 transition-transform border-white/10">
                        <img src="${f.team_away_logo}" class="w-full h-full object-contain">
                    </div>
                    <span class="text-xl font-black uppercase italic tracking-tight text-center group-hover:text-accent transition-colors">${f.team_away_name}</span>
                </div>
            </div>

            <nav class="flex border-t border-white/5 overflow-x-auto no-scrollbar">
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all text-accent border-b-2 border-accent" data-tab="analysis">Intelligence</button>
                <button class="flex-1 py-6 px-4 text-[10px] font-black uppercase tracking-[0.2em] italic border-r border-white/5 hover:bg-white/5 transition-all text-slate-500" data-tab="events">Eventi</button>
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
    const container = document.getElementById('match-tab-content'); // Use the id from match.php
    if (!container) return; // Guard if not in match view

    container.innerHTML = '<div class="flex justify-center py-20"><i data-lucide="loader-2" class="w-8 h-8 text-accent rotator"></i></div>';
    if (window.lucide) lucide.createIcons();

    switch (tab) {
        case 'analysis':
            await renderMatchAnalysis(fixtureId);
            break;
        case 'events':
            renderMatchEvents(matchData.events, matchData.fixture.team_home_id);
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
window.renderMatchTab = renderMatchTab;

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
        const startXI = typeof l.start_xi_json === 'string' ? JSON.parse(l.start_xi_json) : (l.start_xi_json || []);

        html += `
            <div class="glass p-8 rounded-[40px] border-white/5">
                <div class="flex items-center justify-between mb-8">
                     <h3 class="text-xl font-black italic uppercase italic text-white">${l.team_name}</h3>
                     <span class="px-4 py-1.5 rounded-xl bg-accent/10 text-accent text-[10px] font-black uppercase tracking-widest border border-accent/20">${l.formation}</span>
                </div>

                <div class="space-y-4">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-4 italic">Titolari (Starting XI)</div>
                    ${startXI.map(p => `
                        <div class="flex items-center gap-4 group cursor-pointer" onclick="navigate('player', ${p.player.id})">
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


function renderMatchEvents(events, homeId) {
    const container = document.getElementById('match-view-content');
    if (!events || !events.length) {
        container.innerHTML = '<div class="glass p-20 rounded-[40px] text-center font-black uppercase text-slate-500 italic">Nessun evento registrato.</div>';
        return;
    }

    let html = `<div class="max-w-3xl mx-auto space-y-6 relative before:absolute before:inset-y-0 before:left-1/2 before:w-px before:bg-white/10 py-10">`;

    events.forEach(ev => {
        const isHome = ev.team.id === homeId;
        let icon = 'info';
        let color = 'slate-500';

        if (ev.type === 'Goal') { icon = 'trophy'; color = 'accent'; }
        else if (ev.type === 'Card') {
            icon = 'alert-triangle';
            color = ev.detail === 'Yellow Card' ? 'warning' : 'danger';
        }
        else if (ev.type === 'subst') { icon = 'refresh-cw'; color = 'success'; }
        else if (ev.type === 'Var') { icon = 'camera'; color = 'white'; }

        const player = ev.player.name || 'Unknown';
        const detail = ev.detail || ev.type;

        const content = `
            <div class="inline-block glass px-6 py-4 rounded-2xl border-white/5 hover:border-${color}/30 transition-colors group/card">
                <div class="flex items-center gap-3 mb-1">
                    <i data-lucide="${icon}" class="w-3 h-3 text-${color}"></i>
                    <span class="text-[10px] font-black uppercase text-white tracking-widest">${player}</span>
                </div>
                <div class="text-[8px] font-bold text-slate-500 uppercase tracking-widest pl-6">${detail}</div>
            </div>
        `;

        html += `
            <div class="flex items-center justify-between group">
                <div class="flex-1 text-right pr-8 ${isHome ? '' : 'invisible'}">
                    ${isHome ? content : ''}
                </div>

                <div class="relative z-10 w-10 h-10 rounded-full glass border border-white/10 flex items-center justify-center text-xs font-black text-white tabular-nums bg-slate-900 shadow-xl shadow-black/50 group-hover:scale-110 transition-transform cursor-default ring-4 ring-slate-900">
                    ${ev.time.elapsed}'
                </div>

                <div class="flex-1 pl-8 ${!isHome ? '' : 'invisible'}">
                    ${!isHome ? content : ''}
                </div>
            </div>
        `;
    });
    html += '</div>';
    container.innerHTML = html;
    if (window.lucide) lucide.createIcons();
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
                                        <button class="px-4 py-2 rounded-xl bg-white/5 group-hover:bg-accent group-hover:text-white transition-all text-[9px] font-black uppercase tracking-widest border border-white/5" onclick="navigate('player', ${p.id})">Dettagli</button>
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
        if (label) label.textContent = 'Tutte le Nazioni';
        if (flagPlaceholder) flagPlaceholder.innerHTML = '<i data-lucide="globe" class="w-4 h-4 text-accent"></i>';
    } else {
        const c = allFilterData.countries.find(x => x.name === name);
        if (label) label.textContent = name;
        if (c && c.flag && flagPlaceholder) {
            flagPlaceholder.innerHTML = `<img src="${c.flag}" class="w-5 h-5 rounded-sm object-cover">`;
        } else if (flagPlaceholder) {
            flagPlaceholder.textContent = '🏳️';
        }
    }

    closeCountryModal();
    if (window.lucide) lucide.createIcons();
    // Use the shared selectCountry logic to trigger proper HTMX reloads
    selectCountry(name);
}
window.setCountry = setCountry;

function closeCountryModal() { document.getElementById('country-modal').classList.add('hidden'); }

function openBookmakerModal() {
    const modal = document.getElementById('bookmaker-modal');
    const list = document.getElementById('bookmaker-list');
    modal.classList.remove('hidden');

    const bookies = [{ id: 'all', name: 'Tutti i Bookmaker' }, ...allFilterData.bookmakers.filter(b => b.managed_matches > 0)];

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

// --- SHARED DATA FETCHERS & UPDATERS ---

async function selectCountry(country) {
    selectedCountry = country;
    localStorage.setItem('selected_country', country);

    if (currentView === 'dashboard') {
        renderDashboardMatches();
        updateStatsSummary();
    } else if (currentView === 'tracker') {
        renderTracker();
    } else if (currentView === 'predictions') {
        renderPredictions();
    }
}

window.selectCountry = selectCountry;

async function selectBookmaker(bookmakerId) {
    selectedBookmaker = bookmakerId;
    localStorage.setItem('selected_bookmaker', bookmakerId);

    const label = document.getElementById('selected-bookmaker-name');
    if (bookmakerId === 'all') {
        if (label) label.textContent = 'Tutti i Book';
    } else {
        const bookie = allFilterData.bookmakers.find(b => b.id.toString() === bookmakerId.toString());
        if (bookie && label) label.textContent = bookie.name;
    }

    const modal = document.getElementById('bookmaker-modal');
    if (modal) modal.classList.add('hidden');

    if (currentView === 'dashboard') {
        updateDashboardFilters();
        updateStatsSummary();
    } else if (currentView === 'tracker') {
        renderTracker();
    }
}
window.selectBookmaker = selectBookmaker;
window.setBookmaker = selectBookmaker; // Alias for legacy calls


function updateDashboardFilters() {
    const htmxContainer = document.getElementById('htmx-container');
    if (!htmxContainer) return;

    // Construct Query Params
    const params = new URLSearchParams();
    if (selectedCountry !== 'all') params.append('country', selectedCountry);
    if (selectedLeague !== 'all') params.append('league', selectedLeague);
    if (selectedBookmaker !== 'all') params.append('bookmaker', selectedBookmaker);

    const endpoint = `/api/view/dashboard?${params.toString()}`;

    // Update HTMX attribute and trigger load
    htmxContainer.setAttribute('hx-get', endpoint);
    htmx.process(htmxContainer);
    htmx.trigger('#htmx-container', 'load');
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
    const leagueLabel = selectedLeague === 'all' ? 'Tutti' : (Array.from(document.querySelectorAll('#dash-league-filter option')).find(opt => opt.value === selectedLeague)?.innerText || 'League');
    const bookmakerLabel = selectedBookmaker === 'all' ? 'Tutti i Book' : (allFilterData.bookmakers.find(b => b.id.toString() === selectedBookmaker)?.name || 'Filtro');

    container.innerHTML = `
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1 ${summary.availableBalance >= 0 ? 'text-white' : 'text-danger'}">${summary.availableBalance.toFixed(2)}€</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Disponibile</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
             <span class="block text-2xl font-black mb-1">${summary.currentPortfolio.toFixed(2)}€</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Portafoglio</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1 ${summary.netProfit >= 0 ? 'text-success' : 'text-danger'}">${summary.netProfit >= 0 ? '+' : ''}${summary.netProfit.toFixed(2)}€</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Utile Netto | ${countryLabel} ${selectedLeague !== 'all' ? '/ ' + leagueLabel : ''}</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1">${summary.liveCount}</span>
            <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Live Now</span>
        </div>
        <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
            <span class="block text-2xl font-black mb-1 text-warning">${summary.pendingCount} (${summary.pendingStakes.toFixed(0)}€)</span>
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

    let pendingCount = 0;
    let pendingStakes = 0;
    let winCount = 0;
    let lossCount = 0;
    let totalStakeFiltered = 0;
    let netProfitFiltered = 0;
    const startingPortfolio = 100;

    // Global calculations for portfolio
    let globalNetProfit = 0;

    data.forEach(bet => {
        const stake = parseFloat(bet.stake) || 0;
        const odds = parseFloat(bet.odds) || 0;
        const status = (bet.status || "").toLowerCase().trim();

        if (status === "won") {
            globalNetProfit += stake * (odds - 1);
        } else if (status === "lost") {
            globalNetProfit -= stake;
        } else if (status === "pending") {
            pendingStakes += stake;
        }
    });

    // Filtered stats for display
    const filteredHistory = data.filter(bet => {
        const countryName = bet.country || bet.country_name || 'International';
        const matchesCountry = selectedCountry === 'all' || countryName === selectedCountry;
        const matchesBookie = selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === selectedBookmaker;
        return matchesCountry && matchesBookie;
    });

    filteredHistory.forEach(bet => {
        const stake = parseFloat(bet.stake) || 0;
        const odds = parseFloat(bet.odds) || 0;
        const status = (bet.status || "").toLowerCase().trim();

        if (status === "pending") {
            pendingCount++;
        } else if (status === "won") {
            winCount++;
            totalStakeFiltered += stake;
            netProfitFiltered += stake * (odds - 1);
        } else if (status === "lost") {
            lossCount++;
            totalStakeFiltered += stake;
            netProfitFiltered -= stake;
        }
    });

    const liveCount = liveMatches.filter(m => {
        const countryName = m.league.country || m.league.country_name;
        const matchesCountry = selectedCountry === 'all' || countryName === selectedCountry;

        const leagueId = m.league.id.toString();
        const matchesLeague = selectedLeague === 'all' || leagueId === selectedLeague;

        const matchesBookie = selectedBookmaker === 'all'
            ? true
            : (m.available_bookmakers || []).includes(parseInt(selectedBookmaker));

        return matchesCountry && matchesLeague && matchesBookie;
    }).length;

    const currentPortfolio = startingPortfolio + globalNetProfit;
    const availableBalance = currentPortfolio - pendingStakes;

    return {
        pendingCount,
        pendingStakes,
        winCount,
        lossCount,
        netProfit: netProfitFiltered,
        liveCount,
        roi: totalStakeFiltered > 0 ? (netProfitFiltered / totalStakeFiltered) * 100 : 0,
        currentPortfolio,
        availableBalance
    };
}

// --- FILTERS & DASHBOARD HELPERS ---

function populateDashFilters() {
    const countrySelect = document.getElementById('dash-country-filter');
    const leagueSelect = document.getElementById('dash-league-filter');
    if (!countrySelect || !leagueSelect) return;

    // 1. Extract Countries from all live matches
    const countries = [...new Set(liveMatches.map(m => m.league.country || m.league.country_name || 'International'))].sort();

    // Preserve current selection if possible
    const currentCountry = selectedCountry;
    countrySelect.innerHTML = '<option value="all">Tutte le Nazioni</option>';
    countries.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.innerText = c;
        if (c === currentCountry) opt.selected = true;
        countrySelect.appendChild(opt);
    });

    // 2. Extract Leagues filtered by selected country (if any)
    const leaguesMap = new Map();
    liveMatches.forEach(m => {
        const c = m.league.country || m.league.country_name || 'International';
        if (selectedCountry === 'all' || c === selectedCountry) {
            leaguesMap.set(m.league.id.toString(), m.league.name);
        }
    });

    const currentLeague = selectedLeague;
    leagueSelect.innerHTML = '<option value="all">Tutti i Campionati</option>';
    Array.from(leaguesMap.entries()).sort((a, b) => a[1].localeCompare(b[1])).forEach(([id, name]) => {
        const opt = document.createElement('option');
        opt.value = id;
        opt.innerText = name;
        if (id === currentLeague) opt.selected = true;
        leagueSelect.appendChild(opt);
    });
}

function updateSelectedCountry(val) {
    selectedCountry = val;
    selectedLeague = 'all'; // Reset league when country changes
    localStorage.setItem('selected_country', val);
    localStorage.setItem('selected_league', 'all');
    renderDashboardMatches();
    updateStatsSummary();
}

function updateSelectedLeague(val) {
    selectedLeague = val;
    localStorage.setItem('selected_league', val);
    renderDashboardMatches();
}

window.updateSelectedCountry = updateSelectedCountry;
window.updateSelectedLeague = updateSelectedLeague;

function renderDashboardMatches() {
    const container = document.getElementById('live-matches-grid');
    if (!container) return;

    // 1. Filter Matches
    const filteredMatches = liveMatches.filter(m => {
        const countryName = m.league.country || m.league.country_name || '';
        const matchesCountry = selectedCountry === 'all' || countryName === selectedCountry;

        const leagueId = m.league.id.toString();
        const matchesLeague = selectedLeague === 'all' || leagueId === selectedLeague;

        const matchesBookie = selectedBookmaker === 'all'
            ? true
            : (m.available_bookmakers || []).includes(parseInt(selectedBookmaker));

        return matchesCountry && matchesLeague && matchesBookie;
    });

    // Update active count in UI
    const countEl = document.getElementById('live-active-count');
    if (countEl) countEl.innerText = filteredMatches.length;

    // Clear and render matches
    container.innerHTML = '';

    if (filteredMatches.length === 0) {
        container.innerHTML = `
            <div class="glass p-12 rounded-[40px] text-center border-white/5 flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mb-6">
                    <i data-lucide="calendar-off" class="w-8 h-8 text-slate-500"></i>
                </div>
                <h3 class="text-xl font-black text-white uppercase italic tracking-tight mb-2">Nessun Match Live</h3>
                <p class="text-slate-400 font-medium text-sm max-w-md mx-auto">Non ci sono partite in corso che corrispondono ai tuoi filtri.</p>
            </div>
        `;
    }

    filteredMatches.forEach(m => {
        const isHighlighted = pinnedMatches.has(m.fixture.id);
        const card = document.createElement('div');
        // Visual Highlight Effect: ring-2 ring-accent and shadow if pinned
        card.className = `glass rounded-[40px] p-8 border-white/5 hover:border-accent/30 transition-all group cursor-pointer relative overflow-hidden mb-6 ${isHighlighted ? 'pinned-match' : ''}`;

        // Link to match detail
        const goToMatch = () => navigate('match', m.fixture.id);
        card.onclick = goToMatch;

        const homeName = m.teams.home.name;
        const awayName = m.teams.away.name;
        const scoreHome = m.goals.home ?? 0;
        const scoreAway = m.goals.away ?? 0;
        const elapsed = m.fixture.status.elapsed ?? 0;
        const statusShort = m.fixture.status.short;

        let period = '';
        if (['1H', 'HT', '2H', 'ET', 'P'].includes(statusShort)) period = statusShort;
        else if (elapsed <= 45) period = '1H';
        else period = '2H';

        // Event Timeline Logic
        const events = (m.events || []).sort((a, b) => b.time.elapsed - a.time.elapsed).slice(0, 3); // Last 3 events
        let timelineHtml = '';
        if (events.length > 0) {
            timelineHtml = `<div class="flex items-center gap-3 overflow-x-auto no-scrollbar mask-linear-fade pr-4">
                ${events.map(ev => {
                let icon = 'info'; let color = 'slate-500';
                if (ev.type === 'Goal') { icon = 'trophy'; color = 'text-accent'; }
                else if (ev.type === 'Card' && ev.detail === 'Yellow Card') { icon = 'alert-triangle'; color = 'text-warning'; }
                else if (ev.type === 'Card') { icon = 'alert-octagon'; color = 'text-danger'; }

                return `
                        <div class="flex items-center gap-1.5 shrink-0 bg-white/5 px-3 py-1.5 rounded-xl border border-white/5">
                            <span class="text-[9px] font-black text-slate-400">${ev.time.elapsed}'</span>
                            <i data-lucide="${icon}" class="w-3 h-3 ${color}"></i>
                            <span class="text-[9px] font-bold text-white uppercase truncate max-w-[80px] hover:text-accent cursor-pointer transition-colors" onclick="event.stopPropagation(); navigate('player', ev.player.id)">${ev.player.name}</span>
                        </div>
                    `;
            }).join('')}
            </div>`;
        } else {
            timelineHtml = '<div class="text-[9px] font-bold text-slate-600 italic">Nessun evento recente</div>';
        }

        card.innerHTML = `
            <!-- Header: League + Country -->
            <div class="flex items-center justify-between mb-8 border-b border-white/5 pb-4">
                <div class="flex items-center gap-3 opacity-80">
                    <img src="${m.league.flag || m.league.logo}" class="w-5 h-5 rounded-full object-cover">
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] italic text-white">${m.league.name}</span>
                        <span class="text-[8px] font-bold uppercase tracking-widest text-slate-500">${m.league.country || 'International'}</span>
                    </div>
                </div>
                <div class="flex items-center gap-2 bg-white/5 px-3 py-1 rounded-full border border-white/10">
                    <span class="text-[10px] font-black text-accent uppercase tracking-widest">${period}</span>
                    <div class="h-3 w-px bg-white/10"></div>
                    <span class="text-[10px] font-black text-white uppercase tracking-widest">${elapsed}'</span>
                    <div class="w-1.5 h-1.5 bg-danger rounded-full animate-pulse shadow-[0_0_10px_red]"></div>
                </div>
            </div>
            
            <!-- Match Score & Teams -->
            <div class="flex items-center justify-between gap-4 mb-8">
                <!-- Home -->
                <div class="flex flex-col items-center gap-4 flex-1 group/team cursor-pointer" onclick="event.stopPropagation(); openLineupModal(${m.fixture.id}, ${m.teams.home.id})">
                    <div class="w-16 h-16 p-2 glass rounded-2xl flex items-center justify-center group-hover/team:border-accent/50 transition-all relative">
                        <img src="${m.teams.home.logo}" class="w-full h-full object-contain drop-shadow-2xl">
                        <div class="absolute -bottom-2 bg-slate-900 border border-white/10 px-2 rounded text-[8px] font-black uppercase text-slate-500 group-hover/team:text-accent transition-colors">Lineup</div>
                    </div>
                    <span class="text-xs font-black uppercase tracking-tight text-center leading-tight flex items-center gap-2">
                        ${homeName} <i data-lucide="chevron-right" class="w-3 h-3 text-slate-600"></i>
                    </span>
                </div>

                <!-- Score -->
                <div class="text-5xl md:text-6xl font-black italic tracking-tighter text-white tabular-nums flex flex-col items-center">
                    <span>${scoreHome} - ${scoreAway}</span>
                    <span class="text-[9px] font-bold text-slate-500 tracking-widest uppercase mt-2 opacity-50">Live Score</span>
                </div>

                <!-- Away -->
                <div class="flex flex-col items-center gap-4 flex-1 group/team cursor-pointer" onclick="event.stopPropagation(); openLineupModal(${m.fixture.id}, ${m.teams.away.id})">
                    <div class="w-16 h-16 p-2 glass rounded-2xl flex items-center justify-center group-hover/team:border-accent/50 transition-all relative">
                        <img src="${m.teams.away.logo}" class="w-full h-full object-contain drop-shadow-2xl">
                        <div class="absolute -bottom-2 bg-slate-900 border border-white/10 px-2 rounded text-[8px] font-black uppercase text-slate-500 group-hover/team:text-accent transition-colors">Lineup</div>
                    </div>
                    <span class="text-xs font-black uppercase tracking-tight text-center leading-tight flex items-center gap-2">
                         <i data-lucide="chevron-left" class="w-3 h-3 text-slate-600"></i> ${awayName}
                    </span>
                </div>
            </div>

            <!-- Bottom Section: Timeline & Actions -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 pt-6 border-t border-white/5">
                <!-- Left: Timeline -->
                <div class="flex-1 min-w-0 w-full">
                    ${timelineHtml}
                </div>

                <!-- Right: Buttons -->
                <div class="flex items-center gap-3 shrink-0">
                     <button onclick="event.stopPropagation(); navigate('match', m.fixture.id)" class="p-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white border border-white/5 transition-all group/btn" title="Dettagli">
                        <i data-lucide="arrow-right" class="w-4 h-4 group-hover/btn:translate-x-1 transition-transform"></i>
                    </button>
                    
                    <button onclick="event.stopPropagation(); openStatsModal(${m.fixture.id})" class="px-5 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/5 transition-all font-black uppercase text-[9px] tracking-widest flex items-center gap-2">
                        <i data-lucide="bar-chart-2" class="w-3 h-3"></i> Stats & Predictions
                    </button>
                    
                    <button onclick="event.stopPropagation(); analyzeMatch(${m.fixture.id})" class="px-5 py-3 rounded-xl bg-accent text-white shadow-lg shadow-accent/20 hover:scale-[1.02] active:scale-95 transition-all font-black uppercase text-[9px] tracking-widest flex items-center gap-2">
                        <i data-lucide="sparkles" class="w-3 h-3"></i> AI Analysis
                    </button>
                </div>
            </div>
        `;
        container.appendChild(card);
    });

    // Show upcoming matches if less than 10 results
    const upcomingContainer = document.getElementById('upcoming-matches-container');
    if (filteredMatches.length < 10 && upcomingContainer) {
        fetchAndRenderUpcoming(upcomingContainer, 20);
    } else if (upcomingContainer) {
        upcomingContainer.innerHTML = '';
    }

    if (window.lucide) lucide.createIcons();
    // Re-process HTMX for the new elements
    if (window.htmx) htmx.process(container);
}


async function fetchAndRenderUpcoming(container, limit) {
    try {
        const res = await fetch('/api/upcoming');
        const data = await res.json();

        if (!data.response || !data.response.length) {
            container.innerHTML = '';
            return;
        }

        const filtered = data.response.filter(m => {
            const countryName = m.country_name || 'International';
            const matchesCountry = selectedCountry === 'all' || countryName === selectedCountry;
            const leagueId = (m.league_id || m.league?.id || '').toString();
            const matchesLeague = selectedLeague === 'all' || leagueId === selectedLeague;
            const matchesBookie = selectedBookmaker === 'all'
                ? true
                : (m.available_bookmakers || []).includes(parseInt(selectedBookmaker));
            return matchesCountry && matchesLeague && matchesBookie;
        });

        if (filtered.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = `
            <div class="col-span-full border-t border-white/5 my-8 pt-4 text-[10px] font-black uppercase tracking-widest text-slate-500 flex items-center gap-4">
                <div class="h-px bg-white/10 flex-1"></div>PROSSIME 24 ORE<div class="h-px bg-white/10 flex-1"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                ${filtered.slice(0, limit).map(m => upcomingMatchCardHtml(m)).join('')}
            </div>
        `;

        if (window.lucide) lucide.createIcons();
    } catch (e) { console.error("Error fetching upcoming", e); }
}

function upcomingMatchCardHtml(m) {
    const d = new Date(m.date);
    const time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    return `
    <div onclick="navigate('match', m.fixture_id)" class="glass p-6 rounded-3xl border-white/5 hover:border-accent/30 transition-all cursor-pointer group relative overflow-hidden h-full">
        <div class="absolute top-0 right-0 p-4 opacity-50"><i data-lucide="calendar-clock" class="w-4 h-4 text-slate-500"></i></div>
        <div class="flex items-center gap-2 mb-4">
             <span class="text-[8px] font-black uppercase text-accent tracking-widest truncate max-w-[150px]">${m.league_name}</span>
             <span class="text-[8px] font-bold text-slate-500">${time}</span>
        </div>
        
        <div class="flex items-center justify-between gap-4">
            <div class="flex flex-col items-center gap-2 flex-1 min-w-0">
                <img src="${m.home_logo}" class="w-8 h-8 object-contain">
                <span class="text-[9px] font-black uppercase text-center leading-tight truncate w-full">${m.home_name}</span>
            </div>
            <div class="text-[10px] font-black text-slate-600 italic">VS</div>
            <div class="flex flex-col items-center gap-2 flex-1 min-w-0">
                <img src="${m.away_logo}" class="w-8 h-8 object-contain">
                <span class="text-[9px] font-black uppercase text-center leading-tight truncate w-full">${m.away_name}</span>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-4 pt-4 border-t border-white/5">
             <button onclick="event.stopPropagation(); analyzeMatch(${m.fixture_id})" class="text-[8px] font-black uppercase tracking-widest text-accent hover:text-white transition-colors">AI Forecast</button>
             <button onclick="event.stopPropagation(); navigate('match', m.fixture_id)" class="text-[8px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-colors">Pronostico</button>
        </div>
    </div>
    `;
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

async function renderMatchAnalysis(id) {
    const container = document.getElementById('match-tab-content'); // Use correct container
    if (!container) return;

    try {
        const res = await fetch(`/api/predictions/${id}`);
        const data = await res.json();

        if (data.error) {
            container.innerHTML = `<div class="glass p-10 text-center font-black text-slate-500 uppercase italic">${data.error}</div>`;
            return;
        }

        const perc = data.percent || {};
        const comp = data.comparison || {};
        const metrics = [
            { label: 'Forma', key: 'form' }, { label: 'Attacco', key: 'attaching' },
            { label: 'Difesa', key: 'defensive' }, { label: 'Probabilità Gol', key: 'poisson_distribution' }
        ];

        let metricsHtml = metrics.map(m => {
            const val = comp[m.key] || { home: '50%', away: '50%' };
            const h = parseInt(val.home); const a = parseInt(val.away);
            return `
                <div class="space-y-2 mb-6">
                    <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <span>${h}%</span> <span class="text-white italic">${m.label}</span> <span>${a}%</span>
                    </div>
                    <div class="h-2 bg-slate-800 rounded-full overflow-hidden flex">
                        <div class="h-full bg-accent" style="width: ${h}%"></div>
                        <div class="h-full bg-slate-600" style="width: ${a}%"></div>
                    </div>
                </div>
            `;
        }).join('');

        container.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 animate-fade-in">
                <div class="space-y-8">
                     <div class="glass p-10 rounded-[48px] border-white/5 relative overflow-hidden">
                        <div class="absolute top-0 right-0 p-10 opacity-5">
                            <i data-lucide="brain-circuit" class="w-32 h-32 text-accent"></i>
                        </div>
                        <div class="text-[10px] font-black text-accent uppercase tracking-[0.3em] mb-4 italic">AI Match Intelligence</div>
                        <h3 class="text-4xl font-black text-white italic uppercase tracking-tighter leading-none mb-10">${data.advice}</h3>
                        
                        <div class="grid grid-cols-3 gap-4">
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
        if (window.lucide) lucide.createIcons();
    } catch (e) { container.innerHTML = "Errore durante il caricamento delle statistiche."; }
}
window.renderMatchAnalysis = renderMatchAnalysis;

async function showBetDetails(bet) {
    // Reuse existing modal logic
    const modal = document.getElementById("analysis-modal");
    const body = document.getElementById("modal-body");
    const btn = document.getElementById("place-bet-btn");
    const confInd = document.getElementById('confidence-indicator');
    const footerActions = document.getElementById('modal-footer-actions');

    modal.classList.remove('hidden');
    if (footerActions) footerActions.classList.add('hidden');
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
    const footerActions = document.getElementById('modal-footer-actions');

    if (footerActions) footerActions.classList.remove('hidden');
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
        // Double check balance before placing manual bet
        const summary = calculateStats();
        const requestedStake = parseFloat(betData.stake) || 0;

        if (requestedStake > summary.availableBalance) {
            alert(`Saldo insufficiente! Hai ${summary.availableBalance.toFixed(2)}€ disponibili, ma la scommessa richiede ${requestedStake.toFixed(2)}€.`);
            return;
        }

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

// --- TRACKER FILTERS ---
let trackerStatusFilter = 'all';

function setTrackerFilter(status) {
    trackerStatusFilter = status;
    document.querySelectorAll('.tracker-filter-btn').forEach(btn => {
        if (btn.dataset.status === status) {
            btn.classList.add('bg-accent', 'text-white');
            btn.classList.remove('bg-white/5', 'text-slate-500');
        } else {
            btn.classList.remove('bg-accent', 'text-white');
            btn.classList.add('bg-white/5', 'text-slate-500');
        }
    });
    renderFullHistory();
}

async function renderTracker() {
    viewContainer.innerHTML = `
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10" id="tracker-stats-summary"></div>
        
        <div class="flex gap-4 mb-8 overflow-x-auto pb-2 no-scrollbar">
            <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap ${trackerStatusFilter === 'all' ? 'bg-accent text-white' : 'bg-white/5 text-slate-500 hover:bg-white/10'}" onclick="setTrackerFilter('all')" data-status="all">Tutte</button>
            <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap ${trackerStatusFilter === 'won' ? 'bg-accent text-white' : 'bg-white/5 text-slate-500 hover:bg-white/10'}" onclick="setTrackerFilter('won')" data-status="won">Vinte</button>
            <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap ${trackerStatusFilter === 'lost' ? 'bg-accent text-white' : 'bg-white/5 text-slate-500 hover:bg-white/10'}" onclick="setTrackerFilter('lost')" data-status="lost">Perse</button>
            <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap ${trackerStatusFilter === 'pending' ? 'bg-accent text-white' : 'bg-white/5 text-slate-500 hover:bg-white/10'}" onclick="setTrackerFilter('pending')" data-status="pending">In Corso</button>
        </div>

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

// Hash routing dismissed


// Logic moved to HTMX lifecycle events

async function init() {
    await fetchFilterData();
    // Update header labels from localStorage
    const label = document.getElementById('selected-country-name');
    const flagPlaceholder = document.getElementById('selected-country-flag');

    if (label) {
        if (selectedCountry === 'all') {
            label.textContent = 'Tutte le Nazioni';
            if (flagPlaceholder) flagPlaceholder.innerHTML = '<i data-lucide="globe" class="w-4 h-4 text-accent"></i>';
        } else {
            const c = allFilterData.countries.find(x => x.name === selectedCountry);
            label.textContent = selectedCountry;
            if (c && c.flag && flagPlaceholder) {
                flagPlaceholder.innerHTML = `<img src="${c.flag}" class="w-5 h-5 rounded-sm object-cover">`;
            }
        }
    }

    const bookmakerLabel = document.getElementById('selected-bookmaker-name');
    if (selectedBookmaker !== 'all' && bookmakerLabel) {
        const bookie = allFilterData.bookmakers.find(b => b.id.toString() === selectedBookmaker);
        if (bookie) bookmakerLabel.textContent = bookie.name;
    }

    if (window.lucide) lucide.createIcons();
    await fetchUsage();
    await fetchHistory();

    // Detect initial view
    const path = window.location.pathname;
    if (path.includes('leagues')) currentView = 'leagues';
    else if (path.includes('predictions')) currentView = 'predictions';
    else if (path.includes('tracker')) currentView = 'tracker';
    else if (path.includes('match')) currentView = 'match';
    else currentView = 'dashboard';

    updateNavLinks(currentView);
    updateViewTitle(currentView);

    // Initial data refresh for the current view
    if (currentView === 'dashboard') {
        await fetchLive();
        updateStatsSummary();
    } else if (currentView === 'tracker') {
        updateTrackerSummary();
        renderFullHistory();
    }

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


function renderFullHistory() {
    const container = document.getElementById('tracker-history');
    if (!container) return;
    container.innerHTML = '';

    const data = Array.isArray(historyData) ? historyData : [];
    const filtered = data.filter(bet => {
        const matchesCountry = selectedCountry === 'all' || bet.country === selectedCountry;
        const matchesBookie = selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === selectedBookmaker;
        const matchesStatus = trackerStatusFilter === 'all' || (bet.status || '').toLowerCase().trim() === trackerStatusFilter;
        return matchesCountry && matchesBookie && matchesStatus;
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

// --- NEW MODAL HELPERS ---

async function openLineupModal(fixtureId, teamId) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const btn = document.getElementById('place-bet-btn');
    const footer = document.getElementById('modal-footer-actions');

    modal.classList.remove('hidden');
    if (footer) footer.classList.add('hidden');
    if (btn) btn.classList.add('hidden');

    body.innerHTML = '<div class="text-center py-20"><i data-lucide="loader-2" class="w-12 h-12 text-accent rotator mx-auto mb-4"></i><p class="font-black uppercase italic text-xs tracking-widest text-slate-500">Loading Lineups...</p></div>';
    if (window.lucide) lucide.createIcons();

    try {
        const res = await fetch(`/api/match/${fixtureId}`);
        const data = await res.json();

        if (data.error || !data.lineups || !data.lineups.length) {
            body.innerHTML = '<div class="glass p-10 text-center font-bold text-slate-500 uppercase italic">Formazioni non disponibili.</div>';
            return;
        }

        const lineup = data.lineups.find(l => l.team_id == teamId) || data.lineups[0];
        const startXI = typeof lineup.start_xi_json === 'string' ? JSON.parse(lineup.start_xi_json) : (lineup.start_xi_json || []);

        // Coach info if available
        let coachHtml = '';
        if (lineup.coach_name) {
            coachHtml = `
                <div class="flex items-center gap-4 mb-6 p-4 rounded-2xl bg-white/5 border border-white/5">
                    <img src="${lineup.coach_photo || ''}" class="w-10 h-10 rounded-full bg-slate-800 object-cover" onerror="this.src='https://media.api-sports.io/football/players/1.png'">
                    <div>
                        <div class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">Allenatore</div>
                        <div class="font-black italic text-white uppercase">${lineup.coach_name}</div>
                    </div>
                </div>
            `;
        }

        body.innerHTML = `
            <div class="mb-8 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <img src="${lineup.team_logo}" class="w-12 h-12 object-contain">
                    <div>
                        <h2 class="text-2xl font-black italic uppercase tracking-tighter text-white">${lineup.team_name}</h2>
                        <span class="px-2 py-0.5 rounded bg-accent/10 text-accent text-[9px] font-black uppercase tracking-widest border border-accent/20 inline-block">${lineup.formation}</span>
                    </div>
                </div>
            </div>
            
            ${coachHtml}

            <div class="grid grid-cols-1 gap-2 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                ${startXI.map((p, idx) => `
                    <div class="flex items-center gap-4 p-3 rounded-xl hover:bg-white/5 transition-colors border border-transparent hover:border-white/5 cursor-pointer" onclick="navigate('player', ${p.player.id}); closeModal()">
                        <span class="w-6 text-center font-black text-slate-500 italic tabular-nums">${p.player.number || (idx + 1)}</span>
                        <div class="flex-1">
                            <div class="font-bold text-white uppercase italic text-xs">${p.player.name}</div>
                        </div>
                         <span class="text-[9px] font-bold text-slate-600 uppercase tracking-widest">${p.player.pos}</span>
                    </div>
                `).join('')}
            </div>
            
            <button class="w-full mt-6 py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-[10px] uppercase tracking-widest transition-all" onclick="navigate('team', ${teamId}); closeModal()">
                Vai al profilo squadra
            </button>
            <button class="w-full mt-2 py-4 rounded-2xl bg-transparent hover:text-white text-slate-500 font-bold text-[10px] uppercase tracking-widest transition-all" onclick="closeModal()">
                Chiudi
            </button>
        `;
    } catch (e) {
        console.error(e);
        body.innerHTML = '<div class="text-danger font-bold text-center py-10 uppercase italic">Errore caricamento formazioni.</div>';
    }
}

function showBetDetails(bet) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const btn = document.getElementById('place-bet-btn');
    const footer = document.getElementById('modal-footer-actions');
    const title = document.getElementById('modal-title');

    if (!modal || !body) return;

    modal.classList.remove('hidden');
    if (footer) footer.classList.add('hidden');
    if (btn) btn.classList.add('hidden');
    if (title) title.textContent = 'Dettaglio Scommessa';

    const date = new Date(bet.timestamp).toLocaleString('it-IT');
    const odds = parseFloat(bet.odds) || 0;
    const stake = parseFloat(bet.stake) || 0;

    let profit = '0.00';
    let color = 'text-warning';

    if (bet.status === 'won') {
        profit = '+' + (stake * (odds - 1)).toFixed(2);
        color = 'text-success';
    } else if (bet.status === 'lost') {
        profit = '-' + stake.toFixed(2);
        color = 'text-danger';
    }

    body.innerHTML = `
        <div class="space-y-6">
            <div class="flex items-center justify-between p-6 rounded-3xl bg-white/5 border border-white/5">
                <div>
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Match</div>
                    <div class="text-xl font-black italic uppercase text-white">${bet.match_name}</div>
                </div>
                <div class="text-right">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Status</div>
                    <div class="text-lg font-black italic uppercase ${color}">${bet.status}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div class="p-6 rounded-3xl bg-white/5 border border-white/5">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Mercato</div>
                    <div class="text-lg font-black italic uppercase text-white">${bet.market}</div>
                </div>
                <div class="p-6 rounded-3xl bg-white/5 border border-white/5">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Quota</div>
                    <div class="text-lg font-black tabular-nums text-white">${odds.toFixed(2)}</div>
                </div>
                <div class="p-6 rounded-3xl bg-white/5 border border-white/5">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Puntata</div>
                    <div class="text-lg font-black tabular-nums text-white">${stake.toFixed(2)}€</div>
                </div>
                <div class="p-6 rounded-3xl bg-white/5 border border-white/5">
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Risultato</div>
                    <div class="text-xl font-black tabular-nums ${color}">${profit}€</div>
                </div>
            </div>

            ${bet.advice ? `
                <div class="p-6 rounded-3xl bg-accent/5 border border-accent/20">
                    <div class="text-[9px] font-black uppercase tracking-widest text-accent mb-2 italic">Analisi Originale</div>
                    <div class="text-sm font-medium text-slate-300 leading-relaxed">${bet.advice}</div>
                </div>
            ` : ''}

            <div class="text-center text-[10px] text-slate-500 font-bold uppercase tracking-widest italic">Piazzata il ${date}</div>
            
            <button class="w-full mt-4 py-4 rounded-2xl bg-white/5 hover:bg-white/10 border border-white/5 font-black text-[10px] uppercase tracking-widest transition-all" onclick="closeModal()">
                Chiudi
            </button>
        </div>
    `;
    if (window.lucide) lucide.createIcons();
}

function closeBookmakerModal() {
    const modal = document.getElementById('bookmaker-modal');
    if (modal) modal.classList.add('hidden');
}
window.closeBookmakerModal = closeBookmakerModal;

async function openStatsModal(fixtureId) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const btn = document.getElementById('place-bet-btn');
    const footer = document.getElementById('modal-footer-actions');

    modal.classList.remove('hidden');
    if (footer) footer.classList.add('hidden');
    if (btn) btn.classList.add('hidden');

    body.innerHTML = '<div class="text-center py-20"><i data-lucide="loader-2" class="w-12 h-12 text-accent rotator mx-auto mb-4"></i><p class="font-black uppercase italic text-xs tracking-widest text-slate-500">Fetching AI Predictions...</p></div>';
    if (window.lucide) lucide.createIcons();

    try {
        const res = await fetch(`/api/predictions/${fixtureId}`);
        const data = await res.json();

        if (data.error) {
            body.innerHTML = `<div class="glass p-10 text-center font-black text-slate-500 uppercase italic">${data.error}</div>`;
            return;
        }

        // Render metrics
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
                <div class="space-y-1 mb-4">
                    <div class="flex justify-between items-center text-[9px] font-black uppercase tracking-widest text-slate-500">
                        <span>${h}%</span> <span class="text-white italic">${m.label}</span> <span>${a}%</span>
                    </div>
                    <div class="h-1.5 bg-slate-800 rounded-full overflow-hidden flex">
                        <div class="h-full bg-accent" style="width: ${h}%"></div>
                        <div class="h-full bg-slate-600" style="width: ${a}%"></div>
                    </div>
                </div>
            `;
        }).join('');

        body.innerHTML = `
             <div class="mb-8">
                 <div class="text-[9px] font-black text-accent uppercase tracking-widest mb-2 italic flex items-center gap-2">
                    <i data-lucide="brain-circuit" class="w-4 h-4"></i> Algorithmic Prediction
                 </div>
                 <div class="text-3xl font-black text-white italic tracking-tight uppercase leading-none mb-6">${data.advice}</div>
                 
                 <div class="grid grid-cols-3 gap-2 mb-8">
                    <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center">
                        <span class="text-[8px] font-black text-slate-500 uppercase block mb-1">1</span>
                        <span class="text-xl font-black text-white tabular-nums">${perc.home}</span>
                    </div>
                    <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center">
                        <span class="text-[8px] font-black text-slate-500 uppercase block mb-1">X</span>
                        <span class="text-xl font-black text-white tabular-nums">${perc.draw}</span>
                    </div>
                    <div class="bg-white/5 p-4 rounded-2xl border border-white/5 text-center">
                        <span class="text-[8px] font-black text-slate-500 uppercase block mb-1">2</span>
                        <span class="text-xl font-black text-white tabular-nums">${perc.away}</span>
                    </div>
                 </div>
                 
                 <div class="bg-slate-900/50 p-6 rounded-3xl border border-white/5">
                    ${metricsHtml}
                 </div>
             </div>
             
             <button class="w-full py-4 rounded-2xl bg-accent text-white font-black uppercase tracking-widest italic shadow-lg shadow-accent/20 hover:scale-[1.01] transition-all" onclick="analyzeMatch(${fixtureId})">
                 Analizza Live con Gemini
             </button>
              <button class="w-full mt-2 py-4 rounded-2xl bg-transparent hover:text-white text-slate-500 font-bold text-[10px] uppercase tracking-widest transition-all" onclick="closeModal()">
                Chiudi
            </button>
        `;
        if (window.lucide) lucide.createIcons();

    } catch (e) {
        body.innerHTML = '<div class="text-danger font-bold text-center py-10 uppercase italic">Errore caricamento statistiche.</div>';
    }
}
window.analyzeMatch = analyzeMatch;
window.openLineupModal = openLineupModal;
window.openStatsModal = openStatsModal;
window.showBetDetails = showBetDetails;
window.closeModal = closeModal;

// --- INITIALIZATION ---

document.addEventListener('DOMContentLoaded', () => {
    // Re-init icons
    if (window.lucide) lucide.createIcons();
});

