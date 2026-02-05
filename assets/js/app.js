// assets/js/app.js

async function fetchLive() {
    try {
        const res = await fetch('/api/live');
        const data = await res.json();
        renderMatches(data.response || []);
        document.getElementById('active-matches-count').textContent = data.response ? data.response.length : 0;
    } catch (e) {
        console.error("Error fetching live data", e);
    }
}

async function fetchHistory() {
    try {
        const res = await fetch('/api/history');
        const data = await res.json();
        renderHistory(data || []);
        document.getElementById('pending-bets-count').textContent = data.filter(b => b.status === 'pending').length;
    } catch (e) {
        console.error("Error fetching history", e);
    }
}

async function fetchUsage() {
    try {
        const res = await fetch('/api/usage');
        const data = await res.json();
        if (data && data.requests_used !== undefined) {
            document.getElementById('usage-val').textContent = data.requests_used;
        }
    } catch (e) {
        console.error("Error fetching usage", e);
    }
}

function renderMatches(matches) {
    const container = document.getElementById('live-matches-container');
    container.innerHTML = matches.length === 0 ? '<p>Nessuna partita live disponibile.</p>' : '';

    matches.forEach(m => {
        const card = document.createElement('div');
        card.className = 'glass-panel match-card';
        card.innerHTML = `
            <div class="live-badge">${m.fixture.status.elapsed}'</span></div>
            <div class="team-info">
                <span>${m.teams.home.name}</span>
                <span style="color:var(--accent); font-size:1.2rem;">${m.goals.home} - ${m.goals.away}</span>
                <span>${m.teams.away.name}</span>
            </div>
            <button class="btn-analyze" onclick="analyzeMatch(${m.fixture.id})">Analizza AI</button>
        `;
        container.appendChild(card);
    });
}

function renderHistory(history) {
    const container = document.getElementById('history-container');
    container.innerHTML = history.length === 0 ? '<p style="padding:1rem;">Nessuna scommessa registrata.</p>' : '';

    history.slice(0, 10).forEach(h => {
        const item = document.createElement('div');
        item.className = 'history-item';
        item.innerHTML = `
            <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                <span style="font-weight:600; font-size:0.9rem;">${h.match_name || h.match}</span>
                <span class="status-tag status-${h.status}">${h.status}</span>
            </div>
            <div style="color:var(--text-secondary); font-size:0.8rem;">
                ${h.market} @ ${h.odds} | Stake: ${h.stake}
            </div>
        `;
        container.appendChild(item);
    });
}

async function analyzeMatch(id) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const btn = document.getElementById('place-bet-btn');

    modal.style.display = 'block';
    body.innerHTML = '<div style="text-align:center; padding:2rem;"><div class="live-badge" style="animation:none; background:var(--accent);">ANALIZZANDO DATI CON GEMINI AI...</div></div>';
    btn.style.display = 'none';

    try {
        const res = await fetch(`/api/analyze/${id}`);
        const data = await res.json();

        // Match prediction can be a long text with a JSON block
        const prediction = data.prediction;
        const jsonMatch = prediction.match(/```json\n([\s\S]*?)\n```/);

        let displayHtml = prediction.replace(/```json[\s\S]*?```/, '');
        body.innerHTML = `<div style="white-space: pre-wrap;">${displayHtml}</div>`;

        if (jsonMatch) {
            const betData = JSON.parse(jsonMatch[1]);
            btn.style.display = 'block';
            btn.onclick = () => placeBet(id, data.match, betData);
        }

    } catch (e) {
        body.innerHTML = "Errore durante l'analisi. Riprova.";
    }
}

async function placeBet(fixture_id, match, betData) {
    try {
        const res = await fetch('/api/place_bet', {
            method: 'POST',
            body: JSON.stringify({
                fixture_id,
                match: `${match.teams.home.name} vs ${match.teams.away.name}`,
                ...betData
            })
        });
        const result = await res.json();
        if (result.status === 'success') {
            closeModal();
            fetchHistory();
        } else {
            alert("Scommessa già esistente o errore.");
        }
    } catch (e) {
        alert("Errore nell'invio della scommessa.");
    }
}

function closeModal() {
    document.getElementById('analysis-modal').style.display = 'none';
}

// Initial fetch
fetchLive();
fetchHistory();
fetchUsage();

// Refresh live matches every 30s
setInterval(fetchLive, 30000);
setInterval(fetchUsage, 60000);
