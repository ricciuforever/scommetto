// assets/js/app.js

let liveMatches = [];
let matchStates = {};
let pinnedMatches = new Set();
const notificationSound = new Audio('https://assets.mixkit.co/active_storage/sfx/2358/2358-preview.mp3');
notificationSound.volume = 0.5;
let selectedLeague = 'all';

async function fetchLive() {
    try {
        const res = await fetch('/api/live');
        const data = await res.json();
        const newMatches = data.response || [];

        let hasNewUpdates = false;

        newMatches.forEach(m => {
            const id = m.fixture.id;
            const prevState = matchStates[id];
            const currentEventsCount = (m.events || []).filter(ev => ev.type !== 'subst').length;

            if (prevState) {
                const goalsChanged = m.goals.home !== prevState.goals.home || m.goals.away !== prevState.goals.away;
                const eventsChanged = currentEventsCount > prevState.eventsCount;

                if (goalsChanged || eventsChanged) {
                    pinnedMatches.add(id);
                    hasNewUpdates = true;

                    setTimeout(() => {
                        pinnedMatches.delete(id);
                        renderMatches();
                    }, 10000);
                }
            }

            matchStates[id] = {
                goals: { home: m.goals.home, away: m.goals.away },
                eventsCount: currentEventsCount
            };
        });

        liveMatches = newMatches;

        if (hasNewUpdates) {
            notificationSound.play().catch(e => console.warn("Audio play blocked"));
        }

        renderFilters();
        renderMatches();
        document.getElementById('active-matches-count').textContent = liveMatches.length;
    } catch (e) {
        console.error("Error fetching live data", e);
    }
}

async function fetchHistory() {
    try {
        const res = await fetch("/api/history");
        const data = await res.json();
        const history = data || [];
        renderHistory(history);

        // Calculate Stats
        let pendingCount = 0;
        let winCount = 0;
        let lossCount = 0;
        let totalStake = 0;
        let netProfit = 0;
        const startingPortfolio = 100;

        history.forEach(bet => {
            const stake = parseFloat(bet.stake) || 0;
            const odds = parseFloat(bet.odds) || 0;

            if (bet.status === "pending") {
                pendingCount++;
            } else if (bet.status === "won") {
                winCount++;
                totalStake += stake;
                netProfit += stake * (odds - 1);
            } else if (bet.status === "lost") {
                lossCount++;
                totalStake += stake;
                netProfit -= stake;
            }
        });

        const currentPortfolio = startingPortfolio + netProfit;
        const roi = totalStake > 0 ? (netProfit / totalStake) * 100 : 0;
        const portfolioChangePercent = ((currentPortfolio - startingPortfolio) / startingPortfolio) * 100;

        // Update UI
        document.getElementById("pending-bets-count").textContent = pendingCount;
        document.getElementById("win-loss-count").textContent = `${winCount}W - ${lossCount}L`;
        document.getElementById("portfolio-val").textContent = `${currentPortfolio.toFixed(2)}€ (${portfolioChangePercent >= 0 ? "+" : ""}${portfolioChangePercent.toFixed(1)}%)`;

        const profitEl = document.getElementById("profit-val");
        if (profitEl) {
            profitEl.textContent = `${netProfit >= 0 ? "+" : ""}${netProfit.toFixed(2)}€`;
            profitEl.className = `block text-2xl font-black mb-1 ${netProfit >= 0 ? "text-success" : "text-danger"}`;
        }

        const roiEl = document.getElementById("roi-val");
        if (roiEl) {
            roiEl.textContent = `${roi.toFixed(1)}%`;
            roiEl.className = `block text-2xl font-black mb-1 text-success`;
        }
    } catch (e) {
        console.error("Error fetching history", e);
    }
}

async function fetchUsage() {
    try {
        const res = await fetch('/api/usage');
        const data = await res.json();
        if (data && data.requests_used !== undefined) {
            document.getElementById('usage-val').textContent = data.requests_used;
        }
    } catch (e) {
        console.error("Error fetching usage", e);
    }
}

function renderFilters() {
    const container = document.getElementById('league-filters');
    const leagues = ['all'];
    liveMatches.forEach(m => {
        if (!leagues.includes(m.league.name)) { leagues.push(m.league.name); }
    });
    if (container.children.length === leagues.length) return;
    container.innerHTML = '';
    leagues.forEach(league => {
        const isActive = selectedLeague === league;
        const pill = document.createElement('button');
        pill.className = `px-6 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest transition-all border ${isActive
            ? 'bg-accent text-white border-accent shadow-lg shadow-accent/20'
            : 'bg-white/5 text-slate-500 border-white/5 hover:border-accent/50 hover:text-slate-300 dark:bg-slate-800/50'
            }`;
        pill.textContent = league === 'all' ? 'Tutti i Campionati' : league;
        pill.onclick = () => {
            selectedLeague = league;
            renderFilters();
            renderMatches();
        };
        container.appendChild(pill);
    });
}

function renderMatches() {
    const container = document.getElementById('live-matches-container');
    const filtered = selectedLeague === 'all'
        ? [...liveMatches]
        : liveMatches.filter(m => m.league.name === selectedLeague);

    filtered.sort((a, b) => {
        const aPinned = pinnedMatches.has(a.fixture.id) ? 1 : 0;
        const bPinned = pinnedMatches.has(b.fixture.id) ? 1 : 0;
        return bPinned - aPinned;
    });

    container.innerHTML = filtered.length === 0 ? '<div class="glass p-10 rounded-[32px] text-center text-slate-500 font-bold">Nessun evento live disponibile al momento.</div>' : '';

    filtered.forEach(m => {
        const isPinned = pinnedMatches.has(m.fixture.id);
        const eventsHtml = (m.events || []).map(ev => {
            let icon = '⚽'; let iconClass = 'text-success';
            if (ev.type === 'Goal') { icon = '⚽'; iconClass = 'text-success'; }
            if (ev.type === 'Card' && ev.detail === 'Yellow Card') { icon = '🟨'; iconClass = 'text-warning'; }
            if (ev.type === 'Card' && ev.detail === 'Red Card') { icon = '🟥'; iconClass = 'text-danger'; }
            if (ev.type === 'Var') { icon = '🖥️'; iconClass = 'text-accent'; }
            if (ev.type === 'subst') return '';

            return `
                <div class="flex items-center gap-2 bg-white/5 dark:bg-slate-800/50 px-3 py-1.5 rounded-xl text-[10px] font-black cursor-pointer hover:bg-white/10 transition-colors border border-white/5" onclick="showPlayerDetails(${ev.player.id}, '${ev.player.name}')">
                    <span class="text-accent font-black">${ev.time.elapsed}'</span>
                    <span class="${iconClass}">${icon}</span>
                    <span class="truncate max-w-[70px] uppercase tracking-tight">${ev.player.name || ''}</span>
                </div>
            `;
        }).join('');

        const card = document.createElement('div');
        card.className = `glass rounded-[40px] p-8 border-white/5 dark:border-slate-800 transition-all duration-500 relative group ${isPinned ? 'pinned-match' : 'hover:border-accent/30'}`;
        card.dataset.id = m.fixture.id;
        card.innerHTML = `
            ${isPinned ? '<div class="update-badge">LIVE UPDATE</div>' : ''}
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3 cursor-pointer opacity-60 hover:opacity-100 transition-opacity" onclick="showStandings(${m.league.id}, '${m.league.name}')">
                    <img src="${m.league.logo}" class="w-5 h-5 object-contain">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em]">${m.league.name}</span>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-danger rounded-full animate-ping"></div>
                    <span class="text-[10px] font-black text-danger uppercase tracking-widest">Live</span>
                    <span class="text-[10px] font-black text-slate-500 ml-2 uppercase tracking-widest tabular-nums"><span class="elapsed-time" data-start="${m.fixture.status.elapsed}">${m.fixture.status.elapsed}</span>'</span>
                </div>
            </div>

            <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 md:gap-8 mb-8">
                <div class="flex flex-col items-center gap-4 cursor-pointer group/team" onclick="showTeamDetails(${m.teams.home.id})">
                    <div class="w-16 h-16 md:w-24 md:h-24 glass rounded-[32px] p-4 flex items-center justify-center group-hover/team:scale-110 transition-transform border-white/5 dark:bg-slate-800/50">
                        <img src="${m.teams.home.logo}" class="w-full h-full object-contain">
                    </div>
                    <span class="text-xs md:text-sm font-black tracking-tight uppercase group-hover/team:text-accent transition-colors text-center max-w-[100px] truncate">${m.teams.home.name}</span>
                </div>

                <div class="flex flex-col items-center gap-2">
                    <div class="text-4xl md:text-7xl font-black tracking-tighter text-slate-900 dark:text-white tabular-nums drop-shadow-2xl flex items-center">
                        ${m.goals.home}<span class="text-accent/30 mx-2 text-3xl md:text-5xl font-light">-</span>${m.goals.away}
                    </div>
                </div>

                <div class="flex flex-col items-center gap-4 cursor-pointer group/team" onclick="showTeamDetails(${m.teams.away.id})">
                    <div class="w-16 h-16 md:w-24 md:h-24 glass rounded-[32px] p-4 flex items-center justify-center group-hover/team:scale-110 transition-transform border-white/5 dark:bg-slate-800/50">
                        <img src="${m.teams.away.logo}" class="w-full h-full object-contain">
                    </div>
                    <span class="text-xs md:text-sm font-black tracking-tight uppercase group-hover/team:text-accent transition-colors text-center max-w-[100px] truncate">${m.teams.away.name}</span>
                </div>
            </div>

            <div class="flex flex-col md:flex-row items-center gap-6 pt-4 border-t border-white/5">
                <div class="flex-1 flex flex-wrap gap-2">
                    ${eventsHtml}
                </div>
                <button class="w-full md:w-auto bg-accent hover:bg-sky-500 text-white px-8 py-4 rounded-[20px] font-black text-xs uppercase tracking-[0.1em] transition-all shadow-xl shadow-accent/20 hover:scale-105 active:scale-95 flex items-center justify-center gap-3 group/btn" onclick="analyzeMatch(${m.fixture.id})">
                    <i data-lucide="brain-circuit" class="w-5 h-5 group-hover/btn:animate-pulse"></i>
                    Analyze AI
                </button>
                <button class="w-full md:w-auto bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white px-6 py-4 rounded-[20px] font-black text-xs uppercase tracking-[0.1em] transition-all border border-white/5 flex items-center justify-center gap-3 group/intel" onclick="showIntelligence(${m.fixture.id})">
                    <i data-lucide="bar-chart-3" class="w-5 h-5 group-hover/intel:scale-110 transition-transform"></i>
                    Stats
                </button>
            </div>
        `;
        container.appendChild(card);
    });
    if (window.lucide) lucide.createIcons();
}

async function showPlayerDetails(playerId, playerName = 'Giocatore') {
    if (!playerId) return;
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.classList.remove('hidden');
    title.textContent = playerName;
    body.innerHTML = '<div class="text-center py-12 text-slate-500 font-bold uppercase tracking-widest animate-pulse">Caricamento dettagli giocatore...</div>';
    btn.classList.add('hidden');

    try {
        const res = await fetch(`/api/player/${playerId}`);
        const p = await res.json();

        if (!p || p.error) {
            body.innerHTML = "Dati giocatore non disponibili.";
            return;
        }

        body.innerHTML = `
            <div class="space-y-6 text-slate-900 dark:text-slate-100">
                <div class="flex items-center gap-6">
                    <img src="${p.photo}" class="w-24 h-24 rounded-2xl border-2 border-accent object-cover p-1 bg-white">
                    <div>
                        <h2 class="text-3xl font-black tracking-tight text-slate-900 dark:text-white">${p.name}</h2>
                        <p class="text-slate-500 mb-2 font-bold">${p.firstname} ${p.lastname}</p>
                        <span class="bg-accent/10 text-accent px-4 py-1.5 rounded-xl text-xs font-black uppercase tracking-widest border border-accent/20">${p.nationality}</span>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-100 dark:bg-white/5 p-5 rounded-[24px] border border-slate-200 dark:border-white/5">
                        <h4 class="text-[10px] uppercase tracking-widest text-slate-500 font-black mb-1">Età</h4>
                        <p class="text-lg font-black text-slate-900 dark:text-white">${p.age || 'N/A'} anni</p>
                    </div>
                    <div class="bg-slate-100 dark:bg-white/5 p-5 rounded-[24px] border border-slate-200 dark:border-white/5">
                        <h4 class="text-[10px] uppercase tracking-widest text-slate-500 font-black mb-1">Nazionalità</h4>
                        <p class="text-lg font-black text-slate-900 dark:text-white">${p.nationality || 'N/A'}</p>
                    </div>
                    <div class="bg-slate-100 dark:bg-white/5 p-5 rounded-[24px] border border-slate-200 dark:border-white/5">
                        <h4 class="text-[10px] uppercase tracking-widest text-slate-500 font-black mb-1">Altezza</h4>
                        <p class="text-lg font-black text-slate-900 dark:text-white">${p.height || 'N/A'}</p>
                    </div>
                    <div class="bg-slate-100 dark:bg-white/5 p-5 rounded-[24px] border border-slate-200 dark:border-white/5">
                        <h4 class="text-[10px] uppercase tracking-widest text-slate-500 font-black mb-1">Peso</h4>
                        <p class="text-lg font-black text-slate-900 dark:text-white">${p.weight || 'N/A'}</p>
                    </div>
                </div>
            </div>
        `;
    } catch (e) {
        body.innerHTML = "Errore nel caricamento del giocatore.";
    }
}

function updateMinutes() {
    const times = document.querySelectorAll('.elapsed-time');
    times.forEach(el => {
        let current = parseInt(el.textContent);
        if (current < 90) {
            el.textContent = current + 1;
        }
    });
}

function showBetDetails(bet) {
    const modal = document.getElementById("analysis-modal");
    const body = document.getElementById("modal-body");
    const title = document.getElementById("modal-title");
    const btn = document.getElementById("place-bet-btn");

    modal.classList.remove('hidden');
    title.textContent = "Dettaglio Scommessa";
    btn.classList.add('hidden');

    const statusColors = {
        'won': 'text-success border-success',
        'lost': 'text-danger border-danger',
        'pending': 'text-warning border-warning'
    };
    const statusColorClass = statusColors[bet.status] || 'text-slate-500 border-slate-200';

    const stake = parseFloat(bet.stake) || 0;
    const odds = parseFloat(bet.odds) || 0;
    const profit = bet.status === "won" ? (stake * (odds - 1)) : (bet.status === "lost" ? -stake : 0);

    body.innerHTML = `
        <div class="mb-8 text-slate-900 dark:text-white">
            <div class="text-2xl font-black mb-1">${bet.match_name}</div>
            <div class="text-xs text-slate-500 font-black uppercase tracking-widest">${bet.timestamp}</div>
        </div>

        <div class="bg-slate-100 dark:bg-white/5 p-8 rounded-[32px] border-l-4 ${statusColorClass} mb-8 shadow-sm">
            <div class="flex justify-between items-center mb-6">
                <span class="text-[10px] font-black text-slate-400 tracking-widest uppercase italic">Riepilogo Giocata</span>
                <span class="px-4 py-1.5 rounded-xl text-[10px] font-black uppercase bg-white dark:bg-slate-900 border ${statusColorClass.split(' ')[1]}">${bet.status}</span>
            </div>
            <div class="text-xl font-black mb-8 text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-slate-500 font-medium">Risultato:</span> ${bet.result || "In corso..."}
            </div>
            <div class="grid grid-cols-2 gap-8 text-slate-900 dark:text-white">
                <div>
                    <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-2">Mercato</p>
                    <p class="font-black text-accent uppercase tracking-tight">${bet.market}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-2">Quota</p>
                    <p class="font-black text-2xl tabular-nums">${bet.odds}</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-2">Puntata</p>
                    <p class="font-black text-lg tabular-nums">${bet.stake}€</p>
                </div>
                <div>
                    <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-2">Bilancio</p>
                    <p class="font-black text-2xl tabular-nums ${profit >= 0 ? (profit > 0 ? "text-success" : "") : "text-danger"}">
                        ${profit >= 0 ? "+" : ""}${profit.toFixed(2)}€
                    </p>
                </div>
            </div>
        </div>

        ${bet.advice ? `
            <div class="mt-8">
                <div class="text-[10px] font-black text-slate-500 tracking-widest uppercase mb-4 flex items-center gap-2">
                    <i data-lucide="brain-circuit" class="w-4 h-4 text-accent"></i> Analisi di Gemini Intelligence
                </div>
                <div class="bg-slate-100 dark:bg-white/5 p-6 rounded-[24px] border border-slate-200 dark:border-white/5 text-sm leading-relaxed whitespace-pre-wrap max-h-[200px] overflow-y-auto custom-scrollbar italic text-slate-600 dark:text-slate-400 font-medium">
                    ${bet.advice}
                </div>
            </div>
        ` : ""}
    `;
    if (window.lucide) lucide.createIcons();
}

function renderHistory(history) {
    const container = document.getElementById("history-container");
    container.innerHTML = history.length === 0 ? "<div class='p-10 text-center text-slate-500 font-bold text-sm'>Nessuna attività registrata.</div>" : "";

    history.slice(0, 15).forEach(h => {
        const item = document.createElement("div");
        item.className = "p-6 hover:bg-white/5 cursor-pointer transition-all group relative border-l-4 border-transparent dark:hover:bg-slate-800/50";
        item.onclick = () => showBetDetails(h);

        const statusConfigs = {
            'won': { color: 'text-success', bg: 'bg-success/10', border: 'border-success', icon: 'check-circle' },
            'lost': { color: 'text-danger', bg: 'bg-danger/10', border: 'border-danger', icon: 'x-circle' },
            'pending': { color: 'text-warning', bg: 'bg-warning/10', border: 'border-warning', icon: 'clock' }
        };
        const config = statusConfigs[h.status] || { color: 'text-slate-500', bg: 'bg-slate-500/10', border: 'border-slate-500', icon: 'help-circle' };

        if (h.status === 'won') item.classList.add('border-success');
        if (h.status === 'lost') item.classList.add('border-danger');
        if (h.status === 'pending') item.classList.add('border-warning');

        item.innerHTML = `
            <div class="flex justify-between items-start mb-2">
                <span class="font-black text-sm uppercase tracking-tight group-hover:text-accent transition-colors truncate max-w-[150px] text-slate-900 dark:text-slate-100 italic">${h.match_name || h.match}</span>
                <div class="flex items-center gap-1.5 ${config.color} ${config.bg} px-2 py-0.5 rounded-lg border ${config.border}">
                    <i data-lucide="${config.icon}" class="w-3 h-3"></i>
                    <span class="text-[9px] font-black uppercase tracking-widest">${h.status}</span>
                </div>
            </div>
            <div class="text-[11px] text-slate-500 font-bold uppercase tracking-wider">
                ${h.market} <span class="text-accent">@ ${h.odds}</span> <span class="mx-1 opacity-30">•</span> <span class="text-slate-900 dark:text-white font-black">${h.stake}€</span>
            </div>
        `;
        container.appendChild(item);
    });
    if (window.lucide) lucide.createIcons();
}

async function showStandings(leagueId, leagueName) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.classList.remove('hidden');
    title.textContent = `Classifica: ${leagueName}`;
    body.innerHTML = '<div class="text-center py-12 text-slate-500 font-bold uppercase tracking-widest animate-pulse">Caricamento classifica...</div>';
    btn.classList.add('hidden');

    try {
        const res = await fetch(`/api/standings/${leagueId}`);
        const data = await res.json();

        let html = `
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-white/10 text-slate-500 uppercase text-[9px] font-black tracking-widest">
                            <th class="py-4 px-3">Pos</th>
                            <th class="py-4 px-3">Squadra</th>
                            <th class="py-4 px-3 text-center">Punti</th>
                            <th class="py-4 px-3">Forma</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-white/5">
        `;

        data.forEach(row => {
            html += `
                <tr class="hover:bg-slate-50 dark:hover:bg-white/5 transition-colors">
                    <td class="py-4 px-3 font-black text-accent tabular-nums">${row.rank}</td>
                    <td class="py-4 px-3 flex items-center gap-3">
                        <img src="${row.team_logo}" class="w-5 h-5 object-contain">
                        <span class="font-black uppercase tracking-tight text-slate-900 dark:text-white">${row.team_name}</span>
                    </td>
                    <td class="py-4 px-3 font-black text-center text-slate-900 dark:text-white tabular-nums">${row.points}</td>
                    <td class="py-4 px-3">
                        <div class="flex gap-1">
                            ${(row.form || '').split('').map(f => {
                let c = 'bg-slate-500';
                if (f === 'W') c = 'bg-success';
                if (f === 'L') c = 'bg-danger';
                if (f === 'D') c = 'bg-warning';
                return `<span class="w-1.5 h-1.5 rounded-full ${c}"></span>`;
            }).join('')}
                        </div>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        body.innerHTML = `<div class="max-h-[450px] overflow-y-auto pr-2 custom-scrollbar">${html}</div>`;
    } catch (e) {
        body.innerHTML = "Errore nel caricamento della classifica.";
    }
}

async function showTeamDetails(teamId) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.classList.remove('hidden');
    title.textContent = `Dettagli Squadra`;
    body.innerHTML = '<div class="text-center py-12 text-slate-500 font-bold uppercase tracking-widest animate-pulse">Caricamento dettagli...</div>';
    btn.classList.add('hidden');

    try {
        const res = await fetch(`/api/team/${teamId}`);
        const data = await res.json();
        const t = data.team;
        const c = data.coach;
        const squad = data.squad || [];

        title.textContent = t ? t.name : 'Team details';
        if (!t) {
            body.innerHTML = "Dati squadra non trovati.";
            return;
        }

        let squadHtml = '';
        if (squad.length > 0) {
            squadHtml = `
                <div class="mt-8">
                    <h4 class="text-xs uppercase tracking-widest text-slate-500 font-black mb-4 flex items-center gap-2 italic">
                        <i data-lucide="users" class="w-4 h-4 text-accent"></i> Rosa Giocatori
                    </h4>
                    <div class="bg-slate-50 dark:bg-white/5 rounded-[24px] overflow-hidden border border-slate-200 dark:border-white/5">
                        <div class="max-h-[300px] overflow-y-auto custom-scrollbar">
                            <table class="w-full text-[10px] text-left">
                                <thead class="sticky top-0 bg-slate-100 dark:bg-slate-900 text-slate-500 uppercase text-[8px] font-black tracking-widest">
                                    <tr>
                                        <th class="py-3 px-4">#</th>
                                        <th class="py-3 px-4">Nome</th>
                                        <th class="py-3 px-4">Pos</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-white/5">
                                    ${squad.map(p => `
                                        <tr class="hover:bg-accent/10 cursor-pointer transition-colors text-slate-900 dark:text-white" onclick="showPlayerDetails(${p.id}, '${p.name}')">
                                            <td class="py-3 px-4 font-black text-accent tabular-nums">${p.number || '-'}</td>
                                            <td class="py-3 px-4 font-black uppercase tracking-tight">${p.name}</td>
                                            <td class="py-3 px-4 text-slate-500 font-bold uppercase">${p.position}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }

        body.innerHTML = `
            <div class="max-h-[550px] overflow-y-auto pr-2 custom-scrollbar">
                <div class="flex items-center gap-6 mb-10">
                    <div class="w-24 h-24 bg-white rounded-[32px] p-4 shadow-xl border border-slate-200 dark:border-white/10 flex items-center justify-center">
                        <img src="${t.logo}" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <h2 class="text-4xl font-black tracking-tighter text-slate-900 dark:text-white uppercase italic">${t.name}</h2>
                        <p class="text-slate-500 font-bold uppercase tracking-widest text-xs">${t.country} | Fondata nel ${t.founded || 'N/A'}</p>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-slate-50 dark:bg-white/5 p-5 rounded-[24px] border border-slate-200 dark:border-white/5">
                        <h4 class="text-[10px] uppercase tracking-widest text-slate-500 font-black mb-1 italic">Stadio</h4>
                        <p class="font-black text-slate-900 dark:text-white uppercase truncate text-sm">${t.venue_name || 'N/A'}</p>
                        <p class="text-[10px] text-slate-500 font-bold">Capacità: ${t.venue_capacity || 'N/A'}</p>
                    </div>

                    ${c ? `
                    <div class="bg-slate-50 dark:bg-white/5 p-5 rounded-[24px] border border-slate-200 dark:border-white/5">
                        <h4 class="text-[10px] uppercase tracking-widest text-slate-500 font-black mb-1 italic">Coach</h4>
                        <div class="flex items-center gap-3">
                            <img src="${c.photo}" class="w-10 h-10 rounded-full border border-accent/20">
                            <div class="truncate">
                                <p class="font-black text-sm uppercase tracking-tight text-slate-900 dark:text-white truncate">${c.name}</p>
                                <p class="text-[9px] text-slate-500 font-black uppercase truncate tracking-widest">${c.nationality || ''}</p>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>

                ${squadHtml}
            </div>
        `;
        if (window.lucide) lucide.createIcons();
    } catch (e) {
        console.error(e);
        body.innerHTML = "Errore nel caricamento dei dettagli squadra.";
    }
}

async function analyzeMatch(id) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.classList.remove('hidden');
    title.textContent = "Analisi Prossima Giocata";
    body.innerHTML = '<div class="text-center py-12"><div class="inline-flex items-center gap-3 bg-accent text-white px-8 py-4 rounded-[24px] font-black text-xs tracking-widest shadow-xl shadow-accent/30 animate-pulse uppercase"><i data-lucide="brain-circuit" class="w-5 h-5"></i> Analizzando con Gemini AI...</div></div>';
    btn.classList.add('hidden');
    if (window.lucide) lucide.createIcons();

    try {
        const res = await fetch(`/api/analyze/${id}`);
        const data = await res.json();

        if (data.error) {
            body.innerHTML = `<div class="text-danger font-bold">${data.error}</div>`;
            return;
        }

        const prediction = data.prediction;
        let betData = null;
        let displayHtml = prediction;

        const jsonMatch = prediction.match(/```json\n?([\s\S]*?)\n?```/i);
        if (jsonMatch) {
            try {
                betData = JSON.parse(jsonMatch[1]);
                displayHtml = prediction.replace(/```json[\s\S]*?```/i, '');
            } catch (e) {
                console.error("JSON Parse Error", e);
            }
        }

        body.innerHTML = `
            <div class="max-h-[450px] overflow-y-auto pr-4 custom-scrollbar">
                <div class="text-slate-700 dark:text-slate-400 leading-relaxed font-bold mb-10 whitespace-pre-wrap">${displayHtml}</div>
                ${betData ? `
                    <div class="bg-slate-100 dark:bg-white/5 p-8 rounded-[32px] border-l-4 border-accent shadow-xl">
                        <div class="flex items-center gap-2 font-black text-accent mb-4 tracking-widest text-xs uppercase">
                            <i data-lucide="zap" class="w-4 h-4"></i> Consiglio AI Premium
                        </div>
                        <div class="text-xl font-black text-slate-900 dark:text-white mb-6 uppercase italic">${betData.advice}</div>
                        <div class="grid grid-cols-2 gap-8 text-slate-900 dark:text-white">
                            <div>
                                <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-1">Mercato</p>
                                <p class="font-bold text-sm uppercase">${betData.market}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-1">Quota</p>
                                <p class="font-black text-xl tabular-nums">${betData.odds}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-1">Urgenza</p>
                                <p class="font-bold text-warning uppercase text-sm">${betData.urgency}</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase font-black text-slate-500 tracking-widest mb-1">Stake</p>
                                <p class="font-black text-xl text-success tabular-nums">${betData.stake}</p>
                            </div>
                        </div>
                    </div>
                ` : '<div class="text-warning font-bold bg-warning/10 p-4 rounded-xl border border-warning/20">Nessun dato scommessa strutturato trovato.</div>'}
            </div>
        `;
        if (window.lucide) lucide.createIcons();

        if (betData) {
            btn.classList.remove('hidden');
            btn.onclick = () => placeBet(id, data.match, betData);
        }

    } catch (e) {
        body.innerHTML = "Errore durante l'analisi. Riprova.";
    }
}

async function placeBet(fixture_id, match, betData) {
    try {
        const matchName = typeof match === 'string' ? match : `${match.teams.home.name} vs ${match.teams.away.name}`;
        const res = await fetch('/api/place_bet', {
            method: 'POST',
            body: JSON.stringify({ fixture_id, match: matchName, ...betData })
        });
        const result = await res.json();
        if (result.status === 'success') {
            closeModal();
            fetchHistory();
        } else {
            alert("Scommessa già esistente o errore.");
        }
    } catch (e) {
        alert("Errore nell'invio della scommessa.");
    }
}


async function showIntelligence(id) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.classList.remove('hidden');
    title.textContent = "Intelligence & Predizioni";
    body.innerHTML = '<div class="text-center py-12"><div class="inline-flex items-center gap-3 bg-white/5 text-slate-500 px-8 py-4 rounded-[24px] font-black text-xs tracking-widest animate-pulse uppercase border border-white/5"><i data-lucide="loader" class="w-5 h-5 animate-spin"></i> Loading Intelligence...</div></div>';
    btn.classList.add('hidden');
    if (window.lucide) lucide.createIcons();

    try {
        const res = await fetch(`/api/predictions/${id}`);
        const data = await res.json();

        if (!data || data.error) {
            body.innerHTML = '<div class="text-center py-12 text-slate-500 font-bold">Dati non disponibili per questo match.</div>';
            return;
        }

        const comp = data.comparison || {};
        const perc = data.percent || {};

        const metrics = [
            { label: 'Forma', key: 'form' },
            { label: 'Attacco', key: 'attaching' },
            { label: 'Difesa', key: 'defensive' },
            { label: 'Probabilità Gol', key: 'poisson_distribution' },
            { label: 'Testa a Testa', key: 'h2h' },
            { label: 'Gol', key: 'goals' }
        ];

        let metricsHtml = metrics.map(m => {
            const val = comp[m.key] || { home: '50%', away: '50%' };
            const h = parseInt(val.home);
            const a = parseInt(val.away);
            return `
                <div class="space-y-2">
                    <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest text-slate-500">
                        <span>${h}%</span>
                        <span class="text-white italic">${m.label}</span>
                        <span>${a}%</span>
                    </div>
                    <div class="h-2 bg-white/5 rounded-full overflow-hidden flex">
                        <div class="h-full bg-accent" style="width: ${h}%"></div>
                        <div class="h-full bg-success/50" style="width: ${a}%"></div>
                    </div>
                </div>
            `;
        }).join('');

        body.innerHTML = `
            <div class="max-h-[550px] overflow-y-auto pr-4 custom-scrollbar">
                <div class="bg-accent/10 border border-accent/20 p-6 rounded-[32px] mb-10">
                    <div class="text-[10px] font-black text-accent uppercase tracking-widest mb-2 flex items-center gap-2">
                        <i data-lucide="info" class="w-4 h-4"></i> Algorithmic Advice
                    </div>
                    <div class="text-2xl font-black text-white italic tracking-tight uppercase">${data.advice || 'N/A'}</div>
                </div>

                <div class="grid grid-cols-3 gap-4 mb-10">
                    <div class="bg-white/5 p-5 rounded-[24px] border border-white/5 text-center">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Home</p>
                        <p class="text-2xl font-black text-white">${perc.home || 'N/A'}</p>
                    </div>
                    <div class="bg-white/5 p-5 rounded-[24px] border border-white/5 text-center">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Draw</p>
                        <p class="text-2xl font-black text-white">${perc.draw || 'N/A'}</p>
                    </div>
                    <div class="bg-white/5 p-5 rounded-[24px] border border-white/5 text-center">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1">Away</p>
                        <p class="text-2xl font-black text-white">${perc.away || 'N/A'}</p>
                    </div>
                </div>

                <div class="space-y-8 bg-white/5 p-8 rounded-[40px] border border-white/5">
                    <h4 class="text-xs font-black text-white uppercase tracking-[0.2em] mb-4 text-center">Data Comparison</h4>
                    ${metricsHtml}
                </div>
            </div>
        `;
        if (window.lucide) lucide.createIcons();
    } catch (e) {
        console.error(e);
        body.innerHTML = "Errore nel caricamento delle statistiche.";
    }
}

function closeModal() {
    document.getElementById('analysis-modal').classList.add('hidden');
}

// Initial fetch
fetchLive();
fetchHistory();
fetchUsage();

// Event Listener for Manual Sync
const syncBtn = document.getElementById('sync-btn');
if (syncBtn) {
    syncBtn.addEventListener('click', async () => {
        syncBtn.disabled = true;
        const originalContent = syncBtn.innerHTML;
        syncBtn.innerHTML = '<i data-lucide="refresh-cw" class="w-4 h-4 rotator"></i>';
        if (window.lucide) lucide.createIcons();

        try {
            const res = await fetch('/api/sync');
            const data = await res.json();
            console.log('Sync completed:', data);
            await fetchHistory();
        } catch (e) {
            console.error('Sync failed', e);
        } finally {
            syncBtn.disabled = false;
            syncBtn.innerHTML = originalContent;
            if (window.lucide) lucide.createIcons();
        }
    });
}

// Intervals
setInterval(fetchLive, 60000);    // Refresh full data every 60s
setInterval(fetchHistory, 40000); // Refresh history every 40s
setInterval(updateMinutes, 60000); // Increment local minutes every 60s
setInterval(fetchUsage, 60000);   // Refresh usage every 60s

// Close modal on click outside
window.onclick = (event) => {
    if (event.target == document.getElementById('analysis-modal')) {
        closeModal();
    }
};

// Theme Toggle Logic
const themeToggle = document.getElementById('theme-toggle');
const htmlElement = document.documentElement;

function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    if (savedTheme === 'dark') {
        htmlElement.classList.add('dark');
    } else {
        htmlElement.classList.remove('dark');
    }
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

initTheme();