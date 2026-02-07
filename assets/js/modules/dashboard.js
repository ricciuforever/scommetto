import { fetchJson, endpoints } from './api.js';
import { state, updateState, formatDate } from './utils.js';
import { UI } from './ui.js';
import { Tracker } from './tracker.js'; // For stats calculation reuse

export class Dashboard {
    static async render() {
        const container = document.getElementById('view-container');
        if (!container) return;

        container.innerHTML = `
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

        await Promise.all([
            this.fetchLive(),
            Tracker.fetchHistory(), // Pre-fetch history for stats
            this.fetchPredictions()
        ]);

        this.updateStatsSummary();
        this.renderMatches();
        this.renderHistory();
        this.renderPredictions();
        UI.createIcons();
    }

    static async fetchLive() {
        const data = await fetchJson(endpoints.live);
        if (data && data.response) {
            updateState('liveMatches', data.response);
        } else {
            updateState('liveMatches', []);
        }
    }

    static async fetchPredictions() {
        try {
            const data = await fetchJson(endpoints.predictions);
            updateState('predictions', Array.isArray(data) ? data : []);
        } catch (e) {
            console.warn("Predictions API not available yet");
            updateState('predictions', []);
        }
    }


    static updateStatsSummary() {
        const container = document.getElementById('stats-summary');
        if (!container) return;

        const data = state.historyData || [];
        let pendingCount = 0; let winCount = 0; let lossCount = 0; let netProfit = 0;
        const startingPortfolio = 100;
        let globalNetProfit = 0;

        // Calculate stats
        data.forEach(bet => {
            const stake = parseFloat(bet.stake) || 0;
            const odds = parseFloat(bet.odds) || 0;
            const status = (bet.status || "").toLowerCase();

            if (status === "won") globalNetProfit += stake * (odds - 1);
            else if (status === "lost") globalNetProfit -= stake;

            // Filter logic same as original
            const matchesCountry = state.selectedCountry === 'all' || (bet.country || bet.country_name || 'International') === state.selectedCountry;
            const matchesBookie = state.selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === state.selectedBookmaker;

            if (matchesCountry && matchesBookie) {
                if (status === "pending") pendingCount++;
                else if (status === "won") { winCount++; netProfit += stake * (odds - 1); }
                else if (status === "lost") { lossCount++; netProfit -= stake; }
            }
        });

        const liveCount = (state.liveMatches || []).filter(m => {
            const countryName = m.league?.country || m.league?.country_name;
            const matchesCountry = state.selectedCountry === 'all' || countryName === state.selectedCountry;
            const matchesBookie = state.selectedBookmaker === 'all'
                ? (m.available_bookmakers || []).length > 0
                : (m.available_bookmakers || []).includes(parseInt(state.selectedBookmaker));
            return matchesCountry && matchesBookie;
        }).length;

        const currentPortfolio = startingPortfolio + globalNetProfit;
        const countryLabel = state.selectedCountry === 'all' ? 'Tutte' : state.selectedCountry;

        container.innerHTML = `
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <span class="block text-2xl font-black mb-1">${currentPortfolio.toFixed(2)}€</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Portafoglio</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <span class="block text-2xl font-black mb-1">${winCount}W - ${lossCount}L</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Filtro: ${countryLabel}</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <span class="block text-2xl font-black mb-1 ${netProfit >= 0 ? 'text-success' : 'text-danger'}">${netProfit >= 0 ? '+' : ''}${netProfit.toFixed(2)}€</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Profitto Netto</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <span class="block text-2xl font-black mb-1">${liveCount}</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Live Now</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <span class="block text-2xl font-black mb-1 text-warning">${pendingCount}</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">In Sospeso</span>
            </div>
            <div class="glass p-5 rounded-3xl border-white/5 relative overflow-hidden group">
                <span class="block text-2xl font-black mb-1 text-success">ROI</span>
                <span class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Return On Investment</span>
            </div>
        `;
    }

    static renderMatches() {
        const container = document.getElementById('live-matches-list');
        if (!container) return;

        const matches = state.liveMatches || [];
        const filteredMatches = matches.filter(m => {
            const countryName = m.league?.country || m.league?.country_name;
            const matchesCountry = state.selectedCountry === 'all' || countryName === state.selectedCountry;
            const matchesBookie = state.selectedBookmaker === 'all'
                ? (m.available_bookmakers || []).length > 0
                : (m.available_bookmakers || []).includes(parseInt(state.selectedBookmaker));
            return matchesCountry && matchesBookie;
        });

        if (filteredMatches.length === 0) {
            const msg = state.selectedCountry === 'all' ? 'nessun match live' : `nessun match live per ${state.selectedCountry}`;
            container.innerHTML = `<div class="glass p-10 rounded-[32px] text-center text-slate-500 font-black italic uppercase tracking-widest">${msg}</div>`;
            return;
        }

        container.innerHTML = filteredMatches.map(m => `
            <div class="glass rounded-[40px] p-8 border-white/5 hover:border-accent/30 transition-all group cursor-pointer" onclick="window.app.analyzeMatch(${m.fixture.id})">
                <div class="flex items-center justify-between mb-8">
                    <div class="flex items-center gap-3 opacity-60">
                        <img src="${m.league.logo}" class="w-5 h-5 object-contain" onerror="this.src='https://media.api-sports.io/football/leagues/1.png'">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] italic text-slate-400">${m.league.name}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black text-accent uppercase tracking-widest animate-pulse">${m.fixture.status.elapsed || 0}'</span>
                        <div class="w-2 h-2 bg-danger rounded-full animate-ping"></div>
                    </div>
                </div>
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-8 mb-4 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <img src="${m.teams.home.logo}" class="w-16 h-16 object-contain" onerror="this.src='https://media.api-sports.io/football/teams/default.png'">
                        <span class="text-xs font-black uppercase tracking-tight text-white max-w-[100px] truncate">${m.teams.home.name}</span>
                    </div>
                    <div class="text-5xl font-black italic tracking-tighter text-white flex gap-4">
                        <span>${m.goals.home ?? 0}</span><span class="text-white/20">-</span><span>${m.goals.away ?? 0}</span>
                    </div>
                    <div class="flex flex-col items-center gap-3">
                        <img src="${m.teams.away.logo}" class="w-16 h-16 object-contain" onerror="this.src='https://media.api-sports.io/football/teams/default.png'">
                        <span class="text-xs font-black uppercase tracking-tight text-white max-w-[100px] truncate">${m.teams.away.name}</span>
                    </div>
                </div>
                <div class="mt-6 flex justify-center">
                    <button class="bg-white/5 hover:bg-accent hover:text-white px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all">Analizza Match</button>
                </div>
            </div>
        `).join('');
    }

    static renderHistory() {
        const container = document.getElementById('dashboard-history');
        if (!container) return;
        const history = (state.historyData || []).slice(0, 5); // Take first 5

        if (history.length === 0) {
            container.innerHTML = '<div class="p-8 text-center text-slate-500 text-xs uppercase font-bold">Nessuna attività recente</div>';
            return;
        }

        container.innerHTML = history.map(h => `
            <div class="p-6 hover:bg-white/5 transition-colors flex items-center justify-between cursor-pointer" onclick="window.app.showBetDetails(${h.id})">
                <div class="flex items-center gap-4">
                     <div class="w-8 h-8 rounded-full flex items-center justify-center border ${h.status === 'won' ? 'border-success bg-success/10 text-success' : h.status === 'lost' ? 'border-danger bg-danger/10 text-danger' : 'border-warning bg-warning/10 text-warning'}">
                        <i data-lucide="${h.status === 'won' ? 'check' : h.status === 'lost' ? 'x' : 'clock'}" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <div class="text-sm font-black text-white italic uppercase">${h.match_name}</div>
                        <div class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">${h.market}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-lg font-black italic tracking-tighter ${h.status === 'won' ? 'text-success' : h.status === 'lost' ? 'text-danger' : 'text-slate-500'}">
                        ${h.status === 'won' ? '+' + (h.stake * (h.odds - 1)).toFixed(2) : (h.status === 'lost' ? '-' + h.stake : '0.00')}€
                    </div>
                </div>
            </div>
        `).join('');
    }

    static renderPredictions() {
        const container = document.getElementById('dashboard-predictions');
        if (!container) return;

        const preds = (state.predictions || []).slice(0, 3); // Top 3

        if (preds.length === 0) {
            container.innerHTML = '<div class="p-6 text-center text-slate-500 text-xs uppercase font-bold">Nessun pronostico hot</div>';
            return;
        }

        // Basic rendering for now
        container.innerHTML = preds.map(p => `
            <div class="glass p-6 rounded-3xl border-white/5 hover:border-accent/20 cursor-pointer transition-all">
                <div class="flex justify-between items-start mb-2">
                    <span class="text-[9px] font-black uppercase tracking-widest text-accent">High Confidence</span>
                    <span class="text-white font-black italic">${p.confidence || '80%'}</span>
                </div>
                <div class="text-lg font-black italic uppercase text-white leading-tight mb-2">${p.match_name || 'Match Name'}</div>
                <div class="text-slate-400 text-xs font-bold uppercase tracking-wide">${p.prediction || 'Prediction text'}</div>
            </div>
         `).join('');
    }
}
