<?php
// app/Views/players.php
$pageTitle = 'Scommetto.AI - Database Giocatori';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function PlayerModal({ player, onClose }) {
        const [stats, setStats] = useState([]);
        const [loadingStats, setLoadingStats] = useState(false);
        const [activeTab, setActiveTab] = useState('overview'); // overview, stats

        useEffect(() => {
            if (player) {
                setLoadingStats(true);
                // Default to current year, or let the backend decide
                fetch(`/api/player-season-stats?player=${player.id}`)
                    .then(res => res.json())
                    .then(data => {
                        setStats(data.response || []);
                        setLoadingStats(false);
                    })
                    .catch(err => {
                        console.error(err);
                        setLoadingStats(false);
                    });
            }
        }, [player]);

        if (!player) return null;

        // Helper to sum stats if multiple teams/leagues (optional, or just show the first/primary)
        // For simplicity, we show the first entry or map them
        const renderStats = () => {
            if (loadingStats) return <div className="text-center py-8"><div className="w-8 h-8 border-2 border-accent border-t-transparent rounded-full animate-spin mx-auto"></div></div>;
            if (!stats.length) return <div className="text-center py-8 text-slate-500 italic">Nessuna statistica disponibile per la stagione corrente.</div>;

            return (
                <div className="space-y-6 mt-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                    {stats.map((stat, idx) => (
                        <div key={idx} className="bg-white/5 rounded-xl p-4 border border-white/5">
                            <div className="flex items-center gap-3 mb-4 border-b border-white/5 pb-2">
                                <img src={stat.team?.logo || stat.team_logo} className="w-8 h-8 object-contain" alt="Team" />
                                <div>
                                    <div className="text-sm font-bold text-white">{stat.team?.name || 'Team'}</div>
                                    <div className="text-[10px] text-slate-400">{stat.league?.name || 'League'} ({stat.season})</div>
                                </div>
                                {stat.games?.rating && (
                                    <div className={`ml-auto px-2 py-1 rounded text-xs font-black ${parseFloat(stat.games.rating) >= 7 ? 'bg-success text-slate-900' : 'bg-slate-700 text-white'}`}>
                                        {parseFloat(stat.games.rating).toFixed(1)}
                                    </div>
                                )}
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div className="text-center bg-black/20 rounded-lg p-2">
                                    <div className="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Presenze</div>
                                    <div className="text-lg font-black text-white">{stat.games?.appearences || 0}</div>
                                    <div className="text-[9px] text-slate-600">{stat.games?.minutes || 0}' min</div>
                                </div>
                                <div className="text-center bg-black/20 rounded-lg p-2">
                                    <div className="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Gol</div>
                                    <div className="text-lg font-black text-accent">{stat.goals?.total || 0}</div>
                                    <div className="text-[9px] text-slate-600">Assists: {stat.goals?.assists || 0}</div>
                                </div>
                                <div className="text-center bg-black/20 rounded-lg p-2">
                                    <div className="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Passaggi</div>
                                    <div className="text-lg font-black text-white">{stat.passes?.total || 0}</div>
                                    <div className="text-[9px] text-slate-600">Acc: {stat.passes?.accuracy || 0}%</div>
                                </div>
                                <div className="text-center bg-black/20 rounded-lg p-2">
                                    <div className="text-[10px] text-slate-500 uppercase tracking-widest mb-1">Tiri</div>
                                    <div className="text-lg font-black text-white">{stat.shots?.total || 0}</div>
                                    <div className="text-[9px] text-slate-600">In porta: {stat.shots?.on || 0}</div>
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-2 mt-2">
                                <div className="bg-black/20 p-2 rounded text-center">
                                    <span className="block text-[9px] text-slate-500 uppercase">Dribbling</span>
                                    <span className="text-xs font-bold text-white">{stat.dribbles?.success || 0}</span>
                                </div>
                                <div className="bg-black/20 p-2 rounded text-center">
                                    <span className="block text-[9px] text-slate-500 uppercase">Tackle</span>
                                    <span className="text-xs font-bold text-white">{stat.tackles?.total || 0}</span>
                                </div>
                                <div className="bg-black/20 p-2 rounded text-center">
                                    <span className="block text-[9px] text-slate-500 uppercase">Duelli Vinti</span>
                                    <span className="text-xs font-bold text-white">{stat.duels?.won || 0}</span>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            );
        };

        return (
            <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onClick={onClose}></div>
                <div className="relative bg-[#0f172a] border border-white/10 w-full max-w-4xl rounded-[2rem] overflow-hidden shadow-2xl max-h-[90vh] flex flex-col">
                    <button onClick={onClose} className="absolute right-6 top-6 text-slate-500 hover:text-white z-10 bg-black/20 p-2 rounded-full backdrop-blur">
                        <i data-lucide="x" className="w-5 h-5"></i>
                    </button>

                    <div className="flex flex-col md:flex-row h-full overflow-hidden">
                        {/* Left Column: Profile - Fixed on Desktop, Scrollable on Mobile if needed */}
                        <div className="md:w-1/3 p-8 flex flex-col items-center bg-white/5 border-r border-white/5 relative overflow-y-auto">
                            <div className="relative mb-6 shrink-0">
                                <div className="w-40 h-40 rounded-full overflow-hidden border-4 border-accent shadow-2xl shadow-accent/20">
                                    <img src={player.photo} alt={player.name} className="w-full h-full object-cover" />
                                </div>
                                {player.injured && (
                                    <div className="absolute -bottom-2 -right-2 bg-danger text-white p-2 rounded-full border-4 border-[#0f172a]">
                                        <i data-lucide="shield-alert" className="w-5 h-5"></i>
                                    </div>
                                )}
                            </div>

                            <h2 className="text-2xl font-black text-white text-center uppercase italic">{player.name}</h2>
                            <p className="text-accent font-bold uppercase tracking-widest text-xs mt-2">{player.position || 'Player'}</p>

                            <div className="w-full mt-8 space-y-4">
                                <div className="flex justify-between items-center border-b border-white/5 pb-2">
                                    <span className="text-[10px] text-slate-500 uppercase font-bold">Nazionalità</span>
                                    <span className="text-sm text-white font-bold">{player.nationality}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-white/5 pb-2">
                                    <span className="text-[10px] text-slate-500 uppercase font-bold">Età</span>
                                    <span className="text-sm text-white font-bold">{player.age} anni</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-white/5 pb-2">
                                    <span className="text-[10px] text-slate-500 uppercase font-bold">Altezza</span>
                                    <span className="text-sm text-white font-bold">{player.height || 'N/A'}</span>
                                </div>
                                <div className="flex justify-between items-center border-b border-white/5 pb-2">
                                    <span className="text-[10px] text-slate-500 uppercase font-bold">Peso</span>
                                    <span className="text-sm text-white font-bold">{player.weight || 'N/A'}</span>
                                </div>
                                <div className={`p-3 rounded-xl mt-4 border ${player.injured ? 'bg-danger/10 border-danger/20' : 'bg-success/10 border-success/20'}`}>
                                    <div className="flex items-center justify-center gap-2">
                                        <i data-lucide={player.injured ? "alert-circle" : "check-circle"} className={`w-4 h-4 ${player.injured ? 'text-danger' : 'text-success'}`}></i>
                                        <span className={`text-xs font-bold uppercase ${player.injured ? 'text-danger' : 'text-success'}`}>
                                            {player.injured ? 'Indisponibile' : 'Disponibile'}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* Right Column: Stats & Details */}
                        <div className="md:w-2/3 flex flex-col h-full">
                            <div className="flex items-center gap-6 p-6 border-b border-white/5 sticky top-0 bg-[#0f172a] z-10">
                                <button
                                    onClick={() => setActiveTab('overview')}
                                    className={`pb-2 text-xs font-black uppercase tracking-widest transition-colors ${activeTab === 'overview' ? 'text-accent border-b-2 border-accent' : 'text-slate-500 hover:text-slate-300'}`}
                                >
                                    Riepilogo
                                </button>
                                <button
                                    onClick={() => setActiveTab('stats')}
                                    className={`pb-2 text-xs font-black uppercase tracking-widest transition-colors ${activeTab === 'stats' ? 'text-accent border-b-2 border-accent' : 'text-slate-500 hover:text-slate-300'}`}
                                >
                                    Statistiche Stagione
                                </button>
                            </div>

                            <div className="p-6 overflow-y-auto custom-scrollbar flex-1">
                                {activeTab === 'overview' ? (
                                    <div className="space-y-6">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="bg-white/5 p-4 rounded-xl border border-white/5">
                                                <h4 className="text-[10px] text-slate-500 uppercase font-black mb-3">Info Nascita</h4>
                                                <div className="space-y-2">
                                                    <div>
                                                        <span className="block text-[9px] text-slate-500">Data</span>
                                                        <span className="text-sm text-white font-bold">{player.birth?.date || player.birth_date || 'N/A'}</span>
                                                    </div>
                                                    <div>
                                                        <span className="block text-[9px] text-slate-500">Luogo</span>
                                                        <span className="text-sm text-white font-bold">{player.birth?.place || player.birth_place || 'N/A'}</span>
                                                    </div>
                                                    <div>
                                                        <span className="block text-[9px] text-slate-500">Paese</span>
                                                        <span className="text-sm text-white font-bold">{player.birth?.country || player.birth_country || 'N/A'}</span>
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Could add transfer history here if available, or just keeping it clean */}
                                            <div className="bg-gradient-to-br from-accent/10 to-transparent p-4 rounded-xl border border-accent/20">
                                                <h4 className="text-[10px] text-accent uppercase font-black mb-3">Scommetto Insight</h4>
                                                <p className="text-xs text-slate-300 leading-relaxed italic">
                                                    Analisi e previsioni basate sulle prestazioni recenti saranno disponibili a breve.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ) : (
                                    renderStats()
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    function PlayerCard({ player, onClick }) {
        return (
            <div
                className="glass border border-white/10 rounded-3xl p-4 flex items-center gap-4 hover:bg-white/5 transition-all cursor-pointer group"
                onClick={() => onClick(player)}
            >
                <div className="w-16 h-16 rounded-2xl overflow-hidden border border-white/10 group-hover:border-accent transition-colors">
                    <img src={player.photo} alt={player.name} className="w-full h-full object-cover" />
                </div>
                <div className="flex-1 min-w-0">
                    <h3 className="text-sm font-black text-white uppercase truncate">{player.name}</h3>
                    <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{player.nationality}</p>
                    <span className="text-[10px] font-black text-accent">{player.position || 'Player'}</span>
                </div>
                <i data-lucide="chevron-right" className="w-4 h-4 text-slate-700 group-hover:text-white transition-colors"></i>
            </div>
        );
    }

    function App() {
        const [players, setPlayers] = useState([]);
        const [loading, setLoading] = useState(false);
        const [search, setSearch] = useState('');
        const [page, setPage] = useState(1);
        const [selectedPlayer, setSelectedPlayer] = useState(null);

        const fetchPlayers = (p = 1, s = '') => {
            setLoading(true);
            const url = s ? `/api/players?search=${s}` : `/api/players?page=${p}`;
            fetch(url)
                .then(res => res.json())
                .then(data => {
                    const results = data.response.map(item => item.player || item);
                    setPlayers(results);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        };

        useEffect(() => {
            fetchPlayers(page, search);
        }, [page]);

        useEffect(() => {
            const timer = setTimeout(() => {
                if (search.length >= 3) fetchPlayers(1, search);
                else if (search.length === 0) fetchPlayers(1);
            }, 500);
            return () => clearTimeout(timer);
        }, [search]);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [players, loading, selectedPlayer]);

        return (
            <div className="max-w-7xl mx-auto">
                <header className="mb-12">
                    <h1 className="text-4xl font-black italic uppercase text-white mb-4">
                        Database <span className="text-accent">Giocatori</span>
                    </h1>
                    <div className="flex flex-col md:flex-row gap-6 items-center border-b border-white/5 pb-8">
                        <div className="relative flex-1 w-full">
                            <i data-lucide="search" className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Cerca giocatore (es. Neymar, Messi...)"
                                className="w-full bg-white/5 border border-white/10 rounded-2xl py-4 pl-12 pr-4 text-sm focus:outline-none focus:border-accent transition-all"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                        <div className="flex items-center gap-4 bg-white/5 p-1 rounded-2xl border border-white/5">
                            <button
                                onClick={() => setPage(p => Math.max(1, p - 1))}
                                disabled={page === 1}
                                className="p-3 hover:bg-white/5 rounded-xl disabled:opacity-30 transition-colors"
                            >
                                <i data-lucide="chevron-left" className="w-5 h-5"></i>
                            </button>
                            <span className="text-xs font-black text-white px-2">PAGINA {page}</span>
                            <button
                                onClick={() => setPage(p => p + 1)}
                                className="p-3 hover:bg-white/5 rounded-xl transition-colors"
                            >
                                <i data-lucide="chevron-right" className="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                </header>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-32 gap-6">
                        <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-xs font-black uppercase tracking-[0.3em] text-slate-500">Querying Global Database...</span>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {players.map(p => (
                            <PlayerCard key={p.id} player={p} onClick={setSelectedPlayer} />
                        ))}
                    </div>
                )}

                {!loading && players.length === 0 && (
                    <div className="text-center py-32 glass border border-dashed border-white/10 rounded-[3rem]">
                        <i data-lucide="users" className="w-16 h-16 text-slate-800 mx-auto mb-6"></i>
                        <p className="text-slate-500 font-bold uppercase tracking-widest">Nessun giocatore trovato</p>
                    </div>
                )}

                <PlayerModal
                    player={selectedPlayer}
                    onClose={() => setSelectedPlayer(null)}
                />
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<App />);
</script>

<?php
require __DIR__ . '/layout/bottom.php';
?>