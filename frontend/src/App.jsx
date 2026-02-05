import React, { useState, useEffect } from 'react';

function App() {
  const [selectedMatch, setSelectedMatch] = useState(null);
  const [analysis, setAnalysis] = useState(null);
  const [analyzing, setAnalyzing] = useState(false);
  const [betHistory, setBetHistory] = useState([]);
  const [liveMatches, setLiveMatches] = useState([]);
  const [teams, setTeams] = useState([]);
  const [loading, setLoading] = useState(true);
  const [lastFetchTimestamp, setLastFetchTimestamp] = useState(Date.now());
  const [currentTime, setCurrentTime] = useState(Date.now());
  const [usage, setUsage] = useState({ used: 0, remaining: 7500 });
  const [serverLastUpdate, setServerLastUpdate] = useState(Date.now());
  const [logs, setLogs] = useState([]);

  const fetchHistory = async () => {
    try {
      const apiBase = import.meta.env.VITE_API_URL || '';
      const res = await fetch(`${apiBase}/api/history?t=${Date.now()}`);
      const data = await res.json();
      setBetHistory(data);
    } catch (err) {
      console.error("History error:", err);
    }
  };

  const fetchUsage = async () => {
    try {
      const apiBase = import.meta.env.VITE_API_URL || '';
      const res = await fetch(`${apiBase}/api/usage?t=${Date.now()}`);
      const data = await res.json();
      setUsage(data);
    } catch (err) {
      console.error("Usage fetch error:", err);
    }
  };

  const fetchLogs = async () => {
    try {
      const apiBase = import.meta.env.VITE_API_URL || '';
      const res = await fetch(`${apiBase}/api/logs?t=${Date.now()}`);
      const data = await res.json();
      setLogs(data.logs || []);
    } catch (err) {
      console.error("Logs fetch error:", err);
    }
  };

  const placeBet = async (predictionText, fixtureData) => {
    try {
      // Extract JSON from prediction text
      const jsonMatch = predictionText.match(/```json\n([\s\S]*?)\n```/);
      if (!jsonMatch) return;

      const betInfo = JSON.parse(jsonMatch[1]);
      if (!fixtureData || !fixtureData.teams) {
        throw new Error("Dati partita non validi per la scommessa");
      }
      const betData = {
        fixture_id: fixtureData.id || fixtureData.fixture_id || 'unknown',
        match: `${fixtureData.teams.home.name} vs ${fixtureData.teams.away.name}`,
        ...betInfo
      };

      const apiBase = import.meta.env.VITE_API_URL || '';
      await fetch(`${apiBase}/api/place_bet`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(betData)
      });

      fetchHistory();
      alert("Scommessa Simulata Piazzata!");
    } catch (err) {
      console.error("Place bet error:", err);
    }
  };

  const analyzeMatch = async (fixtureId) => {
    setAnalyzing(true);
    setAnalysis(null);
    try {
      const apiBase = import.meta.env.VITE_API_URL || '';
      const res = await fetch(`${apiBase}/api/analyze/${fixtureId}`);
      const data = await res.json();
      setAnalysis(data);
      if (data.auto_bet_status === 'success') {
        fetchHistory();
      }
      fetchUsage(); // Update usage after analysis
    } catch (err) {
      console.error("Analysis error:", err);
    } finally {
      setAnalyzing(false);
    }
  };

  useEffect(() => {
    const fetchData = async () => {
      const apiBase = import.meta.env.VITE_API_URL || '';
      const now = Date.now();

      try {
        const liveRes = await fetch(`${apiBase}/api/live?t=${now}`);
        const liveData = await liveRes.json();
        setLiveMatches(liveData.response || []);
        if (liveData.server_time) {
          setServerLastUpdate(liveData.server_time * 1000);
        }
        setLastFetchTimestamp(now);
      } catch (err) { console.error("Live fetch error:", err); }

      try {
        const teamsRes = await fetch(`${apiBase}/api/teams?t=${now}`);
        const teamsData = await teamsRes.json();
        setTeams(teamsData.response || []);
      } catch (err) { console.error("Teams fetch error:", err); }

      setLoading(false);
    };

    fetchData();
    fetchHistory();
    fetchUsage();
    fetchLogs();

    const interval = setInterval(() => {
      fetchData();
      fetchHistory();
      fetchUsage();
      fetchLogs();
    }, 30000);

    const tick = setInterval(() => setCurrentTime(Date.now()), 1000);

    return () => {
      clearInterval(interval);
      clearInterval(tick);
    };
  }, []);

  const [matchElapsedMap, setMatchElapsedMap] = useState({});

  const getTickedMinute = (fixtureId, apiElapsed) => {
    if (!apiElapsed) return 0;

    // Calculate how many minutes passed since the SERVER updated the data
    const diffSeconds = Math.floor((currentTime - serverLastUpdate) / 1000);
    const extraMinutes = Math.floor(diffSeconds / 60);
    const calculated = apiElapsed + extraMinutes;

    // Prevention of "Snap-back": Always show the highest value we've reached
    // unless the match resets (e.g. from 0)
    const stored = matchElapsedMap[fixtureId] || 0;
    if (calculated > stored || calculated < 10) {
      // Update our local highest minute for this match
      setTimeout(() => {
        setMatchElapsedMap(prev => ({ ...prev, [fixtureId]: calculated }));
      }, 0);
      return calculated;
    }
    return stored;
  };

  const getMatchTimeDisplay = (m) => {
    const status = m.fixture.status.short;
    const elapsed = m.fixture.status.elapsed;

    if (status === 'HT') return <span style={{ color: 'var(--primary)', fontWeight: 'bold' }}>HT</span>;
    if (status === 'FT') return <span style={{ color: '#ef4444' }}>FT</span>;
    if (status === 'NS') return 'Scheduled';

    const ticked = getTickedMinute(m.fixture.id, elapsed);
    return <span className="time">{ticked}'</span>;
  };

  const sortedHistory = [...betHistory].sort((a, b) => {
    // 1. Pending first
    if (a.status === 'pending' && b.status !== 'pending') return -1;
    if (a.status !== 'pending' && b.status === 'pending') return 1;

    // 2. If both pending, try to find the match in liveMatches to see progress
    const matchA = liveMatches.find(m => m.fixture.id === Number(a.fixture_id));
    const matchB = liveMatches.find(m => m.fixture.id === Number(b.fixture_id));

    if (matchA && matchB) {
      const elapsedA = matchA.fixture.status.elapsed || 0;
      const elapsedB = matchB.fixture.status.elapsed || 0;
      return elapsedB - elapsedA; // Higher minute = more "imminent"
    }

    // 3. Last fallback: newest first
    return new Date(b.timestamp) - new Date(a.timestamp);
  });

  const sortedMatches = [...liveMatches].sort((a, b) => {
    // Priority 1: Top Leagues (can add more IDs here)
    const topLeagues = [135, 140, 39, 61, 78]; // Serie A, La Liga, PL, Ligue 1, Bunde
    const aIsTop = topLeagues.includes(a.league.id);
    const bIsTop = topLeagues.includes(b.league.id);
    if (aIsTop && !bIsTop) return -1;
    if (!aIsTop && bIsTop) return 1;

    // Priority 2: Elapsed time (descending, games near the end)
    return (b.fixture.status.elapsed || 0) - (a.fixture.status.elapsed || 0);
  });

  return (
    <div className="app-container">
      <header>
        <div className="logo">SCOMMETTO_AGENTE <span style={{ fontSize: '0.8rem', opacity: 0.5 }}>v2.9</span></div>
        <div style={{ display: 'flex', gap: '20px', alignItems: 'center' }}>
          <div style={{ fontSize: '0.75rem', color: 'var(--text-dim)', textAlign: 'right', borderRight: '1px solid var(--glass-border)', paddingRight: '15px' }}>
            SERVER LAST SYNC<br />
            <span style={{ color: 'var(--primary)', fontWeight: 'bold', fontSize: '0.85rem' }}>
              {Math.max(0, Math.floor((currentTime - serverLastUpdate) / 1000))}s AGO
            </span>
          </div>
          <div className="live-indicator">
            <span className="dot" style={{ animation: 'pulse 1s infinite' }}>•</span> LIVE NOW
          </div>
        </div>
      </header>

      <div className="dashboard-grid">
        <section className="live-section">
          <div className="card">
            <h2>🔥 Live Fixtures</h2>
            {loading ? (
              <div className="loading">Initializing live feed...</div>
            ) : (
              <div className="match-list">
                {sortedMatches.length > 0 ? (
                  sortedMatches.map((m) => (
                    <div key={m.fixture.id} className="match-item" style={{ position: 'relative' }}>
                      <div className="league-name">{m.league.name} - {m.league.country}</div>
                      <div className="time">{getMatchTimeDisplay(m)}</div>
                      <div className="team">
                        <img src={m.teams.home.logo} alt="" />
                        {m.teams.home.name}
                      </div>
                      <div className="score">
                        {m.goals.home} - {m.goals.away}
                      </div>
                      <div className="team">
                        <img src={m.teams.away.logo} alt="" />
                        {m.teams.away.name}
                      </div>
                      <button
                        onClick={() => analyzeMatch(m.fixture.id)}
                        className="live-indicator"
                        style={{
                          gridColumn: '1 / -1',
                          marginTop: '10px',
                          cursor: 'pointer',
                          background: 'rgba(0, 242, 255, 0.1)',
                          borderColor: 'var(--primary)',
                          color: 'var(--primary)',
                          animation: 'none'
                        }}
                      >
                        {analyzing ? 'GATHERING DATA...' : '🧠 ANALYZE PROMPT'}
                      </button>
                    </div>
                  ))
                ) : (
                  <div className="loading">No live matches at the moment.</div>
                )}
              </div>
            )}
          </div>
        </section>

        <section className="stats-section">
          {/* ACTIVE BETS BLOCK */}
          <div className="card">
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <h2 style={{ margin: 0 }}>🎯 Active Bets</h2>
              <span className="badge" style={{ background: 'rgba(245, 158, 11, 0.2)', color: '#f59e0b' }}>
                {sortedHistory.filter(b => b.status === 'pending').length} PENDING
              </span>
            </div>
            <div className="match-list" style={{ maxHeight: '400px', overflow: 'auto' }}>
              {sortedHistory.filter(b => b.status === 'pending').length === 0 ? (
                <p style={{ color: '#94a3b8', padding: '1rem', textAlign: 'center' }}>Nessuna scommessa attiva.</p>
              ) : (
                sortedHistory.filter(b => b.status === 'pending').map((bet) => (
                  <div key={bet.id} className="match-item" style={{
                    borderLeft: `4px solid #f59e0b`,
                    padding: '1rem',
                    marginBottom: '0.5rem',
                    background: 'rgba(255, 255, 255, 0.02)'
                  }}>
                    <div style={{ fontSize: '0.8rem' }}>
                      <div style={{ color: 'var(--primary)', fontWeight: 'bold' }}>{bet.match}</div>
                      <div style={{ color: 'var(--text-dim)', fontSize: '0.7rem' }}>{new Date(bet.timestamp).toLocaleString()}</div>
                    </div>
                    <div style={{ textAlign: 'center' }}>
                      <div style={{ fontSize: '0.85rem', fontWeight: 'bold' }}>{bet.advice}</div>
                      <div style={{ fontSize: '0.7rem', color: '#94a3b8' }}>{bet.market} @ {bet.odds}</div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <span style={{
                        padding: '4px 10px',
                        borderRadius: '20px',
                        fontSize: '0.7rem',
                        fontWeight: 'bold',
                        background: 'rgba(245, 158, 11, 0.1)',
                        color: '#f59e0b',
                        border: '1px solid #f59e0b'
                      }}>
                        PENDING
                      </span>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          {/* ENDED STRATEGY BLOCK */}
          <div className="card" style={{ marginTop: '2rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <h2 style={{ margin: 0 }}>🏆 Ended Strategy</h2>
              <div style={{ fontSize: '0.8rem' }}>
                <span style={{ color: '#22c55e', marginRight: '10px' }}>WIN: {sortedHistory.filter(b => b.status === 'win').length}</span>
                <span style={{ color: '#ef4444' }}>LOST: {sortedHistory.filter(b => b.status === 'lost').length}</span>
              </div>
            </div>
            <div className="match-list" style={{ maxHeight: '400px', overflow: 'auto' }}>
              {sortedHistory.filter(b => b.status !== 'pending').length === 0 ? (
                <p style={{ color: '#94a3b8', padding: '1rem', textAlign: 'center' }}>Nessun risultato ancora.</p>
              ) : (
                sortedHistory.filter(b => b.status !== 'pending').map((bet) => (
                  <div key={bet.id} className="match-item" style={{
                    borderLeft: `4px solid ${bet.status === 'win' ? '#22c55e' : '#ef4444'}`,
                    padding: '1rem',
                    marginBottom: '0.5rem',
                    background: 'rgba(255, 255, 255, 0.02)',
                    opacity: 0.8
                  }}>
                    <div style={{ fontSize: '0.8rem' }}>
                      <div style={{ color: 'var(--primary)', fontWeight: 'bold' }}>{bet.match}</div>
                      <div style={{ color: 'var(--text-dim)', fontSize: '0.7rem' }}>{new Date(bet.timestamp).toLocaleString()}</div>
                    </div>
                    <div style={{ textAlign: 'center' }}>
                      <div style={{ fontSize: '0.85rem', fontWeight: 'bold' }}>{bet.advice}</div>
                      <div style={{ fontSize: '0.7rem', color: '#94a3b8' }}>{bet.market} @ {bet.odds}</div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <span style={{
                        padding: '4px 10px',
                        borderRadius: '20px',
                        fontSize: '0.7rem',
                        fontWeight: 'bold',
                        background: bet.status === 'win' ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                        color: bet.status === 'win' ? '#22c55e' : '#ef4444',
                        border: `1px solid ${bet.status === 'win' ? '#22c55e' : '#ef4444'}`
                      }}>
                        {bet.status.toUpperCase()} {bet.result ? `- ${bet.result}` : ''}
                      </span>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>

          <div className="card" style={{ marginTop: '2rem' }}>
            <h2>🤖 Gemini Intelligence</h2>
            {analysis ? (
              <div className="analysis-result">
                <div style={{
                  background: 'rgba(0, 242, 255, 0.05)',
                  padding: '1.5rem',
                  borderRadius: '1rem',
                  borderLeft: '4px solid var(--primary)',
                  marginBottom: '1rem',
                  whiteSpace: 'pre-wrap',
                  lineHeight: '1.6',
                  color: '#fff',
                  fontSize: '0.95rem'
                }}>
                  {analysis.prediction}
                </div>

                {analysis.auto_bet_status === 'success' && (
                  <div style={{
                    background: 'rgba(0, 255, 0, 0.1)',
                    color: '#00ff00',
                    padding: '0.8rem',
                    borderRadius: '0.5rem',
                    textAlign: 'center',
                    fontWeight: 'bold',
                    marginBottom: '1rem',
                    border: '1px solid #00ff00'
                  }}>
                    ✅ SCOMMESSA PIAZZATA AUTOMATICAMENTE!
                  </div>
                )}

                {analysis.auto_bet_status === 'already_exists' && (
                  <div style={{
                    background: 'rgba(255, 165, 0, 0.1)',
                    color: 'orange',
                    padding: '0.8rem',
                    borderRadius: '0.5rem',
                    textAlign: 'center',
                    fontWeight: 'bold',
                    marginBottom: '1rem',
                    border: '1px solid orange'
                  }}>
                    ⚠️ ANALISI GIÀ PRESENTE NEL REGISTRO
                  </div>
                )}

                <details style={{ marginTop: '1rem' }}>
                  <summary style={{ cursor: 'pointer', color: 'var(--text-dim)', fontSize: '0.8rem' }}>
                    View Raw Intelligence Data
                  </summary>
                  <div style={{
                    background: '#000',
                    padding: '1rem',
                    borderRadius: '1rem',
                    fontSize: '0.7rem',
                    maxHeight: '200px',
                    overflow: 'auto',
                    marginTop: '0.5rem',
                    border: '1px solid var(--glass-border)'
                  }}>
                    <pre style={{ color: '#94a3b8' }}>
                      {JSON.stringify(analysis.raw_data, null, 2)}
                    </pre>
                  </div>
                </details>
              </div>
            ) : (
              <div className="loading">Select a match to prepare the Gemini Intelligence prompt.</div>
            )}
          </div>
        </section>
      </div>

      <section className="logs-section" style={{ marginTop: '2rem' }}>
        <div className="card" style={{ background: '#000', border: '1px solid var(--primary)', fontFamily: 'monospace' }}>
          <h2 style={{ fontSize: '1rem', color: 'var(--primary)', marginBottom: '10px' }}>📡 Live Agent Terminal (Telemetry)</h2>
          <div style={{ height: '180px', overflowY: 'auto', fontSize: '0.8rem', color: '#0f0', padding: '10px', whiteSpace: 'pre-wrap' }}>
            {logs.length > 0 ? logs.map((log, i) => (
              <div key={i} style={{ marginBottom: '4px', borderBottom: '1px solid #030' }}>{log}</div>
            )) : "Awaiting agent telemetry..."}
          </div>
        </div>
      </section>

      <div className="footer-stats" style={{ marginTop: '2rem', padding: '1rem', borderTop: '1px solid var(--glass-border)' }}>
        <div style={{ fontSize: '0.8rem', color: 'var(--text-dim)', textAlign: 'center' }}>
          AGENTE SCOMMESSE PRO v2.9 • API STATUS: <span style={{ color: '#22c55e' }}>ONLINE</span> • QUOTA: {usage.used}/{usage.used + usage.remaining}
        </div>
      </div>
    </div>
  );
}

export default App;
