import { state, updateState } from './utils.js';
import { Tracker } from './tracker.js';
import { Dashboard } from './dashboard.js';
// import { Leagues } from './leagues.js';
// import { Predictions } from './predictions.js';
import { UI } from './ui.js';

const viewContainer = document.getElementById('view-content');
const viewTitle = document.getElementById('view-title');
const viewLoader = document.getElementById('view-loader');

export const routes = {
    dashboard: { title: 'Dashboard', render: () => Dashboard.render() },
    leagues: { title: 'Competizioni', render: () => { /* Leagues.render() */ } },
    predictions: { title: 'Pronostici', render: () => { /* Predictions.render() */ } },
    tracker: { title: 'Il Mio Tracker', render: () => Tracker.render() }
};

export async function handleRouting() {
    let hash = window.location.hash.replace('#', '') || 'dashboard';
    if (!routes[hash]) hash = 'dashboard';

    updateState('currentView', hash);

    // Update Nav
    document.querySelectorAll('.nav-link').forEach(l => {
        l.classList.remove('active-nav');
        if (l.dataset.view === hash) l.classList.add('active-nav');
    });

    // Update Title
    if (viewTitle) viewTitle.textContent = routes[hash].title;

    // Show Loader
    if (viewLoader) viewLoader.classList.remove('hidden');
    if (viewContainer) viewContainer.classList.add('hidden');

    // Render View
    if (viewContainer) {
        viewContainer.innerHTML = '';
        await routes[hash].render();

        viewLoader.classList.add('hidden');
        viewContainer.classList.remove('hidden');
    }
}
