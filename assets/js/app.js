// assets/js/app.js

let liveMatches = [];
let selectedLeague = 'all';

async function fetchLive() {
    try {
        const res = await fetch('/api/live');
        const data = await res.json();
        liveMatches = data.response || [];
        renderFilters();
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
        const pendingCount = (data || []).filter(b => b.status === 'pending').length;
        document.getElementById('pending-bets-count').textContent = pendingCount;
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

function renderFilters() {
    const container = document.getElementById('league-filters');
    const leagues = ['all'];

    liveMatches.forEach(m => {
        if (!leagues.includes(m.league.name)) {
            leagues.push(m.league.name);
        }
    });

    if (container.children.length === leagues.length) return;

    container.innerHTML = '';
    leagues.forEach(league => {
        const pill = document.createElement('div');
        pill.className = `filter-pill ${selectedLeague === league ? 'active' : ''}`;
        pill.textContent = league === 'all' ? 'Tutti i Campionati' : league;
        pill.onclick = () => {
            selectedLeague = league;
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            renderMatches();
        };
        container.appendChild(pill);
    });
}

function renderMatches() {
    const container = document.getElementById('live-matches-container');
    const filtered = selectedLeague === 'all'
        ? liveMatches
        : liveMatches.filter(m => m.league.name === selectedLeague);

    container.innerHTML = filtered.length === 0 ? '<p>Nessuna partita disponibile per questo filtro.</p>' : '';

    filtered.forEach(m => {
        const eventsHtml = (m.events || []).map(ev => {
            let icon = '⚽';
            let iconClass = 'event-icon-goal';

            if (ev.type === 'Goal') { icon = '⚽'; iconClass = 'event-icon-goal'; }
            if (ev.type === 'Card' && ev.detail === 'Yellow Card') { icon = '🟨'; iconClass = 'event-icon-yellow'; }
            if (ev.type === 'Card' && ev.detail === 'Red Card') { icon = '🟥'; iconClass = 'event-icon-red'; }
            if (ev.type === 'Var') { icon = '🖥️'; iconClass = 'event-icon-var'; }
            if (ev.type === 'subst') return '';

            return `
                <div class="event-pill" style="cursor:pointer" onclick="showPlayerDetails(${ev.player.id}, '${ev.player.name}')">
                    <span class="event-time">${ev.time.elapsed}'</span>
                    <span class="${iconClass}">${icon}</span>
                    <span>${ev.player.name || ''}</span>
                </div>
            `;
        }).join('');

        const card = document.createElement('div');
        card.className = 'glass-panel match-card';
        card.dataset.id = m.fixture.id;
        card.innerHTML = `
            <div class="league-header" style="cursor:pointer" onclick="showStandings(${m.league.id}, '${m.league.name}')">
                <img src="${m.league.logo}" class="league-logo" alt="${m.league.name}">
                <span>${m.league.name} - ${m.league.country}</span>
            </div>
            <div class="match-main-info">
                <div class="live-badge"><span class="elapsed-time" data-start="${m.fixture.status.elapsed}">${m.fixture.status.elapsed}</span>'</div>
                <div class="team-info">
                    <div style="display:flex; align-items:center; gap:0.5rem; cursor:pointer" onclick="showTeamDetails(${m.teams.home.id})">
                        <img src="${m.teams.home.logo}" class="team-logo" alt="${m.teams.home.name}">
                        <span>${m.teams.home.name}</span>
                    </div>
                    <span style="color:var(--accent); font-size:1.4rem; font-weight:800; margin:0 10px;">${m.goals.home} - ${m.goals.away}</span>
                    <div style="display:flex; align-items:center; gap:0.5rem; cursor:pointer" onclick="showTeamDetails(${m.teams.away.id})">
                        <span>${m.teams.away.name}</span>
                        <img src="${m.teams.away.logo}" class="team-logo" alt="${m.teams.away.name}">
                    </div>
                </div>
                <button class="btn-analyze" onclick="analyzeMatch(${m.fixture.id})">Analizza AI</button>
            </div>
            ${eventsHtml ? `<div class="match-events">${eventsHtml}</div>` : ''}
        `;
        container.appendChild(card);
    });
}

async function showPlayerDetails(playerId, playerName = 'Giocatore') {
    if (!playerId) return;
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.style.display = 'block';
    title.textContent = playerName;
    body.innerHTML = '<div style="text-align:center; padding:2rem;">Caricamento dettagli giocatore...</div>';
    btn.style.display = 'none';

    try {
        const res = await fetch(`/api/player/${playerId}`);
        const p = await res.json();

        if (!p || p.error) {
            body.innerHTML = "Dati giocatore non disponibili.";
            return;
        }

        body.innerHTML = `
            <div class="analysis-content">
                <div style="display:flex; gap:2rem; margin-bottom:2rem; align-items:center;">
                    <img src="${p.photo}" style="width:100px; border-radius:10px; border:2px solid var(--accent);">
                    <div>
                        <h2 style="margin:0;">${p.name}</h2>
                        <p style="color:var(--text-secondary); margin:5px 0;">${p.firstname} ${p.lastname}</p>
                        <span class="status-tag status-active">${p.nationality}</span>
                    </div>
                </div>
                
                <div class="glass-panel" style="padding:1.5rem; display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem;">
                    <div>
                        <h4 style="margin:0 0 0.5rem 0; color:var(--accent); font-size:0.8rem;">Età</h4>
                        <p style="margin:0; font-size:1.1rem; font-weight:600;">${p.age || 'N/A'} anni</p>
                    </div>
                    <div>
                        <h4 style="margin:0 0 0.5rem 0; color:var(--accent); font-size:0.8rem;">Nazionalità</h4>
                        <p style="margin:0; font-size:1.1rem; font-weight:600;">${p.nationality || 'N/A'}</p>
                    </div>
                    <div>
                        <h4 style="margin:0 0 0.5rem 0; color:var(--accent); font-size:0.8rem;">Altezza</h4>
                        <p style="margin:0; font-size:1.1rem; font-weight:600;">${p.height || 'N/A'}</p>
                    </div>
                    <div>
                        <h4 style="margin:0 0 0.5rem 0; color:var(--accent); font-size:0.8rem;">Peso</h4>
                        <p style="margin:0; font-size:1.1rem; font-weight:600;">${p.weight || 'N/A'}</p>
                    </div>
                </div>
            </div>
        `;
    } catch (e) {
        body.innerHTML = "Errore nel caricamento del giocatore.";
    }
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

async function showStandings(leagueId, leagueName) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.style.display = 'block';
    title.textContent = `Classifica: ${leagueName}`;
    body.innerHTML = '<div style="text-align:center; padding:2rem;">Caricamento classifica...</div>';
    btn.style.display = 'none';

    try {
        const res = await fetch(`/api/standings/${leagueId}`);
        const data = await res.json();

        let html = `
            <table style="width:100%; border-collapse: collapse; font-size:0.85rem;">
                <thead>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.1); text-align:left;">
                        <th style="padding:0.5rem;">#</th>
                        <th style="padding:0.5rem;">Squadra</th>
                        <th style="padding:0.5rem;">Punti</th>
                        <th style="padding:0.5rem;">Forma</th>
                    </tr>
                </thead>
                <tbody>
        `;

        data.forEach(row => {
            html += `
                <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                    <td style="padding:0.5rem;">${row.rank}</td>
                    <td style="padding:0.5rem; display:flex; align-items:center; gap:0.5rem;">
                        <img src="${row.team_logo}" style="width:16px;"> ${row.team_name}
                    </td>
                    <td style="padding:0.5rem;"><strong>${row.points}</strong></td>
                    <td style="padding:0.5rem;">${row.form || '-'}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        body.innerHTML = `<div class="analysis-content" style="max-height:500px; overflow-y:auto;">${html}</div>`;
    } catch (e) {
        body.innerHTML = "Errore nel caricamento della classifica.";
    }
}

async function showTeamDetails(teamId) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.style.display = 'block';
    title.textContent = `Dettagli Squadra`;
    body.innerHTML = '<div style="text-align:center; padding:2rem;">Caricamento dettagli...</div>';
    btn.style.display = 'none';

    try {
        const res = await fetch(`/api/team/${teamId}`);
        const data = await res.json();
        const t = data.team;
        const c = data.coach;
        const squad = data.squad || [];

        title.textContent = t ? t.name : 'Team details';
        if (!t) {
            body.innerHTML = "Dati squadra non trovati.";
            return;
        }

        let squadHtml = '';
        if (squad.length > 0) {
            squadHtml = `
                <div class="glass-panel" style="padding:1rem; margin-top:1rem;">
                    <h4 style="margin-top:0; color:var(--accent);">Rosa Giocatori</h4>
                    <div style="max-height: 250px; overflow-y: auto;">
                        <table style="width:100%; border-collapse: collapse; font-size:0.8rem;">
                            <thead>
                                <tr style="border-bottom:1px solid rgba(255,255,255,0.1); text-align:left; color:var(--text-secondary);">
                                    <th style="padding:0.4rem;">#</th>
                                    <th style="padding:0.4rem;">Nome</th>
                                    <th style="padding:0.4rem;">Pos</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${squad.map(p => `
                                    <tr style="border-bottom:1px solid rgba(255,255,255,0.03); cursor:pointer" onclick="showPlayerDetails(${p.id}, '${p.name}')">
                                        <td style="padding:0.4rem; color:var(--accent); font-weight:800;">${p.number || '-'}</td>
                                        <td style="padding:0.4rem;">${p.name}</td>
                                        <td style="padding:0.4rem; font-size:0.7rem; color:var(--text-secondary);">${p.position}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
        }

        body.innerHTML = `
            <div class="analysis-content" style="max-height: 550px; overflow-y: auto;">
                <div style="display:flex; gap:2rem; margin-bottom:2rem; align-items:center;">
                    <img src="${t.logo}" style="width:80px;">
                    <div>
                        <h2 style="margin:0;">${t.name}</h2>
                        <p style="color:var(--text-secondary); margin:5px 0;">${t.country} | Fondata nel ${t.founded || 'N/A'}</p>
                    </div>
                </div>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                    <div class="glass-panel" style="padding:1rem;">
                        <h4 style="margin-top:0; color:var(--accent); font-size:0.8rem;">Stadio</h4>
                        <p style="margin:0; font-size:0.9rem;">${t.venue_name || 'N/A'}</p>
                        <p style="margin:0; font-size:0.75rem; color:var(--text-secondary);">Posti: ${t.venue_capacity || 'N/A'}</p>
                    </div>

                    ${c ? `
                    <div class="glass-panel" style="padding:1rem;">
                        <h4 style="margin-top:0; color:var(--accent); font-size:0.8rem;">Allenatore</h4>
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <img src="${c.photo}" style="width:30px; border-radius:50%;">
                            <div>
                                <p style="margin:0; font-weight:600; font-size:0.85rem;">${c.name}</p>
                                <p style="margin:0; font-size:0.7rem; color:var(--text-secondary);">${c.nationality || ''}</p>
                            </div>
                        </div>
                    </div>
                    ` : ''}
                </div>

                ${squadHtml}
            </div>
        `;
    } catch (e) {
        console.error(e);
        body.innerHTML = "Errore nel caricamento dei dettagli squadra.";
    }
}

async function analyzeMatch(id) {
    const modal = document.getElementById('analysis-modal');
    const body = document.getElementById('modal-body');
    const title = document.getElementById('modal-title');
    const btn = document.getElementById('place-bet-btn');

    modal.style.display = 'block';
    title.textContent = "Analisi Prossima Giocata";
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
            body: JSON.stringify({ fixture_id, match: matchName, ...betData })
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

// Event Listener for Manual Sync
const syncBtn = document.getElementById('sync-btn');
if (syncBtn) {
    syncBtn.addEventListener('click', async () => {
        syncBtn.disabled = true;
        const originalContent = syncBtn.innerHTML;
        syncBtn.innerHTML = '<i data-lucide="loader" class="rotator"></i> Attendere...';
        if (window.lucide) lucide.createIcons();

        try {
            const res = await fetch('/api/sync');
            const data = await res.json();
            console.log('Sync completed:', data);
            await fetchHistory();
        } catch (e) {
            console.error('Sync failed', e);
        } finally {
            syncBtn.disabled = false;
            syncBtn.innerHTML = originalContent;
            if (window.lucide) lucide.createIcons();
        }
    });
}

// Intervals
setInterval(fetchLive, 60000);    // Refresh full data every 60s
setInterval(fetchHistory, 40000); // Refresh history every 40s
setInterval(updateMinutes, 60000); // Increment local minutes every 60s
setInterval(fetchUsage, 60000);   // Refresh usage every 60s

// Close modal on click outside
window.onclick = (event) => {
    if (event.target == document.getElementById('analysis-modal')) {
        closeModal();
    }
};
