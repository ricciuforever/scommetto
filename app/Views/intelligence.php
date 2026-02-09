<?php
// app/Views/intelligence.php
$pageTitle = 'Scommetto.AI - Live Intelligence';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect, useMemo } = React;

    function IntelligenceDashboard() {
        // State
        const [liveEvents, setLiveEvents] = useState([]);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);

        // Filters State
        const [bookmakers, setBookmakers] = useState([]);
        const [selectedBookmaker, setSelectedBookmaker] = useState(''); // ID
        const [selectedLeague, setSelectedLeague] = useState('');
        const [searchQuery, setSearchQuery] = useState('');

        // Fetch Live Data
        const fetchLive = async () => {
            setLoading(true);
            try {
                // Fetch basic live odds data
                // We construct the URL with current filters
                let url = '/api/intelligence/live';
                const params = new URLSearchParams();
                if (selectedBookmaker) params.append('bookmaker', selectedBookmaker);
                if (selectedLeague) params.append('league', selectedLeague);

                const res = await fetch(`${url}?${params.toString()}`);
                const data = await res.json();

                if (data.response) {
                    setLiveEvents(data.response);
                } else {
                    setLiveEvents([]); // No live events or error structure
                }
            } catch (err) {
                console.error("Error fetching live intelligence:", err);
                setError("Errore nel caricamento dei dati live.");
            } finally {
                setLoading(false);
            }
        };

        // Fetch Bookmakers for filter
        useEffect(() => {
            fetch('/api/odds/bookmakers')
                .then(res => res.json())
                .then(data => setBookmakers(data.response || []))
                .catch(console.error);

            // Initial fetch
            fetchLive();

            // Auto refresh every 60s
            const interval = setInterval(fetchLive, 60000);
            return () => clearInterval(interval);
        }, [selectedBookmaker]); // Refetch if bookmaker changes (API filter)

        // Extract unique leagues from current live events for filter
        const availableLeagues = useMemo(() => {
            const leagues = new Map();
            liveEvents.forEach(evt => {
                if (evt.league && evt.league.id) {
                    leagues.set(evt.league.id, evt.league.name);
                }
            });
            return Array.from(leagues.entries()).map(([id, name]) => ({ id, name }));
        }, [liveEvents]);

        // Client-side filtering
        const filteredEvents = useMemo(() => {
            return liveEvents.filter(evt => {
                const matchLeague = selectedLeague ? evt.league.id === parseInt(selectedLeague) : true;
                const matchSearch = searchQuery
                    ? (evt.teams.home.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                        evt.teams.away.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
                        evt.league.name.toLowerCase().includes(searchQuery.toLowerCase()))
                    : true;
                return matchLeague && matchSearch;
            });
        }, [liveEvents, selectedLeague, searchQuery]);

        return (
            <div className="flex flex-col h-[calc(100vh-100px)]">
                {/* Statistics / Header */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div className="glass p-4 rounded-xl border border-white/5 flex items-center justify-between">
                        <div>
                            <p className="text-[10px] text-slate-500 uppercase font-bold tracking-widest">Eventi Live</p>
                            <h3 className="text-2xl font-black text-white">{filteredEvents.length}</h3>
                        </div>
                        <div className="w-10 h-10 rounded-full bg-accent/10 flex items-center justify-center">
                            <i data-lucide="activity" className="w-5 h-5 text-accent animate-pulse"></i>
                        </div>
                    </div>
                </div>

                <div className="flex flex-1 gap-6 overflow-hidden">
                    {/* Sidebar Filters */}
                    <div className="w-64 flex-shrink-0 glass rounded-xl border border-white/5 p-4 flex flex-col gap-6 overflow-y-auto no-scrollbar">
                        <div>
                            <label className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-2 block">Cerca</label>
                            <div className="relative">
                                <i data-lucide="search" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500"></i>
                                <input
                                    type="text"
                                    placeholder="Squadra o campionato..."
                                    className="w-full bg-black/20 text-white text-xs rounded-lg pl-9 pr-3 py-2 border border-white/5 focus:border-accent/50 focus:outline-none transition-colors"
                                    value={searchQuery}
                                    onChange={(e) => setSearchQuery(e.target.value)}
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-2 block">Bookmaker</label>
                            <select
                                className="w-full bg-black/20 text-white text-xs rounded-lg px-3 py-2 border border-white/5 focus:border-accent/50 focus:outline-none"
                                value={selectedBookmaker}
                                onChange={(e) => setSelectedBookmaker(e.target.value)}
                            >
                                <option value="">Tutti</option>
                                {bookmakers.map(bk => (
                                    <option key={bk.id} value={bk.id}>{bk.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-2 block">Campionato ({availableLeagues.length})</label>
                            <select
                                className="w-full bg-black/20 text-white text-xs rounded-lg px-3 py-2 border border-white/5 focus:border-accent/50 focus:outline-none"
                                value={selectedLeague}
                                onChange={(e) => setSelectedLeague(e.target.value)}
                            >
                                <option value="">Tutti</option>
                                {availableLeagues.sort((a, b) => (a.name || '').localeCompare(b.name || '')).map(lg => (
                                    <option key={lg.id} value={lg.id}>{lg.name}</option>
                                ))}
                            </select>
                        </div>

                        <div className="mt-auto">
                            <button
                                onClick={fetchLive}
                                className="w-full bg-accent/10 hover:bg-accent/20 text-accent text-xs font-bold py-2 rounded-lg border border-accent/20 transition-colors flex items-center justify-center gap-2"
                            >
                                <i data-lucide="refresh-cw" className={`w-3 h-3 ${loading ? 'animate-spin' : ''}`}></i>
                                Aggiorna
                            </button>
                        </div>
                    </div>

                    {/* Main Content Grid */}
                    <div className="flex-1 overflow-y-auto pr-2 custom-scrollbar">
                        {loading && filteredEvents.length === 0 ? (
                            <div className="flex justify-center items-center h-64">
                                <div className="w-8 h-8 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                            </div>
                        ) : filteredEvents.length === 0 ? (
                            <div className="flex flex-col items-center justify-center h-64 text-slate-500">
                                <i data-lucide="radio-off" className="w-12 h-12 mb-4 opacity-50"></i>
                                <p className="text-sm font-bold">Nessun evento live trovato</p>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                                {filteredEvents.map((evt) => (
                                    <LiveEventCard key={evt.fixture.id} event={evt} />
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    function LiveEventCard({ event }) {
        const { fixture, league, teams, goals, odds } = event;
        const mainOdds = odds && odds.length > 0 ? odds[0].values : []; // Usually 1X2

        // Determine scores (some API responses differ)
        const homeScore = goals?.home ?? teams.home.goals ?? 0;
        const awayScore = goals?.away ?? teams.away.goals ?? 0;

        return (
            <div className="glass p-4 rounded-xl border border-white/5 hover:border-accent/20 transition-all group relative overflow-hidden">
                <div className="absolute top-0 right-0 p-2 opacity-50">
                    <img src={league.logo} alt={league.name} className="w-12 h-12 object-contain grayscale opacity-20 group-hover:opacity-40 transition-all" />
                </div>

                {/* Header */}
                <div className="flex items-center justify-between mb-4 relative z-10">
                    <div className="flex items-center gap-2">
                        {league.flag && <img src={league.flag} className="w-4 h-4 rounded-full object-cover" />}
                        <span className="text-[10px] font-bold text-slate-400 uppercase tracking-widest truncate max-w-[150px]">{league.name}</span>
                    </div>
                    <div className="flex items-center gap-1.5 bg-danger/10 text-danger px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-widest border border-danger/20">
                        <span className="animate-pulse w-1.5 h-1.5 rounded-full bg-danger"></span>
                        {fixture.status.short}
                        <span className="text-slate-400 mx-1">|</span>
                        {fixture.status.elapsed}'
                    </div>
                </div>

                {/* Teams & Score */}
                <div className="flex items-center justify-between mb-6 relative z-10">
                    <div className="flex items-center gap-3 flex-1">
                        <img src={teams.home.logo} alt={teams.home.name} className="w-10 h-10 object-contain" />
                        <span className="text-sm font-bold text-white leading-tight">{teams.home.name}</span>
                    </div>
                    <div className="flex flex-col items-center px-4">
                        <div className="text-2xl font-black text-white font-mono tracking-wider flex items-center gap-2">
                            <span>{homeScore}</span>
                            <span className="text-slate-600">-</span>
                            <span>{awayScore}</span>
                        </div>
                    </div>
                    <div className="flex items-center gap-3 flex-1 justify-end">
                        <span className="text-sm font-bold text-white leading-tight text-right">{teams.away.name}</span>
                        <img src={teams.away.logo} alt={teams.away.name} className="w-10 h-10 object-contain" />
                    </div>
                </div>

                {/* Odds (1X2 usually) */}
                {mainOdds && mainOdds.length > 0 && (
                    <div className="grid grid-cols-3 gap-2 relative z-10">
                        {mainOdds.map((odd, idx) => (
                            <div key={idx} className="bg-black/40 hover:bg-accent/10 p-2 rounded-lg border border-white/5 hover:border-accent/30 transition-all text-center cursor-pointer">
                                <span className="block text-[9px] text-slate-500 font-bold mb-0.5">{odd.value}</span>
                                <span className="block text-sm font-black text-accent">{odd.odd}</span>
                            </div>
                        ))}
                    </div>
                )}

                {!mainOdds || mainOdds.length === 0 && (
                    <div className="text-center py-2 text-xs text-slate-500 italic">Quote non disponibili al momento</div>
                )}
            </div>
        )
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<IntelligenceDashboard />);

    // Icons
    if (window.lucide) lucide.createIcons();
</script>
<?php require __DIR__ . '/layout/bottom.php'; ?>