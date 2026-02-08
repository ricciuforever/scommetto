<?php
// app/Views/leagues.php
$pageTitle = 'Scommetto.AI - Competizioni Globali';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function LeagueCard({ league }) {
        const coverage = league.coverage_json ? JSON.parse(league.coverage_json) : {};

        return (
            <div className="glass border border-white/10 rounded-2xl p-5 flex flex-col gap-4 hover:scale-[1.02] transition-all cursor-pointer group relative overflow-hidden">
                <div className="flex justify-between items-start z-10">
                    <div className="w-12 h-12 bg-white/5 rounded-xl flex items-center justify-center p-2 border border-white/5">
                        <img src={league.logo} alt={league.name} className="max-w-full max-h-full object-contain" />
                    </div>
                    <span className={`text-[9px] font-black px-2 py-1 rounded-full uppercase tracking-widest ${league.type === 'League' ? 'bg-accent/20 text-accent' : 'bg-warning/20 text-warning'}`}>
                        {league.type === 'League' ? 'Campionato' : 'Coppa'}
                    </span>
                </div>

                <div className="z-10">
                    <h3 className="font-bold text-white group-hover:text-accent transition-colors truncate">
                        {league.name}
                    </h3>
                    <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-widest flex items-center gap-1 mt-1">
                        <i data-lucide="globe" className="w-3 h-3"></i>
                        {league.country_name}
                    </p>
                </div>

                <div className="grid grid-cols-4 gap-1 z-10 pt-2 border-t border-white/5">
                    <div title="Pronostici" className={`flex flex-col items-center p-1 rounded ${coverage.predictions ? 'text-success' : 'text-slate-600'}`}>
                        <i data-lucide="brain" className="w-3.5 h-3.5"></i>
                    </div>
                    <div title="Quote" className={`flex flex-col items-center p-1 rounded ${coverage.odds ? 'text-success' : 'text-slate-600'}`}>
                        <i data-lucide="trending-up" className="w-3.5 h-3.5"></i>
                    </div>
                    <div title="Classifica" className={`flex flex-col items-center p-1 rounded ${coverage.standings ? 'text-success' : 'text-slate-600'}`}>
                        <i data-lucide="list-ordered" className="w-3.5 h-3.5"></i>
                    </div>
                    <div title="Infortuni" className={`flex flex-col items-center p-1 rounded ${coverage.injuries ? 'text-success' : 'text-slate-600'}`}>
                        <i data-lucide="heart-pulse" className="w-3.5 h-3.5"></i>
                    </div>
                </div>

                {/* Background Decorative Logo */}
                <img src={league.logo} className="absolute -right-4 -bottom-4 w-24 h-24 opacity-[0.03] grayscale pointer-events-none" alt="" />
            </div>
        );
    }

    function App() {
        const [leagues, setLeagues] = useState([]);
        const [loading, setLoading] = useState(true);
        const [search, setSearch] = useState('');
        const [filterType, setFilterType] = useState('all');
        const [filterCountry, setFilterCountry] = useState('all');

        useEffect(() => {
            fetch('/api/leagues')
                .then(res => res.json())
                .then(data => {
                    setLeagues(data.response || []);
                    setLoading(false);
                })
                .catch(err => {
                    console.error('Errore nel recupero campionati:', err);
                    setLoading(false);
                });
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [leagues, loading, search, filterType, filterCountry]);

        const countries = [...new Set(leagues.map(l => l.country_name))].sort();

        const filteredLeagues = leagues.filter(l => {
            const matchesSearch = l.name.toLowerCase().includes(search.toLowerCase()) ||
                                l.country_name.toLowerCase().includes(search.toLowerCase());
            const matchesType = filterType === 'all' || l.type === filterType;
            const matchesCountry = filterCountry === 'all' || l.country_name === filterCountry;
            return matchesSearch && matchesType && matchesCountry;
        });

        return (
            <div className="max-w-7xl mx-auto">
                <header className="flex flex-col gap-6 mb-10">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                                Competizioni <span className="text-accent">Globali</span>
                            </h1>
                            <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                                <span className="w-2 h-2 bg-success rounded-full animate-pulse"></span>
                                Sync: API Football (Aggiornamento Orario)
                            </p>
                        </div>

                        <div className="relative w-full md:w-80">
                            <i data-lucide="search" className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Cerca lega o nazione..."
                                className="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors shadow-2xl shadow-black/20"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <select
                            className="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest text-slate-400 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer"
                            value={filterType}
                            onChange={(e) => setFilterType(e.target.value)}
                        >
                            <option value="all">Tutti i tipi</option>
                            <option value="League">Campionati</option>
                            <option value="Cup">Coppe</option>
                        </select>

                        <select
                            className="bg-white/5 border border-white/10 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-widest text-slate-400 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer max-w-[200px]"
                            value={filterCountry}
                            onChange={(e) => setFilterCountry(e.target.value)}
                        >
                            <option value="all">Tutte le nazioni</option>
                            {countries.map(c => <option key={c} value={c}>{c}</option>)}
                        </select>

                        <div className="ml-auto flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            <span className="bg-white/5 px-3 py-2 rounded-xl border border-white/5">
                                Trovate: <span className="text-accent">{filteredLeagues.length}</span>
                            </span>
                        </div>
                    </div>
                </header>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-40 gap-6">
                        <div className="relative">
                            <div className="w-16 h-16 border-4 border-accent/10 border-t-accent rounded-full animate-spin"></div>
                            <i data-lucide="trophy" className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-6 h-6 text-accent/50"></i>
                        </div>
                        <span className="text-xs font-black uppercase tracking-widest text-slate-500 animate-pulse">Analisi database competizioni...</span>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        {filteredLeagues.map(l => (
                            <LeagueCard key={l.id} league={l} />
                        ))}
                    </div>
                )}

                {!loading && filteredLeagues.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-40 gap-4 glass rounded-3xl border border-dashed border-white/10">
                        <i data-lucide="search-x" className="w-12 h-12 text-slate-700"></i>
                        <p className="text-slate-500 font-bold uppercase tracking-widest text-sm">Nessuna competizione corrispondente</p>
                        <button
                            onClick={() => { setSearch(''); setFilterType('all'); setFilterCountry('all'); }}
                            className="text-accent text-[10px] font-black uppercase tracking-widest hover:underline"
                        >
                            Resetta Filtri
                        </button>
                    </div>
                )}
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<App />);
</script>

<?php
require __DIR__ . '/layout/bottom.php';
?>
