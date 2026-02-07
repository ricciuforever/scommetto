export const state = {
    allFilterData: { countries: [], bookmakers: [] },
    selectedCountry: localStorage.getItem('scommetto_country') || 'all',
    selectedBookmaker: localStorage.getItem('scommetto_bookmaker') || 'all',
    currentView: 'dashboard',
    historyData: [],
    trackerStatusFilter: 'all' // won, lost, pending, all
};

export function updateState(key, value) {
    state[key] = value;
    if (key === 'selectedCountry') localStorage.setItem('scommetto_country', value);
    if (key === 'selectedBookmaker') localStorage.setItem('scommetto_bookmaker', value);
}

export const formatDate = (isoString) => {
    if (!isoString) return '';
    return new Date(isoString).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
};

export const formatCurrency = (amount) => {
    return new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(amount);
};
