import { state, updateState } from './utils.js';
import { fetchJson, endpoints } from './api.js';
import { UI } from './ui.js';

export class Tracker {
    static async fetchHistory() {
        const response = await fetchJson(endpoints.history);
        if (Array.isArray(response)) {
            updateState('historyData', response);
            return response;
        }
        updateState('historyData', []);
        return [];
    }

    static calculateStats() {
        const data = state.historyData || [];
        const filtered = data.filter(bet => {
            const matchesCountry = state.selectedCountry === 'all' || bet.country === state.selectedCountry;
            const matchesBookie = state.selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === state.selectedBookmaker;
            return matchesCountry && matchesBookie;
        });

        let won = 0, lost = 0, profit = 0, pending = 0;

        filtered.forEach(bet => {
            const s = (bet.status || '').toLowerCase();
            if (s === 'won') {
                won++;
                profit += (parseFloat(bet.stake) * (parseFloat(bet.odds) - 1));
            } else if (s === 'lost') {
                lost++;
                profit -= parseFloat(bet.stake);
            } else {
                pending++;
            }
        });

        const totalResolved = won + lost;
        const roi = totalResolved > 0 || pending > 0 ? (profit / (totalResolved + pending)) * 100 : 0; // Very rough ROI

        return {
            won,
            lost,
            pending,
            profit,
            roi: roi, // Simplified
            netProfit: profit,
            winCount: won,
            lossCount: lost,
            currentPortfolio: 100 + profit // Mock starting bankroll
        };
    }

    static async render() {
        const container = document.getElementById('view-container');
        if (!container) return;

        // Render Structure
        container.innerHTML = `
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10" id="tracker-stats-summary"></div>
            
            <div class="flex gap-4 mb-8 overflow-x-auto pb-2 no-scrollbar">
                <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap bg-white/5 text-slate-500 hover:bg-white/10" data-status="all">Tutte</button>
                <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap bg-white/5 text-slate-500 hover:bg-white/10" data-status="won">Vinte</button>
                <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap bg-white/5 text-slate-500 hover:bg-white/10" data-status="lost">Perse</button>
                <button class="tracker-filter-btn px-6 py-3 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all whitespace-nowrap bg-white/5 text-slate-500 hover:bg-white/10" data-status="pending">In Corso</button>
            </div>

            <div class="glass rounded-[40px] border-white/5 overflow-hidden divide-y divide-white/5" id="tracker-history"></div>
        `;

        // Attach event listeners for filters
        this.attachFilterListeners();

        // Fetch and Render Data
        await this.fetchHistory();
        this.updateFilterUI(); // Set initial active state
        this.renderSummary();
        this.renderHistoryList();
    }

    static attachFilterListeners() {
        const buttons = document.querySelectorAll('.tracker-filter-btn');
        buttons.forEach(btn => {
            btn.onclick = () => {
                const status = btn.dataset.status;
                updateState('trackerStatusFilter', status);
                this.updateFilterUI();
                this.renderHistoryList();
            };
        });
    }

    static updateFilterUI() {
        const currentFilter = state.trackerStatusFilter || 'all';
        document.querySelectorAll('.tracker-filter-btn').forEach(btn => {
            if (btn.dataset.status === currentFilter) {
                btn.classList.add('bg-accent', 'text-white');
                btn.classList.remove('bg-white/5', 'text-slate-500');
            } else {
                btn.classList.remove('bg-accent', 'text-white');
                btn.classList.add('bg-white/5', 'text-slate-500');
            }
        });
    }

    static renderSummary() {
        const summary = this.calculateStats();
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

    static renderHistoryList() {
        const container = document.getElementById('tracker-history');
        if (!container) return;

        const data = state.historyData || [];
        const filterStatus = state.trackerStatusFilter || 'all';

        const filtered = data.filter(bet => {
            const matchesCountry = state.selectedCountry === 'all' || bet.country === state.selectedCountry;
            const matchesBookie = state.selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === state.selectedBookmaker;
            const matchesStatus = filterStatus === 'all' || (bet.status || '').toLowerCase().trim() === filterStatus;

            return matchesCountry && matchesBookie && matchesStatus;
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div class="p-20 text-center text-slate-500 font-black uppercase italic">Nessuna scommessa trovata per i filtri selezionati.</div>';
            return;
        }

        container.innerHTML = filtered.map(h => `
            <div class="p-8 hover:bg-white/5 cursor-pointer transition-all flex items-center justify-between group" onclick="window.app.showBetDetails(${h.id})">
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
            </div>
        `).join('');

        UI.createIcons();
    }
}
