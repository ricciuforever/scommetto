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
        const [allBookmakers, setAllBookmakers] = useState([]);
        const [availableBookmakers, setAvailableBookmakers] = useState([]); // Bookmakers with live odds
        const [selectedBookmaker, setSelectedBookmaker] = useState('');
        const [selectedCountry, setSelectedCountry] = useState('');
        const [selectedLeague, setSelectedLeague] = useState('');

        // Fetch Live Data (matches only, odds are fetched separately if bookmaker selected)
        const fetchLive = async () => {
            setLoading(true);
            try {
                // Fetch live fixtures
                let fixturesUrl = '/api/intelligence/live';
                const fixturesParams = new URLSearchParams();
                if (selectedLeague) fixturesParams.append('league', selectedLeague);

                const fixturesRes = await fetch(`${fixturesUrl}?${fixturesParams.toString()}`);
                const fixturesData = await fixturesRes.json();

                let fixtures = fixturesData.response || [];

                // Detect which bookmakers have odds for these fixtures (only if not already detected)
                if (fixtures.length > 0 && allBookmakers.length > 0 && !selectedBookmaker) {
                    const activeBookmakerIds = new Set();

                    // Check first fixture for all bookmakers to see which have odds
                    const firstFixture = fixtures[0];
                    await Promise.all(
                        allBookmakers.map(async (bookmaker) => {
                            try {
                                const oddsRes = await fetch(`/api/odds?fixture=${firstFixture.fixture.id}&bookmaker=${bookmaker.id}`);
                                const oddsData = await oddsRes.json();

                                if (oddsData.response && oddsData.response.length > 0) {
                                    const fixtureOdds = oddsData.response[0];
                                    if (fixtureOdds.bookmakers && fixtureOdds.bookmakers.length > 0) {
                                        activeBookmakerIds.add(bookmaker.id);
                                    }
                                }
                            } catch (err) {
                                // Bookmaker doesn't have odds, skip
                            }
                        })
                    );

                    // Filter to only bookmakers with odds
                    const active = allBookmakers.filter(bk => activeBookmakerIds.has(bk.id));
                    setAvailableBookmakers(active);
                }

                // If a bookmaker is selected, fetch odds for each fixture
                if (selectedBookmaker && fixtures.length > 0) {
                    const fixturesWithOdds = await Promise.all(
                        fixtures.map(async (fixture) => {
                            try {
                                const oddsRes = await fetch(`/api/odds?fixture=${fixture.fixture.id}&bookmaker=${selectedBookmaker}`);
                                const oddsData = await oddsRes.json();

                                // Extract bookmaker odds from response
                                if (oddsData.response && oddsData.response.length > 0) {
                                    const fixtureOdds = oddsData.response[0];
                                    if (fixtureOdds.bookmakers && fixtureOdds.bookmakers.length > 0) {
                                        return {
                                            ...fixture,
                                            odds: fixtureOdds.bookmakers[0].bets // First bookmaker's bets
                                        };
                                    }
                                }
                            } catch (err) {
                                console.error(`Error fetching odds for fixture ${fixture.fixture.id}:`, err);
                            }
                            return fixture;
                        })
                    );
                    fixtures = fixturesWithOdds;
                }

                setLiveEvents(fixtures);
            } catch (err) {
                console.error("Error fetching live intelligence:", err);
                setError("Errore nel caricamento dei dati live.");
            } finally {
                setLoading(false);
            }
        };

        // Fetch Bookmakers list (all available from DB)
        useEffect(() => {
            fetch('/api/odds/bookmakers')
                .then(res => res.json())
                .then(data => setAllBookmakers(data.response || []))
                .catch(console.error);
        }, []);

        // Initial fetch and auto-refresh
        useEffect(() => {
            fetchLive();

            // Auto refresh every 60s
            const interval = setInterval(fetchLive, 60000);
            return () => clearInterval(interval);
        }, [selectedBookmaker, selectedLeague]);

        // Extract unique countries from current live events
        const availableCountries = useMemo(() => {
            const countries = new Map();
            liveEvents.forEach(evt => {
                if (evt.league && evt.league.country) {
                    countries.set(evt.league.country, evt.league.country);
                }
            });
            return Array.from(countries.values()).sort();
        }, [liveEvents]);

        // Extract unique leagues from current live events for filter
        const availableLeagues = useMemo(() => {
            const leagues = new Map();
            liveEvents.forEach(evt => {
                if (evt.league && evt.league.id) {
                    // Filter by country if selected
                    if (!selectedCountry || evt.league.country === selectedCountry) {
                        leagues.set(evt.league.id, { id: evt.league.id, name: evt.league.name, country: evt.league.country });
                    }
                }
            });
            return Array.from(leagues.values()).sort((a, b) => (a.name || '').localeCompare(b.name || ''));
        }, [liveEvents, selectedCountry]);

        // Client-side filtering
        const filteredEvents = useMemo(() => {
            return liveEvents.filter(evt => {
                const matchCountry = selectedCountry ? evt.league.country === selectedCountry : true;
                const matchLeague = selectedLeague ? evt.league.id === parseInt(selectedLeague) : true;
                return matchCountry && matchLeague;
            });
        }, [liveEvents, selectedCountry, selectedLeague]);

        return (
            <div className="flex flex-col h-full space-y-6">
                {/* Horizontal Filters Bar */}
                <div className="glass rounded-2xl border border-white/10 p-6">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* Stats Summary */}
                        <div className="glass bg-gradient-to-br from-accent/10 to-transparent rounded-xl p-4 border border-accent/20">
                            <div className="flex items-center gap-2 mb-3">
                                <i data-lucide="radio" className="w-5 h-5 text-accent"></i>
                                <h3 className="text-xs font-bold text-white uppercase tracking-wider">Live Now</h3>
                            </div>
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <span className="text-[10px] text-slate-400">Partite</span>
                                    <span className="text-lg font-black text-white">{filteredEvents.length}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[10px] text-slate-400">Bookmakers</span>
                                    <span className="text-sm font-bold text-accent">{availableBookmakers.length}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <span className="text-[10px] text-slate-400">Nazioni</span>
                                    <span className="text-sm font-bold text-accent">{availableCountries.length}</span>
                                </div>
                            </div>
                        </div>

                        {/* Bookmaker Filter */}
                        <div>
                            <label className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-2 block">
                                Bookmaker ({availableBookmakers.length} disponibili)
                            </label>
                            <select
                                className="w-full bg-black/30 text-white text-sm rounded-lg px-4 py-2.5 border border-white/10 focus:border-accent/50 focus:outline-none transition-all"
                                value={selectedBookmaker}
                                onChange={(e) => setSelectedBookmaker(e.target.value)}
                                disabled={availableBookmakers.length === 0}
                            >
                                <option value="">{availableBookmakers.length > 0 ? 'Tutti i Bookmaker' : 'Nessun bookmaker disponibile'}</option>
                                {availableBookmakers.map(bk => (
                                    <option key={bk.id} value={bk.id}>
                                        {bk.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {/* Country Filter */}
                        <div>
                            <label className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-2 block">Nazione ({availableCountries.length})</label>
                            <select
                                className="w-full bg-black/30 text-white text-sm rounded-lg px-4 py-2.5 border border-white/10 focus:border-accent/50 focus:outline-none transition-all"
                                value={selectedCountry}
                                onChange={(e) => {
                                    setSelectedCountry(e.target.value);
                                    setSelectedLeague(''); // Reset league when country changes
                                }}
                            >
                                <option value="">Tutte le Nazioni</option>
                                {availableCountries.map(country => (
                                    <option key={country} value={country}>{country}</option>
                                ))}
                            </select>
                        </div>

                        {/* League Filter */}
                        <div>
                            <label className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-2 block">Campionato ({availableLeagues.length})</label>
                            <select
                                className="w-full bg-black/30 text-white text-sm rounded-lg px-4 py-2.5 border border-white/10 focus:border-accent/50 focus:outline-none transition-all"
                                value={selectedLeague}
                                onChange={(e) => setSelectedLeague(e.target.value)}
                            >
                                <option value="">Tutti i Campionati</option>
                                {availableLeagues.map(lg => (
                                    <option key={lg.id} value={lg.id}>{lg.name}</option>
                                ))}
                            </select>
                        </div>
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
                            <i data-lucide="radio" className="w-16 h-16 mb-4 opacity-30"></i>
                            <p className="text-lg font-bold">Nessun evento live al momento</p>
                            <p className="text-sm text-slate-600 mt-2">Prova a modificare i filtri o attendi nuovi match</p>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-4">
                            {filteredEvents.map((evt) => (
                                <LiveEventCard key={evt.fixture.id} event={evt} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        );
    }

    function LiveEventCard({ event }) {
        const { fixture, league, teams, goals, score, odds } = event;

        // Determine scores from goals or score object
        const homeScore = goals?.home ?? score?.fulltime?.home ?? 0;
        const awayScore = goals?.away ?? score?.fulltime?.away ?? 0;

        // Extract odds if available (from merged data)
        const mainOdds = odds && odds.length > 0 && odds[0].bets && odds[0].bets.length > 0
            ? odds[0].bets[0].values
            : null;

        return (
            <div className="glass p-6 rounded-2xl border border-white/10 hover:border-accent/30 transition-all group cursor-pointer">
                {/* League Header */}
                <div className="flex items-center justify-between mb-6 pb-4 border-b border-white/5">
                    <div className="flex items-center gap-3">
                        {league.logo && <img src={league.logo} alt={league.name} className="w-8 h-8 object-contain opacity-80" />}
                        <div>
                            <p className="text-xs font-bold text-white">{league.name || 'N/A'}</p>
                            <p className="text-[10px] text-slate-500 uppercase tracking-wider">{league.country || 'N/A'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2 bg-danger/10 text-danger px-3 py-1.5 rounded-lg border border-danger/20">
                        <span className="animate-pulse w-2 h-2 rounded-full bg-danger"></span>
                        <span className="text-xs font-black uppercase tracking-wider">{fixture.status.short}</span>
                        <span className="text-slate-400 mx-1">•</span>
                        <span className="text-xs font-bold">{fixture.status.elapsed || 0}'</span>
                    </div>
                </div>

                {/* Teams & Score */}
                <div className="space-y-4 mb-6">
                    {/* Home Team */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4 flex-1">
                            {teams.home.logo && <img src={teams.home.logo} alt={teams.home.name} className="w-16 h-16 object-contain" />}
                            {!teams.home.logo && <div className="w-16 h-16 bg-white/5 rounded-lg flex items-center justify-center"><i data-lucide="shield" className="w-8 h-8 text-slate-600"></i></div>}
                            <span className="text-lg font-bold text-white">{teams.home.name || 'Home Team'}</span>
                        </div>
                        <div className="text-3xl font-black text-white font-mono w-12 text-center">
                            {homeScore}
                        </div>
                    </div>

                    {/* Away Team */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4 flex-1">
                            {teams.away.logo && <img src={teams.away.logo} alt={teams.away.name} className="w-16 h-16 object-contain" />}
                            {!teams.away.logo && <div className="w-16 h-16 bg-white/5 rounded-lg flex items-center justify-center"><i data-lucide="shield" className="w-8 h-8 text-slate-600"></i></div>}
                            <span className="text-lg font-bold text-white">{teams.away.name || 'Away Team'}</span>
                        </div>
                        <div className="text-3xl font-black text-white font-mono w-12 text-center">
                            {awayScore}
                        </div>
                    </div>
                </div>

                {/* Odds (1X2) - Only show if bookmaker selected */}
                {mainOdds && mainOdds.length > 0 && (
                    <div>
                        <p className="text-[10px] text-slate-500 uppercase font-bold tracking-widest mb-3">Quote Live - {odds[0].name}</p>
                        <div className="grid grid-cols-3 gap-3">
                            {mainOdds.map((odd, idx) => (
                                <div key={idx} className="bg-black/40 hover:bg-accent/10 p-3 rounded-xl border border-white/10 hover:border-accent/30 transition-all text-center cursor-pointer group/odd">
                                    <span className="block text-[10px] text-slate-400 font-bold mb-1 uppercase tracking-wider">{odd.value}</span>
                                    <span className="block text-xl font-black text-accent group-hover/odd:text-white transition-colors">{odd.odd}</span>
                                </div>
                            ))}
                        </div>
                    </div>
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