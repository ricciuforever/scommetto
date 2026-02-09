<?php
// app/Views/fixture_injuries.php
$pageTitle = 'Scommetto.AI - Infortunati e Squalificati';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function InjuryCard({ injury }) {
        const isSuspended = injury.reason.toLowerCase().includes('suspended') || injury.type.toLowerCase().includes('suspended');
        const isQuestionable = injury.type.toLowerCase().includes('questionable');

        return (
            <div className="glass border border-white/10 rounded-2xl p-4 flex items-center gap-4 hover:bg-white/5 transition-all group overflow-hidden relative">
                <div className={`absolute top-0 right-0 px-2 py-0.5 text-[8px] font-black uppercase tracking-tighter ${isSuspended ? 'bg-danger text-white' : isQuestionable ? 'bg-warning text-darkbg' : 'bg-accent text-white'}`}>
                    {injury.type}
                </div>

                <div className="w-12 h-12 rounded-xl bg-white/5 overflow-hidden border border-white/10 flex-shrink-0">
                    <img
                        src={injury.player_photo || `https://media.api-sports.io/football/players/${injury.player_id}.png`}
                        className="w-full h-full object-cover"
                        alt={injury.player_name}
                        onError={(e) => e.target.src = 'https://media.api-sports.io/football/players/notfound.png'}
                    />
                </div>

                <div className="flex flex-col flex-1 min-w-0">
                    <span className="text-xs font-black text-white truncate">{injury.player_name}</span>
                    <div className="flex items-center gap-1.5 mt-0.5">
                        <img src={injury.team_logo} className="w-3 h-3 object-contain" alt="" />
                        <span className="text-[10px] font-bold text-slate-500 uppercase truncate">{injury.team_name}</span>
                    </div>
                </div>

                <div className="flex flex-col items-end gap-1">
                    <div className="flex items-center gap-1 text-danger">
                        <i data-lucide={isSuspended ? "stop-circle" : "activity"} className="w-3 h-3"></i>
                        <span className="text-[10px] font-black uppercase tracking-widest">{injury.reason}</span>
                    </div>
                </div>
            </div>
        );
    }

    function App() {
        const [injuries, setInjuries] = useState([]);
        const [loading, setLoading] = useState(false);
        const [searchId, setSearchId] = useState('');

        const loadInjuries = (id) => {
            if (!id) return;
            setLoading(true);
            fetch(`/api/fixture-injuries?fixture=${id}`)
                .then(res => res.json())
                .then(data => {
                    setInjuries(data.response || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        };

        useEffect(() => {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('fixture');
            if (id) {
                setSearchId(id);
                loadInjuries(id);
            }
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [injuries, loading]);

        // Group by team
        const teamInjuries = injuries.reduce((acc, curr) => {
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
            <div className="max-w-4xl mx-auto">
                <header className="mb-10 text-center">
                    <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                        Missing <span className="text-accent">Players</span>
                    </h1>
                    <p className="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Infortuni, Squalifiche e Indisponibili</p>

                    <div className="flex justify-center mt-6">
                        <div className="relative w-full max-w-sm">
                            <i data-lucide="hash" className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                            <input
                                type="text"
                                placeholder="Inserisci Fixture ID..."
                                className="w-full bg-white/5 border border-white/10 rounded-2xl py-3 pl-12 pr-4 text-sm focus:outline-none focus:border-accent/50 transition-colors"
                                value={searchId}
                                onChange={(e) => setSearchId(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && loadInjuries(searchId)}
                            />
                            <button
                                onClick={() => loadInjuries(searchId)}
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
                        <span className="text-[10px] font-black uppercase text-slate-500 tracking-widest">Controllo infermeria...</span>
                    </div>
                ) : (
                    Object.keys(teamInjuries).length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                            {Object.values(teamInjuries).map(team => (
                                <div key={team.name} className="flex flex-col gap-4">
                                    <div className="flex items-center gap-3 px-2">
                                        <img src={team.logo} className="w-6 h-6 object-contain" alt="" />
                                        <h2 className="text-xs font-black text-white uppercase tracking-widest">{team.name}</h2>
                                        <div className="h-px bg-white/10 flex-1"></div>
                                        <span className="text-[10px] font-black text-accent">{team.players.length}</span>
                                    </div>
                                    <div className="flex flex-col gap-3">
                                        {team.players.map((injury, idx) => (
                                            <InjuryCard key={idx} injury={injury} />
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-24 glass border border-dashed border-white/10 rounded-3xl">
                            <i data-lucide="shield-check" className="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">
                                {searchId ? 'Tutti i giocatori sono disponibili' : 'Inserisci un Fixture ID per controllare gli indisponibili'}
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