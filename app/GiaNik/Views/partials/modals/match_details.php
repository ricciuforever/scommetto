<!-- app/GiaNik/Views/partials/modals/match_details.php -->
<div id="match-details-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">
    <div class="bg-slate-900 w-full max-w-6xl max-h-[90vh] rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative flex flex-col"
        onclick="event.stopPropagation()">

        <!-- Header -->
        <div class="p-8 border-b border-white/5 flex-shrink-0">
            <button onclick="document.getElementById('match-details-modal').remove()"
                class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
                <i data-lucide="x" class="w-6 h-6"></i>
            </button>

            <div class="flex items-center justify-between">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-4 cursor-pointer hover:opacity-80 transition-opacity"
                        onclick="openTeamModal(matchData.fixture.team_home_id)">
                        <img src="" id="match-home-logo" class="w-16 h-16 object-contain" alt="">
                        <div>
                            <div class="text-2xl font-black text-white uppercase italic" id="match-home-name"></div>
                            <div class="text-xs text-slate-500 font-bold uppercase tracking-wider">Home</div>
                        </div>
                    </div>

                    <div class="px-8">
                        <div class="text-5xl font-black text-white text-center" id="match-score">0-0</div>
                        <div class="text-xs text-accent font-black uppercase tracking-widest text-center mt-2"
                            id="match-status">NS</div>
                    </div>

                    <div class="flex items-center gap-4 cursor-pointer hover:opacity-80 transition-opacity"
                        onclick="openTeamModal(matchData.fixture.team_away_id)">
                        <div class="text-right">
                            <div class="text-2xl font-black text-white uppercase italic" id="match-away-name"></div>
                            <div class="text-xs text-slate-500 font-bold uppercase tracking-wider">Away</div>
                        </div>
                        <img src="" id="match-away-logo" class="w-16 h-16 object-contain" alt="">
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-xs text-slate-500 font-bold uppercase tracking-wider" id="match-league"></div>
                    <div class="text-xs text-slate-600 font-bold mt-1" id="match-date"></div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 px-8 pt-6 border-b border-white/5 flex-shrink-0">
            <button onclick="switchMatchTab('stats')" id="tab-stats"
                class="match-tab px-6 py-3 rounded-t-2xl font-black text-xs uppercase tracking-wider transition-all bg-accent text-white">
                📊 Live Stats
            </button>
            <button onclick="switchMatchTab('events')" id="tab-events"
                class="match-tab px-6 py-3 rounded-t-2xl font-black text-xs uppercase tracking-wider transition-all text-slate-500 hover:text-white">
                ⚽ Eventi
            </button>
            <button onclick="switchMatchTab('lineups')" id="tab-lineups"
                class="match-tab px-6 py-3 rounded-t-2xl font-black text-xs uppercase tracking-wider transition-all text-slate-500 hover:text-white">
                📋 Formazioni
            </button>
            <button onclick="switchMatchTab('h2h')" id="tab-h2h"
                class="match-tab px-6 py-3 rounded-t-2xl font-black text-xs uppercase tracking-wider transition-all text-slate-500 hover:text-white">
                🔄 H2H & Form
            </button>
            <button onclick="switchMatchTab('predictions')" id="tab-predictions"
                class="match-tab px-6 py-3 rounded-t-2xl font-black text-xs uppercase tracking-wider transition-all text-slate-500 hover:text-white">
                🔮 Predictions
            </button>
        </div>

        <!-- Content -->
        <div class="flex-1 overflow-y-auto p-8">
            <!-- Tab: Live Stats -->
            <div id="content-stats" class="match-tab-content">
                <div class="grid grid-cols-2 gap-6 mb-8">
                    <!-- Home Stats -->
                    <div class="glass p-6 rounded-3xl border-white/5">
                        <h4 class="text-xs font-black text-accent uppercase tracking-widest mb-4">Home Statistics</h4>
                        <div id="home-stats" class="space-y-3"></div>
                    </div>
                    <!-- Away Stats -->
                    <div class="glass p-6 rounded-3xl border-white/5">
                        <h4 class="text-xs font-black text-accent uppercase tracking-widest mb-4">Away Statistics</h4>
                        <div id="away-stats" class="space-y-3"></div>
                    </div>
                </div>
            </div>

            <!-- Tab: Events -->
            <div id="content-events" class="match-tab-content hidden">
                <div class="space-y-3" id="events-timeline"></div>
            </div>

            <!-- Tab: Lineups -->
            <div id="content-lineups" class="match-tab-content hidden">
                <div class="grid grid-cols-2 gap-6">
                    <div id="lineup-home"></div>
                    <div id="lineup-away"></div>
                </div>
            </div>

            <!-- Tab: H2H -->
            <div id="content-h2h" class="match-tab-content hidden">
                <div id="h2h-content"></div>
            </div>

            <!-- Tab: Predictions -->
            <div id="content-predictions" class="match-tab-content hidden">
                <div id="predictions-content"></div>
            </div>
        </div>
    </div>

    <script>
        let matchData = null;

        function switchMatchTab(tab) {
            // Update tabs
            document.querySelectorAll('.match-tab').forEach(t => {
                t.classList.remove('bg-accent', 'text-white');
                t.classList.add('text-slate-500');
            });
            document.getElementById('tab-' + tab).classList.add('bg-accent', 'text-white');
            document.getElementById('tab-' + tab).classList.remove('text-slate-500');

            // Update content
            document.querySelectorAll('.match-tab-content').forEach(c => c.classList.add('hidden'));
            document.getElementById('content-' + tab).classList.remove('hidden');
        }

        function renderMatchStats(stats) {
            const homeStats = stats.find(s => s.team_id == matchData.fixture.team_home_id);
            const awayStats = stats.find(s => s.team_id == matchData.fixture.team_away_id);

            if (!homeStats || !awayStats) return;

            const statTypes = ['Ball Possession', 'Total Shots', 'Shots on Goal', 'Corner Kicks', 'Fouls', 'Yellow Cards', 'Total passes', 'Passes %'];

            const renderStat = (stat, container) => {
                const statsJson = JSON.parse(stat.stats_json || '[]');
                let html = '';
                statTypes.forEach(type => {
                    const item = statsJson.find(s => s.type === type);
                    const value = item?.value || 0;
                    html += `
                    <div class="flex justify-between items-center py-2 border-b border-white/5">
                        <span class="text-xs text-slate-500 font-bold uppercase">${type}</span>
                        <span class="text-sm font-black text-white">${value}</span>
                    </div>
                `;
                });
                container.innerHTML = html;
            };

            renderStat(homeStats, document.getElementById('home-stats'));
            renderStat(awayStats, document.getElementById('away-stats'));
        }

        function renderEvents(events) {
            const container = document.getElementById('events-timeline');
            if (!events || events.length === 0) {
                container.innerHTML = '<div class="text-center text-slate-500 py-8">Nessun evento registrato</div>';
                return;
            }

            let html = '';
            events.forEach(event => {
                const icon = event.type === 'Goal' ? '⚽' : event.type === 'Card' ? (event.detail === 'Yellow Card' ? '🟨' : '🟥') : '🔄';
                html += `
                <div class="glass p-4 rounded-2xl border-white/5 flex items-center gap-4 cursor-pointer hover:bg-white/5 transition-all" onclick="openPlayerModal(${event.player_id})">
                    <div class="text-2xl">${icon}</div>
                    <div class="flex-1">
                        <div class="text-sm font-black text-white">${event.detail}</div>
                        <div class="text-xs text-slate-500 mt-1">Giocatore ID: ${event.player_id}</div>
                    </div>
                    <div class="text-accent font-black text-sm">${event.time_elapsed}'</div>
                </div>
            `;
            });
            container.innerHTML = html;
        }

        function openPlayerModal(playerId) {
            // TODO: Fetch player data and show modal
            console.log('Open player modal:', playerId);
        }

        function openTeamModal(teamId) {
            // TODO: Fetch team data and show modal
            console.log('Open team modal:', teamId);
        }

        // Load match data
        async function loadMatchData(fixtureId) {
            try {
                const response = await fetch(`/api/match-details/${fixtureId}`);
                matchData = await response.json();

                // Update header
                document.getElementById('match-home-logo').src = matchData.fixture.team_home_logo;
                document.getElementById('match-away-logo').src = matchData.fixture.team_away_logo;
                document.getElementById('match-home-name').textContent = matchData.fixture.team_home_name;
                document.getElementById('match-away-name').textContent = matchData.fixture.team_away_name;
                document.getElementById('match-score').textContent = `${matchData.fixture.score_home || 0}-${matchData.fixture.score_away || 0}`;
                document.getElementById('match-status').textContent = matchData.fixture.status_short + (matchData.fixture.elapsed ? ` ${matchData.fixture.elapsed}'` : '');
                document.getElementById('match-league').textContent = matchData.fixture.league_name;
                document.getElementById('match-date').textContent = new Date(matchData.fixture.date).toLocaleString('it-IT');

                // Render tabs
                renderMatchStats(matchData.statistics);
                renderEvents(matchData.events);
                // TODO: render lineups, h2h, predictions

            } catch (error) {
                console.error('Error loading match data:', error);
            }
        }

        if (window.lucide) lucide.createIcons();
    </script>
</div>