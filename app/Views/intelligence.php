<?php
// app/Views/intelligence.php
$pageTitle = 'Scommetto.AI - Live Intelligence';
require __DIR__ . '/layout/top.php';
?>

<div id="root"></div>

<script type="text/babel">
    const { useState, useEffect, useMemo, useRef } = React;

    /**
     * Componente helper per le icone Lucide.
     * Usa dangerouslySetInnerHTML per evitare conflitti tra React e la manipolazione DOM di Lucide.
     */
    function LucideIcon({ name, className = "w-4 h-4" }) {
        return (
            <span className="inline-flex" dangerouslySetInnerHTML={{
                __html: `<i data-lucide="${name}" class="${className}"></i>`
            }} />
        );
    }

    function IntelligenceDashboard() {
        // State
        const [liveEvents, setLiveEvents] = useState([]);
        const [loading, setLoading] = useState(true);
        const [error, setError] = useState(null);

        // Initial filters from URL
        const getInitialFilter = (key) => new URLSearchParams(window.location.search).get(key) || '';

        // Filters State
        const [availableBookmakers, setAvailableBookmakers] = useState([]); // Bookmakers with live odds
        const [selectedBookmaker, setSelectedBookmaker] = useState(getInitialFilter('bookmaker'));
        const [selectedCountry, setSelectedCountry] = useState(getInitialFilter('country'));
        const [selectedLeague, setSelectedLeague] = useState(getInitialFilter('league'));

        // Modal State
        const [selectedMatch, setSelectedMatch] = useState(null);
        const [matchDetails, setMatchDetails] = useState(null);
        const [loadingDetails, setLoadingDetails] = useState(false);

        // AI Prediction Modal State
        const [predictionMatch, setPredictionMatch] = useState(null);
        const [predictionData, setPredictionData] = useState(null);
        const [loadingPrediction, setLoadingPrediction] = useState(false);

        // Fetch Live Data
        const fetchLive = async () => {
            setLoading(true);
            setError(null);
            try {
                // 1. Fetch live fixtures (always needed for scores, status, etc.)
                let fixturesUrl = '/api/intelligence/live';
                const fixturesParams = new URLSearchParams();
                if (selectedLeague) fixturesParams.append('league', selectedLeague);

                const fixturesRes = await fetch(`${fixturesUrl}?${fixturesParams.toString()}`);
                const fixturesData = await fixturesRes.json();

                if (fixturesData.error) {
                    setError(typeof fixturesData.error === 'object' ? JSON.stringify(fixturesData.error) : fixturesData.error);
                    setLiveEvents([]);
                    return;
                }

                let fixtures = fixturesData.response || [];

                // 2. Fetch active bookmakers for live matches
                let bookmakers = [];
                try {
                    const activeBkRes = await fetch('/api/odds/active-bookmakers');
                    const activeBkData = await activeBkRes.json();
                    bookmakers = activeBkData.response || [];
                    setAvailableBookmakers(bookmakers);
                } catch (err) {
                    console.error("Error fetching active bookmakers:", err);
                }

                // 3. If a bookmaker is selected, filter events and attach live odds
                if (selectedBookmaker && fixtures.length > 0) {
                    try {
                        // Fetch all live odds for the selected bookmaker in one call
                        const oddsParams = new URLSearchParams();
                        oddsParams.append('bookmaker', selectedBookmaker);
                        if (selectedLeague) oddsParams.append('league', selectedLeague);

                        const oddsRes = await fetch(`/api/odds/live?${oddsParams.toString()}`);
                        const oddsData = await oddsRes.json();

                        if (oddsData.error) {
                            setError(`Errore Bookmaker: ${typeof oddsData.error === 'object' ? JSON.stringify(oddsData.error) : oddsData.error}`);
                        }

                        const oddsMap = new Map();
                        (oddsData.response || []).forEach(item => {
                            // Support both item.odds (flat) and item.bookmakers[0].bets (nested)
                            const bets = item.odds || (item.bookmakers && item.bookmakers.length > 0 ? item.bookmakers[0].bets : null);
                            const fid = item.fixture?.id || item.fixture; // Handle object or direct ID

                            if (bets && fid) {
                                oddsMap.set(Number(fid), bets);
                            }
                        });

                        // Filter: only show matches that have live odds for the selected broker
                        // AND attach the odds in the format expected by LiveEventCard
                        fixtures = fixtures.filter(f => oddsMap.has(Number(f.fixture.id)))
                            .map(f => {
                                const bets = oddsMap.get(Number(f.fixture.id));
                                return {
                                    ...f,
                                    odds: [{
                                        name: bookmakers.find(b => b.id == selectedBookmaker)?.name || 'Bookmaker',
                                        bets: bets
                                    }]
                                };
                            });
                    } catch (err) {
                        console.error("Error fetching live odds for bookmaker:", err);
                        setError("Errore nel recupero delle quote live per il bookmaker selezionato.");
                    }
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

        // Open AI Prediction modal
        const openPrediction = async (match) => {
            setPredictionMatch(match);
            setPredictionData(null);
            setLoadingPrediction(true);
            
            try {
                const res = await fetch(`/api/fixture-predictions?fixture=${match.fixture.id}`);
                const data = await res.json();
                setPredictionData(data);
            } catch (err) {
                console.error("Error fetching prediction:", err);
                setPredictionData({ error: "Impossibile caricare la predizione" });
            } finally {
                setLoadingPrediction(false);
            }
        };

        // Close AI Prediction modal
        const closePrediction = () => {
            setPredictionMatch(null);
            setPredictionData(null);
        };

        // Update URL parameters when filters change
        useEffect(() => {
            const params = new URLSearchParams(window.location.search);
            if (selectedBookmaker) params.set('bookmaker', selectedBookmaker); else params.delete('bookmaker');
            if (selectedCountry) params.set('country', selectedCountry); else params.delete('country');
            if (selectedLeague) params.set('league', selectedLeague); else params.delete('league');

            const newRelativePathQuery = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.replaceState(null, '', newRelativePathQuery);
        }, [selectedBookmaker, selectedCountry, selectedLeague]);

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
                const matchLeague = selectedLeague ? (evt.league && evt.league.id === parseInt(selectedLeague)) : true;
                return matchCountry && matchLeague;
            });
        }, [liveEvents, selectedCountry, selectedLeague]);

        // Refresh icons whenever events or loading state changes
        useEffect(() => {
            if (window.lucide) {
                lucide.createIcons();
            }
        }, [filteredEvents, loading]);

        return (
            <div className="flex flex-col h-full space-y-6">
                {/* Horizontal Filters Bar */}
                <div className="glass rounded-2xl border border-white/10 p-6">
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* Stats Summary */}
                        <div className="glass bg-gradient-to-br from-accent/10 to-transparent rounded-xl p-4 border border-accent/20">
                            <div className="flex items-center gap-2 mb-3">
                                <LucideIcon name="radio" className="w-5 h-5 text-accent" />
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
                    {error && (
                        <div className="bg-danger/10 border border-danger/20 rounded-xl p-4 mb-6 flex items-center gap-3 text-danger">
                            <LucideIcon name="alert-triangle" className="w-5 h-5" />
                            <p className="text-sm font-bold">{error}</p>
                        </div>
                    )}

                    {loading && filteredEvents.length === 0 ? (
                        <div className="flex justify-center items-center h-64">
                            <div className="w-8 h-8 border-4 border-accent/20 border-t-accent rounded-full animate-spin"></div>
                        </div>
                    ) : filteredEvents.length === 0 ? (
                        <div className="flex flex-col items-center justify-center h-64 text-slate-500 text-center px-4">
                            <LucideIcon name="radio" className="w-16 h-16 mb-4 opacity-30" />
                            <p className="text-lg font-bold">
                                {selectedBookmaker ? `Nessun evento disponibile per il bookmaker selezionato` : `Nessun evento live al momento`}
                            </p>
                            <p className="text-sm text-slate-600 mt-2">
                                {selectedBookmaker ? `Prova a cambiare bookmaker o attendi nuovi match con quote live` : `Prova a modificare i filtri o attendi nuovi match`}
                            </p>
                            {selectedBookmaker && (
                                <button
                                    onClick={() => setSelectedBookmaker('')}
                                    className="mt-6 px-4 py-2 bg-white/5 hover:bg-white/10 rounded-xl text-xs font-bold transition-all border border-white/10 text-white"
                                >
                                    Rimuovi filtro bookmaker
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-4">
                            {filteredEvents.map((evt) => (
                                <LiveEventCard key={evt.fixture.id} event={evt} onClick={() => openMatchDetail(evt)} onPredictionClick={openPrediction} />
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

                {/* AI Prediction Modal */}
                {predictionMatch && (
                    <PredictionModal 
                        match={predictionMatch}
                        data={predictionData}
                        loading={loadingPrediction}
                        onClose={closePrediction}
                    />
                )}
            </div>
        );
    }

    function LiveEventCard({ event, onClick, onPredictionClick }) {
        const { fixture, league, teams, goals, score, odds } = event;

        // Determine scores from goals or score object
        const homeScore = goals?.home ?? score?.fulltime?.home ?? 0;
        const awayScore = goals?.away ?? score?.fulltime?.away ?? 0;

        // Extract odds if available (from merged data)
        // We look for "Match Winner" (ID 1) or just the first bet available
        const getMainOdds = () => {
            if (!odds || odds.length === 0 || !odds[0].bets) return null;

            // Try to find Match Winner (ID 1)
            const matchWinner = odds[0].bets.find(b => b.id === 1 || b.name === 'Match Winner');
            if (matchWinner && matchWinner.values) return matchWinner.values;

            // Fallback to first available bet if it has values
            if (odds[0].bets.length > 0 && odds[0].bets[0].values) {
                return odds[0].bets[0].values;
            }

            return null;
        };

        const mainOdds = getMainOdds();

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
                            {!teams.home.logo && <div className="w-16 h-16 bg-white/5 rounded-lg flex items-center justify-center"><LucideIcon name="shield" className="w-8 h-8 text-slate-600" /></div>}
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
                            {!teams.away.logo && <div className="w-16 h-16 bg-white/5 rounded-lg flex items-center justify-center"><LucideIcon name="shield" className="w-8 h-8 text-slate-600" /></div>}
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

                {/* AI Prediction Button */}
                <div className="mt-4 pt-4 border-t border-white/10">
                    <button
                        onClick={(e) => {
                            e.stopPropagation();
                            onPredictionClick(event);
                        }}
                        className="w-full bg-gradient-to-r from-purple-500/20 to-pink-500/20 hover:from-purple-500/30 hover:to-pink-500/30 text-white px-4 py-3 rounded-xl border border-purple-500/30 hover:border-purple-500/50 transition-all flex items-center justify-center gap-2 group"
                    >
                        <LucideIcon name="brain" className="w-5 h-5 text-purple-400 group-hover:text-purple-300" />
                        <span className="font-bold text-sm">AI Prediction</span>
                        <LucideIcon name="sparkles" className="w-4 h-4 text-purple-400 group-hover:text-purple-300" />
                    </button>
                </div>
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
                                <LucideIcon name="x" className="w-5 h-5 text-white" />
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
                                <span className="flex items-center gap-1"><LucideIcon name="map-pin" className="w-3 h-3 text-slate-500" />{fixture.venue.name || 'N/A'}</span>
                                <span>•</span>
                                <span className="flex items-center gap-1"><LucideIcon name="map" className="w-3 h-3 text-slate-500" />{fixture.venue.city || 'N/A'}</span>
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
                                <LucideIcon name={tab.icon} className="w-4 h-4" />
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

    // AI Prediction Modal Component
    function PredictionModal({ match, data, loading, onClose }) {
        const { fixture, league, teams } = match;

        React.useEffect(() => {
            if (window.lucide) {
                setTimeout(() => lucide.createIcons(), 100);
            }
        }, [data]);

        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" onClick={onClose}>
                <div className="glass w-full max-w-4xl max-h-[90vh] overflow-hidden rounded-3xl border border-purple-500/30" onClick={(e) => e.stopPropagation()}>
                    {/* Header */}
                    <div className="bg-gradient-to-r from-purple-500/20 to-pink-500/20 p-6 border-b border-purple-500/30">
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center gap-3">
                                <LucideIcon name="brain" className="w-10 h-10 text-purple-400" />
                                <div>
                                    <h2 className="text-xl font-black text-white">AI Prediction</h2>
                                    <p className="text-xs text-slate-400">{teams.home.name} vs {teams.away.name}</p>
                                </div>
                            </div>
                            <button onClick={onClose} className="w-10 h-10 rounded-full bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all">
                                <LucideIcon name="x" className="w-5 h-5 text-white" />
                            </button>
                        </div>
                    </div>

                    {/* Content */}
                    <div className="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
                        {loading ? (
                            <div className="flex justify-center items-center h-64">
                                <div className="w-12 h-12 border-4 border-purple-500/20 border-t-purple-500 rounded-full animate-spin"></div>
                            </div>
                        ) : data && data.error ? (
                            <div className="text-center text-red-400 py-12">
                                <LucideIcon name="alert-circle" className="w-16 h-16 mx-auto mb-4 opacity-50" />
                                <p>{data.error}</p>
                            </div>
                        ) : data && data.response ? (
                            <div className="space-y-6">
                                {/* Prediction Advice */}
                                {data.response.advice && (
                                    <div className="glass p-6 rounded-xl border border-purple-500/20">
                                        <h3 className="text-lg font-bold text-purple-400 mb-3 flex items-center gap-2">
                                            <LucideIcon name="lightbulb" className="w-5 h-5" />
                                            Consiglio AI
                                        </h3>
                                        <p className="text-white leading-relaxed">{data.response.advice}</p>
                                    </div>
                                )}

                                {/* Comparison Stats */}
                                {data.response.comparison && (
                                    <div className="glass p-6 rounded-xl border border-purple-500/20">
                                        <h3 className="text-lg font-bold text-purple-400 mb-4 flex items-center gap-2">
                                            <LucideIcon name="bar-chart-2" className="w-5 h-5" />
                                            Confronto Squadre
                                        </h3>
                                        <div className="space-y-3">
                                            {Object.entries(data.response.comparison).map(([key, value]) => (
                                                <div key={key} className="flex justify-between items-center">
                                                    <span className="text-sm text-slate-400 capitalize">{key.replace(/_/g, ' ')}</span>
                                                    <span className="text-sm font-bold text-white">{value}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Win Percentages */}
                                {data.response.percent && (
                                    <div className="glass p-6 rounded-xl border border-purple-500/20">
                                        <h3 className="text-lg font-bold text-purple-400 mb-4 flex items-center gap-2">
                                            <LucideIcon name="percent" className="w-5 h-5" />
                                            Probabilità
                                        </h3>
                                        <div className="grid grid-cols-3 gap-4">
                                            {Object.entries(data.response.percent).map(([key, value]) => (
                                                <div key={key} className="text-center">
                                                    <div className="text-3xl font-black text-purple-400 mb-1">{value}</div>
                                                    <div className="text-xs text-slate-400 uppercase tracking-wider">{key}</div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center text-slate-500 py-12">
                                <LucideIcon name="info" className="w-16 h-16 mx-auto mb-4 opacity-30" />
                                <p>Nessuna predizione disponibile</p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
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