<?php
// app/Views/fixture_player_stats.php
$pageTitle = 'Scommetto.AI - Statistiche Giocatori Match';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function PlayerRow({ stat }) {
        const s = stat.stats_json;
        return (
            <tr className="border-b border-white/5 hover:bg-white/5 transition-colors group">
                <td className="py-4 pl-4">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 rounded-full bg-white/5 overflow-hidden border border-white/10 group-hover:border-accent/50 transition-colors">
                            <img src={stat.player_photo} className="w-full h-full object-cover" alt="" />
                        </div>
                        <div className="flex flex-col">
                            <span className="text-xs font-black text-white">{stat.player_name}</span>
                            <span className="text-[9px] font-bold text-slate-500 uppercase">{s.games.position} - {s.games.minutes}'</span>
                        </div>
                    </div>
                </td>
                <td className="py-4 text-center">
                    <span className={`text-[10px] font-black px-2 py-1 rounded-md ${parseFloat(s.games.rating) >= 7.5 ? 'bg-success/20 text-success' : parseFloat(s.games.rating) >= 6.5 ? 'bg-accent/20 text-accent' : 'bg-slate-700/20 text-slate-500'}`}>
                        {s.games.rating || '-'}
                    </span>
                </td>
                <td className="py-4 text-center text-[10px] font-bold text-white">{s.goals.total || 0}</td>
                <td className="py-4 text-center text-[10px] font-bold text-white">{s.goals.assists || 0}</td>
                <td className="py-4 text-center text-[10px] font-bold text-white">{s.shots.total || 0}({s.shots.on || 0})</td>
                <td className="py-4 text-center text-[10px] font-bold text-white">{s.passes.total || 0}</td>
                <td className="py-4 text-center text-[10px] font-bold text-white">{s.passes.accuracy || '-'}</td>
                <td className="py-4 text-center text-[10px] font-bold text-white">{s.tackles.interceptions || 0}</td>
                <td className="py-4 pr-4 text-center">
                    <div className="flex justify-center gap-1">
                        {s.cards.yellow > 0 && <div className="w-2 h-3 bg-warning rounded-sm"></div>}
                        {s.cards.red > 0 && <div className="w-2 h-3 bg-danger rounded-sm"></div>}
                    </div>
                </td>
            </tr>
        );
    }

    function TeamStatsTable({ teamName, logo, stats }) {
        return (
            <div className="glass border border-white/10 rounded-3xl overflow-hidden mb-8">
                <div className="bg-white/5 p-4 flex items-center gap-4 border-b border-white/10">
                    <img src={logo} className="w-8 h-8 object-contain" alt="" />
                    <h2 className="text-sm font-black text-white uppercase tracking-tighter">{teamName}</h2>
                </div>
                <div className="overflow-x-auto no-scrollbar">
                    <table className="w-full text-left">
                        <thead>
                            <tr className="bg-darkbg/50 border-b border-white/5">
                                <th className="py-3 pl-4 text-[10px] font-black text-slate-500 uppercase tracking-widest">Giocatore</th>
                                <th className="py-3 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Rating</th>
                                <th className="py-3 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">G</th>
                                <th className="py-3 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">A</th>
                                <th className="py-3 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Tiri</th>
                                <th className="py-3 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Pass</th>
                                <th className="py-3 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">%</th>
                                <th className="py-3 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">Int</th>
                                <th className="py-3 pr-4 text-center text-[10px] font-black text-slate-500 uppercase tracking-widest">C</th>
                            </tr>
                        </thead>
                        <tbody>
                            {stats.map(stat => (
                                <PlayerRow key={stat.player_id} stat={stat} />
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        );
    }

    function App() {
        const [stats, setStats] = useState([]);
        const [loading, setLoading] = useState(false);
        const [searchId, setSearchId] = useState('');

        const loadStats = (id) => {
            if (!id) return;
            setLoading(true);
            fetch(`/api/fixture-player-stats?fixture=${id}`)
                .then(res => res.json())
                .then(data => {
                    setStats(data.response || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        };

        useEffect(() => {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('fixture');
            if (id) {
                setSearchId(id);
                loadStats(id);
            }
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [stats, loading]);

        // Group stats by team
        const teamStats = stats.reduce((acc, curr) => {
            if (!acc[curr.team_id]) {
                acc[curr.team_id] = {
                    name: curr.team_name,
                    logo: curr.team_logo,
                    players: []
                };
            }
            acc[curr.team_id].players.push(curr);
            return acc;
        }, {});

        return (
            <div className="max-w-6xl mx-auto">
                <header className="mb-10 text-center">
                    <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                        Player <span className="text-accent">Performance</span>
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
                        <span className="text-[10px] font-black uppercase text-slate-500 tracking-widest">Scouting digitale...</span>
                    </div>
                ) : (
                    Object.keys(teamStats).length > 0 ? (
                        <div className="flex flex-col gap-4">
                            {Object.values(teamStats).map(team => (
                                <TeamStatsTable
                                    key={team.name}
                                    teamName={team.name}
                                    logo={team.logo}
                                    stats={team.players}
                                />
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-24 glass border border-dashed border-white/10 rounded-3xl">
                            <i data-lucide="user-check" className="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">
                                {searchId ? 'Statistiche non ancora disponibili' : 'Inserisci un Fixture ID per vedere le performance dei singoli'}
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