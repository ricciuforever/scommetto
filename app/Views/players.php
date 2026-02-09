<?php
// app/Views/players.php
$pageTitle = 'Scommetto.AI - Database Giocatori';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function PlayerModal({ player, onClose }) {
        if (!player) return null;

        return (
            <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onClick={onClose}></div>
                <div className="relative bg-[#0f172a] border border-white/10 w-full max-w-2xl rounded-[2rem] overflow-hidden shadow-2xl">
                    <button onClick={onClose} className="absolute right-6 top-6 text-slate-500 hover:text-white z-10">
                        <i data-lucide="x" className="w-6 h-6"></i>
                    </button>

                    <div className="flex flex-col md:flex-row">
                        <div className="md:w-1/2 p-8 flex flex-col items-center justify-center bg-white/5 border-r border-white/5">
                            <div className="relative mb-6">
                                <div className="w-32 h-32 rounded-full overflow-hidden border-4 border-accent shadow-xl shadow-accent/20">
                                    <img src={player.photo} alt={player.name} className="w-full h-full object-cover" />
                                </div>
                                {player.injured && (
                                    <div className="absolute -bottom-2 -right-2 bg-danger text-white p-2 rounded-full border-4 border-[#0f172a]">
                                        <i data-lucide="shield-alert" className="w-4 h-4"></i>
                                    </div>
                                )}
                            </div>
                            <h2 className="text-2xl font-black text-white text-center uppercase italic">{player.name}</h2>
                            <p className="text-accent font-bold uppercase tracking-widest text-xs mt-2">{player.position || 'Player'}</p>
                            <div className="flex gap-4 mt-6">
                                <div className="text-center">
                                    <span className="block text-[10px] text-slate-500 uppercase font-black">Nazionalità</span>
                                    <span className="text-white font-bold">{player.nationality}</span>
                                </div>
                                <div className="text-center">
                                    <span className="block text-[10px] text-slate-500 uppercase font-black">Età</span>
                                    <span className="text-white font-bold">{player.age} anni</span>
                                </div>
                            </div>
                        </div>

                        <div className="md:w-1/2 p-8 space-y-6">
                            <h3 className="text-xs font-black text-slate-500 uppercase tracking-widest border-b border-white/5 pb-2">Dettagli Fisici</h3>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="block text-[9px] text-slate-500 uppercase font-black">Altezza</span>
                                    <span className="text-white font-bold">{player.height || 'N/A'}</span>
                                </div>
                                <div>
                                    <span className="block text-[9px] text-slate-500 uppercase font-black">Peso</span>
                                    <span className="text-white font-bold">{player.weight || 'N/A'}</span>
                                </div>
                                <div>
                                    <span className="block text-[9px] text-slate-500 uppercase font-black">Data Nascita</span>
                                    <span className="text-white font-bold">{player.birth?.date || player.birth_date || 'N/A'}</span>
                                </div>
                                <div>
                                    <span className="block text-[9px] text-slate-500 uppercase font-black">Luogo</span>
                                    <span className="text-white font-bold">{player.birth?.place || player.birth_place || 'N/A'}</span>
                                </div>
                            </div>

                            <h3 className="text-xs font-black text-slate-500 uppercase tracking-widest border-b border-white/5 pb-2 pt-4">Status</h3>
                            <div className={`p-4 rounded-2xl border ${player.injured ? 'bg-danger/10 border-danger/20' : 'bg-success/10 border-success/20'}`}>
                                <div className="flex items-center gap-3">
                                    <i data-lucide={player.injured ? "alert-circle" : "check-circle"} className={`w-5 h-5 ${player.injured ? 'text-danger' : 'text-success'}`}></i>
                                    <span className={`text-sm font-bold uppercase ${player.injured ? 'text-danger' : 'text-success'}`}>
                                        {player.injured ? 'Infortunato / Indisponibile' : 'Disponibile'}
                                    </span>
                                </div>
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