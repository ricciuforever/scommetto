<div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
    <!-- Quick Stats -->
    <div class="glass p-8 rounded-3xl border-l-4 border-blue-500">
        <div class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mb-1">Ruolo Utente</div>
        <div class="text-2xl font-black italic uppercase text-white"><?= strtoupper($user['role']) ?></div>
    </div>
    <div class="glass p-8 rounded-3xl border-l-4 border-green-500">
        <div class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mb-1">Agente Assegnato</div>
        <div class="text-2xl font-black italic uppercase text-white"><?= strtoupper($user['agent']) ?></div>
    </div>
    <div class="glass p-8 rounded-3xl border-l-4 border-purple-500">
        <div class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mb-1">Server Time</div>
        <div class="text-2xl font-black italic uppercase text-white"><?= date('H:i:s') ?></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="glass p-8 rounded-3xl">
        <h3 class="text-xl font-bold mb-6 text-white border-b border-gray-800 pb-4">Ultimi Log di Sistema</h3>
        <div class="space-y-4 font-mono text-[11px]">
            <?php
            $logFile = \App\Config\Config::LOGS_PATH . 'app_error.log';
            if (file_exists($logFile)) {
                $lines = array_slice(file($logFile), -10);
                foreach (array_reverse($lines) as $line) {
                    echo "<div class='p-2 bg-black/20 rounded border border-white/5 truncate' title='".htmlspecialchars($line)."'>" . htmlspecialchars($line) . "</div>";
                }
            } else {
                echo "<p class='text-gray-600'>Nessun log disponibile.</p>";
            }
            ?>
        </div>
    </div>

    <div class="glass p-8 rounded-3xl">
        <h3 class="text-xl font-bold mb-6 text-white border-b border-gray-800 pb-4">Azioni Rapide</h3>
        <div class="grid grid-cols-2 gap-4">
            <a href="/admin/strategy" class="p-6 bg-blue-600/10 hover:bg-blue-600/20 border border-blue-500/20 rounded-2xl text-center group transition-all">
                <i data-lucide="brain-circuit" class="w-8 h-8 mx-auto mb-3 text-blue-400 group-hover:scale-110 transition-transform"></i>
                <span class="block text-xs font-bold uppercase tracking-widest">Configura Agente</span>
            </a>
            <a href="/admin/war-room" class="p-6 bg-green-600/10 hover:bg-green-600/20 border border-green-500/20 rounded-2xl text-center group transition-all">
                <i data-lucide="database" class="w-8 h-8 mx-auto mb-3 text-green-400 group-hover:scale-110 transition-transform"></i>
                <span class="block text-xs font-bold uppercase tracking-widest">Esplora Dati</span>
            </a>
        </div>
    </div>
</div>
