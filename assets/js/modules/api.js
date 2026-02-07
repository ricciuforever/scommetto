export async function fetchJson(endpoint, options = {}) {
    try {
        const response = await fetch(endpoint, options);
        if (!response.ok) {
            throw new Error(`API call failed: ${response.statusText}`);
        }
        try {
            return await response.json();
        } catch {
            return { error: 'Invalid JSON response' };
        }
    } catch (error) {
        console.error("API Error", error);
        return { error: error.message };
    }
}

export const endpoints = {
    live: "/api/live",
    upcoming: "/api/upcoming",
    history: "/api/history",
    filters: "/api/filters",
    leagues: "/api/competitions",
    predictions: "/api/predictions",
    usage: "/api/usage",
    betDelete: "/api/bets/delete/",
    betDeduplicate: "api/bets/deduplicate",
    betPlace: "/api/place_bet",
    analyze: "/api/analyze/",
    sync: "/api/sync"
};
