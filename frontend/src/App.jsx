import React, { useState, useEffect } from 'react';

function App() {
  const [liveMatches, setLiveMatches] = useState([]);
  const [teams, setTeams] = useState([]);
  const [loading, setLoading] = useState(true);

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
                    <div key={m.fixture.id} className="match-item">
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
