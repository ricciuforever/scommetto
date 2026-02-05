// assets/js/app.js

let liveMatches = [];

async function fetchLive() {
    try {
        const res = await fetch('/api/live');
        const data = await res.json();
        liveMatches = data.response || [];
        renderMatches();
        document.getElementById('active-matches-count').textContent = liveMatches.length;
    } catch (e) {
        console.error("Error fetching live data", e);
    }
}

async function fetchHistory() {
    try {
        const res = await fetch('/api/history');
        const data = await res.json();
        renderHistory(data || []);
        document.getElementById('pending-bets-count').textContent = (data || []).filter(b => b.status === 'pending').length;
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

function renderMatches() {
    const container = document.getElementById('live-matches-container');
    container.innerHTML = liveMatches.length === 0 ? '<p>Nessuna partita live disponibile.</p>' : '';

    liveMatches.forEach(m => {
        const card = document.createElement('div');
        card.className = 'glass-panel match-card';
        card.dataset.id = m.fixture.id;
        card.innerHTML = `
            <div class="live-badge"><span class="elapsed-time" data-start="${m.fixture.status.elapsed}">${m.fixture.status.elapsed}</span>'</div>
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

function updateMinutes() {
    const times = document.querySelectorAll('.elapsed-time');
    times.forEach(el => {
        let current = parseInt(el.textContent);
        if (current < 90) {
            el.textContent = current + 1;
        }
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

        if (data.error) {
            body.innerHTML = `<div style="color:var(--danger);">${data.error}</div>`;
            return;
        }

        const prediction = data.prediction;

        // Robust JSON extraction
        let betData = null;
        let displayHtml = prediction;

        const jsonMatch = prediction.match(/```json\n?([\s\S]*?)\n?```/i);
        if (jsonMatch) {
            try {
                betData = JSON.parse(jsonMatch[1]);
                displayHtml = prediction.replace(/```json[\s\S]*?```/i, '');
            } catch (e) {
                console.error("JSON Parse Error", e);
            }
        }

        body.innerHTML = `
            <div class="analysis-content" style="max-height: 400px; overflow-y: auto; padding-right: 10px;">
                <div style="white-space: pre-wrap; margin-bottom: 2rem;">${displayHtml}</div>
                ${betData ? `
                    <div class="glass-panel" style="padding: 1rem; border-left: 4px solid var(--accent);">
                        <div style="font-weight: 800; color: var(--accent); margin-bottom: 0.5rem;">CONSIGLIO AI</div>
                        <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">${betData.advice}</div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem;">
                            <div><strong>Mercato:</strong> ${betData.market}</div>
                            <div><strong>Quota:</strong> ${betData.odds}</div>
                            <div><strong>Urgenza:</strong> ${betData.urgency}</div>
                            <div><strong>Stake:</strong> ${betData.stake}</div>
                        </div>
                    </div>
                ` : '<div style="color:var(--warning);">Nessun dato scommessa strutturato trovato.</div>'}
            </div>
        `;

        if (betData) {
            btn.style.display = 'block';
            btn.onclick = () => placeBet(id, data.match, betData);
        }

    } catch (e) {
        body.innerHTML = "Errore durante l'analisi. Riprova.";
    }
}

async function placeBet(fixture_id, match, betData) {
    try {
        const matchName = typeof match === 'string' ? match : `${match.teams.home.name} vs ${match.teams.away.name}`;
        const res = await fetch('/api/place_bet', {
            method: 'POST',
            body: JSON.stringify({
                fixture_id,
                match: matchName,
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

// Intervals
setInterval(fetchLive, 30000);   // Refresh full data every 30s
setInterval(updateMinutes, 60000); // Increment local minutes every 60s
setInterval(fetchUsage, 60000);   // Refresh usage every 60s

// Close modal on click outside
window.onclick = (event) => {
    if (event.target == document.getElementById('analysis-modal')) {
        closeModal();
    }
};
