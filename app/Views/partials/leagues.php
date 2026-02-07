<?php
// app/Views/partials/leagues.php
// HTMX Partial for Leagues List

// $leagues is passed from controller
$countries = [];
foreach ($leagues as $l) {
    $c = $l['country_name'] ?? $l['country'] ?? 'International';
    if (!in_array($c, $countries))
        $countries[] = $c;
}
sort($countries);
?>

<div id="leagues-view-content" class="animate-fade-in">
    <div class="mb-12 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <h1 class="text-4xl font-black italic uppercase tracking-tighter mb-2 text-white">Competizioni</h1>
            <p class="text-slate-500 font-bold uppercase tracking-widest text-xs italic">Esplora le leghe sincronizzate
                nel database di Scommetto.AI</p>
        </div>

        <div
            class="flex items-center gap-4 bg-white/5 p-2 rounded-2xl border border-white/10 group focus-within:border-accent/80 transition-all min-w-[240px]">
            <div
                class="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center text-slate-500 group-focus-within:text-accent transition-colors">
                <i data-lucide="filter" class="w-5 h-5"></i>
            </div>
            <select id="country-filter-php" onchange="filterLeagues(this.value)"
                class="flex-1 bg-transparent border-none text-sm font-black uppercase italic text-white focus:ring-0 cursor-pointer pr-10 appearance-none outline-none">
                <option value="all" class="bg-slate-900">Tutte le Nazioni</option>
                <?php foreach ($countries as $c): ?>
                    <option value="<?php echo htmlspecialchars($c); ?>" class="bg-slate-900">
                        <?php echo htmlspecialchars($c); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div id="leagues-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($leagues as $l):
            $country = $l['country_name'] ?? $l['country'] ?? 'International';
            $logo = $l['logo'];
            $name = $l['name'];
            ?>
            <div class="glass p-6 rounded-[32px] border-white/5 hover:border-accent/30 transition-all cursor-pointer group league-card active-card flex items-center gap-4"
                data-country="<?php echo htmlspecialchars($country); ?>"
                onclick="window.location.hash = 'leagues/<?php echo $l['id']; ?>'">

                <div
                    class="w-14 h-14 bg-white rounded-2xl p-2 flex items-center justify-center shadow-lg border border-white/10 overflow-hidden shrink-0">
                    <img src="<?php echo $logo; ?>"
                        class="max-w-full max-h-full object-contain group-hover:scale-110 transition-transform">
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-black uppercase italic text-white group-hover:text-accent transition-colors truncate">
                        <?php echo $name; ?>
                    </h3>
                    <p class="text-[10px] font-bold text-slate-500 tracking-widest uppercase">
                        <?php echo $country; ?>
                    </p>
                </div>
                <i data-lucide="chevron-right"
                    class="w-5 h-5 ml-auto text-slate-500 opacity-0 group-hover:opacity-100 group-hover:translate-x-1 transition-all"></i>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    if (window.lucide) lucide.createIcons();

    // Simple inline filter function
    function filterLeagues(val) {
        document.querySelectorAll('.league-card').forEach(card => {
            if (val === 'all' || card.getAttribute('data-country') === val) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    // Restore previous filter if needed
    // const saved = localStorage.getItem('selected_country');
    // if(saved) { document.getElementById('country-filter-php').value = saved; filterLeagues(saved); }
</script>