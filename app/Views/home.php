<?php
// app/Views/home.php
require __DIR__ . '/layout/top.php';
?>

<div class="space-y-12 py-8">
    <!-- Hero Section -->
    <section class="text-center space-y-6 max-w-3xl mx-auto px-4">
        <div class="inline-block p-3 rounded-2xl bg-accent/10 border border-accent/20 animate-bounce">
            <i data-lucide="brain-circuit" class="text-accent w-8 h-8"></i>
        </div>
        <h1 class="text-4xl md:text-6xl font-black italic uppercase tracking-tighter text-white leading-none">
            Benvenuti in <span class="text-accent text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-indigo-400">Scommetto.AI</span>
        </h1>
        <p class="text-lg md:text-xl text-slate-400 font-medium leading-relaxed">
            La piattaforma di trading sportivo guidata dall'intelligenza artificiale.
            Algoritmi avanzati, analisi dei volumi e strategie predittive per dominare i mercati Betfair.
        </p>
    </section>

    <!-- Better Agents Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto px-4">

        <!-- GiaNik Card -->
        <div class="glass p-8 rounded-[40px] border-white/5 hover:border-accent/30 transition-all group flex flex-col justify-between">
            <div class="space-y-4">
                <div class="flex justify-between items-start">
                    <div class="w-16 h-16 bg-accent rounded-3xl flex items-center justify-center shadow-2xl shadow-accent/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="zap" class="text-white w-8 h-8 font-black"></i>
                    </div>
                    <div class="bg-accent/10 px-4 py-1.5 rounded-full border border-accent/20">
                        <span class="text-[10px] font-black text-accent uppercase tracking-widest">Active Agent</span>
                    </div>
                </div>
                <h2 class="text-3xl font-black italic uppercase tracking-tighter text-white">GiaNik <span class="text-accent">Live</span></h2>
                <p class="text-slate-400 text-sm font-medium leading-relaxed">
                    L'agente specializzato nel calcio live. Analizza intensità di gioco, momentum e variazioni di quota in tempo reale per identificare value bet istantanee.
                </p>

                <div class="grid grid-cols-3 gap-4 pt-4">
                    <div class="bg-black/20 p-3 rounded-2xl border border-white/5 text-center">
                        <span class="text-[9px] font-black text-slate-500 uppercase block">Bets</span>
                        <span class="text-sm font-black text-white"><?= number_format($gianikStats['total_bets'] ?? 0) ?></span>
                    </div>
                    <div class="bg-black/20 p-3 rounded-2xl border border-white/5 text-center">
                        <span class="text-[9px] font-black text-slate-500 uppercase block">Win Rate</span>
                        <span class="text-sm font-black text-white"><?= $gianikStats['total_bets'] > 0 ? round(($gianikStats['wins'] / $gianikStats['total_bets']) * 100, 1) : 0 ?>%</span>
                    </div>
                    <div class="bg-black/20 p-3 rounded-2xl border border-white/5 text-center">
                        <span class="text-[9px] font-black text-slate-500 uppercase block">P&L Net</span>
                        <span class="text-sm font-black <?= ($gianikStats['net_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($gianikStats['net_profit'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($gianikStats['net_profit'] ?? 0, 2) ?>€
                        </span>
                    </div>
                </div>
            </div>
            <div class="pt-8">
                <a href="/gianik/brain" class="w-full py-4 bg-white/5 hover:bg-accent text-white font-black uppercase italic tracking-tighter rounded-2xl border border-white/10 group-hover:border-accent transition-all flex items-center justify-center gap-2">
                    <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Performance Storiche
                </a>
            </div>
        </div>

        <!-- Dio Card -->
        <div class="glass p-8 rounded-[40px] border-white/5 hover:border-indigo-500/30 transition-all group flex flex-col justify-between">
            <div class="space-y-4">
                <div class="flex justify-between items-start">
                    <div class="w-16 h-16 bg-indigo-600 rounded-3xl flex items-center justify-center shadow-2xl shadow-indigo-500/20 group-hover:scale-110 transition-transform">
                        <i data-lucide="eye" class="text-white w-8 h-8"></i>
                    </div>
                    <div class="bg-indigo-500/10 px-4 py-1.5 rounded-full border border-indigo-500/20">
                        <span class="text-[10px] font-black text-indigo-400 uppercase tracking-widest">Quantum Trader</span>
                    </div>
                </div>
                <h2 class="text-3xl font-black italic uppercase tracking-tighter text-white">Modulo <span class="text-indigo-400">Dio</span></h2>
                <p class="text-slate-400 text-sm font-medium leading-relaxed">
                    Il trader quantistico multisport. Utilizza la Price Action e il Tape Reading dei mercati Betfair per operare con precisione chirurgica su volumi e flussi.
                </p>

                <div class="grid grid-cols-3 gap-4 pt-4">
                    <div class="bg-black/20 p-3 rounded-2xl border border-white/5 text-center">
                        <span class="text-[9px] font-black text-slate-500 uppercase block">Bets</span>
                        <span class="text-sm font-black text-white"><?= number_format($dioStats['total_bets'] ?? 0) ?></span>
                    </div>
                    <div class="bg-black/20 p-3 rounded-2xl border border-white/5 text-center">
                        <span class="text-[9px] font-black text-slate-500 uppercase block">Win Rate</span>
                        <span class="text-sm font-black text-white"><?= $dioStats['total_bets'] > 0 ? round(($dioStats['wins'] / $dioStats['total_bets']) * 100, 1) : 0 ?>%</span>
                    </div>
                    <div class="bg-black/20 p-3 rounded-2xl border border-white/5 text-center">
                        <span class="text-[9px] font-black text-slate-500 uppercase block">P&L</span>
                        <span class="text-sm font-black <?= ($dioStats['total_profit'] ?? 0) >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($dioStats['total_profit'] ?? 0) >= 0 ? '+' : '' ?><?= number_format($dioStats['total_profit'] ?? 0, 2) ?>€
                        </span>
                    </div>
                </div>
            </div>
            <div class="pt-8">
                <a href="/dio" class="w-full py-4 bg-white/5 hover:bg-indigo-600 text-white font-black uppercase italic tracking-tighter rounded-2xl border border-white/10 group-hover:border-indigo-500 transition-all flex items-center justify-center gap-2">
                    <i data-lucide="trending-up" class="w-4 h-4"></i> Analisi Quantistica
                </a>
            </div>
        </div>

    </div>

    <!-- How it Works -->
    <section class="max-w-5xl mx-auto px-4 space-y-8">
        <h3 class="text-2xl font-black italic uppercase tracking-tighter text-white text-center">Come Funziona il <span class="text-accent">Sistema</span></h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 space-y-3">
                <div class="text-accent font-black text-4xl opacity-20 italic">01</div>
                <h4 class="text-lg font-black uppercase italic text-white leading-tight">Data Ingestion</h4>
                <p class="text-xs text-slate-500 leading-relaxed font-bold uppercase tracking-widest">
                    Il sistema monitora migliaia di eventi sportivi al secondo tramite API dirette e flussi Betfair.
                </p>
            </div>
            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 space-y-3">
                <div class="text-indigo-400 font-black text-4xl opacity-20 italic">02</div>
                <h4 class="text-lg font-black uppercase italic text-white leading-tight">AI Processing</h4>
                <p class="text-xs text-slate-500 leading-relaxed font-bold uppercase tracking-widest">
                    Gemini AI analizza statistiche live, momentum e parametri storici per calcolare la confidenza reale.
                </p>
            </div>
            <div class="bg-white/5 p-6 rounded-3xl border border-white/5 space-y-3">
                <div class="text-success font-black text-4xl opacity-20 italic">03</div>
                <h4 class="text-lg font-black uppercase italic text-white leading-tight">Smart Betting</h4>
                <p class="text-xs text-slate-500 leading-relaxed font-bold uppercase tracking-widest">
                    Viene applicata la strategia di Kelly o Flat Stake per ottimizzare il profitto e minimizzare il rischio.
                </p>
            </div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/layout/bottom.php'; ?>
