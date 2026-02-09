<?php
// app/Views/standings.php
$pageTitle = 'Scommetto.AI - Classifiche';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect, useMemo } = React;

    function StandingRow({ row }) {
        const formToBadge = (form) => {
            if (!form) return null;
            return form.split('').map((char, i) => {
                let color = 'bg-slate-500';
                if (char === 'W') color = 'bg-success';
                if (char === 'L') color = 'bg-danger';
                if (char === 'D') color = 'bg-warning';
                return (
                    <span key={i} className={`w-4 h-4 rounded-[4px] flex items-center justify-center text-[8px] font-black text-white ${color} shadow-sm shadow-black/20`}>
                        {char}
                    </span>
                );
            });
        };

        return (
            <tr className="border-b border-white/5 hover:bg-white/[0.02] transition-colors group">
                <td className="py-4 pl-4 text-center">
                    <span className="text-xs font-black text-slate-400 group-hover:text-accent transition-colors">
                        {row.rank}
                    </span>
                </td>
                <td className="py-4">
                    <div className="flex items-center gap-3">
                        <div className="w-8 h-8 bg-white/5 rounded-lg flex items-center justify-center p-1 border border-white/5">
                            <img src={row.team_logo} alt={row.team_name} className="w-full h-full object-contain" />
                        </div>
                        <div className="flex flex-col min-w-0">
                            <span className="text-sm font-bold text-white truncate">{row.team_name}</span>
                            {row.description && (
                                <span className="text-[9px] font-medium text-slate-500 truncate uppercase tracking-tighter">
                                    {row.description}
                                </span>
                            )}
                        </div>
                    </div>
                </td>
                <td className="py-4 text-center">
                    <span className="text-sm font-black text-accent">{row.points}</span>
                </td>
                <td className="py-4 text-center text-xs font-bold text-slate-400">{row.played}</td>
                <td className="py-4 text-center text-xs font-bold text-success/80">{row.win}</td>
                <td className="py-4 text-center text-xs font-bold text-warning/80">{row.draw}</td>
                <td className="py-4 text-center text-xs font-bold text-danger/80">{row.lose}</td>
                <td className="py-4 text-center text-[10px] font-medium text-slate-500 hidden md:table-cell">
                    {row.goals_for} : {row.goals_against}
                </td>
                <td className="py-4 text-center">
                    <span className={`text-xs font-bold ${row.goals_diff > 0 ? 'text-success' : row.goals_diff < 0 ? 'text-danger' : 'text-slate-500'}`}>
                        {row.goals_diff > 0 ? `+${row.goals_diff}` : row.goals_diff}
                    </span>
                </td>
                <td className="py-4 pr-4">
                    <div className="flex justify-center gap-1">
                        {formToBadge(row.form)}
                    </div>
                </td>
            </tr>
        );
    }

    function App() {
        const [standings, setStandings] = useState([]);
        const [leagues, setLeagues] = useState([]);
        const [seasons, setSeasons] = useState([]);
        const [loading, setLoading] = useState(false);
        const [initLoading, setInitLoading] = useState(true);

        const [selectedLeague, setSelectedLeague] = useState('');
        const [selectedSeason, setSelectedSeason] = useState('');
        
        const [activeTab, setActiveTab] = useState('teams');
        const [playerStats, setPlayerStats] = useState({
            scorers: [],
            assists: [],
            yellowcards: [],
            redcards: []
        });

        // Effect for Player Stats Fetching
        useEffect(() => {
            if (activeTab === 'teams' || !selectedLeague || !selectedSeason) return;
            
            // Check if already loaded
            if (playerStats[activeTab] && playerStats[activeTab].length > 0) return;

            setLoading(true);
            fetch(`/api/league-top-stats?league=${selectedLeague}&season=${selectedSeason}&type=${activeTab}`)
                .then(res => res.json())
                .then(data => {
                    setPlayerStats(prev => ({
                        ...prev,
                        [activeTab]: data.response || []
                    }));
                    setLoading(false);
                })
                .catch(err => {
                    console.error('Errore caricamento statistiche giocatori:', err);
                    setLoading(false);
                });
        }, [activeTab, selectedLeague, selectedSeason]);

        // Caricamento Iniziale: Leghe e Stagioni
        useEffect(() => {
            Promise.all([
                fetch('/api/leagues').then(res => res.json()),
                fetch('/api/seasons').then(res => res.json())
            ]).then(([leaguesData, seasonsData]) => {
                setLeagues(leaguesData.response || []);
                setSeasons(seasonsData.response || []);

                if (leaguesData.response?.length > 0) {
                    const serieA = leaguesData.response.find(l => l.id === 135) || leaguesData.response[0];
                    setSelectedLeague(serieA.id);
                }
                if (seasonsData.response?.length > 0) {
                    setSelectedSeason(seasonsData.current || seasonsData.response[0]);
                }
                setInitLoading(false);
            }).catch(err => {
                console.error('Errore inizializzazione:', err);
                setInitLoading(false);
            });
        }, []);

        // Reset Player Stats on context change
        useEffect(() => {
            setPlayerStats({
                scorers: [],
                assists: [],
                yellowcards: [],
                redcards: []
            });
            // Also reset to teams tab? Maybe not, keep user preference but reload data if tab is active
        }, [selectedLeague, selectedSeason]);

        // Caricamento Classifica
        useEffect(() => {
            if (selectedLeague && selectedSeason) {
                setLoading(true);
                fetch(`/api/standings?league=${selectedLeague}&season=${selectedSeason}`)
                    .then(res => res.json())
                    .then(data => {
                        setStandings(data.response || []);
                        setLoading(false);
                    })
                    .catch(err => {
                        console.error('Errore caricamento classifica:', err);
                        setLoading(false);
                    });
            }
        }, [selectedLeague, selectedSeason]);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [standings, loading, initLoading]);

        // Raggruppamento per Gruppo (per Coppe/Champions)
        const groupedStandings = useMemo(() => {
            const groups = {};
            standings.forEach(row => {
                const gName = row.group_name || 'Classifica';
                if (!groups[gName]) groups[gName] = [];
                groups[gName].push(row);
            });
            return groups;
        }, [standings]);

        if (initLoading) {
            return (
                <div className="flex flex-col items-center justify-center py-40 gap-6">
                    <div className="w-16 h-16 border-4 border-accent/10 border-t-accent rounded-full animate-spin"></div>
                    <span className="text-xs font-black uppercase tracking-widest text-slate-500">Inizializzazione database classifiche...</span>
                </div>
            );
        }

        return (
            <div className="max-w-6xl mx-auto">
                <header className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-10">
                    <div>
                        <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                            Classifiche <span className="text-accent">Live</span>
                        </h1>
                        <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                            <span className={`w-2 h-2 rounded-full ${loading ? 'bg-warning animate-pulse' : 'bg-success'}`}></span>
                            {loading ? 'Aggiornamento in corso...' : 'Dati sincronizzati ogni ora'}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3 w-full md:w-auto">
                        <div className="relative flex-1 md:flex-none min-w-[200px]">
                            <select
                                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-300 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer pr-10 shadow-2xl"
                                value={selectedLeague}
                                onChange={(e) => setSelectedLeague(e.target.value)}
                            >
                                {leagues.map(l => (
                                    <option key={l.id} value={l.id}>{l.country_name} - {l.name}</option>
                                ))}
                            </select>
                            <i data-lucide="chevron-down" className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                        </div>

                        <div className="relative flex-1 md:flex-none">
                            <select
                                className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-300 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer pr-10 shadow-2xl"
                                value={selectedSeason}
                                onChange={(e) => setSelectedSeason(e.target.value)}
                            >
                                {seasons.map(s => (
                                    <option key={s} value={s}>Stagione {s}</option>
                                ))}
                            </select>
                            <i data-lucide="chevron-down" className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                        </div>
                    </div>
                </header>

                <div className="flex flex-wrap gap-4 mb-8 border-b border-white/10 pb-4">
                    <button
                        onClick={() => setActiveTab('teams')}
                        className={`px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'teams' ? 'bg-accent text-slate-900 shadow-lg shadow-accent/20' : 'bg-white/5 text-slate-400 hover:bg-white/10'}`}
                    >
                        Classifica
                    </button>
                    <button
                        onClick={() => setActiveTab('scorers')}
                        className={`px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'scorers' ? 'bg-accent text-slate-900 shadow-lg shadow-accent/20' : 'bg-white/5 text-slate-400 hover:bg-white/10'}`}
                    >
                        Marcatori
                    </button>
                    <button
                        onClick={() => setActiveTab('assists')}
                        className={`px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'assists' ? 'bg-accent text-slate-900 shadow-lg shadow-accent/20' : 'bg-white/5 text-slate-400 hover:bg-white/10'}`}
                    >
                        Assist
                    </button>
                    <button
                        onClick={() => setActiveTab('yellowcards')}
                        className={`px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'yellowcards' ? 'bg-warning text-slate-900 shadow-lg shadow-warning/20' : 'bg-white/5 text-slate-400 hover:bg-white/10'}`}
                    >
                        Cartellini Gialli
                    </button>
                    <button
                        onClick={() => setActiveTab('redcards')}
                        className={`px-4 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all ${activeTab === 'redcards' ? 'bg-danger text-white shadow-lg shadow-danger/20' : 'bg-white/5 text-slate-400 hover:bg-white/10'}`}
                    >
                        Cartellini Rossi
                    </button>
                </div>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-40 gap-6">
                        <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-xs font-black uppercase tracking-widest text-slate-500">
                            {activeTab === 'teams' ? 'Recupero posizioni...' : 'Statistiche giocatori...'}
                        </span>
                    </div>
                ) : activeTab === 'teams' ? (
                    standings.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-40 gap-4 glass rounded-3xl border border-dashed border-white/10">
                            <i data-lucide="trophy" className="w-12 h-12 text-slate-700"></i>
                            <p className="text-slate-500 font-bold uppercase tracking-widest text-sm text-center px-6">
                                Nessun dato disponibile per questa stagione.<br />
                                <span className="text-[10px] font-medium opacity-50">La competizione potrebbe non essere ancora iniziata.</span>
                            </p>
                        </div>
                    ) : (
                        <div className="flex flex-col gap-10">
                            {Object.entries(groupedStandings).map(([groupName, rows]) => (
                                <div key={groupName} className="glass rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
                                    <div className="bg-white/5 px-6 py-4 border-b border-white/10 flex justify-between items-center">
                                        <h2 className="text-sm font-black uppercase tracking-widest text-white italic">{groupName}</h2>
                                        <span className="text-[10px] font-bold text-slate-500 uppercase">{rows.length} Squadre</span>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left border-collapse">
                                            <thead>
                                                <tr className="bg-black/20 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                                    <th className="py-4 pl-4 text-center w-12">#</th>
                                                    <th className="py-4">Squadra</th>
                                                    <th className="py-4 text-center">PT</th>
                                                    <th className="py-4 text-center">G</th>
                                                    <th className="py-4 text-center">V</th>
                                                    <th className="py-4 text-center">N</th>
                                                    <th className="py-4 text-center">P</th>
                                                    <th className="py-4 text-center hidden md:table-cell">Gf:Gs</th>
                                                    <th className="py-4 text-center">DR</th>
                                                    <th className="py-4 text-center pr-4">Forma</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {rows.map(row => <StandingRow key={row.team_id} row={row} />)}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )
                ) : (
                    // Player Stats View
                    !playerStats[activeTab] || playerStats[activeTab].length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-40 gap-4 glass rounded-3xl border border-dashed border-white/10">
                            <i data-lucide="user-x" className="w-12 h-12 text-slate-700"></i>
                            <p className="text-slate-500 font-bold uppercase tracking-widest text-sm text-center px-6">
                                Nessuna statistica disponibile.
                            </p>
                        </div>
                    ) : (
                        <div className="glass rounded-3xl border border-white/10 overflow-hidden shadow-2xl">
                            <div className="overflow-x-auto">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-black/20 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                            <th className="py-4 pl-4 text-center w-12">#</th>
                                            <th className="py-4">Giocatore</th>
                                            <th className="py-4">Squadra</th>
                                            <th className="py-4 text-center">
                                                {activeTab === 'scorers' && 'Gol'}
                                                {activeTab === 'assists' && 'Assist'}
                                                {activeTab === 'yellowcards' && 'Gialli'}
                                                {activeTab === 'redcards' && 'Rossi'}
                                            </th>
                                            <th className="py-4 text-center hidden md:table-cell">Presenze</th>
                                            <th className="py-4 text-center hidden md:table-cell">Rating</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {playerStats[activeTab].map((item, idx) => {
                                            const stat = item.statistics[0];
                                            const value = activeTab === 'scorers' ? stat.goals.total :
                                                activeTab === 'assists' ? stat.goals.assists :
                                                    activeTab === 'yellowcards' ? stat.cards.yellow :
                                                        stat.cards.red;
                                            return (
                                                <tr key={idx} className="border-b border-white/5 hover:bg-white/[0.02] transition-colors">
                                                    <td className="py-4 pl-4 text-center text-xs font-black text-slate-500">{idx + 1}</td>
                                                    <td className="py-4">
                                                        <div className="flex items-center gap-3">
                                                            <div className="w-8 h-8 rounded-full overflow-hidden bg-white/10 border border-white/10">
                                                                <img src={item.player.photo} alt={item.player.name} className="w-full h-full object-cover" />
                                                            </div>
                                                            <div>
                                                                <div className="text-sm font-bold text-white">{item.player.name}</div>
                                                                <div className="text-[10px] text-slate-500 uppercase tracking-tight">{item.player.nationality}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td className="py-4">
                                                        <div className="flex items-center gap-2">
                                                            <img src={stat.team.logo} className="w-5 h-5 object-contain" alt={stat.team.name} />
                                                            <span className="text-xs font-bold text-slate-300 hidden md:inline">{stat.team.name}</span>
                                                        </div>
                                                    </td>
                                                    <td className="py-4 text-center">
                                                        <span className="text-sm font-black text-accent bg-accent/10 px-2 py-1 rounded border border-accent/20">
                                                            {value || 0}
                                                        </span>
                                                    </td>
                                                    <td className="py-4 text-center text-xs font-bold text-slate-500 hidden md:table-cell">
                                                        {stat.games.appearences} <span className="text-[9px] font-normal">({stat.games.minutes}')</span>
                                                    </td>
                                                    <td className="py-4 text-center hidden md:table-cell">
                                                        {stat.games.rating ? (
                                                            <span className={`text-[10px] font-black px-1.5 py-0.5 rounded ${parseFloat(stat.games.rating) >= 7 ? 'bg-success text-slate-900' : 'bg-slate-800 text-slate-400'}`}>
                                                                {parseFloat(stat.games.rating).toFixed(1)}
                                                            </span>
                                                        ) : '-'}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
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