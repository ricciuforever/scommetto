// public/js/modals.js
// Helper functions per aprire le modali

/**
 * Apre la modale con i dettagli completi del match
 */
async function openMatchDetailsModal(fixtureId) {
    try {
        // Fetch modal HTML
        const response = await fetch(`/gianik/match-details-modal/${fixtureId}`);
        const html = await response.text();

        // Inject into DOM
        const container = document.createElement('div');
        container.innerHTML = html;
        document.body.appendChild(container.firstElementChild);

        // Load data
        if (typeof loadMatchData === 'function') {
            loadMatchData(fixtureId);
        }

        // Reinitialize lucide icons
        if (window.lucide) lucide.createIcons();
    } catch (error) {
        console.error('Error opening match details modal:', error);
    }
}

/**
 * Apre la modale con i dettagli del giocatore
 */
async function openPlayerModal(playerId, fixtureId = null) {
    try {
        const url = fixtureId
            ? `/gianik/player-modal/${playerId}?fixture=${fixtureId}`
            : `/gianik/player-modal/${playerId}`;

        const response = await fetch(url);
        const html = await response.text();

        const container = document.createElement('div');
        container.innerHTML = html;
        document.body.appendChild(container.firstElementChild);

        if (window.lucide) lucide.createIcons();
    } catch (error) {
        console.error('Error opening player modal:', error);
    }
}

/**
 * Apre la modale con i dettagli della squadra
 */
async function openTeamModal(teamId) {
    try {
        const response = await fetch(`/gianik/team-modal/${teamId}`);
        const html = await response.text();

        const container = document.createElement('div');
        container.innerHTML = html;
        document.body.appendChild(container.firstElementChild);

        if (window.lucide) lucide.createIcons();
    } catch (error) {
        console.error('Error opening team modal:', error);
    }
}

/**
 * Chiude tutte le modali aperte
 */
function closeAllModals() {
    document.querySelectorAll('[id$="-modal"]').forEach(modal => {
        modal.remove();
    });
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeAllModals();
    }
});
