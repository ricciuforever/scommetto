import React, { useState, useEffect } from 'react';

function App() {
  const [selectedMatch, setSelectedMatch] = useState(null);
  const [analysis, setAnalysis] = useState(null);
  const [analyzing, setAnalyzing] = useState(false);

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
            <h2>🏆 Serie A Teams (24/25)</h2>
            <div className="teams-grid">
              {teams.length > 0 ? (
                teams.map((t) => (
                  <div key={t.team.id} className="team-badge">
                    <img src={t.team.logo} alt="" />
                    <span>{t.team.name}</span>
                  </div>
                ))
              ) : (
                <div className="loading">Loading teams...</div>
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
