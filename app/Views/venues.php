<?php
// app/Views/venues.php
$pageTitle = 'Scommetto.AI - Database Stadi';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function VenueCard({ venue }) {
        return (
            <div className="glass border border-white/10 rounded-2xl p-5 flex flex-col gap-4 hover:scale-[1.02] transition-all cursor-pointer group relative overflow-hidden">
                <div className="z-10 flex justify-between items-start">
                    <div className="w-full h-40 bg-white/5 rounded-xl flex items-center justify-center border border-white/5 overflow-hidden shadow-inner relative">
                        {venue.image ? (
                            <img src={venue.image} alt={venue.name} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" />
                        ) : (
                            <i data-lucide="building-2" className="w-12 h-12 text-slate-700"></i>
                        )}
                        <div className="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></div>
                        <div className="absolute bottom-3 left-3 right-3">
                            <h3 className="font-bold text-lg text-white truncate drop-shadow-lg">{venue.name}</h3>
                            <p className="text-[10px] font-black uppercase tracking-widest text-accent drop-shadow-lg">{venue.city}, {venue.country}</p>
                        </div>
                    </div>
                </div>

                <div className="z-10 grid grid-cols-2 gap-3">
                    <div className="bg-white/5 p-3 rounded-xl border border-white/5">
                        <span className="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Capacità</span>
                        <span className="text-sm font-bold text-white italic">
                            {venue.capacity ? venue.capacity.toLocaleString() : 'N/A'}
                        </span>
                    </div>
                    <div className="bg-white/5 p-3 rounded-xl border border-white/5">
                        <span className="text-[9px] font-black text-slate-500 uppercase tracking-widest block mb-1">Superficie</span>
                        <span className="text-sm font-bold text-white italic capitalize">
                            {venue.surface || 'N/A'}
                        </span>
                    </div>
                </div>

                <div className="z-10 pt-3 border-t border-white/5">
                    <p className="text-[10px] text-slate-400 font-medium flex items-center gap-2">
                        <i data-lucide="map-pin" className="w-3 h-3 text-accent"></i>
                        {venue.address || 'Indirizzo non disponibile'}
                    </p>
                </div>
            </div>
        );
    }

    function App() {
        const [venues, setVenues] = useState([]);
        const [countries, setCountries] = useState([]);
        const [loading, setLoading] = useState(false);
        const [initLoading, setInitLoading] = useState(true);

        const [selectedCountry, setSelectedCountry] = useState('Italy');
        const [search, setSearch] = useState('');

        // Caricamento Iniziale: Nazioni
        useEffect(() => {
            fetch('/api/teams/countries')
                .then(res => res.json())
                .then(data => {
                    setCountries(data.response || []);
                    setInitLoading(false);
                })
                .catch(err => {
                    console.error('Errore inizializzazione:', err);
                    setInitLoading(false);
                });
        }, []);

        // Caricamento Stadi al cambio nazione
        useEffect(() => {
            if (selectedCountry) {
                setLoading(true);
                fetch(`/api/venues?country=${selectedCountry}`)
                    .then(res => res.json())
                    .then(data => {
                        setVenues(Array.isArray(data.response) ? data.response : (data.response ? [data.response] : []));
                        setLoading(false);
                    })
                    .catch(err => {
                        console.error('Errore caricamento stadi:', err);
                        setLoading(false);
                    });
            }
        }, [selectedCountry]);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [venues, loading, initLoading]);

        const filteredVenues = venues.filter(v =>
            (v.name || '').toLowerCase().includes(search.toLowerCase()) ||
            (v.city || '').toLowerCase().includes(search.toLowerCase())
        );

        if (initLoading) {
            return (
                <div className="flex flex-col items-center justify-center py-40 gap-6">
                    <div className="w-16 h-16 border-4 border-accent/10 border-t-accent rounded-full animate-spin"></div>
                    <span className="text-xs font-black uppercase tracking-widest text-slate-500">Inizializzazione database stadi...</span>
                </div>
            );
        }

        return (
            <div className="max-w-7xl mx-auto">
                <header className="flex flex-col gap-6 mb-10">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                                Database <span className="text-accent">Stadi</span>
                            </h1>
                            <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                                <span className={`w-2 h-2 rounded-full ${loading ? 'bg-warning animate-pulse' : 'bg-success'}`}></span>
                                {loading ? 'Ricerca in corso...' : 'Infrastruttura Dati API Football'}
                            </p>
                        </div>

                        <div className="relative w-full md:w-80">
                            <i data-lucide="search" className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Cerca stadio o città..."
                                className="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors shadow-2xl shadow-black/20"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <div className="relative">
                            <select
                                className="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-300 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer pr-10"
                                value={selectedCountry}
                                onChange={(e) => setSelectedCountry(e.target.value)}
                            >
                                {countries.map(c => (
                                    <option key={c.name} value={c.name}>{c.name}</option>
                                ))}
                            </select>
                            <i data-lucide="chevron-down" className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                        </div>

                        <div className="ml-auto flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            <span className="bg-white/5 px-3 py-2 rounded-xl border border-white/5">
                                Risultati: <span className="text-accent">{filteredVenues.length}</span>
                            </span>
                        </div>
                    </div>
                </header>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-40 gap-6">
                        <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-xs font-black uppercase tracking-widest text-slate-500">Recupero stadi...</span>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        {filteredVenues.map(v => (
                            <VenueCard key={v.id} venue={v} />
                        ))}
                    </div>
                )}

                {!loading && filteredVenues.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-40 gap-4 glass rounded-3xl border border-dashed border-white/10">
                        <i data-lucide="search-x" className="w-12 h-12 text-slate-700"></i>
                        <p className="text-slate-500 font-bold uppercase tracking-widest text-sm">Nessuno stadio trovato</p>
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