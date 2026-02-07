import { fetchJson, endpoints } from './api.js';
import { state, updateState, formatDate } from './utils.js';
import { UI } from './ui.js';
import { Tracker } from './tracker.js'; // For stats calculation reuse

export class Dashboard {
    static async render() {
        const container = document.getElementById('view-content');
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
        const data = await fetchJson(endpoints.predictions);
        updateState('predictions', Array.isArray(data) ? data : []);
    }


    static updateStatsSummary() {
        const container = document.getElementById('stats-summary');
        if (!container) return;

        // Reuse Tracker logic for consistent stats
        const stats = Tracker.calculateStats();

        // Mock Bankroll for now or fetch from API if available
        const bankroll = stats.profit + 100; // Example base

        const cards = [
            { label: 'Active', value: stats.pending, color: 'text-warning' },
            { label: 'Won', value: stats.won, color: 'text-success' },
            { label: 'Lost', value: stats.lost, color: 'text-danger' },
            { label: 'Net Profit', value: `${stats.profit >= 0 ? '+' : ''}${stats.profit.toFixed(2)}€`, color: stats.profit >= 0 ? 'text-success' : 'text-danger' },
            { label: 'ROI', value: `${stats.roi.toFixed(1)}%`, color: 'text-accent' },
            { label: 'Win Rate', value: `${(stats.won + stats.lost) > 0 ? ((stats.won / (stats.won + stats.lost)) * 100).toFixed(0) : 0}%`, color: 'text-white' }
        ];

        container.innerHTML = cards.map(c => `
            <div class="glass p-6 rounded-[32px] border-white/5 flex flex-col items-center justify-center text-center group hover:bg-white/5 transition-all">
                <span class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-2">${c.label}</span>
                <div class="text-3xl font-black italic tracking-tighter ${c.color}">${c.value}</div>
            </div>
         `).join('');
    }

    static renderMatches() {
        const container = document.getElementById('live-matches-list');
        if (!container) return;

        const matches = state.liveMatches || [];
        if (matches.length === 0) {
            container.innerHTML = '<div class="p-10 text-center text-slate-500 font-bold uppercase italic">Nessun match live al momento</div>';
            return;
        }

        container.innerHTML = matches.map(m => `
            <div class="glass p-8 rounded-[40px] border-white/5 hover:border-accent/20 transition-all group relative overflow-hidden">
                <div class="absolute top-0 right-0 p-6 opacity-0 group-hover:opacity-10 transition-opacity">
                    <i data-lucide="zap" class="w-24 h-24 text-accent rotate-12"></i>
                </div>
                <div class="flex flex-col md:flex-row items-center gap-8 relative z-10">
                    <div class="flex items-center gap-4 flex-1 w-full justify-center md:justify-start">
                         <div class="text-center w-12">
                            <span class="text-accent font-black text-xs block mb-1">Live</span>
                            <span class="text-white font-black text-lg animate-pulse">'${m.fixture.status.elapsed || 0}</span>
                         </div>
                         <div class="flex-1 flex flex-col gap-2">
                             <div class="flex justify-between items-center bg-white/5 p-3 rounded-2xl">
                                <span class="font-black text-lg uppercase italic text-white truncate max-w-[200px]">${m.teams.home.name}</span>
                                <span class="bg-darkbg px-4 py-1 rounded-lg text-xl font-black text-accent">${m.goals.home ?? 0}</span>
                             </div>
                             <div class="flex justify-between items-center bg-white/5 p-3 rounded-2xl">
                                <span class="font-black text-lg uppercase italic text-white truncate max-w-[200px]">${m.teams.away.name}</span>
                                <span class="bg-darkbg px-4 py-1 rounded-lg text-xl font-black text-accent">${m.goals.away ?? 0}</span>
                             </div>
                         </div>
                    </div>
                     <div class="w-full md:w-auto flex flex-col gap-2">
                        <button onclick="window.app.analyzeMatch(${m.fixture.id})" class="w-full bg-accent hover:bg-sky-400 text-white px-8 py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-lg shadow-accent/20 hover:scale-105 active:scale-95 transition-all flex items-center justify-center gap-2 group-hover:animate-pulse">
                            <i data-lucide="brain-circuit" class="w-4 h-4"></i>
                            Analizza con AI
                        </button>
                    </div>
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
