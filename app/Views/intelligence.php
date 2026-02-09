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

        // Modal State
        const [selectedMatch, setSelectedMatch] = useState(null);
        const [matchDetails, setMatchDetails] = useState(null);
        const [loadingDetails, setLoadingDetails] = useState(false);

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

        // Fetch comprehensive match details
        const fetchMatchDetails = async (fixtureId) => {
            setLoadingDetails(true);
            try {
                // Fetch multiple endpoints in parallel
                const [statsRes, eventsRes, lineupsRes, h2hRes] = await Promise.all([
                    fetch(`/api/fixtures/${fixtureId}/statistics`),
                    fetch(`/api/fixtures/${fixtureId}/events`),
                    fetch(`/api/fixtures/${fixtureId}/lineups`),
                    fetch(`/api/fixtures/${fixtureId}/h2h?last=5`)
                ]);

                const [stats, events, lineups, h2h] = await Promise.all([
                    statsRes.json(),
                    eventsRes.json(),
                    lineupsRes.json(),
                    h2hRes.json()
                ]);

                setMatchDetails({
                    statistics: stats.response || [],
                    events: events.response || [],
                    lineups: lineups.response || [],
                    h2h: h2h.response || []
                });
            } catch (err) {
                console.error("Error fetching match details:", err);
            } finally {
                setLoadingDetails(false);
            }
        };

        // Open match detail modal
        const openMatchDetail = (match) => {
            setSelectedMatch(match);
            setMatchDetails(null);
            fetchMatchDetails(match.fixture.id);
        };

        // Close match detail modal
        const closeMatchDetail = () => {
            setSelectedMatch(null);
            setMatchDetails(null);
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
                                <LiveEventCard key={evt.fixture.id} event={evt} onClick={() => openMatchDetail(evt)} />
                            ))}
                        </div>
                    )}
                </div>

                {/* Match Detail Modal */}
                {selectedMatch && (
                    <MatchDetailModal
                        match={selectedMatch}
                        details={matchDetails}
                        loading={loadingDetails}
                        onClose={closeMatchDetail}
                    />
                )}
            </div>
        );
    }

    function LiveEventCard({ event, onClick }) {
        const { fixture, league, teams, goals, score, odds } = event;

        // Determine scores from goals or score object
        const homeScore = goals?.home ?? score?.fulltime?.home ?? 0;
        const awayScore = goals?.away ?? score?.fulltime?.away ?? 0;

        // Extract odds if available (from merged data)
        const mainOdds = odds && odds.length > 0 && odds[0].bets && odds[0].bets.length > 0
            ? odds[0].bets[0].values
            : null;

        return (
            <div onClick={onClick} className="glass p-6 rounded-2xl border border-white/10 hover:border-accent/30 transition-all group cursor-pointer hover:scale-[1.02]">
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

    // Match Detail Modal Component
    function MatchDetailModal({ match, details, loading, onClose }) {
        const [activeTab, setActiveTab] = React.useState('stats');
        const { fixture, league, teams, goals, score } = match;

        const homeScore = goals?.home ?? score?.fulltime?.home ?? 0;
        const awayScore = goals?.away ?? score?.fulltime?.away ?? 0;

        // Refresh icons when modal opens or tab changes
        React.useEffect(() => {
            if (window.lucide) {
                setTimeout(() => lucide.createIcons(), 100);
            }
        }, [activeTab]);

        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" onClick={onClose}>
                <div className="glass w-full max-w-6xl max-h-[90vh] overflow-hidden rounded-3xl border border-white/10" onClick={(e) => e.stopPropagation()}>
                    {/* Header */}
                    <div className="bg-gradient-to-r from-accent/20 to-transparent p-6 border-b border-white/10">
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center gap-3">
                                {league.logo && <img src={league.logo} alt={league.name} className="w-10 h-10 object-contain" />}
                                <div>
                                    <h2 className="text-xl font-black text-white">{league.name}</h2>
                                    <p className="text-xs text-slate-400">{league.country} • Round {fixture.league?.round || 'N/A'}</p>
                                </div>
                            </div>
                            <button onClick={onClose} className="w-10 h-10 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all">
                                <i data-lucide="x" className="w-5 h-5 text-white"></i>
                            </button>
                        </div>

                        {/* Score Display */}
                        <div className="grid grid-cols-3 gap-4 items-center">
                            <div className="flex items-center gap-3 justify-end">
                                {teams.home.logo && <img src={teams.home.logo} alt={teams.home.name} className="w-16 h-16 object-contain" />}
                                <span className="text-lg font-bold text-white">{teams.home.name}</span>
                            </div>
                            <div className="text-center">
                                <div className="text-5xl font-black text-white font-mono">
                                    {homeScore} - {awayScore}
                                </div>
                                <div className="mt-2 inline-flex items-center gap-2 bg-danger/10 text-danger px-4 py-2 rounded-lg border border-danger/20">
                                    <span className="animate-pulse w-2 h-2 rounded-full bg-danger"></span>
                                    <span className="text-sm font-bold">{fixture.status.short} {fixture.status.elapsed}'</span>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-lg font-bold text-white">{teams.away.name}</span>
                                {teams.away.logo && <img src={teams.away.logo} alt={teams.away.name} className="w-16 h-16 object-contain" />}
                            </div>
                        </div>

                        {/* Venue Info */}
                        {fixture.venue && (
                            <div className="mt-4 flex items-center justify-center gap-4 text-xs text-slate-400">
                                <span><i data-lucide="map-pin" className="w-3 h-3 inline mr-1"></i>{fixture.venue.name || 'N/A'}</span>
                                <span>•</span>
                                <span><i data-lucide="map" className="w-3 h-3 inline mr-1"></i>{fixture.venue.city || 'N/A'}</span>
                            </div>
                        )}
                    </div>

                    {/* Tabs */}
                    <div className="flex gap-2 p-4 border-b border-white/10 overflow-x-auto">
                        {[
                            { id: 'stats', label: 'Statistiche', icon: 'bar-chart-2' },
                            { id: 'events', label: 'Eventi', icon: 'activity' },
                            { id: 'lineups', label: 'Formazioni', icon: 'users' },
                            { id: 'h2h', label: 'Testa a Testa', icon: 'git-compare' }
                        ].map(tab => (
                            <button
                                key={tab.id}
                                onClick={() => setActiveTab(tab.id)}
                                className={`px-4 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2 whitespace-nowrap ${activeTab === tab.id
                                        ? 'bg-accent text-black'
                                        : 'bg-white/5 text-slate-400 hover:bg-white/10'
                                    }`}
                            >
                                <i data-lucide={tab.icon} className="w-4 h-4"></i>
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    {/* Content */}
                    <div className="p-6 overflow-y-auto max-h-[calc(90vh-400px)]">
                        {loading ? (
                            <div className="flex justify-center items-center h-64">
                                <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                            </div>
                        ) : (
                            <>
                                {activeTab === 'stats' && <StatsTab stats={details?.statistics} teams={teams} />}
                                {activeTab === 'events' && <EventsTab events={details?.events} teams={teams} />}
                                {activeTab === 'lineups' && <LineupsTab lineups={details?.lineups} />}
                                {activeTab === 'h2h' && <H2HTab h2h={details?.h2h} teams={teams} />}
                            </>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    // Stats Tab Component
    function StatsTab({ stats, teams }) {
        if (!stats || stats.length === 0) {
            return <div className="text-center text-slate-500 py-12">Nessuna statistica disponibile</div>;
        }

        const homeStats = stats[0]?.statistics || [];
        const awayStats = stats[1]?.statistics || [];

        return (
            <div className="space-y-4">
                {homeStats.map((stat, idx) => {
                    const awayStat = awayStats[idx];
                    const homeValue = parseInt(stat.value) || 0;
                    const awayValue = parseInt(awayStat?.value) || 0;
                    const total = homeValue + awayValue || 1;
                    const homePercent = (homeValue / total) * 100;
                    const awayPercent = (awayValue / total) * 100;

                    return (
                        <div key={idx} className="glass p-4 rounded-xl">
                            <div className="flex justify-between items-center mb-2">
                                <span className="text-sm font-bold text-white">{stat.value || 0}</span>
                                <span className="text-xs text-slate-400 uppercase tracking-wider">{stat.type}</span>
                                <span className="text-sm font-bold text-white">{awayStat?.value || 0}</span>
                            </div>
                            <div className="flex h-2 rounded-full overflow-hidden bg-white/5">
                                <div className="bg-accent" style={{ width: `${homePercent}%` }}></div>
                                <div className="bg-danger" style={{ width: `${awayPercent}%` }}></div>
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }

    // Events Tab Component
    function EventsTab({ events, teams }) {
        if (!events || events.length === 0) {
            return <div className="text-center text-slate-500 py-12">Nessun evento disponibile</div>;
        }

        const getEventIcon = (type) => {
            switch (type) {
                case 'Goal': return '⚽';
                case 'Card': return '🟨';
                case 'subst': return '🔄';
                default: return '•';
            }
        };

        return (
            <div className="space-y-3">
                {events.map((event, idx) => (
                    <div key={idx} className={`glass p-4 rounded-xl flex items-center gap-4 ${event.team.id === teams.home.id ? 'flex-row' : 'flex-row-reverse'}`}>
                        <div className="text-2xl">{getEventIcon(event.type)}</div>
                        <div className={event.team.id === teams.home.id ? 'text-left' : 'text-right'}>
                            <p className="text-sm font-bold text-white">{event.player.name}</p>
                            <p className="text-xs text-slate-400">{event.detail} {event.comments ? `(${event.comments})` : ''}</p>
                        </div>
                        <div className="ml-auto bg-accent/20 text-accent px-3 py-1 rounded-lg text-sm font-bold">
                            {event.time.elapsed}'
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    // Lineups Tab Component
    function LineupsTab({ lineups }) {
        if (!lineups || lineups.length === 0) {
            return <div className="text-center text-slate-500 py-12">Nessuna formazione disponibile</div>;
        }

        return (
            <div className="grid grid-cols-2 gap-6">
                {lineups.map((lineup, idx) => {
                    // Safety checks
                    if (!lineup || !lineup.team) return null;

                    return (
                        <div key={idx} className="glass p-4 rounded-xl">
                            <div className="flex items-center gap-3 mb-4 pb-3 border-b border-white/10">
                                {lineup.team.logo && <img src={lineup.team.logo} alt={lineup.team.name} className="w-8 h-8 object-contain" />}
                                <div>
                                    <p className="text-sm font-bold text-white">{lineup.team.name || 'N/A'}</p>
                                    <p className="text-xs text-slate-400">{lineup.formation || 'N/A'}</p>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <p className="text-xs font-bold text-accent uppercase tracking-wider mb-2">Starting XI</p>
                                {lineup.startXI && lineup.startXI.length > 0 ? (
                                    lineup.startXI.map((player, pidx) => (
                                        <div key={pidx} className="flex items-center gap-2 text-sm">
                                            <span className="w-6 h-6 rounded-full bg-accent/20 text-accent flex items-center justify-center text-xs font-bold">
                                                {player.player?.number || '?'}
                                            </span>
                                            <span className="text-white">{player.player?.name || 'Unknown'}</span>
                                            <span className="text-xs text-slate-500 ml-auto">{player.player?.pos || ''}</span>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-xs text-slate-500">Nessun giocatore disponibile</p>
                                )}
                            </div>
                        </div>
                    );
                })}
            </div>
        );
    }
            </div >
        );
    }

    // H2H Tab Component
    function H2HTab({ h2h, teams }) {
        if (!h2h || h2h.length === 0) {
            return <div className="text-center text-slate-500 py-12">Nessuno storico disponibile</div>;
        }

        return (
            <div className="space-y-3">
                {h2h.map((match, idx) => (
                    <div key={idx} className="glass p-4 rounded-xl">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-xs text-slate-400">{new Date(match.fixture.date).toLocaleDateString()}</span>
                            <span className="text-xs text-slate-400">{match.league.name}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                {match.teams.home.logo && <img src={match.teams.home.logo} alt={match.teams.home.name} className="w-6 h-6 object-contain" />}
                                <span className="text-sm font-bold text-white">{match.teams.home.name}</span>
                            </div>
                            <div className="text-lg font-black text-white font-mono">
                                {match.goals.home} - {match.goals.away}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-sm font-bold text-white">{match.teams.away.name}</span>
                                {match.teams.away.logo && <img src={match.teams.away.logo} alt={match.teams.away.name} className="w-6 h-6 object-contain" />}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<IntelligenceDashboard />);

    // Icons
    if (window.lucide) lucide.createIcons();
</script>
<?php require __DIR__ . '/layout/bottom.php'; ?>