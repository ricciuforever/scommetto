<?php
// app/Views/team_stats.php
$pageTitle = 'Scommetto.AI - Statistiche Squadra';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function StatCard({ title, value, subValue, icon, color = 'accent' }) {
        return (
            <div className="glass border border-white/10 rounded-2xl p-5 flex flex-col gap-1 relative overflow-hidden group">
                <div className="flex justify-between items-center z-10">
                    <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest">{title}</span>
                    <i data-lucide={icon} className={`w-4 h-4 text-${color}`}></i>
                </div>
                <div className="text-2xl font-black text-white mt-1 z-10">{value}</div>
                {subValue && <div className="text-[10px] font-bold text-slate-400 z-10">{subValue}</div>}
                <div className={`absolute -right-2 -bottom-2 w-16 h-16 bg-${color}/5 rounded-full blur-xl group-hover:bg-${color}/10 transition-all`}></div>
            </div>
        );
    }

    function ProgressBar({ label, value, max, color = 'accent' }) {
        const percentage = Math.min(100, Math.round((value / max) * 100)) || 0;
        return (
            <div className="space-y-1.5">
                <div className="flex justify-between text-[10px] font-black uppercase tracking-widest text-slate-400">
                    <span>{label}</span>
                    <span>{value} / {max} ({percentage}%)</span>
                </div>
                <div className="h-2 bg-white/5 rounded-full overflow-hidden border border-white/5">
                    <div
                        className={`h-full bg-${color} shadow-[0_0_10px_rgba(56,189,248,0.5)] transition-all duration-1000`}
                        style={{ width: `${percentage}%` }}
                    ></div>
                </div>
            </div>
        );
    }

    function App() {
        const [data, setData] = useState(null);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);

        const urlParams = new URLSearchParams(window.location.search);
        const teamId = urlParams.get('team');
        const leagueId = urlParams.get('league');
        const season = urlParams.get('season');

        useEffect(() => {
            if (!teamId || !leagueId || !season) {
                setError('Parametri mancanti nell\'URL.');
                setLoading(false);
                return;
            }

            fetch(`/api/team-stats?team=${teamId}&league=${leagueId}&season=${season}`)
                .then(res => res.json())
                .then(json => {
                    if (json.error) setError(json.error);
                    else setData(json);
                    setLoading(false);
                })
                .catch(err => {
                    console.error('Errore:', err);
                    setError('Errore di caricamento dati.');
                    setLoading(false);
                });
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [data, loading, error]);

        if (loading) {
            return (
                <div className="flex flex-col items-center justify-center py-40 gap-6">
                    <div className="w-16 h-16 border-4 border-accent/10 border-t-accent rounded-full animate-spin"></div>
                    <span className="text-xs font-black uppercase tracking-widest text-slate-500">Analisi statistiche avanzate...</span>
                </div>
            );
        }

        if (error) {
            return (
                <div className="glass border border-danger/20 rounded-3xl p-10 text-center flex flex-col items-center gap-4">
                    <i data-lucide="alert-circle" className="w-12 h-12 text-danger"></i>
                    <h2 className="text-xl font-bold text-white uppercase">Si è verificato un errore</h2>
                    <p className="text-slate-400">{error}</p>
                    <button onClick={() => window.history.back()} className="mt-4 px-6 py-2 bg-white/5 rounded-xl text-xs font-bold uppercase hover:bg-white/10 transition-all">Indietro</button>
                </div>
            );
        }

        const stats = data.response;
        const team = data.team;
        const league = data.league;

        return (
            <div className="max-w-7xl mx-auto space-y-8">
                {/* Header Profilo */}
                <header className="flex flex-col md:flex-row gap-6 items-center md:items-end">
                    <div className="w-32 h-32 bg-white/5 rounded-3xl p-4 border border-white/10 flex items-center justify-center shadow-2xl relative">
                        <img src={team.logo} className="max-w-full max-h-full object-contain" alt={team.name} />
                        <div className="absolute -bottom-2 -right-2 w-10 h-10 bg-darkbg border border-white/10 rounded-xl p-2">
                            <img src={league.logo} className="w-full h-full object-contain opacity-50" alt="" />
                        </div>
                    </div>

                    <div className="flex-1 text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start gap-3 mb-2">
                            <h1 className="text-4xl font-black italic uppercase text-white leading-none">
                                {team.name}
                            </h1>
                            <span className="bg-accent/10 text-accent text-[10px] font-black px-2 py-1 rounded border border-accent/20 uppercase tracking-widest">
                                {data.season}
                            </span>
                        </div>
                        <div className="flex flex-wrap justify-center md:justify-start gap-4">
                            <span className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                                <i data-lucide="globe" className="w-4 h-4"></i> {league.name} ({league.country_name})
                            </span>
                            <span className="text-slate-500 text-sm font-semibold uppercase tracking-widest flex items-center gap-2">
                                <i data-lucide="map-pin" className="w-4 h-4 text-accent"></i> {team.country}
                            </span>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <div className="flex flex-col items-center glass border border-white/5 px-4 py-2 rounded-2xl">
                            <span className="text-[10px] font-black text-slate-500 uppercase tracking-tighter">Forma Ultimi Match</span>
                            <div className="flex gap-1 mt-1">
                                {(stats.form || '').split('').slice(-10).map((char, i) => (
                                    <div key={i} className={`w-3 h-3 rounded-sm flex items-center justify-center text-[7px] font-black text-white ${
                                        char === 'W' ? 'bg-success' : char === 'L' ? 'bg-danger' : 'bg-warning'
                                    }`}>{char}</div>
                                ))}
                            </div>
                        </div>
                    </div>
                </header>

                {/* Main Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <StatCard title="Partite Giocate" value={stats.fixtures.played.total} subValue={`${stats.fixtures.played.home} Casa / ${stats.fixtures.played.away} Trasferta`} icon="play" />
                    <StatCard title="Vittorie" value={stats.fixtures.wins.total} subValue={`${stats.fixtures.wins.home} Casa / ${stats.fixtures.wins.away} Trasferta`} icon="trending-up" color="success" />
                    <StatCard title="Pareggi" value={stats.fixtures.draws.total} subValue={`${stats.fixtures.draws.home} Casa / ${stats.fixtures.draws.away} Trasferta`} icon="minus" color="warning" />
                    <StatCard title="Sconfitte" value={stats.fixtures.loses.total} subValue={`${stats.fixtures.loses.home} Casa / ${stats.fixtures.loses.away} Trasferta`} icon="trending-down" color="danger" />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    {/* Sezione Gol */}
                    <div className="glass border border-white/10 rounded-3xl p-8 space-y-8">
                        <div className="flex items-center gap-3 border-b border-white/5 pb-4">
                            <i data-lucide="goal" className="text-accent"></i>
                            <h2 className="text-lg font-black italic uppercase text-white">Analisi Reti</h2>
                        </div>

                        <div className="grid grid-cols-2 gap-8">
                            <div className="space-y-4">
                                <div className="text-center">
                                    <span className="text-4xl font-black text-white">{stats.goals.for.total.total}</span>
                                    <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Gol Fatti</p>
                                    <p className="text-[10px] text-accent font-bold mt-1">Media: {stats.goals.for.average.total}</p>
                                </div>
                                <ProgressBar label="In Casa" value={stats.goals.for.total.home} max={stats.goals.for.total.total} />
                                <ProgressBar label="Fuori Casa" value={stats.goals.for.total.away} max={stats.goals.for.total.total} color="warning" />
                            </div>
                            <div className="space-y-4">
                                <div className="text-center">
                                    <span className="text-4xl font-black text-white">{stats.goals.against.total.total}</span>
                                    <p className="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Gol Subiti</p>
                                    <p className="text-[10px] text-danger font-bold mt-1">Media: {stats.goals.against.average.total}</p>
                                </div>
                                <ProgressBar label="In Casa" value={stats.goals.against.total.home} max={stats.goals.against.total.total} />
                                <ProgressBar label="Fuori Casa" value={stats.goals.against.total.away} max={stats.goals.against.total.total} color="warning" />
                            </div>
                        </div>

                        {/* Distribuzione Minuti */}
                        <div className="space-y-4 pt-4">
                            <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest">Distribuzione Temporale Gol (Fatti)</span>
                            <div className="flex h-12 gap-1 items-end">
                                {Object.entries(stats.goals.for.minute).map(([min, val]) => (
                                    <div key={min} className="flex-1 group relative">
                                        <div
                                            className="bg-accent/40 group-hover:bg-accent rounded-t transition-all"
                                            style={{ height: `${val.total ? (val.total / 20) * 100 : 0}%`, minHeight: '2px' }}
                                        ></div>
                                        <div className="opacity-0 group-hover:opacity-100 absolute -top-8 left-1/2 -translate-x-1/2 bg-white text-darkbg text-[8px] font-black px-1.5 py-0.5 rounded pointer-events-none">
                                            {val.total || 0}
                                        </div>
                                        <div className="text-[7px] text-slate-600 font-bold text-center mt-1 truncate">{min}</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Altre Stats */}
                    <div className="space-y-6">
                        <div className="glass border border-white/10 rounded-3xl p-8">
                            <div className="flex items-center gap-3 border-b border-white/5 pb-4 mb-6">
                                <i data-lucide="zap" className="text-warning"></i>
                                <h2 className="text-lg font-black italic uppercase text-white">Record & Performance</h2>
                            </div>
                            <div className="grid grid-cols-2 gap-6">
                                <div>
                                    <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Massimi</span>
                                    <ul className="space-y-3">
                                        <li className="flex justify-between items-center">
                                            <span className="text-xs font-bold text-slate-400">Vittoria Casa</span>
                                            <span className="text-xs font-black text-white bg-success/10 px-2 py-0.5 rounded">{stats.biggest.wins.home || '-'}</span>
                                        </li>
                                        <li className="flex justify-between items-center">
                                            <span className="text-xs font-bold text-slate-400">Vittoria Fuori</span>
                                            <span className="text-xs font-black text-white bg-success/10 px-2 py-0.5 rounded">{stats.biggest.wins.away || '-'}</span>
                                        </li>
                                        <li className="flex justify-between items-center">
                                            <span className="text-xs font-bold text-slate-400">Sconfitta Casa</span>
                                            <span className="text-xs font-black text-white bg-danger/10 px-2 py-0.5 rounded">{stats.biggest.loses.home || '-'}</span>
                                        </li>
                                    </ul>
                                </div>
                                <div>
                                    <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest block mb-2">Clean Sheets & FTS</span>
                                    <ul className="space-y-3">
                                        <li className="flex justify-between items-center">
                                            <span className="text-xs font-bold text-slate-400">Clean Sheets</span>
                                            <span className="text-xs font-black text-success">{stats.clean_sheet.total}</span>
                                        </li>
                                        <li className="flex justify-between items-center">
                                            <span className="text-xs font-bold text-slate-400">Gare Senza Segnare</span>
                                            <span className="text-xs font-black text-danger">{stats.failed_to_score.total}</span>
                                        </li>
                                        <li className="flex justify-between items-center border-t border-white/5 pt-2 mt-2">
                                            <span className="text-xs font-bold text-slate-400">Rigori Segnati</span>
                                            <span className="text-xs font-black text-white">{stats.penalty.scored.total} / {stats.penalty.total}</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div className="glass border border-white/10 rounded-3xl p-8">
                            <div className="flex items-center gap-3 border-b border-white/5 pb-4 mb-4">
                                <i data-lucide="layout" className="text-accent"></i>
                                <h2 className="text-lg font-black italic uppercase text-white">Moduli Utilizzati</h2>
                            </div>
                            <div className="space-y-3">
                                {stats.lineups.map((l, i) => (
                                    <div key={i} className="flex items-center gap-4">
                                        <span className="w-12 text-xs font-black text-white">{l.formation}</span>
                                        <div className="flex-1 h-1.5 bg-white/5 rounded-full overflow-hidden">
                                            <div className="h-full bg-accent" style={{ width: `${(l.played / stats.fixtures.played.total) * 100}%` }}></div>
                                        </div>
                                        <span className="text-[10px] font-black text-slate-500 uppercase">{l.played} Gare</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<App />);
</script>

<?php
require __DIR__ . '/layout/bottom.php';
?>
