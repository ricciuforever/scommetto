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

  const fetchHistory = async () => {
    try {
      const apiBase = import.meta.env.VITE_API_URL || '';
      const res = await fetch(`${apiBase}/api/history`);
      const data = await res.json();
      setBetHistory(data);
    } catch (err) {
      console.error("History error:", err);
    }
  };

  const fetchUsage = async () => {
    try {
      const apiBase = import.meta.env.VITE_API_URL || '';
      const res = await fetch(`${apiBase}/api/usage`);
      const data = await res.json();
      setUsage(data);
    } catch (err) {
      console.error("Usage fetch error:", err);
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
      try {
        const apiBase = import.meta.env.VITE_API_URL || '';
        const [liveRes, teamsRes] = await Promise.all([
          fetch(`${apiBase}/api/live`),
          fetch(`${apiBase}/api/teams`)
        ]);

        const liveData = await liveRes.json();
        const teamsData = await teamsRes.json();

        setLiveMatches(liveData.response || []);
        setTeams(teamsData.response || []);
        setLastFetchTimestamp(Date.now());
        setCurrentTime(Date.now());
      } catch (err) {
        console.error("Error fetching data:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
    fetchHistory();
    fetchUsage();
    const interval = setInterval(() => {
      fetchData();
      fetchHistory();
      fetchUsage();
    }, 30000); // UI poll every 30s for PRO plan

    // Clock tick every second for smooth minute calculation
    const tick = setInterval(() => setCurrentTime(Date.now()), 1000);

    return () => {
      clearInterval(interval);
      clearInterval(tick);
    };
  }, []);

  const getTickedMinute = (elapsed) => {
    if (!elapsed) return 0;
    const diffMinutes = Math.floor((currentTime - lastFetchTimestamp) / 60000);
    const total = elapsed + diffMinutes;
    // Don't show more than 90+ offset or 45+ offset for standard halves
    if (elapsed <= 45 && total > 48) return 45;
    if (elapsed > 45 && total > 95) return 90;
    return total;
  };

  const getMatchTimeDisplay = (m) => {
    const status = m.fixture.status.short;
    const elapsed = m.fixture.status.elapsed;

    if (status === 'HT') return <span style={{ color: 'var(--primary)', fontWeight: 'bold' }}>HT</span>;
    if (status === 'FT') return 'FT';
    if (status === 'NS') return 'Scheduled';

    const ticked = getTickedMinute(elapsed);
    return `${ticked}'`;
  };

  const sortedHistory = [...history].sort((a, b) => {
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
        <div className="logo">SCOMMETTO_AGENTE</div>
        <div className="live-indicator">
          <span className="dot">•</span> LIVE NOW
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
          <div className="card">
            <h2>🗒️ Betting Book (Simulator)</h2>
            <div className="match-list" style={{ maxHeight: '400px', overflow: 'auto' }}>
              {sortedHistory.length === 0 ? (
                <p style={{ color: '#94a3b8', padding: '1rem', textAlign: 'center' }}>Nessuna scommessa nel registro.</p>
              ) : (
                sortedHistory.map((bet) => (
                  <div key={bet.id} className="match-item" style={{
                    borderLeft: `4px solid ${bet.status === 'win' ? '#22c55e' : (bet.status === 'lost' ? '#ef4444' : '#f59e0b')}`,
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
                        background: bet.status === 'pending' ? 'rgba(245, 158, 11, 0.1)' : (bet.status === 'win' ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)'),
                        color: bet.status === 'pending' ? '#f59e0b' : (bet.status === 'win' ? '#22c55e' : '#ef4444'),
                        border: `1px solid ${bet.status === 'pending' ? '#f59e0b' : (bet.status === 'win' ? '#22c55e' : '#ef4444')}`
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

          <div className="card">
            <h2>ℹ️ Agent Status</h2>
            <div style={{ color: '#94a3b8', fontSize: '0.9rem', lineHeight: '1.6' }}>
              • API Polling: Active (15m interval)<br />
              • Daily Quota: {usage.used} / {usage.used + usage.remaining} used<br />
              • Remaining: <span style={{ color: usage.remaining < 20 ? 'red' : 'var(--primary)' }}>{usage.remaining} calls</span><br />
              • Strategy: Real-time monitoring<br />
              • Monitoring: {liveMatches.length} events
            </div>
          </div>
        </section>
      </div>
    </div>
  );
}

export default App;
