<?php
// app/Views/fixture_lineups.php
$pageTitle = 'Scommetto.AI - Formazioni Match';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function FormationGrid({ lineup, side }) {
        const isHome = side === 'home';
        const startXI = lineup.start_xi_json || [];

        // Group players by row (X in grid X:Y)
        const rows = {};
        startXI.forEach(p => {
            const [x, y] = p.player.grid.split(':').map(Number);
            if (!rows[x]) rows[x] = [];
            rows[x].push(p.player);
        });

        // Ensure rows are sorted
        const sortedX = Object.keys(rows).sort((a, b) => isHome ? a - b : b - a);

        return (
            <div className={`relative w-full h-[400px] bg-emerald-900/20 rounded-3xl border border-white/5 overflow-hidden flex flex-col p-6 ${isHome ? 'justify-start' : 'justify-end'}`}>
                {/* Field markings */}
                <div className="absolute inset-0 pointer-events-none opacity-10">
                    <div className="absolute top-0 left-1/4 right-1/4 h-16 border-x border-b border-white"></div>
                    <div className="absolute bottom-0 left-1/4 right-1/4 h-16 border-x border-t border-white"></div>
                    <div className="absolute top-1/2 left-0 right-0 h-px bg-white -translate-y-1/2"></div>
                    <div className="absolute top-1/2 left-1/2 w-24 h-24 border border-white rounded-full -translate-x-1/2 -translate-y-1/2"></div>
                </div>

                <div className="relative z-10 flex flex-col flex-1 gap-4 justify-around">
                    {sortedX.map(x => (
                        <div key={x} className="flex justify-around items-center w-full">
                            {rows[x].map(player => (
                                <div key={player.id} className="flex flex-col items-center gap-1 group cursor-pointer">
                                    <div className="w-8 h-8 rounded-full bg-accent flex items-center justify-center border-2 border-white/20 shadow-lg group-hover:scale-110 transition-all">
                                        <span className="text-[10px] font-black text-white">{player.number}</span>
                                    </div>
                                    <span className="text-[9px] font-bold text-white bg-darkbg/50 px-2 py-0.5 rounded-full whitespace-nowrap">{player.name}</span>
                                </div>
                            ))}
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    function SquadList({ title, players }) {
        return (
            <div className="flex flex-col gap-2">
                <h3 className="text-[10px] font-black uppercase tracking-widest text-slate-500 mb-2">{title}</h3>
                <div className="grid grid-cols-2 gap-2">
                    {players.map(p => (
                        <div key={p.player.id} className="flex items-center gap-3 bg-white/5 p-2 rounded-xl border border-white/5">
                            <div className="w-6 h-6 rounded-lg bg-white/5 flex items-center justify-center text-[10px] font-black text-accent border border-white/5">
                                {p.player.number}
                            </div>
                            <div className="flex flex-col">
                                <span className="text-[10px] font-bold text-white">{p.player.name}</span>
                                <span className="text-[8px] font-bold text-slate-500 uppercase">{p.player.pos}</span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    function App() {
        const [lineups, setLineups] = useState([]);
        const [loading, setLoading] = useState(false);
        const [searchId, setSearchId] = useState('');

        const loadLineups = (id) => {
            if (!id) return;
            setLoading(true);
            fetch(`/api/fixture-lineups?fixture=${id}`)
                .then(res => res.json())
                .then(data => {
                    setLineups(data.response || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        };

        useEffect(() => {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('fixture');
            if (id) {
                setSearchId(id);
                loadLineups(id);
            }
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [lineups, loading]);

        return (
            <div className="max-w-5xl mx-auto">
                <header className="mb-10 text-center">
                    <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                        Match <span className="text-accent">Lineups</span>
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
                                onKeyDown={(e) => e.key === 'Enter' && loadLineups(searchId)}
                            />
                            <button
                                onClick={() => loadLineups(searchId)}
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
                        <span className="text-[10px] font-black uppercase text-slate-500 tracking-widest">Analisi tattica...</span>
                    </div>
                ) : (
                    lineups.length === 2 ? (
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            {lineups.map((lineup, i) => (
                                <div key={lineup.team_id} className="glass border border-white/10 rounded-3xl p-6 flex flex-col gap-6">
                                    <div className="flex items-center gap-4">
                                        <div className="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center p-2 border border-white/5">
                                            <img src={lineup.team_logo} className="max-w-full max-h-full object-contain" alt="" />
                                        </div>
                                        <div>
                                            <h2 className="text-sm font-black text-white uppercase tracking-tighter">{lineup.team_name}</h2>
                                            <div className="flex gap-2 items-center">
                                                <span className="text-[10px] font-black text-accent uppercase tracking-widest">{lineup.formation}</span>
                                                <div className="w-1 h-1 bg-slate-700 rounded-full"></div>
                                                <span className="text-[10px] font-bold text-slate-500 uppercase">{lineup.coach_id ? 'Coach' : 'Staff'}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <FormationGrid lineup={lineup} side={i === 0 ? 'home' : 'away'} />

                                    <div className="flex flex-col gap-6">
                                        <SquadList title="Titolari" players={lineup.start_xi_json} />
                                        <SquadList title="Panchina" players={lineup.substitutes_json} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-24 glass border border-dashed border-white/10 rounded-3xl">
                            <i data-lucide="users" className="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">
                                {searchId ? 'Formazioni non ancora disponibili' : 'Inserisci un Fixture ID per vedere le tattiche'}
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