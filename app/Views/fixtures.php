<?php
// app/Views/fixtures.php
$pageTitle = 'Scommetto.AI - Database Calendario';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function FixtureCard({ fixture }) {
        const date = new Date(fixture.date);
        const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const day = date.toLocaleDateString([], { day: '2-digit', month: '2-digit' });

        return (
            <div className="glass border border-white/10 rounded-2xl p-5 hover:bg-white/5 transition-all group overflow-hidden relative">
                <div className="flex justify-between items-center mb-4">
                    <span className="text-[9px] font-black uppercase tracking-widest text-slate-500 bg-white/5 px-2 py-1 rounded-lg border border-white/5">
                        {fixture.round}
                    </span>
                    <span className={`text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-lg border ${fixture.status_short === 'FT' ? 'text-slate-500 bg-white/5 border-white/5' : 'text-accent bg-accent/10 border-accent/20 animate-pulse'
                        }`}>
                        {fixture.status_long}
                    </span>
                </div>

                <div className="flex items-center justify-between gap-4 py-2">
                    <div className="flex-1 flex flex-col items-center text-center gap-3">
                        <div className="w-12 h-12 bg-white/5 rounded-xl flex items-center justify-center p-2 border border-white/5 shadow-inner">
                            <img src={fixture.home_logo} alt={fixture.home_name} className="max-w-full max-h-full object-contain" />
                        </div>
                        <span className="text-xs font-bold text-white truncate w-full">{fixture.home_name}</span>
                    </div>

                    <div className="flex flex-col items-center gap-1">
                        {fixture.status_short === 'NS' || fixture.status_short === 'TBD' ? (
                            <div className="flex flex-col items-center">
                                <span className="text-lg font-black text-white">{time}</span>
                                <span className="text-[10px] font-bold text-slate-500">{day}</span>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center">
                                <div className="flex items-center gap-2">
                                    <span className="text-2xl font-black text-accent">{fixture.score_home}</span>
                                    <span className="text-slate-600 font-bold">-</span>
                                    <span className="text-2xl font-black text-accent">{fixture.score_away}</span>
                                </div>
                                {fixture.status_short !== 'FT' && (
                                    <span className="text-[10px] font-black text-danger">{fixture.elapsed}'</span>
                                )}
                            </div>
                        )}
                    </div>

                    <div className="flex-1 flex flex-col items-center text-center gap-3">
                        <div className="w-12 h-12 bg-white/5 rounded-xl flex items-center justify-center p-2 border border-white/5 shadow-inner">
                            <img src={fixture.away_logo} alt={fixture.away_name} className="max-w-full max-h-full object-contain" />
                        </div>
                        <span className="text-xs font-bold text-white truncate w-full">{fixture.away_name}</span>
                    </div>
                </div>

                {/* Info decorative in background */}
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-8xl font-black text-white/[0.02] pointer-events-none select-none italic">
                    {fixture.id}
                </div>
            </div>
        );
    }

    function App() {
        const [fixtures, setFixtures] = useState([]);
        const [leagues, setLeagues] = useState([]);
        const [seasons, setSeasons] = useState([]);
        const [loading, setLoading] = useState(false);
        const [initLoading, setInitLoading] = useState(true);

        const [selectedLeague, setSelectedLeague] = useState('');
        const [selectedSeason, setSelectedSeason] = useState('');
        const [selectedRound, setSelectedRound] = useState('All');
        const [availableRounds, setAvailableRounds] = useState([]);

        useEffect(() => {
            Promise.all([
                fetch('/api/leagues').then(res => res.json()),
                fetch('/api/seasons').then(res => res.json())
            ]).then(([leaguesData, seasonsData]) => {
                const availableLeagues = (leaguesData.response || []).filter(l => l.type === 'League');
                setLeagues(availableLeagues);
                setSeasons(seasonsData.response || []);

                if (availableLeagues.length > 0) {
                    const serieA = availableLeagues.find(l => l.id === 135) || availableLeagues[0];
                    setSelectedLeague(serieA.id);
                }
                if (seasonsData.response && seasonsData.response.length > 0) {
                    const current = seasonsData.current || seasonsData.response[0];
                    setSelectedSeason(current);
                }
                setInitLoading(false);
            }).catch(err => {
                console.error('Errore inizializzazione:', err);
                setInitLoading(false);
            });
        }, []);

        useEffect(() => {
            if (selectedLeague && selectedSeason) {
                setLoading(true);
                fetch(`/api/fixtures?league=${selectedLeague}&season=${selectedSeason}`)
                    .then(res => res.json())
                    .then(data => {
                        setFixtures(data.response || []);

                        // Extract unique rounds
                        const rounds = [...new Set((data.response || []).map(f => f.round))];
                        setAvailableRounds(rounds);

                        setLoading(false);
                    })
                    .catch(err => {
                        console.error('Errore caricamento fixtures:', err);
                        setLoading(false);
                    });
            }
        }, [selectedLeague, selectedSeason]);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [fixtures, loading, initLoading, selectedRound]);

        const filteredFixtures = selectedRound === 'All'
            ? fixtures
            : fixtures.filter(f => f.round === selectedRound);

        if (initLoading) {
            return (
                <div className="flex flex-col items-center justify-center py-40 gap-6">
                    <div className="w-16 h-16 border-4 border-accent/10 border-t-accent rounded-full animate-spin"></div>
                    <span className="text-xs font-black uppercase tracking-widest text-slate-500">Inizializzazione database calendario...</span>
                </div>
            );
        }

        return (
            <div className="max-w-7xl mx-auto">
                <header className="flex flex-col gap-6 mb-10">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                                Database <span className="text-accent">Calendario</span>
                            </h1>
                            <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                                <span className={`w-2 h-2 rounded-full ${loading ? 'bg-warning animate-pulse' : 'bg-success'}`}></span>
                                {loading ? 'Sincronizzazione in corso...' : 'Infrastruttura Partite API Football'}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <div className="relative">
                            <select
                                className="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-300 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer pr-10"
                                value={selectedLeague}
                                onChange={(e) => { setSelectedLeague(e.target.value); setSelectedRound('All'); }}
                            >
                                {leagues.map(l => (
                                    <option key={l.id} value={l.id}>{l.country_name} - {l.name}</option>
                                ))}
                            </select>
                            <i data-lucide="chevron-down" className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                        </div>

                        <div className="relative">
                            <select
                                className="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-300 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer pr-10"
                                value={selectedSeason}
                                onChange={(e) => { setSelectedSeason(e.target.value); setSelectedRound('All'); }}
                            >
                                {seasons.map(s => (
                                    <option key={s} value={s}>Stagione {s}</option>
                                ))}
                            </select>
                            <i data-lucide="chevron-down" className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                        </div>

                        <div className="relative">
                            <select
                                className="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-300 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer pr-10 min-w-[200px]"
                                value={selectedRound}
                                onChange={(e) => setSelectedRound(e.target.value)}
                            >
                                <option value="All">Tutte le Giornate</option>
                                {availableRounds.map(r => (
                                    <option key={r} value={r}>{r}</option>
                                ))}
                            </select>
                            <i data-lucide="filter" className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                        </div>

                        <div className="ml-auto flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            <span className="bg-white/5 px-3 py-2 rounded-xl border border-white/5">
                                Match: <span className="text-accent">{filteredFixtures.length}</span>
                            </span>
                        </div>
                    </div>
                </header>

                {loading && fixtures.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-40 gap-6">
                        <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-xs font-black uppercase tracking-widest text-slate-500">Recupero partite...</span>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {filteredFixtures.map(f => (
                            <FixtureCard key={f.id} fixture={f} />
                        ))}
                    </div>
                )}

                {!loading && filteredFixtures.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-40 gap-4 glass rounded-3xl border border-dashed border-white/10">
                        <i data-lucide="info" className="w-12 h-12 text-slate-700"></i>
                        <p className="text-slate-500 font-bold uppercase tracking-widest text-sm">Nessun match trovato per questa selezione</p>
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