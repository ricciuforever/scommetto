<?php if($message): ?>
    <div class="bg-green-900/20 border border-green-500/50 text-green-400 p-6 mb-8 rounded-2xl text-center font-bold uppercase tracking-widest animate-pulse">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="max-w-4xl mx-auto">
    <div class="mb-8 border-b border-gray-800 pb-6">
        <h1 class="text-3xl font-black italic uppercase text-white tracking-tighter">Sistema <span class="text-accent">Global</span></h1>
        <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Configurazione globale della piattaforma</p>
    </div>

    <form method="POST" class="space-y-8">
        <div class="glass p-8 rounded-3xl">
            <div class="flex items-center gap-3 mb-6">
                <i data-lucide="settings" class="text-accent w-6 h-6"></i>
                <h3 class="text-lg font-black italic uppercase text-white">Parametri Generali</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Modalità Simulazione (Globale)</label>
                    <div class="flex items-center gap-3">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="simulation_mode" class="sr-only peer" <?= $settings['simulation_mode'] ? 'checked' : '' ?>>
                            <div class="w-11 h-6 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-accent"></div>
                        </label>
                        <span class="text-xs font-bold uppercase <?= $settings['simulation_mode'] ? 'text-accent' : 'text-gray-500' ?>">
                            <?= $settings['simulation_mode'] ? 'ATTIVA (Virtuale)' : 'DISATTIVATA (Reale)' ?>
                        </span>
                    </div>
                    <p class="text-[9px] text-gray-600 mt-2 italic">Se attiva, tutte le operazioni di base saranno simulate, indipendentemente dagli agenti.</p>
                </div>

                <div>
                    <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Bankroll Iniziale (€)</label>
                    <input type="number" step="0.01" name="initial_bankroll" value="<?= htmlspecialchars($settings['initial_bankroll']) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-accent">
                </div>
            </div>
        </div>

        <div class="pt-8">
            <button type="submit" class="w-full py-5 bg-gradient-to-r from-accent to-indigo-600 hover:from-accent hover:to-indigo-500 text-white font-black uppercase italic tracking-tighter text-lg rounded-2xl shadow-2xl shadow-accent/20 transition-all">
                Aggiorna Configurazione Sistema
            </button>
        </div>
    </form>
</div>
