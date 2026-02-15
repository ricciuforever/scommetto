<?php if($message): ?>
    <div class="bg-green-900/20 border border-green-500/50 text-green-400 p-6 mb-8 rounded-2xl text-center font-bold uppercase tracking-widest animate-pulse">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-8 border-b border-gray-800 pb-6">
        <div>
            <h1 class="text-3xl font-black italic uppercase text-white tracking-tighter">Strategy <span class="text-blue-500">Lab</span></h1>
            <p class="text-xs text-gray-500 font-bold uppercase tracking-widest mt-1">Configurazione Agente: <?= strtoupper($currentAgent) ?></p>
        </div>

        <div class="flex gap-2">
            <?php foreach ($agents as $a): ?>
                <a href="?agent=<?= $a ?>" class="px-6 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-all <?= $currentAgent === $a ? 'bg-blue-600 border-blue-500 text-white' : 'bg-gray-800 border-gray-700 text-gray-500 hover:text-white' ?>">
                    <?= $a ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <form method="POST" class="space-y-8">
        <!-- System Prompt -->
        <div class="glass p-8 rounded-3xl">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <i data-lucide="brain" class="text-blue-500 w-6 h-6"></i>
                    <h3 class="text-lg font-black italic uppercase text-white">System Strategy Prompt</h3>
                </div>
                <button type="button" onclick="resetPrompt()" class="px-3 py-1 bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white text-[9px] font-bold uppercase tracking-widest rounded-lg border border-white/5 transition-all">
                    Ripristina Default
                </button>
            </div>
            <textarea id="strategy_prompt" name="config[strategy_prompt]" class="w-full bg-black/40 border border-white/5 rounded-2xl p-6 text-sm font-mono text-blue-200 focus:border-blue-500 outline-none h-64 leading-relaxed" placeholder="Inserisci qui il prompt strategico per l'AI..."><?= htmlspecialchars($config['strategy_prompt'] ?? '') ?></textarea>

            <!-- Placeholder Legend -->
            <div class="mt-6">
                <h4 class="text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Dati disponibili per l'AI (Placeholder):</h4>
                <div class="flex flex-wrap gap-2">
                    <?php
                    $placeholders = ($currentAgent === 'gianik') ? [
                        '{{portfolio_stats}}' => 'Saldo e Budget',
                        '{{event_markets}}' => 'Quote e Mercati',
                        '{{live_match_data}}' => 'Score e Minuto',
                        '{{live_statistics}}' => 'Stats (Tiri, Angoli)',
                        '{{momentum}}' => 'Intensità 10 min',
                        '{{historical_context}}' => 'H2H e Classifica',
                        '{{ai_lessons}}' => 'Lezioni Passate',
                        '{{live_events}}' => 'Gol e Cartellini',
                        '{{events_batch}}' => 'Batch Eventi (Auto)'
                    ] : [
                        '{{portfolio_stats}}' => 'Saldo e Budget',
                        '{{candidates_list}}' => 'Eventi e Price Action'
                    ];
                    foreach($placeholders as $ph => $desc): ?>
                        <button type="button" onclick="insertAtCursor('<?= $ph ?>')" class="px-2 py-1 bg-blue-500/10 border border-blue-500/20 rounded text-[9px] text-blue-400 font-bold hover:bg-blue-500/20 transition-all cursor-pointer" title="<?= $desc ?>">
                            <?= $ph ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="text-[9px] text-gray-600 mt-3 italic">Clicca su un placeholder per inserirlo nel prompt. Se non ne usi nessuno, i dati verranno appesi automaticamente in coda.</p>
            </div>

            <p class="text-[10px] text-gray-600 mt-6 uppercase font-bold tracking-widest border-t border-white/5 pt-4">Questo prompt definisce il "carattere" e le priorità dell'Agente durante l'analisi.</p>
        </div>

        <script>
            function insertAtCursor(text) {
                const textarea = document.getElementById('strategy_prompt');
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + text + textarea.value.substring(end);
                textarea.focus();
                textarea.selectionStart = textarea.selectionEnd = start + text.length;
            }
        </script>

        <script>
            function resetPrompt() {
                const defaults = {
                    'gianik': `<?= addslashes((new \App\Services\GeminiService())->getDefaultStrategyPrompt('gianik')) ?>`,
                    'dio': `<?= addslashes((new \App\Services\GeminiService())->getDefaultStrategyPrompt('dio')) ?>`
                };
                if (confirm("Sei sicuro di voler ripristinare il prompt predefinito per <?= strtoupper($currentAgent) ?>? Tutte le modifiche attuali verranno perse.")) {
                    document.getElementById('strategy_prompt').value = defaults['<?= $currentAgent ?>'] || '';
                }
            }
        </script>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Staking Mode -->
            <div class="glass p-8 rounded-3xl">
                <div class="flex items-center gap-3 mb-6">
                    <i data-lucide="calculator" class="text-green-500 w-6 h-6"></i>
                    <h3 class="text-lg font-black italic uppercase text-white">Gestione Stake</h3>
                </div>

                <div class="space-y-6">
                    <?php if ($currentAgent === 'dio'): ?>
                    <!-- Sport Targeting -->
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Sport in Target (Quantum)</label>
                        <div class="grid grid-cols-2 gap-2">
                            <?php
                            $availableSports = [
                                '1' => 'Soccer (Calcio)',
                                '2' => 'Tennis',
                                '7522' => 'Basketball',
                                '5' => 'Rugby Union'
                            ];
                            $currentTargets = explode(',', $config['target_sports'] ?? '1');
                            foreach ($availableSports as $id => $label): ?>
                                <label class="flex items-center gap-2 p-3 bg-slate-900/50 border border-slate-700/50 rounded-xl cursor-pointer hover:bg-slate-800 transition-all">
                                    <input type="checkbox" name="config[target_sports][]" value="<?= $id ?>" <?= in_array($id, $currentTargets) ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-700 text-blue-600 focus:ring-blue-500 bg-gray-900">
                                    <span class="text-[10px] font-bold text-gray-300 uppercase"><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Modalità Puntata</label>
                        <select name="config[stake_mode]" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-xs font-bold uppercase outline-none focus:border-blue-500">
                            <option value="kelly" <?= ($config['stake_mode'] ?? '') === 'kelly' ? 'selected' : '' ?>>Kelly Criterion (Dinamico)</option>
                            <option value="flat" <?= ($config['stake_mode'] ?? '') === 'flat' ? 'selected' : '' ?>>Flat Bet (Importo Fisso)</option>
                            <option value="percentage" <?= ($config['stake_mode'] ?? '') === 'percentage' ? 'selected' : '' ?>>Percentuale Saldo</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Valore Moltiplicatore / Fisso</label>
                        <input type="number" step="0.01" name="config[stake_value]" value="<?= htmlspecialchars($config['stake_value'] ?? '0.15') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-blue-500">
                        <p class="text-[9px] text-gray-600 mt-2 italic">Kelly: 0.15 = 15% di Kelly frazionario. Flat: 5.00 = 5€ fissi.</p>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Puntata Minima (€)</label>
                        <input type="number" step="0.01" min="2.00" name="config[min_stake]" value="<?= htmlspecialchars($config['min_stake'] ?? '2.00') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-blue-500">
                        <p class="text-[9px] text-gray-600 mt-2 italic">Minimo assoluto: 2.00€ (limite Betfair).</p>
                    </div>
                </div>
            </div>

            <!-- Risk Management -->
            <div class="glass p-8 rounded-3xl">
                <div class="flex items-center gap-3 mb-6">
                    <i data-lucide="shield-alert" class="text-red-500 w-6 h-6"></i>
                    <h3 class="text-lg font-black italic uppercase text-white">Gestione Rischio & Modalità</h3>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Modalità Operativa (Agente)</label>
                        <select name="config[operational_mode]" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-xs font-bold uppercase outline-none focus:border-blue-500">
                            <option value="real" <?= ($config['operational_mode'] ?? 'real') === 'real' ? 'selected' : '' ?>>Real (Scommesse Reali)</option>
                            <option value="virtual" <?= ($config['operational_mode'] ?? 'real') === 'virtual' ? 'selected' : '' ?>>Virtual (Simulazione)</option>
                        </select>
                        <p class="text-[9px] text-gray-600 mt-2 italic">Specifica se questo agente deve operare su fondi reali o simulati.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Confidenza Minima (%)</label>
                            <input type="number" name="config[min_confidence]" value="<?= htmlspecialchars($config['min_confidence'] ?? '80') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Liquidità Minima (€)</label>
                            <input type="number" step="100" name="config[min_liquidity]" value="<?= htmlspecialchars($config['min_liquidity'] ?? '2000') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-blue-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Stop Loss Giornaliero (€)</label>
                        <input type="number" step="0.01" name="config[daily_stop_loss]" value="<?= htmlspecialchars($config['daily_stop_loss'] ?? '50.00') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-blue-500">
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t border-white/5 pt-6">
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Bankroll Iniziale (€)</label>
                            <input type="number" step="1" name="config[initial_bankroll]" value="<?= htmlspecialchars($config['initial_bankroll'] ?? '100') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Ricalibrazione P&L (€)</label>
                            <input type="number" step="0.01" name="config[initial_pnl_adjustment]" value="<?= htmlspecialchars($config['initial_pnl_adjustment'] ?? '0.00') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono outline-none focus:border-blue-500">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Credentials (Custom) -->
            <div class="glass p-8 rounded-3xl md:col-span-2">
                <div class="flex items-center gap-3 mb-6">
                    <i data-lucide="key" class="text-yellow-500 w-6 h-6"></i>
                    <h3 class="text-lg font-black italic uppercase text-white">Integrazione Credenziali (Personali)</h3>
                </div>
                <p class="text-[10px] text-gray-400 mb-6 uppercase font-bold tracking-widest italic">
                    Inserisci qui le tue credenziali personali se desideri che l'agente operi sul tuo conto invece che su quello di sistema.
                </p>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Gemini API Key</label>
                        <input type="text" name="config[GEMINI_API_KEY]" value="<?= htmlspecialchars($config['GEMINI_API_KEY'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-[10px] font-mono outline-none focus:border-blue-500" placeholder="LASCIA VUOTO PER SISTEMA">
                    </div>
                    <div class="hidden lg:block"></div> <!-- Spacer -->
                    <div class="hidden lg:block"></div> <!-- Spacer -->

                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Betfair App Key (LIVE)</label>
                        <input type="text" name="config[BETFAIR_APP_KEY_LIVE]" value="<?= htmlspecialchars($config['BETFAIR_APP_KEY_LIVE'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-[10px] font-mono outline-none focus:border-blue-500" placeholder="LASCIA VUOTO PER SISTEMA">
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Betfair App Key (DELAY)</label>
                        <input type="text" name="config[BETFAIR_APP_KEY_DELAY]" value="<?= htmlspecialchars($config['BETFAIR_APP_KEY_DELAY'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-[10px] font-mono outline-none focus:border-blue-500" placeholder="LASCIA VUOTO PER SISTEMA">
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Betfair Username</label>
                        <input type="text" name="config[BETFAIR_USERNAME]" value="<?= htmlspecialchars($config['BETFAIR_USERNAME'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-[10px] font-mono outline-none focus:border-blue-500" placeholder="LASCIA VUOTO PER SISTEMA">
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Betfair Password</label>
                        <input type="password" name="config[BETFAIR_PASSWORD]" value="<?= htmlspecialchars($config['BETFAIR_PASSWORD'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-[10px] font-mono outline-none focus:border-blue-500" placeholder="••••••••">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3">Betfair Identity SSO URL (Opzionale)</label>
                        <input type="text" name="config[BETFAIR_SSO_URL]" value="<?= htmlspecialchars($config['BETFAIR_SSO_URL'] ?? '') ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-[10px] font-mono outline-none focus:border-blue-500" placeholder="Es: https://identitysso.betfair.it/api/certlogin">
                    </div>
                </div>
            </div>
        </div>

        <div class="pt-8">
            <button type="submit" class="w-full py-5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-black uppercase italic tracking-tighter text-lg rounded-2xl shadow-2xl shadow-blue-500/20 transition-all">
                Salva Nuova Strategia di Comando
            </button>
        </div>
    </form>
</div>
