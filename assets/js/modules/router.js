import { state, updateState } from './utils.js';
import { Tracker } from './tracker.js';
import { Dashboard } from './dashboard.js';
// import { Leagues } from './leagues.js';
// import { Predictions } from './predictions.js';
import { UI } from './ui.js';

// DOM Elements are fetched lazily
const getElement = (id) => document.getElementById(id);

export const routes = {
    dashboard: { title: 'Dashboard', render: () => Dashboard.render() },
    leagues: { title: 'Competizioni', render: async () => { console.log("Leagues TODO"); } },
    predictions: { title: 'Pronostici', render: async () => { console.log("Predictions TODO"); } },
    tracker: { title: 'Il Mio Tracker', render: () => Tracker.render() }
};

export async function handleRouting() {
    let hash = window.location.hash.replace('#', '') || 'dashboard';
    if (!routes[hash]) hash = 'dashboard';

    console.log(`Routing to: ${hash}`);

    updateState('currentView', hash);
    const viewTitle = getElement('view-title');
    const viewLoader = getElement('view-loader');
    const viewContainer = getElement('view-container');

    // Update Nav
    document.querySelectorAll('.nav-link').forEach(l => {
        l.classList.remove('active-nav');
        if (l.dataset.view === hash) l.classList.add('active-nav');
    });

    // Update Title
    if (viewTitle && routes[hash]) viewTitle.textContent = routes[hash].title;

    // Show Loader
    if (viewLoader) viewLoader.classList.remove('hidden');
    if (viewContainer) viewContainer.classList.add('hidden');

    try {
        // Render View
        if (viewContainer) {
            viewContainer.innerHTML = '';
            await routes[hash].render();

            console.log("Render complete");
            if (viewLoader) viewLoader.classList.add('hidden');
            viewContainer.classList.remove('hidden');
        } else {
            console.error("View container #view-content not found!");
        }
    } catch (err) {
        console.error("Error rendering view:", err);
        if (viewLoader) viewLoader.classList.add('hidden'); // Hide loader on error
        if (viewContainer) viewContainer.innerHTML = `<div class="p-10 text-center text-danger">Errore caricamento vista: ${err.message}</div>`;
        if (viewContainer) viewContainer.classList.remove('hidden');
    }
}
