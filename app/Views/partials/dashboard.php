<?php
// app/Views/partials/dashboard.php
// Semplificato per Betfair-only rendering via JS
?>

<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-10" id="stats-summary">
    <!-- Popolato da JS updateStatsSummary() -->
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8" id="dashboard-content">
    <!-- Live Matches Column -->
    <div class="lg:col-span-2 space-y-6" id="live-matches-list">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10" id="live-header">
            <h2 class="text-4xl font-black italic uppercase tracking-tighter text-white leading-none">Live Now <span class="text-accent">.</span></h2>
            <div class="flex flex-wrap items-center gap-3">
                <span class="px-4 py-2 bg-accent/10 text-accent rounded-2xl text-[10px] font-black uppercase tracking-widest border border-accent/20">
                    <span id="live-active-count">0</span> Active
                </span>
            </div>
        </div>

        <div id="live-matches-grid" class="space-y-6">
             <div class="glass p-20 text-center font-black uppercase italic text-slate-500">Inizializzazione match live...</div>
        </div>

        <div id="upcoming-matches-container"></div>
    </div>

    <!-- Sidebar / Stats Column -->
    <aside class="space-y-8">
        <div>
            <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Hot Predictions</h2>
            <div id="dashboard-predictions" class="space-y-4">
                 <div class="text-[10px] font-bold text-slate-500 italic uppercase">Caricamento pronostici...</div>
            </div>
        </div>
        <div>
            <h2 class="text-xl font-black tracking-tight mb-6 italic uppercase">Recent Activity</h2>
            <div class="glass rounded-[32px] border-white/5 divide-y divide-white/5 overflow-hidden" id="dashboard-history">
                 <div class="p-10 text-center text-slate-500 font-bold text-[10px] uppercase italic">Caricamento attività...</div>
            </div>
        </div>
    </aside>
</div>
<script>
    if (window.lucide) lucide.createIcons();
    if (typeof updateStatsSummary === 'function') updateStatsSummary();
</script>
