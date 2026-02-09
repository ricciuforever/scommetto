<?php
// app/Views/fixture_events.php
$pageTitle = 'Scommetto.AI - Eventi Match';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect } = React;

    function EventItem({ event, side }) {
        const isLeft = side === 'home';

        const getIcon = (type) => {
            switch (type.toLowerCase()) {
                case 'goal': return <i data-lucide="trophy" className="w-4 h-4 text-warning"></i>;
                case 'card':
                    return <div className={`w-3 h-4 rounded-sm ${event.detail.includes('Red') ? 'bg-danger' : 'bg-warning'}`}></div>;
                case 'subst': return <i data-lucide="refresh-cw" className="w-4 h-4 text-accent"></i>;
                case 'var': return <i data-lucide="tv-2" className="w-4 h-4 text-info"></i>;
                default: return <i data-lucide="info" className="w-4 h-4 text-slate-500"></i>;
            }
        };

        return (
            <div className={`flex items-center gap-4 mb-4 ${isLeft ? 'flex-row' : 'flex-row-reverse'}`}>
                <div className={`flex flex-col ${isLeft ? 'items-end' : 'items-start'} flex-1`}>
                    <span className="text-xs font-black text-white">{event.player.name}</span>
                    <span className="text-[9px] font-bold text-slate-500 uppercase tracking-widest">{event.detail}</span>
                    {event.comments && <span className="text-[8px] italic text-slate-600 truncate max-w-[150px]">{event.comments}</span>}
                </div>

                <div className="relative flex flex-col items-center">
                    <div className="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center z-10">
                        {getIcon(event.type)}
                    </div>
                    <div className="absolute -top-1 px-1.5 py-0.5 bg-darkbg border border-white/10 rounded-md z-20">
                        <span className="text-[8px] font-black text-accent">{event.time.elapsed}{event.time.extra ? `+${event.time.extra}` : ''}'</span>
                    </div>
                </div>

                <div className="flex-1"></div>
            </div>
        );
    }

    function App() {
        const [events, setEvents] = useState([]);
        const [loading, setLoading] = useState(false);
        const [searchId, setSearchId] = useState('');
        const [homeTeamId, setHomeTeamId] = useState(null);

        const loadEvents = (id) => {
            if (!id) return;
            setLoading(true);

            // Prima recuperiamo i dettagli del match per sapere chi è in casa
            fetch(`/api/fixtures?league=135&season=2025`) // Mocking league for context, ideally we'd have a getFixture endpoint
                .then(res => res.json())
                .then(data => {
                    const fixture = data.response.find(f => f.id == id);
                    if (fixture) setHomeTeamId(fixture.team_home_id);

                    return fetch(`/api/fixture-events?fixture=${id}`);
                })
                .then(res => res.json())
                .then(data => {
                    setEvents(data.response || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        };

        useEffect(() => {
            const params = new URLSearchParams(window.location.search);
            const id = params.get('fixture');
            if (id) {
                setSearchId(id);
                loadEvents(id);
            }
        }, []);

        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [events, loading]);

        return (
            <div className="max-w-2xl mx-auto">
                <header className="mb-10 text-center">
                    <h1 className="text-3xl font-black italic uppercase text-white mb-2">
                        Match <span className="text-accent">Events</span>
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
                                onKeyDown={(e) => e.key === 'Enter' && loadEvents(searchId)}
                            />
                            <button
                                onClick={() => loadEvents(searchId)}
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
                        <span className="text-[10px] font-black uppercase text-slate-500 tracking-widest">Recupero eventi...</span>
                    </div>
                ) : (
                    events.length > 0 ? (
                        <div className="relative">
                            {/* Vertical Line */}
                            <div className="absolute left-1/2 top-0 bottom-0 w-px bg-white/5 -translate-x-1/2"></div>

                            <div className="flex flex-col gap-2 relative">
                                {events.map((e, i) => (
                                    <EventItem
                                        key={i}
                                        event={e}
                                        side={e.team.id == homeTeamId ? 'home' : 'away'}
                                    />
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="text-center py-24 glass border border-dashed border-white/10 rounded-3xl">
                            <i data-lucide="zap-off" className="w-12 h-12 text-slate-700 mx-auto mb-4"></i>
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">
                                {searchId ? 'Nessun evento registrato' : 'Inserisci un Fixture ID per vedere il timeline'}
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