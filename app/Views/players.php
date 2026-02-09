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
        const [career, setCareer] = useState([]);
        const [loadingCareer, setLoadingCareer] = useState(false);
        const [transfers, setTransfers] = useState([]);
        const [loadingTransfers, setLoadingTransfers] = useState(false);
        const [trophies, setTrophies] = useState([]);
        const [loadingTrophies, setLoadingTrophies] = useState(false);
        const [sidelined, setSidelined] = useState([]);
        const [loadingSidelined, setLoadingSidelined] = useState(false);
        const [activeTab, setActiveTab] = useState('overview'); // overview, stats, career, transfers, trophies, sidelined

        useEffect(() => {
            if (player) {
                // Fetch Stats
                setLoadingStats(true);
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

                // Fetch Career
                setLoadingCareer(true);
                fetch(`/api/player-teams?player=${player.id}`)
                    .then(res => res.json())
                    .then(data => {
                        setCareer(data.response || []);
                        setLoadingCareer(false);
                    })
                    .catch(err => {
                        console.error(err);
                        setLoadingCareer(false);
                    });

                // Fetch Transfers
                setLoadingTransfers(true);
                fetch(`/api/player-transfers?player=${player.id}`)
                    .then(res => res.json())
                    .then(data => {
                        setTransfers(data.response || []);
                        setLoadingTransfers(false);
                    })
                    .catch(err => {
                        console.error(err);
                        setLoadingTransfers(false);
                    });

                // Fetch Trophies
                setLoadingTrophies(true);
                fetch(`/api/player-trophies?player=${player.id}`)
                    .then(res => res.json())
                    .then(data => {
                        setTrophies(data.response || []);
                        setLoadingTrophies(false);
                    })
                    .catch(err => {
                        console.error(err);
                        setLoadingTrophies(false);
                    });

                // Fetch Sidelined
                setLoadingSidelined(true);
                fetch(`/api/player-sidelined?player=${player.id}`)
                    .then(res => res.json())
                    .then(data => {
                        setSidelined(data.response || []);
                        setLoadingSidelined(false);
                    })
                    .catch(err => {
                        console.error(err);
                        setLoadingSidelined(false);
                    });
            }
        }, [player]);

        if (!player) return null;

        // Render Career
        const renderCareer = () => {
            if (loadingCareer) return <div className="text-center py-8"><div className="w-8 h-8 border-2 border-accent border-t-transparent rounded-full animate-spin mx-auto"></div></div>;
            if (!career.length) return <div className="text-center py-8 text-slate-500 italic">Nessuna informazione sulla carriera disponibile.</div>;

            return (
                <div className="space-y-4 mt-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                    {career.map((item, idx) => (
                        <div key={idx} className="bg-white/5 rounded-xl p-4 border border-white/5 flex items-center gap-4 hover:bg-white/10 transition-colors">
                            <div className="w-12 h-12 bg-white/10 rounded-lg p-2 flex items-center justify-center">
                                <img src={item.team_logo} className="w-full h-full object-contain" alt={item.team_name} />
                            </div>
                            <div className="flex-1">
                                <h4 className="text-sm font-bold text-white">{item.team_name}</h4>
                                <div className="flex flex-wrap gap-1 mt-1">
                                    {(item.seasons || []).map(year => (
                                        <span key={year} className="text-[10px] bg-accent/10 text-accent px-1.5 py-0.5 rounded font-bold border border-accent/20">
                                            {year}
                                        </span>
                                    ))}
                                </div>
                            </div>
                            {item.team_country && (
                                <div className="text-[10px] bg-slate-800 text-slate-400 px-2 py-1 rounded uppercase tracking-wider font-bold">
                                    {item.team_country}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            );
        };

        // Helper to sum stats if multiple teams/leagues
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

        const renderTransfers = () => {
            if (loadingTransfers) return (
                <div className="flex justify-center items-center py-20">
                    <div className="w-8 h-8 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                </div>
            );

            if (!transfers || transfers.length === 0) return (
                <div className="text-center py-20 text-slate-500 font-bold uppercase tracking-widest text-xs">
                    Nessun trasferimento registrato
                </div>
            );

            return (
                <div className="relative border-l-2 border-white/10 ml-4 pl-8 py-4 space-y-8">
                    {transfers.map((item, index) => {
                        const date = new Date(item.date).toLocaleDateString('it-IT', { year: 'numeric', month: 'long', day: 'numeric' });
                        return (
                            <div key={index} className="relative group">
                                <div className="absolute -left-[41px] top-0 w-5 h-5 bg-slate-800 border-2 border-white/10 rounded-full group-hover:border-accent group-hover:bg-accent transition-colors"></div>
                                <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-1 block">{date}</span>
                                <div className="glass p-4 rounded-xl border border-white/5 flex items-center justify-between gap-4">
                                    <div className="flex items-center gap-3 w-1/2">
                                        <div className="relative shrink-0">
                                            <div className="w-10 h-10 bg-white/5 rounded-lg border border-white/10 p-1 flex items-center justify-center">
                                                <img src={item.teams.out.logo} alt={item.teams.out.name} className="w-full h-full object-contain opacity-50 grayscale group-hover:grayscale-0 group-hover:opacity-100 transition-all" />
                                            </div>
                                            <div className="absolute -right-2 top-1/2 -translate-y-1/2 bg-danger text-[8px] font-black text-white px-1 rounded">OUT</div>
                                        </div>
                                        <span className="text-xs font-bold text-slate-400 truncate">{item.teams.out.name}</span>
                                    </div>

                                    <div className="flex flex-col items-center shrink-0">
                                        <i data-lucide="arrow-right" className="w-4 h-4 text-slate-600"></i>
                                        <span className="text-[9px] font-black text-accent uppercase">{item.type || 'Transfer'}</span>
                                    </div>

                                    <div className="flex items-center gap-3 w-1/2 justify-end">
                                        <span className="text-xs font-bold text-white truncate text-right">{item.teams.in.name}</span>
                                        <div className="relative shrink-0">
                                            <div className="w-10 h-10 bg-white/5 rounded-lg border border-white/10 p-1 flex items-center justify-center">
                                                <img src={item.teams.in.logo} alt={item.teams.in.name} className="w-full h-full object-contain" />
                                            </div>
                                            <div className="absolute -left-2 top-1/2 -translate-y-1/2 bg-success text-[8px] font-black text-slate-900 px-1 rounded">IN</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            );
        };

        const renderTrophies = () => {
            if (loadingTrophies) return (
                <div className="flex justify-center items-center py-20">
                    <div className="w-8 h-8 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                </div>
            );

            if (!trophies || trophies.length === 0) return (
                <div className="text-center py-20 text-slate-500 font-bold uppercase tracking-widest text-xs">
                    Nessun trofeo registrato
                </div>
            );

            return (
                <div className="space-y-6">
                    {trophies.map((item, index) => (
                        <div key={index} className="glass p-4 rounded-xl border border-white/5 flex items-center justify-between gap-4">
                            <div className="flex items-center gap-4">
                                <div className="w-12 h-12 bg-white/5 rounded-full flex items-center justify-center border border-white/5">
                                    <i data-lucide="trophy" className="w-6 h-6 text-accent"></i>
                                </div>
                                <div className="flex flex-col">
                                    <span className="text-sm font-black text-white">{item.league}</span>
                                    <div className="flex items-center gap-2">
                                        <span className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{item.country}</span>
                                        <span className="text-slate-700">•</span>
                                        <span className="text-[10px] font-bold text-accent uppercase tracking-widest">{item.season}</span>
                                    </div>
                                </div>
                            </div>
                            <div className="bg-accent/10 px-3 py-1 rounded-lg border border-accent/20">
                                <span className="text-xs font-black text-accent uppercase tracking-widest">{item.place}</span>
                            </div>
                        </div>
                    ))}
                </div>
            );
        };

        const renderSidelined = () => {
            if (loadingSidelined) return (
                <div className="flex justify-center items-center py-20">
                    <div className="w-8 h-8 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                </div>
            );

            if (!sidelined || sidelined.length === 0) return (
                <div className="text-center py-20 text-slate-500 font-bold uppercase tracking-widest text-xs">
                    Nessun infortunio registrato
                </div>
            );

            return (
                <div className="space-y-4">
                    {sidelined.map((item, index) => {
                        const start = new Date(item.start_date).toLocaleDateString();
                        const end = new Date(item.end_date).toLocaleDateString();
                        return (
                            <div key={index} className="glass p-4 rounded-xl border border-white/5 flex items-center justify-between gap-4">
                                <div className="flex items-center gap-4">
                                    <div className="w-10 h-10 bg-danger/10 rounded-full flex items-center justify-center border border-danger/20">
                                        <i data-lucide="cross" className="w-5 h-5 text-danger"></i>
                                    </div>
                                    <div>
                                        <span className="text-sm font-black text-white block">{item.type}</span>
                                        <span className="text-[10px] text-slate-500 uppercase font-bold">{start} - {end}</span>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>
            );
        };

        return (
            <div className="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div className="absolute inset-0 bg-slate-950/80 backdrop-blur-sm" onClick={onClose}></div>
                <div className="relative bg-[#0f172a] border border-white/10 w-full max-w-5xl rounded-[2rem] overflow-hidden shadow-2xl max-h-[90vh] flex flex-col md:flex-row">
                    <button onClick={onClose} className="absolute right-6 top-6 text-slate-500 hover:text-white z-10 bg-black/20 p-2 rounded-full backdrop-blur md:hidden">
                        <i data-lucide="x" className="w-5 h-5"></i>
                    </button>

                    {/* Left Sidebar */}
                    <div className="w-full md:w-80 bg-slate-900/50 p-6 flex flex-col gap-6 border-b md:border-b-0 md:border-r border-white/5 relative shrink-0 overflow-y-auto no-scrollbar">
                        <div className="flex flex-col items-center text-center">
                            <div className="w-24 h-24 rounded-2xl overflow-hidden border-2 border-white/10 shadow-lg mb-4">
                                <img src={player.photo} alt={player.name} className="w-full h-full object-cover" />
                            </div>
                            <h2 className="text-xl font-black text-white uppercase leading-tight mb-1">{player.name}</h2>
                            <div className="flex items-center gap-2 mb-4">
                                <span className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{player.nationality}</span>
                                <span className="w-1 h-1 rounded-full bg-slate-600"></span>
                                <span className="text-[10px] font-black text-accent uppercase tracking-widest">{player.position}</span>
                            </div>

                            <div className="grid grid-cols-3 gap-2 w-full mb-6">
                                <div className="bg-white/5 p-2 rounded-lg border border-white/5">
                                    <span className="block text-[9px] text-slate-500 uppercase font-bold">Età</span>
                                    <span className="text-sm font-black text-white">{player.age || 'N/A'}</span>
                                </div>
                                <div className="bg-white/5 p-2 rounded-lg border border-white/5">
                                    <span className="block text-[9px] text-slate-500 uppercase font-bold">Alt.</span>
                                    <span className="text-sm font-black text-white">{player.height || 'N/A'}</span>
                                </div>
                                <div className="bg-white/5 p-2 rounded-lg border border-white/5">
                                    <span className="block text-[9px] text-slate-500 uppercase font-bold">Peso</span>
                                    <span className="text-sm font-black text-white">{player.weight || 'N/A'}</span>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white/5 p-4 rounded-xl border border-white/5">
                            <h4 className="text-[10px] text-slate-500 uppercase font-black mb-3 border-b border-white/5 pb-2">Info Nascita</h4>
                            <div className="space-y-3">
                                <div>
                                    <span className="block text-[9px] text-slate-500 uppercase tracking-wider mb-0.5">Data</span>
                                    <span className="text-sm text-white font-bold">{player.birth?.date || player.birth_date || 'N/A'}</span>
                                </div>
                                <div>
                                    <span className="block text-[9px] text-slate-500 uppercase tracking-wider mb-0.5">Luogo</span>
                                    <span className="text-sm text-white font-bold">{player.birth?.place || player.birth_place || 'N/A'}</span>
                                </div>
                                <div>
                                    <span className="block text-[9px] text-slate-500 uppercase tracking-wider mb-0.5">Paese</span>
                                    <span className="text-sm text-white font-bold">{player.birth?.country || player.birth_country || 'N/A'}</span>
                                </div>
                            </div>
                        </div>

                        <div className="bg-gradient-to-br from-accent/10 to-transparent p-4 rounded-xl border border-accent/20">
                            <div className="flex items-center gap-2 mb-2">
                                <i data-lucide="zap" className="w-3 h-3 text-accent"></i>
                                <h4 className="text-[10px] text-accent uppercase font-black">Scommetto Insight</h4>
                            </div>
                            <p className="text-xs text-slate-300 leading-relaxed italic opacity-80">
                                Analisi e previsioni basate sulle prestazioni recenti saranno disponibili a breve.
                            </p>
                        </div>
                    </div>

                    {/* Right Content */}
                    <div className="flex-1 p-6 md:p-8 overflow-y-auto no-scrollbar relative flex flex-col">
                        <button onClick={onClose} className="absolute right-8 top-8 text-slate-500 hover:text-white hidden md:flex bg-white/5 hover:bg-white/10 p-2 rounded-full transition-colors">
                            <i data-lucide="x" className="w-5 h-5"></i>
                        </button>

                        <div className="flex items-center gap-2 mb-8 overflow-x-auto pb-2 no-scrollbar border-b border-white/5">
                            {['stats', 'career', 'transfers', 'trophies', 'sidelined'].map(tab => (
                                <button
                                    key={tab}
                                    onClick={() => setActiveTab(tab)}
                                    className={`px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap mb-2 ${activeTab === tab
                                            ? 'bg-accent text-slate-900 shadow-lg shadow-accent/20'
                                            : 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-white'
                                        }`}
                                >
                                    {tab === 'stats' && 'Statistiche'}
                                    {tab === 'career' && 'Carriera'}
                                    {tab === 'transfers' && 'Trasferimenti'}
                                    {tab === 'trophies' && 'Trofei'}
                                    {tab === 'sidelined' && 'Infortuni'}
                                </button>
                            ))}
                        </div>

                        <div className="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                            {(activeTab === 'overview' || activeTab === 'stats') && renderStats()}
                            {activeTab === 'career' && renderCareer()}
                            {activeTab === 'transfers' && renderTransfers()}
                            {activeTab === 'trophies' && renderTrophies()}
                            {activeTab === 'sidelined' && renderSidelined()}
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