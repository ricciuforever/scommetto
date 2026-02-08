<?php
// app/Views/rounds.php
$pageTitle = 'Scommetto.AI - Database Giornate';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function RoundItem({ round, index }) {
        return (
            <div className="glass border border-white/10 rounded-2xl p-4 flex items-center justify-between hover:bg-white/5 transition-all group">
                <div className="flex items-center gap-4">
                    <div className="w-10 h-10 bg-accent/10 rounded-xl flex items-center justify-center border border-accent/20">
                        <span className="text-accent font-black">{index + 1}</span>
                    </div>
                    <div>
                        <h3 className="font-bold text-white group-hover:text-accent transition-colors">
                            {round}
                        </h3>
                        <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-widest">
                            {round.includes('Regular') ? 'Campionato Regolare' : 'Fase Finale'}
                        </p>
                    </div>
                </div>
                <div className="opacity-0 group-hover:opacity-100 transition-opacity">
                    <i data-lucide="chevron-right" className="w-4 h-4 text-slate-500"></i>
                </div>
            </div>
        );
    }

    function App() {
        const [rounds, setRounds] = useState([]);
        const [leagues, setLeagues] = useState([]);
        const [seasons, setSeasons] = useState([]);
        const [loading, setLoading] = useState(false);
        const [initLoading, setInitLoading] = useState(true);

        const [selectedLeague, setSelectedLeague] = useState('');
        const [selectedSeason, setSelectedSeason] = useState('');

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
                fetch(`/api/rounds?league=${selectedLeague}&season=${selectedSeason}`)
                    .then(res => res.json())
                    .then(data => {
                        setRounds(data.response || []);
                        setLoading(false);
                    })
                    .catch(err => {
                        console.error('Errore caricamento round:', err);
                        setLoading(false);
                    });
            }
        }, [selectedLeague, selectedSeason]);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [rounds, loading, initLoading]);

        if (initLoading) {
            return (
                <div className="flex flex-col items-center justify-center py-40 gap-6">
                    <div className="w-16 h-16 border-4 border-accent/10 border-t-accent rounded-full animate-spin"></div>
                    <span className="text-xs font-black uppercase tracking-widest text-slate-500">Inizializzazione database giornate...</span>
                </div>
            );
        }

        return (
            <div className="max-w-4xl mx-auto">
                <header className="flex flex-col gap-6 mb-10">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                                Database <span className="text-accent">Giornate</span>
                            </h1>
                            <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                                <span className={`w-2 h-2 rounded-full ${loading ? 'bg-warning animate-pulse' : 'bg-success'}`}></span>
                                {loading ? 'Sincronizzazione in corso...' : 'Struttura Calendario API Football'}
                            </p>
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <div className="relative">
                            <select
                                className="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-xs font-bold uppercase tracking-widest text-slate-300 focus:outline-none focus:border-accent/50 appearance-none cursor-pointer pr-10"
                                value={selectedLeague}
                                onChange={(e) => setSelectedLeague(e.target.value)}
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
                                onChange={(e) => setSelectedSeason(e.target.value)}
                            >
                                {seasons.map(s => (
                                    <option key={s} value={s}>Stagione {s}</option>
                                ))}
                            </select>
                            <i data-lucide="chevron-down" className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none"></i>
                        </div>

                        <div className="ml-auto flex items-center gap-2 text-[10px] font-black text-slate-500 uppercase tracking-widest">
                            <span className="bg-white/5 px-3 py-2 rounded-xl border border-white/5">
                                Giornate: <span className="text-accent">{rounds.length}</span>
                            </span>
                        </div>
                    </div>
                </header>

                {loading && rounds.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-40 gap-6">
                        <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-xs font-black uppercase tracking-widest text-slate-500">Recupero giornate...</span>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {rounds.map((r, i) => (
                            <RoundItem key={i} round={r} index={i} />
                        ))}
                    </div>
                )}

                {!loading && rounds.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-40 gap-4 glass rounded-3xl border border-dashed border-white/10">
                        <i data-lucide="calendar-x" className="w-12 h-12 text-slate-700"></i>
                        <p className="text-slate-500 font-bold uppercase tracking-widest text-sm">Nessuna giornata trovata</p>
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