<?php
// app/Views/teams.php
$pageTitle = 'Scommetto.AI - Database Squadre';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function TeamCard({ team, leagueId, season }) {
        return (
            <div className="glass border border-white/10 rounded-2xl p-5 flex flex-col gap-4 hover:scale-[1.02] transition-all cursor-pointer group relative overflow-hidden">
                <div className="flex justify-between items-start z-10">
                    <div className="w-14 h-14 bg-white/5 rounded-xl flex items-center justify-center p-2 border border-white/5 shadow-inner">
                        <img src={team.logo} alt={team.name} className="max-w-full max-h-full object-contain" />
                    </div>
                    {team.code && (
                        <span className="text-[10px] font-black px-2 py-1 rounded-lg bg-white/5 text-slate-500 uppercase tracking-widest border border-white/5">
                            {team.code}
                        </span>
                    )}
                </div>

                <div className="z-10">
                    <h3 className="font-bold text-lg text-white group-hover:text-accent transition-colors truncate">
                        {team.name}
                    </h3>
                    <p className="text-[10px] font-semibold text-slate-500 uppercase tracking-widest flex items-center gap-1 mt-1">
                        <i data-lucide="map-pin" className="w-3 h-3 text-accent"></i>
                        {team.country}
                        {team.founded && <span className="ml-auto text-slate-600">Est. {team.founded}</span>}
                    </p>
                </div>

                {team.venue_name && (
                    <div className="z-10 pt-3 border-t border-white/5 mt-auto">
                        <div className="flex items-center justify-between mb-3">
                            <div className="flex items-center gap-2">
                            <div className="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center border border-white/5 overflow-hidden">
                                {team.venue_image ? (
                                    <img src={team.venue_image} className="w-full h-full object-cover" alt="" />
                                ) : (
                                    <i data-lucide="building-2" className="w-4 h-4 text-slate-600"></i>
                                )}
                            </div>
                                <div className="flex flex-col min-w-0">
                                    <span className="text-[10px] font-bold text-slate-300 truncate leading-tight">{team.venue_name}</span>
                                    <span className="text-[9px] font-medium text-slate-500 truncate uppercase tracking-tighter">{team.venue_city}</span>
                                </div>
                            </div>
                            <a
                                href={`/team-stats?team=${team.id}&league=${leagueId}&season=${season}`}
                                className="w-8 h-8 rounded-lg bg-accent/10 border border-accent/20 flex items-center justify-center text-accent hover:bg-accent hover:text-white transition-all shadow-lg shadow-accent/10"
                                title="Statistiche Avanzate"
                            >
                                <i data-lucide="bar-chart-2" className="w-4 h-4"></i>
                            </a>
                        </div>
                    </div>
                )}

                {/* Background Decorative Logo */}
                <img src={team.logo} className="absolute -right-6 -bottom-6 w-32 h-32 opacity-[0.03] grayscale pointer-events-none" alt="" />
            </div>
        );
    }

    function App() {
        const [teams, setTeams] = useState([]);
        const [leagues, setLeagues] = useState([]);
        const [seasons, setSeasons] = useState([]);
        const [loading, setLoading] = useState(false);
        const [initLoading, setInitLoading] = useState(true);

        const [selectedLeague, setSelectedLeague] = useState('');
        const [selectedSeason, setSelectedSeason] = useState('');
        const [search, setSearch] = useState('');

        // Caricamento Iniziale: Leghe e Stagioni
        useEffect(() => {
            Promise.all([
                fetch('/api/leagues').then(res => res.json()),
                fetch('/api/seasons').then(res => res.json())
            ]).then(([leaguesData, seasonsData]) => {
                const availableLeagues = (leaguesData.response || []).filter(l => l.type === 'League');
                setLeagues(availableLeagues);
                setSeasons(seasonsData.response || []);

                // Imposta valori predefiniti (Serie A e ultima stagione)
                if (availableLeagues.length > 0) {
                    const serieA = availableLeagues.find(l => l.id === 135) || availableLeagues[0];
                    setSelectedLeague(serieA.id);
                }
                if (seasonsData.response && seasonsData.response.length > 0) {
                    // Usa la stagione corrente fornita dal server, altrimenti la prima disponibile
                    const current = seasonsData.current || seasonsData.response[0];
                    setSelectedSeason(current);
                }
                setInitLoading(false);
            }).catch(err => {
                console.error('Errore inizializzazione:', err);
                setInitLoading(false);
            });
        }, []);

        // Caricamento Squadre al cambio filtri
        useEffect(() => {
            if (selectedLeague && selectedSeason) {
                setLoading(true);
                fetch(`/api/teams?league=${selectedLeague}&season=${selectedSeason}`)
                    .then(res => res.json())
                    .then(data => {
                        setTeams(data.response || []);
                        setLoading(false);
                    })
                    .catch(err => {
                        console.error('Errore caricamento squadre:', err);
                        setLoading(false);
                    });
            }
        }, [selectedLeague, selectedSeason]);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [teams, loading, initLoading]);

        const filteredTeams = teams.filter(t =>
            t.name.toLowerCase().includes(search.toLowerCase())
        );

        if (initLoading) {
            return (
                <div className="flex flex-col items-center justify-center py-40 gap-6">
                    <div className="w-16 h-16 border-4 border-accent/10 border-t-accent rounded-full animate-spin"></div>
                    <span className="text-xs font-black uppercase tracking-widest text-slate-500">Inizializzazione database squadre...</span>
                </div>
            );
        }

        return (
            <div className="max-w-7xl mx-auto">
                <header className="flex flex-col gap-6 mb-10">
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                                Database <span className="text-accent">Squadre</span>
                            </h1>
                            <p className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                                <span className={`w-2 h-2 rounded-full ${loading ? 'bg-warning animate-pulse' : 'bg-success'}`}></span>
                                {loading ? 'Sincronizzazione in corso...' : 'Infrastruttura Dati API Football'}
                            </p>
                        </div>

                        <div className="relative w-full md:w-80">
                            <i data-lucide="search" className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Cerca club..."
                                className="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors shadow-2xl shadow-black/20"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
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
                                Squadre: <span className="text-accent">{filteredTeams.length}</span>
                            </span>
                        </div>
                    </div>
                </header>

                {loading && teams.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-40 gap-6">
                        <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        <span className="text-xs font-black uppercase tracking-widest text-slate-500">Recupero club...</span>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        {filteredTeams.map(t => (
                            <TeamCard key={t.id} team={t} leagueId={selectedLeague} season={selectedSeason} />
                        ))}
                    </div>
                )}

                {!loading && filteredTeams.length === 0 && (
                    <div className="flex flex-col items-center justify-center py-40 gap-4 glass rounded-3xl border border-dashed border-white/10">
                        <i data-lucide="search-x" className="w-12 h-12 text-slate-700"></i>
                        <p className="text-slate-500 font-bold uppercase tracking-widest text-sm">Nessuna squadra trovata</p>
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
