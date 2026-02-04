import React, { useState, useEffect } from 'react';

function App() {
  const [selectedMatch, setSelectedMatch] = useState(null);
  const [analysis, setAnalysis] = useState(null);
  const [analyzing, setAnalyzing] = useState(false);
  const [betHistory, setBetHistory] = useState([]);
  const [liveMatches, setLiveMatches] = useState([]);
  const [teams, setTeams] = useState([]);
  const [loading, setLoading] = useState(true);

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

  const placeBet = async (predictionText, fixtureData) => {
    try {
      // Extract JSON from prediction text
      const jsonMatch = predictionText.match(/```json\n([\s\S]*?)\n```/);
      if (!jsonMatch) return;

      const betInfo = JSON.parse(jsonMatch[1]);
      const betData = {
        fixture_id: fixtureData.id,
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
      } catch (err) {
        console.error("Error fetching data:", err);
      } finally {
        setLoading(false);
      }
    };

    fetchData();
    fetchHistory();
    const interval = setInterval(fetchData, 60000); // UI poll every minute
    return () => clearInterval(interval);
  }, []);

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
                {liveMatches.length > 0 ? (
                  liveMatches.map((m) => (
                    <div key={m.fixture.id} className="match-item" style={{ position: 'relative' }}>
                      <div className="league-name">{m.league.name} - {m.league.country}</div>
                      <div className="time">{m.fixture.status.elapsed}'</div>
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
            <div className="match-list" style={{ maxHeight: '300px', overflow: 'auto' }}>
              {betHistory.length > 0 ? (
                betHistory.slice().reverse().map((b) => (
                  <div key={b.id} className="match-item" style={{ gridTemplateColumns: '1fr 1fr 1fr', padding: '0.8rem' }}>
                    <div style={{ fontSize: '0.8rem' }}>
                      <div style={{ color: 'var(--primary)', fontWeight: 'bold' }}>{b.match}</div>
                      <div style={{ color: 'var(--text-dim)' }}>{b.timestamp}</div>
                    </div>
                    <div style={{ textAlign: 'center' }}>
                      <div style={{ fontWeight: 'bold' }}>{b.advice}</div>
                      <div style={{ fontSize: '0.7rem' }}>{b.market} @ {b.odds}</div>
                    </div>
                    <div style={{ textAlign: 'right' }}>
                      <span style={{
                        padding: '2px 8px',
                        borderRadius: '10px',
                        fontSize: '0.7rem',
                        background: b.status === 'pending' ? 'rgba(255,165,0,0.2)' : 'rgba(0,255,0,0.2)',
                        color: b.status === 'pending' ? 'orange' : 'green'
                      }}>
                        {b.status.toUpperCase()}
                      </span>
                    </div>
                  </div>
                ))
              ) : (
                <div className="loading">No bets placed yet.</div>
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

                <button
                  onClick={() => placeBet(analysis.prediction, analysis.raw_data.fixture)}
                  className="live-indicator"
                  style={{
                    width: '100%',
                    background: 'var(--primary)',
                    color: '#000',
                    fontWeight: '800',
                    cursor: 'pointer',
                    animation: 'none'
                  }}
                >
                  📝 PIAZZA SCOMMESSA SIMULATA
                </button>

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
              • Daily Quota: 18/100 used<br />
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
