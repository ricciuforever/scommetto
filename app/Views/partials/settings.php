<?php
// app/Views/partials/settings.php
use App\Config\Config;

$settings = Config::getSettings();
$simMode = $settings['simulation_mode'];
$initialBankroll = $settings['initial_bankroll'];
$virtualBookmakerId = $settings['virtual_bookmaker_id'];
?>
<div class="mb-12">
    <h1 class="text-4xl font-black italic uppercase tracking-tighter mb-2">Impostazioni <span
            class="text-accent">Scommetto PRO</span></h1>
    <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic">Area riservata amministratori</p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
    <!-- Simulation Settings -->
    <div class="glass p-10 rounded-[40px] border-white/5 space-y-8">
        <h2 class="text-2xl font-black italic uppercase text-white">Modalità Operativa</h2>

        <div class="flex items-center justify-between p-6 rounded-3xl bg-white/5 border border-white/5">
            <div>
                <div class="font-black uppercase italic text-white mb-1">Scommesse Simulate</div>
                <div class="text-[10px] uppercase font-bold text-slate-500 tracking-widest max-w-xs">
                    Attiva: Le scommesse vengono salvate nel DB ma NON inviate a Betfair.
                </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" id="settings-sim-toggle" class="sr-only peer" <?php echo $simMode ? 'checked' : ''; ?> onchange="updateSettingsFromPanel()">
                <div
                    class="w-14 h-7 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-accent">
                </div>
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="p-6 rounded-3xl bg-white/5 border border-white/5">
                <label class="block font-black uppercase italic text-white mb-2 text-xs">Budget Iniziale (€)</label>
                <input type="number" id="settings-bankroll" value="<?php echo $initialBankroll; ?>"
                    class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-2 text-white font-black italic focus:border-accent outline-none"
                    onchange="updateSettingsFromPanel()">
            </div>
            <div class="p-6 rounded-3xl bg-white/5 border border-white/5">
                <label class="block font-black uppercase italic text-white mb-2 text-xs">Bookmaker Virtuale (ID)</label>
                <input type="number" id="settings-bookmaker-id" value="<?php echo $virtualBookmakerId; ?>"
                    class="w-full bg-black/20 border border-white/10 rounded-xl px-4 py-2 text-white font-black italic focus:border-accent outline-none"
                    onchange="updateSettingsFromPanel()">
                <p class="text-[8px] text-slate-500 mt-2 font-bold uppercase">Default: 7 (William Hill)</p>
            </div>
        </div>

        <?php if ($simMode): ?>
            <div class="p-6 rounded-3xl bg-danger/5 border border-danger/20">
                <h3 class="text-danger font-black uppercase italic mb-2 text-sm">Reset Ambiente Virtuale</h3>
                <p class="text-[10px] text-slate-400 mb-4 font-bold uppercase tracking-widest">
                    Cancella tutte le scommesse simulate dal database e ripristina il budget scelto. Azione irreversibile.
                </p>
                <button onclick="resetSimulationPanel()"
                    class="w-full py-3 rounded-xl bg-danger text-white font-black uppercase tracking-widest hover:bg-red-600 transition-all shadow-lg shadow-danger/20 text-xs">
                    Reset Simulazione
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- API & System Status -->
    <div class="glass p-10 rounded-[40px] border-white/5 space-y-8">
        <h2 class="text-2xl font-black italic uppercase text-white">System Status</h2>

        <div class="space-y-4">
            <div class="flex justify-between items-center border-b border-white/5 pb-4">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">PHP Version</span>
                <span class="text-xs font-black text-white uppercase italic">
                    <?php echo phpversion(); ?>
                </span>
            </div>
            <div class="flex justify-between items-center border-b border-white/5 pb-4">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Betfair API</span>
                <span class="text-xs font-black text-success uppercase italic">Configured</span>
            </div>
            <div class="flex justify-between items-center border-b border-white/5 pb-4">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Database</span>
                <span class="text-xs font-black text-white uppercase italic">
                    <?php echo Config::get('DB_NAME'); ?>
                </span>
            </div>
            <div class="flex justify-between items-center border-b border-white/5 pb-4">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-widest">Server Time</span>
                <span class="text-xs font-black text-white uppercase italic">
                    <?php echo date('Y-m-d H:i:s'); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<script>
    async function updateSettingsFromPanel() {
        const toggle = document.getElementById('settings-sim-toggle');
        const bankroll = document.getElementById('settings-bankroll');
        const bookieId = document.getElementById('settings-bookmaker-id');

        const payload = {
            simulation_mode: toggle.checked,
            initial_bankroll: parseFloat(bankroll.value),
            virtual_bookmaker_id: parseInt(bookieId.value)
        };

        try {
            const res = await fetch('/api/settings/update', {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: { 'Content-Type': 'application/json' }
            });

            if (res.status === 401) {
                alert("Sessione scaduta. Ricarica la pagina.");
                location.reload();
                return;
            }

            const data = await res.json();
            if (data.status === 'success') {
                // Reload partial to update UI (e.g. show/hide reset button)
                htmx.ajax('GET', '/api/view/settings', '#htmx-container');
                // Update sidebar indicator
                initSimulationMode();
            } else {
                alert("Errore: " + data.message);
                toggle.checked = !newVal; // Revert
            }
        } catch (e) {
            alert("Errore di connessione.");
            toggle.checked = !newVal;
        }
    }

    async function resetSimulationPanel() {
        if (!confirm("CONFERMI IL RESET? Tutte le scommesse virtuali verranno cancellate.")) return;

        try {
            const res = await fetch('/api/simulation/reset', { method: 'POST' });
            if (res.status === 401) {
                alert("Sessione scaduta.");
                return;
            }
            alert("Simulazione resettata con successo.");
            htmx.ajax('GET', '/api/view/tracker', '#htmx-container');
        } catch (e) { alert("Errore durante il reset"); }
    }

    // Update title
    document.getElementById('view-title').textContent = 'Admin Settings';
</script>