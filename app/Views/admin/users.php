<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

    <!-- Form Creazione -->
    <div class="col-span-1">
        <div class="glass p-8 rounded-3xl">
            <h3 class="text-xl font-black italic uppercase text-white mb-6 border-b border-gray-800 pb-4">Nuovo Gestore</h3>
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-2 ml-1">Username</label>
                    <input type="text" name="username" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-2 ml-1">Password</label>
                    <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-2 ml-1">Ruolo</label>
                    <select name="role" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-xs font-bold uppercase outline-none focus:border-blue-500">
                        <option value="manager">Manager</option>
                        <option value="admin">Super Admin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] text-gray-500 uppercase font-black tracking-widest mb-2 ml-1">Agenti Assegnati</label>
                    <input type="text" name="agent" placeholder="gianik,dio" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm outline-none focus:border-blue-500">
                    <p class="text-[9px] text-gray-600 mt-2 italic">Usa 'all' per tutti gli agenti, oppure nomi separati da virgola.</p>
                </div>

                <button type="submit" class="w-full py-4 bg-blue-600 hover:bg-blue-500 text-white font-black uppercase italic tracking-tighter rounded-xl transition-all shadow-lg shadow-blue-500/20">
                    Crea Account
                </button>
            </form>
        </div>
    </div>

    <!-- Elenco Utenti -->
    <div class="col-span-2">
        <div class="glass rounded-3xl overflow-hidden">
            <div class="p-6 border-b border-gray-800 bg-gray-800/50">
                <h3 class="text-xl font-black italic uppercase text-white">Database Utenti</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs font-mono">
                    <thead class="bg-gray-900 text-gray-500 uppercase">
                        <tr>
                            <th class="p-4 border-b border-gray-800">Username</th>
                            <th class="p-4 border-b border-gray-800">Ruolo</th>
                            <th class="p-4 border-b border-gray-800">Agenti</th>
                            <th class="p-4 border-b border-gray-800">Creato il</th>
                            <th class="p-4 border-b border-gray-800 text-right">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="p-4 font-bold text-blue-400"><?= htmlspecialchars($u['username']) ?></td>
                            <td class="p-4">
                                <span class="px-2 py-1 rounded-md text-[9px] font-black uppercase tracking-tighter <?= $u['role'] === 'admin' ? 'bg-red-900/30 text-red-400' : 'bg-green-900/30 text-green-400' ?>">
                                    <?= $u['role'] ?>
                                </span>
                            </td>
                            <td class="p-4 text-gray-400"><?= htmlspecialchars($u['assigned_agent']) ?></td>
                            <td class="p-4 text-gray-600"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                            <td class="p-4 text-right">
                                <?php if ($u['username'] !== $user['username']): ?>
                                <form method="POST" onsubmit="return confirm('Sicuro di voler eliminare questo utente?')" class="inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-300 transition-colors">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
