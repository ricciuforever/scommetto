<?php
// app/Views/betfair/live_markets.php
$pageTitle = 'GiaNik Live Intelligence';
require __DIR__ . '/../layout/top.php';
?>

<div id="betfair-markets-root"></div>

<script type="text/babel">
    const { useState, useEffect, useMemo } = React;

    function LucideIcon({ name, className = "w-4 h-4" }) {
        return (
            <span className="inline-flex" dangerouslySetInnerHTML={{
                __html: `<i data-lucide="${name}" class="${className}"></i>`
            }} />
        );
    }

    function BetfairLiveMarket() {
        const [activeSport, setActiveSport] = useState('Soccer');
        const [sportsData, setSportsData] = useState({});
        const [loading, setLoading] = useState(false);
        const [analyzing, setAnalyzing] = useState(null); // Market ID being analyzed
        const [geminiResult, setGeminiResult] = useState(null);

        // Fetch Initial Sports Structure
        const fetchSports = async () => {
            setLoading(true);
            try {
                // Mock endpoint or real one
                const res = await fetch('/api/betfair-live/events');
                const data = await res.json();
                if (data.response) {
                    setSportsData(data.response);
                    if (Object.keys(data.response).length > 0 && !activeSport) {
                        setActiveSport(Object.keys(data.response)[0]);
                    }
                }
            } catch (err) {
                console.error("Error fetching sports:", err);
            } finally {
                setLoading(false);
            }
        };

        useEffect(() => {
            fetchSports();
            // Refresh loop every 30s
            // const interval = setInterval(fetchSports, 30000);
            // return () => clearInterval(interval);
        }, []);

        const events = useMemo(() => {
            return sportsData[activeSport] || [];
        }, [activeSport, sportsData]);

        const analyzeMarket = async (event, marketName = 'Match Odds') => {
            setAnalyzing(event.event.id);
            setGeminiResult(null);

            try {
                // 1. Get Market ID for this event (Client-side logic or new endpoint)
                // For simplicity, let's assume we fetch Catalogue first
                const catRes = await fetch(`/api/betfair-live/catalogue/${event.event.id}`);
                const catData = await catRes.json();

                const matchOddsMarket = (catData.response || []).find(m => m.marketName === marketName || m.marketName === 'Esito Finale');

                if (!matchOddsMarket) {
                    alert("Mercato non trovato per analisi.");
                    setAnalyzing(null);
                    return;
                }

                // 2. Send Context to Gemini
                const context = {
                    marketId: matchOddsMarket.marketId,
                    event: {
                        event: event.event.name,
                        competition: event.competition?.name || activeSport,
                        market: matchOddsMarket.marketName
                    },
                    runnersMap: matchOddsMarket.runners.reduce((acc, r) => ({ ...acc, [r.selectionId]: r.runnerName }), {})
                };

                const res = await fetch(`/api/betfair-live/analyze`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(context)
                });

                const analysisData = await res.json();
                setGeminiResult(analysisData);

            } catch (e) {
                console.error("Analysis Error:", e);
                alert("Errore Analisi: " + e.message);
            } finally {
                setAnalyzing(null);
            }
        };


        const [account, setAccount] = useState(null);

        // Fetch User Account (Balance + Orders)
        const fetchAccount = async () => {
            try {
                const res = await fetch('/api/betfair-live/account');
                const data = await res.json();
                if (data.balance) {
                    setAccount(data);
                }
            } catch (err) {
                console.error("Error fetching account:", err);
            }
        };

        useEffect(() => {
            fetchSports();
            fetchAccount();
            const interval = setInterval(() => {
                // fetchSports(); // Heavy, maybe less frequent
                fetchAccount(); // Important for balance updates
            }, 30000);
            return () => clearInterval(interval);
        }, []);


        // Icon Refresh
        useEffect(() => {
            if (window.lucide) lucide.createIcons();
        }, [activeSport, events, geminiResult]);

        return (
            <div className="flex flex-col h-full space-y-6">

                {/* Header / Title + Wallet Info */}
                <div className="bg-warning/10 p-6 rounded-2xl border border-warning/20 flex flex-col md:flex-row gap-6 justify-between items-start md:items-center">
                    <div className="flex items-center gap-4">
                        <div className="w-12 h-12 bg-warning rounded-xl flex items-center justify-center shadow-lg shadow-warning/20">
                            <LucideIcon name="zap" className="text-black w-7 h-7" />
                        </div>
                        <div>
                            <h1 className="text-3xl font-black text-white italic uppercase tracking-tighter">
                                GiaNik <span className="text-warning">Live Mercato</span>
                            </h1>
                            <div className="flex items-center gap-3 mt-1">
                                <span className="text-xs text-slate-400 font-bold uppercase tracking-widest">
                                    Sport Globali • Gemini AI
                                </span>
                                {account && (
                                    <div className="flex items-center gap-2 bg-black/40 px-3 py-1 rounded-lg border border-white/5">
                                        <LucideIcon name="wallet" className="text-accent w-3 h-3" />
                                        <span className="text-xs font-mono font-bold text-white">
                                            €{account.balance.availableToBetBalance?.toFixed(2)}
                                        </span>
                                        <span className="text-[10px] text-danger font-mono ml-2">
                                            (Exp: -€{Math.abs(account.balance.exposure || 0).toFixed(2)})
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center gap-3 w-full md:w-auto">
                        {/* Open Bets Preview */}
                        {account?.orders?.length > 0 && (
                            <div className="hidden lg:flex items-center gap-2 mr-4 bg-white/5 px-4 py-2 rounded-xl text-xs font-bold text-slate-300">
                                <LucideIcon name="ticket" className="text-warning w-4 h-4" />
                                <span>{account.orders.length} Ordini Aperti</span>
                            </div>
                        )}
                        <button onClick={() => { fetchSports(); fetchAccount(); }} className="ml-auto md:ml-0 w-10 h-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all">
                            <LucideIcon name="refresh-cw" className={`w-5 h-5 text-slate-400 ${loading ? 'animate-spin' : ''}`} />
                        </button>
                    </div>
                </div>

                {/* Sports Tabs */}
                <div className="flex gap-2 overflow-x-auto pb-2 no-scrollbar">
                    {Object.keys(sportsData).map(sport => (
                        <button
                            key={sport}
                            onClick={() => setActiveSport(sport)}
                            className={`px-6 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border whitespace-nowrap ${activeSport === sport
                                    ? 'bg-accent text-black border-accent shadow-lg shadow-accent/20'
                                    : 'bg-white/5 text-slate-400 hover:text-white border-white/5 hover:bg-white/10'
                                }`}
                        >
                            {sport} <span className="ml-1 opacity-50 text-[10px]">({sportsData[sport].length})</span>
                        </button>
                    ))}
                </div>

                {/* Gemini Result Panel (if active) */}
                {geminiResult && (
                    <div className="glass border border-accent/20 p-6 rounded-2xl bg-gradient-to-br from-accent/5 to-transparent relative overflow-hidden">
                        <div className="absolute top-0 right-0 p-4 opacity-10">
                            <LucideIcon name="brain" className="w-32 h-32" />
                        </div>
                        <h3 className="text-lg font-black text-white mb-4 flex items-center gap-2">
                            <LucideIcon name="sparkles" className="text-accent" />
                            Risultati Analisi Gemini
                        </h3>

                        {(() => {
                            // Try to parse JSON from Markdown
                            let betData = null;
                            try {
                                const text = typeof geminiResult.analysis === 'string' ? geminiResult.analysis : JSON.stringify(geminiResult.analysis);
                                const jsonMatch = text.match(/```json\s*([\s\S]*?)\s*```/);
                                if (jsonMatch && jsonMatch[1]) {
                                    betData = JSON.parse(jsonMatch[1]);
                                } else if (text.trim().startsWith('{')) {
                                    betData = JSON.parse(text);
                                }
                            } catch (e) { console.log("Errore Analisi JSON", e); }

                            const placeBet = async () => {
                                if (!betData) return;
                                if (!confirm(`Piazzare scommessa su ${betData.advice} @ ${betData.odds}?`)) return;

                                try {
                                    const res = await fetch('/api/place_bet', {
                                        method: 'POST',
                                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                        body: new URLSearchParams({
                                            fixture_id: betData.marketId,
                                            match_name: geminiResult.market_book?.runners?.length ? "Evento Live" : "Sconosciuto", // Fallback
                                            advice: betData.advice,
                                            market: 'Match Odds', // Assumed for now
                                            odd: betData.odds,
                                            stake: betData.stake || 2,
                                            confidence: betData.confidence,
                                            reason: betData.motivation
                                        })
                                    });
                                    const data = await res.json();
                                    alert(data.status === 'success' ? 'Scommessa Piazzata!' : 'Errore: ' + JSON.stringify(data));
                                } catch (e) {
                                    alert("Errore di rete: " + e.message);
                                }
                            };

                            return (
                                <div>
                                    {betData ? (
                                        <div className="bg-white/5 p-4 rounded-xl border border-accent/20 mb-4">
                                            <div className="flex justify-between items-start">
                                                <div>
                                                    <span className="text-xs font-bold text-slate-400 uppercase">Suggerimento</span>
                                                    <div className="text-xl font-black text-accent">{betData.advice}</div>
                                                </div>
                                                <div className="text-right">
                                                    <span className="text-xs font-bold text-slate-400 uppercase">Quota</span>
                                                    <div className="text-xl font-black text-white">{betData.odds}</div>
                                                </div>
                                            </div>
                                            <div className="mt-2 text-sm text-slate-300 italic">"{betData.motivation}"</div>
                                            <div className="mt-4 flex gap-2">
                                                <button
                                                    onClick={placeBet}
                                                    className="bg-accent text-black px-4 py-2 rounded-lg text-sm font-bold uppercase tracking-wider hover:bg-white transition-colors flex items-center gap-2"
                                                >
                                                    <LucideIcon name="check-circle" /> Piazza Scommessa (€{betData.stake || 2})
                                                </button>
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-warning text-sm mb-4">Nessun JSON strutturato rilevato.</div>
                                    )}

                                    <div className="prose prose-invert prose-sm max-w-none opacity-80">
                                        <pre className="whitespace-pre-wrap font-mono text-xs bg-black/30 p-4 rounded-xl border border-white/5 text-slate-300">
                                            {typeof geminiResult.analysis === 'string' ? geminiResult.analysis : JSON.stringify(geminiResult.analysis, null, 2)}
                                        </pre>
                                    </div>
                                </div>
                            );
                        })()}

                        <button onClick={() => setGeminiResult(null)} className="absolute top-4 right-4 text-slate-400 hover:text-white">
                            <LucideIcon name="x" />
                        </button>
                    </div>
                )}


                {/* Events Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 pb-20">
                    {loading && events.length === 0 ? (
                        <div className="col-span-full py-20 text-center">
                            <div className="w-12 h-12 border-4 border-accent/20 border-t-accent rounded-full animate-spin mx-auto mb-4"></div>
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">Caricamento Eventi Live...</p>
                        </div>
                    ) : events.length === 0 ? (
                        <div className="col-span-full py-20 text-center glass rounded-2xl border border-white/5 border-dashed">
                            <LucideIcon name="cloud-off" className="w-16 h-16 text-slate-600 mx-auto mb-4" />
                            <p className="text-slate-500 font-bold uppercase text-xs tracking-widest">Nessun evento live trovato per {activeSport}</p>
                        </div>
                    ) : (
                        events.map(evt => (
                            <div key={evt.event.id} className="glass p-5 rounded-2xl border border-white/5 hover:border-accent/30 transition-all group flex flex-col justify-between">
                                <div>
                                    <div className="flex justify-between items-start mb-4">
                                        <div className="bg-white/5 px-2 py-1 rounded-md text-[10px] uppercase font-bold text-slate-400">
                                            {evt.competition ? evt.competition.name : activeSport}
                                        </div>
                                        {/* Live Indicator if openDate is passed */}
                                        <div className="flex items-center gap-1 text-danger animate-pulse">
                                            <div className="w-2 h-2 rounded-full bg-danger"></div>
                                            <span className="text-[10px] font-black uppercase">LIVE</span>
                                        </div>
                                    </div>

                                    <h3 className="text-lg font-bold text-white mb-1 group-hover:text-accent transition-colors">
                                        {evt.event.name}
                                    </h3>
                                    <p className="text-xs text-slate-500">ID: {evt.event.id} • {evt.event.countryCode || 'INT'}</p>
                                </div>

                                <div className="mt-6 pt-4 border-t border-white/5 flex gap-2">
                                    <button
                                        onClick={() => analyzeMarket(evt)}
                                        disabled={analyzing === evt.event.id}
                                        className="flex-1 bg-white/5 hover:bg-accent hover:text-black text-white py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all flex items-center justify-center gap-2 group/btn"
                                    >
                                        {analyzing === evt.event.id ? (
                                            <LucideIcon name="loader" className="animate-spin" />
                                        ) : (
                                            <LucideIcon name="brain-circuit" />
                                        )}
                                        <span>Analisi Intelligenza Artificiale</span>
                                    </button>
                                </div>
                            </div>
                        ))
                    )}
                </div>

            </div>
        );
    }

    const root = ReactDOM.createRoot(document.getElementById('betfair-markets-root'));
    root.render(<BetfairLiveMarket />);
</script>

<?php require __DIR__ . '/../layout/bottom.php'; ?>