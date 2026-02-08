<?php
// app/Views/seasons.php
$pageTitle = 'Scommetto.AI - Archivio Stagioni';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function SeasonCard({ year }) {
        return (
            <div className="glass border border-white/10 rounded-2xl p-6 flex flex-col items-center justify-center gap-2 hover:scale-105 transition-transform cursor-pointer group">
                <i data-lucide="history" className="w-8 h-8 text-slate-700 group-hover:text-accent transition-colors"></i>
                <span className="text-2xl font-black text-white group-hover:text-accent transition-colors">
                    {year}
                </span>
                <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest">
                    Stagione Calcistica
                </span>
            </div>
        );
    }

    function App() {
        const [seasons, setSeasons] = useState([]);
        const [loading, setLoading] = useState(true);
        const [search, setSearch] = useState('');

        useEffect(() => {
            fetch('/api/seasons')
                .then(res => res.json())
                .then(data => {
                    // Assicuriamoci che siano ordinati (l'API lo fa già ma per sicurezza)
                    const sorted = (data.response || []).sort((a, b) => b - a);
                    setSeasons(sorted);
                    setLoading(false);
                })
                .catch(err => {
                    console.error('Errore nel recupero stagioni:', err);
                    setLoading(false);
                });
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [seasons, loading, search]);

        const filteredSeasons = seasons.filter(s =>
            s.toString().includes(search)
        );

        return (
            <div className="max-w-7xl mx-auto">
                <header className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <div>
                        <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                            Archivio <span className="text-accent">Stagioni</span>
                        </h1>
                        <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                            Filtro anni disponibili per competizioni
                        </p>
                    </div>

                    <div className="relative w-full md:w-64">
                        <i data-lucide="search" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                        <input
                            type="text"
                            placeholder="Cerca anno..."
                            className="w-full bg-white/5 border border-white/10 rounded-xl py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                </header>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-20 gap-4">
                        <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-xs font-black uppercase tracking-widest text-slate-500">Recupero anni...</span>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        {filteredSeasons.map(s => (
                            <SeasonCard key={s} year={s} />
                        ))}
                    </div>
                )}

                {!loading && filteredSeasons.length === 0 && (
                    <div className="text-center py-20">
                        <p className="text-slate-500 italic">Nessuna stagione trovata per "{search}"</p>
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
