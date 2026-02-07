import { handleRouting, routes } from './router.js';
import { updateState } from './utils.js';
import { UI } from './ui.js';
import { Tracker } from './tracker.js';

// --- GLOBAL APP STATE & EXPORTS ---
window.app = {
    showToast: UI.showToast,
    showBetDetails: (id) => { /* Reuse or import logic */ },
    // ... expose methods needed by legacy onclick handlers
};

// --- INIT ---
document.addEventListener('DOMContentLoaded', () => {
    // Init Router
    window.addEventListener('hashchange', handleRouting);
    handleRouting();

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
