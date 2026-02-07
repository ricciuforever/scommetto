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
        return [];
    }

    static calculateStats() {
        // ... (existing implementation)
        const bets = state.historyData.filter(bet => {
            const matchesCountry = state.selectedCountry === 'all' || bet.country === state.selectedCountry;
            const matchesBookie = state.selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === state.selectedBookmaker;
            return matchesCountry && matchesBookie;
        });

        // Simplified stats calculation
        let won = 0, lost = 0, profit = 0;
        let pending = 0;

        bets.forEach(bet => {
            const s = (bet.status || '').toLowerCase();
            if (s === 'won') { won++; profit += (parseFloat(bet.stake) * (parseFloat(bet.odds) - 1)); }
            else if (s === 'lost') { lost++; profit -= parseFloat(bet.stake); }
            else { pending++; }
        });

        return { won, lost, pending, profit: profit, roi: (profit / (won + lost + pending)) * 100 }; // Very simplified
    }

    static async render() {
        const container = document.getElementById('tracker-history');
        if (!container) return;

        // Stat Summary Logic (correctly isolated)
        this.renderSummary();

        // History Logic with Correct Filtering
        const filtered = state.historyData.filter(bet => {
            const matchesCountry = state.selectedCountry === 'all' || bet.country === state.selectedCountry;
            const matchesBookie = state.selectedBookmaker === 'all' || bet.bookmaker_id?.toString() === state.selectedBookmaker;
            const matchesStatus = state.trackerStatusFilter === 'all' || (bet.status || '').toLowerCase().trim() === state.trackerStatusFilter;
            return matchesCountry && matchesBookie && matchesStatus;
        });

        if (filtered.length === 0) {
            container.innerHTML = '<div class="p-20 text-center text-slate-500 font-black uppercase italic">Nessuna scommessa trovata per i filtri selezionati.</div>';
            return;
        }

        container.innerHTML = filtered.map(h => this.createBetItem(h)).join('');
        UI.createIcons();
    }

    static createBetItem(h) {
        // ... (Template for single bet item)
        const profitClass = h.status === 'won' ? 'text-success' : h.status === 'lost' ? 'text-danger' : 'text-slate-500';
        const iconClass = h.status === 'won' ? 'check-circle' : h.status === 'lost' ? 'x-circle' : 'clock';
        const borderClass = h.status === 'won' ? 'border-success bg-success/10 text-success' : h.status === 'lost' ? 'border-danger bg-danger/10 text-danger' : 'border-warning bg-warning/10 text-warning';

        return `
            <div class="p-8 hover:bg-white/5 cursor-pointer transition-all flex items-center justify-between group" onclick="window.app.showBetDetails(${h.id})">
                <div class="flex items-center gap-6">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center border ${borderClass}">
                        <i data-lucide="${iconClass}" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="text-xl font-black italic uppercase tracking-tight text-white group-hover:text-accent transition-colors">${h.match_name}</div>
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">${h.market} @ ${h.odds} | ${new Date(h.timestamp).toLocaleDateString()}</div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-black italic tracking-tighter ${profitClass}">
                        ${h.status === 'won' ? '+' + (h.stake * (h.odds - 1)).toFixed(2) : (h.status === 'lost' ? '-' + h.stake : '0.00')}€
                    </div>
                    <div class="text-[9px] font-black uppercase tracking-widest text-slate-500">Puntata: ${h.stake}€</div>
                </div>
            </div>
        `;
    }

    static renderSummary() {
        // ... (Logic to render the 4 cards)
        // Assuming logic similar to previous `updateTrackerSummary`
        // Use `this.calculateStats()`
    }
}
