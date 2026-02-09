<?php
// app/Views/h2h.php
$pageTitle = 'Scommetto.AI - Head to Head';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function MatchRow({ match }) {
        const date = new Date(match.fixture.date).toLocaleDateString([], { day: '2-digit', month: '2-digit', year: '2-digit' });

        return (
            <div className="glass border border-white/10 rounded-xl p-4 flex items-center justify-between hover:bg-white/5 transition-all">
                <div className="flex flex-col gap-1 w-20">
                    <span className="text-[9px] font-black text-slate-500 uppercase">{date}</span>
                    <span className="text-[8px] font-bold text-slate-600 truncate">{match.league.name}</span>
                </div>

                <div className="flex-1 flex items-center justify-center gap-4">
                    <div className="flex items-center gap-2 flex-1 justify-end">
                        <span className={`text-xs font-bold ${match.teams.home.winner ? 'text-white' : 'text-slate-400'}`}>{match.teams.home.name}</span>
                        <img src={match.teams.home.logo} className="w-5 h-5 object-contain" alt="" />
                    </div>

                    <div className="bg-white/5 px-3 py-1 rounded-lg border border-white/5 flex items-center gap-2">
                        <span className={`text-sm font-black ${match.teams.home.winner ? 'text-accent' : 'text-white'}`}>{match.goals.home}</span>
                        <span className="text-slate-600 font-bold">-</span>
                        <span className={`text-sm font-black ${match.teams.away.winner ? 'text-accent' : 'text-white'}`}>{match.goals.away}</span>
                    </div>

                    <div className="flex items-center gap-2 flex-1">
                        <img src={match.teams.away.logo} className="w-5 h-5 object-contain" alt="" />
                        <span className={`text-xs font-bold ${match.teams.away.winner ? 'text-white' : 'text-slate-400'}`}>{match.teams.away.name}</span>
                    </div>
                </div>
            </div>
        );
    }

    function App() {
        const [leagues, setLeagues] = useState([]);
        const [teams, setTeams] = useState([]);
        const [h2hData, setH2hData] = useState([]);
        const [loading, setLoading] = useState(false);
        const [initLoading, setInitLoading] = useState(true);

        const [selectedLeague, setSelectedLeague] = useState('');
        const [team1, setTeam1] = useState('');
        const [team2, setTeam2] = useState('');

        // Caricamento Iniziale: Leghe
        useEffect(() => {
            fetch('/api/leagues')
                .then(res => res.json())
                .then(data => {
                    const availableLeagues = (data.response || []).filter(l => l.type === 'League');
                    setLeagues(availableLeagues);
                    if (availableLeagues.length > 0) {
                        setSelectedLeague(availableLeagues.find(l => l.id === 135)?.id || availableLeagues[0].id);
                    }
                    setInitLoading(false);
                });
        }, []);

        // Caricamento Squadre per la lega selezionata
        useEffect(() => {
            if (selectedLeague) {
                fetch(`/api/teams?league=${selectedLeague}&season=2025`)
                    .then(res => res.json())
                    .then(data => {
                        const fetchedTeams = data.response || [];
                        setTeams(fetchedTeams);
                        if (fetchedTeams.length >= 2) {
                            setTeam1(fetchedTeams[0].id);
                            setTeam2(fetchedTeams[1].id);
                        }
                    });
            }
        }, [selectedLeague]);

        // Caricamento H2H
        useEffect(() => {
            if (team1 && team2 && team1 !== team2) {
                setLoading(true);
                fetch(`/api/h2h?t1=${team1}&t2=${team2}`)
                    .then(res => res.json())
                    .then(data => {
                        setH2hData(data.response || []);
                        setLoading(false);
                    })
                    .catch(() => setLoading(false));
            }
        }, [team1, team2]);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [h2hData, loading]);

        if (initLoading) {
            return (
                <div className="flex items-center justify-center py-40">
                    <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                </div>
            );
        }

        return (
            <div className="max-w-4xl mx-auto">
                <header className="mb-10 text-center">
                    <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                        Head <span className="text-accent">to</span> Head
                    </h1>
                    <p className="text-slate-500 text-xs font-bold uppercase tracking-[0.2em]">Confronto Storico tra Team</p>
                </header>

                <div className="glass border border-white/10 rounded-3xl p-6 mb-8">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        <div className="flex flex-col gap-2">
                            <label className="text-[10px] font-black uppercase text-slate-500 ml-1">Campionato</label>
                            <div className="relative">
                                <select
                                    className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm font-bold text-white appearance-none cursor-pointer focus:border-accent/50 outline-none"
                                    value={selectedLeague}
                                    onChange={(e) => setSelectedLeague(e.target.value)}
                                >
                                    {leagues.map(l => (
                                        <option key={l.id} value={l.id}>{l.name}</option>
                                    ))}
                                </select>
                                <i data-lucide="chevron-down" className="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <label className="text-[10px] font-black uppercase text-slate-500 ml-1">Team 1</label>
                            <div className="relative">
                                <select
                                    className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm font-bold text-white appearance-none cursor-pointer focus:border-accent/50 outline-none"
                                    value={team1}
                                    onChange={(e) => setTeam1(e.target.value)}
                                >
                                    {teams.map(t => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </select>
                                <i data-lucide="chevron-down" className="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                            </div>
                        </div>

                        <div className="flex flex-col gap-2">
                            <label className="text-[10px] font-black uppercase text-slate-500 ml-1">Team 2</label>
                            <div className="relative">
                                <select
                                    className="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm font-bold text-white appearance-none cursor-pointer focus:border-accent/50 outline-none"
                                    value={team2}
                                    onChange={(e) => setTeam2(e.target.value)}
                                >
                                    {teams.map(t => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </select>
                                <i data-lucide="chevron-down" className="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                            </div>
                        </div>
                    </div>
                </div>

                {loading ? (
                    <div className="flex flex-col items-center justify-center py-20 gap-4">
                        <div className="w-10 h-10 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-[10px] font-black uppercase text-slate-500 tracking-widest">Sincronizzazione dati...</span>
                    </div>
                ) : (
                    <div className="flex flex-col gap-3">
                        {h2hData.length > 0 ? (
                            h2hData.map((m, i) => <MatchRow key={i} match={m} />)
                        ) : (
                            team1 === team2 ? (
                                <div className="text-center py-20 bg-white/5 rounded-3xl border border-dashed border-white/10">
                                    <p className="text-slate-500 font-bold uppercase text-sm">Seleziona due team diversi</p>
                                </div>
                            ) : (
                                <div className="text-center py-20 bg-white/5 rounded-3xl border border-dashed border-white/10">
                                    <p className="text-slate-500 font-bold uppercase text-sm">Nessun precedente trovato</p>
                                </div>
                            )
                        )}
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