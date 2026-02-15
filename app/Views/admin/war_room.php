<?php if($message): ?>
    <div class="bg-gray-800 border-b border-gray-600 p-4 mb-6 text-center text-sm font-bold text-yellow-400 animate-pulse rounded-xl">
        <?= $message ?>
    </div>
<?php endif; ?>

<div class="flex flex-1 overflow-hidden gap-8 h-full">

    <!-- Sidebar Tabelle -->
    <div class="w-64 bg-gray-900/50 rounded-3xl border border-gray-800 flex flex-col shrink-0 overflow-hidden">
        <div class="p-4 text-[10px] font-bold text-gray-500 uppercase border-b border-gray-800 flex justify-between items-center">
            <span>Tabelle: <?= $dbConfig['name'] ?></span>
            <span class="<?= $dbConfig['color'] ?>">●</span>
        </div>
        <div class="overflow-y-auto flex-1 p-4 space-y-1 no-scrollbar">
            <?php foreach ($tables as $t): ?>
                <a href="?db=<?= $currentDbKey ?>&table=<?= $t ?>"
                   class="block px-4 py-2 rounded-xl text-xs truncate transition-all <?= $currentTable === $t ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30' : 'text-gray-500 hover:bg-white/5 hover:text-white' ?>">
                    <?= $t ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="p-4 border-t border-gray-800 flex bg-black/20 gap-2">
            <?php foreach ($databases as $k => $info): ?>
                <a href="?db=<?= $k ?>" class="flex-1 py-2 rounded-lg text-[9px] text-center font-bold uppercase border border-gray-800 hover:bg-gray-800 transition-all <?= $currentDbKey === $k ? 'bg-gray-800 text-white' : 'text-gray-600' ?>">
                    <?= $k ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Main Data Table -->
    <div class="flex-1 flex flex-col bg-gray-900/50 rounded-3xl border border-gray-800 overflow-hidden relative">

        <div class="p-6 border-b border-gray-800 bg-gray-800/50 flex justify-between items-center shrink-0">
            <div class="flex items-center gap-8">
                <!-- Tabs -->
                <div class="flex bg-black/20 p-1 rounded-xl border border-white/5">
                    <a href="?db=<?= $currentDbKey ?>&tab=data" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase transition-all <?= ($tab ?? 'data') === 'data' ? 'bg-blue-600 text-white' : 'text-gray-500 hover:text-white' ?>">
                        Data Browser
                    </a>
                    <?php if($currentDbKey === 'gianik'): ?>
                        <a href="?db=<?= $currentDbKey ?>&tab=intelligence" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase transition-all <?= ($tab ?? '') === 'intelligence' ? 'bg-purple-600 text-white' : 'text-gray-500 hover:text-white' ?>">
                            Brain Intelligence
                        </a>
                    <?php endif; ?>
                    <?php if($currentDbKey === 'dio'): ?>
                        <a href="?db=<?= $currentDbKey ?>&tab=quantum" class="px-4 py-2 rounded-lg text-[10px] font-black uppercase transition-all <?= ($tab ?? '') === 'quantum' ? 'bg-indigo-600 text-white' : 'text-gray-500 hover:text-white' ?>">
                            Quantum Tracker
                        </a>
                    <?php endif; ?>
                </div>

                <div class="h-8 w-px bg-white/10"></div>

                <div class="flex items-center gap-4">
                <div>
                    <span class="text-[10px] text-gray-500 uppercase font-bold block">Tabella Corrente</span>
                    <span class="<?= $dbConfig['color'] ?> font-black italic text-xl"><?= strtoupper($currentTable) ?></span>
                </div>
                <div class="px-3 py-1 bg-black/30 rounded-lg border border-white/5">
                    <span class="text-xs text-gray-400 font-mono"><?= number_format($totalRows) ?> rows</span>
                </div>
            </div>

            <form action="" method="GET" class="flex items-center gap-2">
                <input type="hidden" name="db" value="<?= $currentDbKey ?>">
                <input type="hidden" name="table" value="<?= $currentTable ?>">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="input-dark w-64 text-xs py-2 px-4 rounded-xl bg-slate-900 border-slate-700 outline-none focus:border-blue-500">

                <div class="flex gap-1 items-center ml-4">
                    <?php if($page > 1): ?>
                        <a href="?db=<?= $currentDbKey ?>&table=<?= $currentTable ?>&search=<?= urlencode($search) ?>&page=<?= $page-1 ?>" class="w-8 h-8 flex items-center justify-center bg-gray-800 hover:bg-gray-700 rounded-lg">◀</a>
                    <?php endif; ?>
                    <span class="px-3 text-[10px] font-bold text-gray-500 uppercase"><?= $page ?> / <?= $totalPages ?: 1 ?></span>
                    <?php if($page < $totalPages): ?>
                        <a href="?db=<?= $currentDbKey ?>&table=<?= $currentTable ?>&search=<?= urlencode($search) ?>&page=<?= $page+1 ?>" class="w-8 h-8 flex items-center justify-center bg-gray-800 hover:bg-gray-700 rounded-lg">▶</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="flex-1 overflow-auto no-scrollbar">
            <table class="w-full text-left border-collapse text-[11px] font-mono">
                <thead class="bg-gray-900 text-gray-500 uppercase sticky top-0 z-10">
                    <tr>
                        <th class="p-4 border-b border-gray-800 w-16">Act</th>
                        <?php if(!empty($rows)) foreach(array_keys($rows[0]) as $col) if($col !== '_id') echo "<th class='p-4 border-b border-gray-800 whitespace-nowrap'>$col</th>"; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800 bg-gray-900/20">
                    <?php foreach ($rows as $row): ?>
                        <tr class="hover:bg-white/5 group transition-colors">
                            <td class="p-4 flex gap-3 items-center">
                                <?php if($dbConfig['editable'] && $row['_id']): ?>
                                    <button onclick="openEdit(<?= htmlspecialchars(json_encode($row)) ?>)" class="text-blue-500 hover:text-blue-300 transition-colors" title="Edit">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <a href="?db=<?= $currentDbKey ?>&table=<?= $currentTable ?>&action=delete&id=<?= $row['_id'] ?>"
                                       onclick="return confirm('Sicuro?')" class="text-red-500 hover:text-red-300 transition-colors" title="Delete">
                                        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    </a>
                                <?php else: ?>
                                    <i data-lucide="lock" class="w-3.5 h-3.5 text-gray-700"></i>
                                <?php endif; ?>
                            </td>
                            <?php foreach ($row as $col => $val):
                                if ($col === '_id') continue;
                                $isNum = is_numeric($val);
                                $color = $isNum ? 'text-blue-300' : 'text-gray-400';
                                if ($val === null) { $val = 'NULL'; $color='text-gray-700 italic'; }
                            ?>
                                <td class="p-4 whitespace-nowrap max-w-xs truncate <?= $color ?>" title="<?= htmlspecialchars($val) ?>">
                                    <?= htmlspecialchars($val) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if(empty($rows)): ?>
                <div class="flex flex-col items-center justify-center h-64 text-gray-600">
                    <i data-lucide="database-zap" class="w-12 h-12 mb-4 opacity-20"></i>
                    <p class="uppercase font-bold tracking-widest text-xs">Nessun dato trovato</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Modifica -->
<div id="editModal" class="fixed inset-0 bg-black/90 hidden items-center justify-center backdrop-blur-sm z-50 p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl w-full max-w-4xl flex flex-col max-h-[90vh]">
        <div class="p-6 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
            <div>
                <h3 class="font-black italic text-white uppercase tracking-tighter text-xl">Modifica Record</h3>
                <span id="modalTitleId" class="text-blue-500 font-mono text-xs"></span>
            </div>
            <button onclick="document.getElementById('editModal').classList.add('hidden')" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-white/5 text-gray-500 hover:text-white transition-all">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="db" value="<?= $currentDbKey ?>">
            <input type="hidden" name="table" value="<?= $currentTable ?>">
            <input type="hidden" name="id" id="modalInputId">

            <div id="modalFields" class="p-8 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-6 no-scrollbar"></div>

            <div class="p-6 border-t border-slate-800 bg-slate-900/80 flex justify-end gap-4 shrink-0">
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-6 py-3 bg-slate-800 hover:bg-slate-700 rounded-xl text-gray-400 font-bold uppercase tracking-widest text-xs transition-all">Annulla</button>
                <button type="submit" class="px-8 py-3 bg-blue-600 hover:bg-blue-500 rounded-xl text-white font-black uppercase italic tracking-tighter shadow-xl shadow-blue-500/20 transition-all">Salva Modifiche</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(row) {
    if (!row._id) return alert("Impossibile modificare: Chiave Primaria non trovata.");
    document.getElementById('modalInputId').value = row._id;
    document.getElementById('modalTitleId').innerText = 'ID: ' + row._id;
    const container = document.getElementById('modalFields');
    container.innerHTML = '';

    for (const [key, value] of Object.entries(row)) {
        if (key === '_id') continue;

        const div = document.createElement('div');
        div.className = 'col-span-1';
        if (value && String(value).length > 80) div.className = 'col-span-2';

        const label = document.createElement('label');
        label.className = 'block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-2 ml-1';
        label.innerText = key;

        let input;
        if (value && String(value).length > 80) {
            input = document.createElement('textarea');
            input.className = 'w-full bg-slate-950 border border-slate-800 rounded-xl p-4 text-xs text-blue-200 font-mono focus:border-blue-500 outline-none h-32 no-scrollbar';
        } else {
            input = document.createElement('input');
            input.type = 'text';
            input.className = 'w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-xs text-blue-200 font-mono focus:border-blue-500 outline-none';
        }
        input.name = `data[${key}]`;
        input.value = (value === 'NULL' || value === null) ? '' : value;

        div.appendChild(label);
        div.appendChild(input);
        container.appendChild(div);
    }
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
    lucide.createIcons();
}
</script>
