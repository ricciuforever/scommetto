<?php
// app/Views/fixture_stats.php
$pageTitle = 'Scommetto.AI - Statistiche Match';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function StatBar({ label, home, away }) {
        // Parse percentages if they are strings
        const hVal = typeof home === 'string' && home.includes('%') ? parseInt(home) : parseFloat(home || 0);
        const aVal = typeof away === 'string' && away.includes('%') ? parseInt(away) : parseFloat(away || 0);

        const total = hVal + aVal;
        const hPerc = total > 0 ? (hVal / total) * 100 : 50;
        const aPerc = total > 0 ? (aVal / total) * 100 : 50;

        return (
            <div className="flex flex-col gap-2 mb-6 last:mb-0">
                <div className="flex justify-between items-center px-1">
                    <span className="text-xs font-black text-white">{home || 0}</span>
                    <span className="text-[10px] font-black uppercase tracking-widest text-slate-500">{label}</span>
                    <span className="text-xs font-black text-white">{away || 0}</span>
                </div>
                <div className="h-2 bg-white/5 rounded-full overflow-hidden flex border border-white/5">
                    <div
                        className="h-full bg-accent transition-all duration-1000"
                        style={{ width: `${hPerc}%` }}
                    ></div>
                    <div
                        className="h-full bg-slate-700 transition-all duration-1000"
                        style={{ width: `${aPerc}%` }}
                    ></div>
                </div>
            </div>
        );
    }

    function App() {
        const [stats, setStats] = useState([]);
        const [loading, setLoading] = useState(false);
        const [fixtureId, setFixtureId] = useState('');
        const [searchId, setSearchId] = useState('');

        const loadStats = (id) => {
            if (!id) return;
            setLoading(true);
            fetch(`/api/fixture-stats?fixture=${id}`)
                .then(res => res.json())
                .then(data => {
                    setStats(data.response || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        };

        useEffect(() => {
            // Se c'è un ID in URL, usalo
            const params = new URLSearchParams(window.location.search);
            const id = params.get('fixture');
            if (id) {
                setFixtureId(id);
                setSearchId(id);
                loadStats(id);
            }
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [stats, loading]);

        // Mappa le statistiche per un confronto facile
        const getStatValue = (teamIdx, type) => {
            if (!stats[teamIdx]) return null;
            const stat = stats[teamIdx].stats_json.find(s => s.type === type);
            return stat ? stat.value : null;
        };

        const availableTypes = stats.length > 0
            ? stats[0].stats_json.map(s => s.type)
            : [];

        return (
            <div className="max-w-3xl mx-auto">
                <header className="mb-10 text-center">
                    <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                        Match <span className="text-accent">Statistics</span>
                    </h1>
                    <div className="flex justify-center mt-6">
                        <div className="relative w-full max-w-sm">
                            <i data-lucide="hash" className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Inserisci Fixture ID..."
                                className="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors"
                                value={searchId}
                                onChange={(e) => setSearchId(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && loadStats(searchId)}
                            />
                            <button
                                onClick={() => loadStats(searchId)}
                                className="absolute right-2 top-1/2 -translate-y-1/2 bg-accent text-white px-4 py-1.5 rounded-xl text-[10px] font-black uppercase hover:scale-105 transition-all shadow-lg shadow-accent/20"
                            >
                                Carica
                            </button>
                        </div>
                    </div>
                </header>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-20 gap-4">
                        <div className="w-10 h-10 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-[10px] font-black uppercase text-slate-500 tracking-widest">Analisi dati in corso...</span>
                    </div>
                ) : (
                    stats.length === 2 ? (
                        <div className="glass border border-white/10 rounded-3xl p-8 shadow-2xl">
                            <div className="flex justify-between items-center mb-10 pb-6 border-b border-white/5">
                                <div className="flex flex-col items-center gap-3 flex-1">
                                    <div className="w-16 h-16 bg-white/5 rounded-2xl flex items-center justify-center p-3 border border-white/5">
                                        <img src={stats[0].team_logo} className="max-w-full max-h-full object-contain" alt="" />
                                    </div>
                                    <span className="text-xs font-black text-white text-center uppercase tracking-tighter">{stats[0].team_name}</span>
                                </div>
                                <div className="px-6">
                                    <span className="text-2xl font-black italic text-slate-700">VS</span>
                                </div>
                                <div className="flex flex-col items-center gap-3 flex-1">
                                    <div className="w-16 h-16 bg-white/5 rounded-2xl flex items-center justify-center p-3 border border-white/5">
                                        <img src={stats[1].team_logo} className="max-w-full max-h-full object-contain" alt="" />
                                    </div>
                                    <span className="text-xs font-black text-white text-center uppercase tracking-tighter">{stats[1].team_name}</span>
                                </div>
                            </div>

                            <div className="space-y-2">
                                {availableTypes.map(type => (
                                    <StatBar
                                        key={type}
                                        label={type}
                                        home={getStatValue(0, type)}
                                        away={getStatValue(1, type)}
                                    />
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="text-center py-24 glass border border-dashed border-white/10 rounded-3xl">
                            <i data-lucide="bar-chart-2" className="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">
                                {searchId ? 'Nessuna statistica disponibile per questo ID' : 'Inserisci un Fixture ID per vedere le statistiche'}
                            </p>
                        </div>
                    )
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