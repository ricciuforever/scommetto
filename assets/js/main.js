import { handleRouting, routes } from './modules/router.js';
import { updateState } from './modules/utils.js';
import { UI } from './modules/ui.js';
import { Tracker } from './modules/tracker.js';

// --- GLOBAL APP STATE & EXPORTS ---
window.app = {
    showToast: UI.showToast,
    showBetDetails: (id) => {
        console.log("Show bet details for", id);
        // We need to implement this logic or reuse it. For now, simple alert to prove it works
        // Ideally Tracker.showDetails(id) 
    },
    analyzeMatch: (id) => {
        console.log("Analyze match", id);
        // Dashboard.analyzeMatch or similar
    }
};

// --- INIT ---
document.addEventListener('DOMContentLoaded', () => {
    console.log("App initialized");

    // Init Router
    window.addEventListener('hashchange', handleRouting);

    // Force initial routing
    console.log("Triggering initial route...");
    handleRouting().catch(e => console.error("Routing failed:", e));

    // Theme Toggle
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.onclick = () => {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            UI.createIcons();
        };
    }
});
