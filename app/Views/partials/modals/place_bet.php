<?php
// app/Views/partials/modals/place_bet.php
// HTMX Modal for Placing Bet
?>
<div id="place-bet-modal"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-xl animate-fade-in"
    onclick="if(event.target === this) this.remove()">

    <div class="bg-slate-900 w-full max-w-lg rounded-[40px] border border-white/10 shadow-2xl overflow-hidden relative">
        <button onclick="document.getElementById('place-bet-modal').remove()"
            class="absolute top-6 right-6 w-12 h-12 rounded-2xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-all text-white cursor-pointer z-10">
            <i data-lucide="x" class="w-6 h-6"></i>
        </button>

        <div class="p-10" id="place-bet-modal-content">
            <h3 class="text-3xl font-black mb-2 tracking-tight text-white uppercase italic">Nuova <span
                    class="text-accent">Scommessa</span></h3>
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-8">Piazza la tua giocata su
                questo evento</p>

            <form hx-post="/api/place_bet" hx-target="#place-bet-modal-content" hx-swap="innerHTML">
                <input type="hidden" name="fixture_id" value="<?php echo htmlspecialchars($fixtureId); ?>">
                <input type="hidden" name="market" value="<?php echo htmlspecialchars($market); ?>">
                <input type="hidden" name="advice" value="<?php echo htmlspecialchars($selection); ?>">
                <!-- Note: using 'advice' key to map to existing backend logic where advice = selection -->
                <input type="hidden" name="odds" value="<?php echo htmlspecialchars($odd); ?>">
                <!-- Match name fallback if needed by backend -->
                <input type="hidden" name="match_name" value="<?php echo htmlspecialchars($eventName); ?>">

                <div class="space-y-6">
                    <div class="glass p-6 rounded-3xl border-white/5 bg-accent/5">
                        <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Evento</div>
                        <div class="text-lg font-black italic uppercase text-white truncate">
                            <?php echo htmlspecialchars($eventName); ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="glass p-5 rounded-3xl border-white/5">
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Selezione
                            </div>
                            <div class="text-sm font-black italic uppercase text-white truncate">
                                <?php echo htmlspecialchars($selection); ?>
                            </div>
                        </div>
                        <div class="glass p-5 rounded-3xl border-white/5">
                            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1">Quota</div>
                            <div class="text-2xl font-black italic uppercase text-accent">
                                <?php echo htmlspecialchars($odd); ?>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label
                                class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-2 ml-2">Puntata
                                (€)</label>
                            <input type="number" name="stake" value="10" step="0.5" min="2" required
                                class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-2xl font-black text-white focus:border-accent focus:ring-0 outline-none transition-all placeholder-white/20">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-2 ml-2">Confidenza
                                (%)</label>
                            <input type="number" name="confidence" value="100" min="1" max="100"
                                class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-xl font-bold text-white focus:border-accent focus:ring-0 outline-none transition-all">
                        </div>
                        <div>
                            <label
                                class="text-[10px] font-bold text-slate-500 uppercase tracking-widest block mb-2 ml-2">Note
                                (Opzionale)</label>
                            <textarea name="notes" rows="2"
                                class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-sm font-medium text-white focus:border-accent focus:ring-0 outline-none transition-all resize-none"></textarea>
                        </div>
                    </div>

                    <button type="submit"
                        class="w-full bg-accent hover:bg-sky-400 text-white font-black uppercase tracking-widest py-5 rounded-2xl shadow-lg shadow-accent/20 transition-all hover:scale-[1.02] active:scale-95 text-xs">
                        Conferma Giocata
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>if (window.lucide) lucide.createIcons();</script>
</div>