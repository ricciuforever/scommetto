<!DOCTYPE html>
<html lang="it" class="dark">
<head>
    <meta charset="UTF-8">
    <title>Scommetto - Login Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="h-screen flex items-center justify-center">

<div class="w-full max-w-md p-8 glass rounded-3xl shadow-2xl">
    <div class="text-center mb-10">
        <h1 class="text-3xl font-bold tracking-wider text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-green-400 mb-2">
            🛡️ WAR ROOM
        </h1>
        <p class="text-gray-500 text-xs uppercase font-bold tracking-widest">Accesso Riservato</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-900/30 border border-red-500/50 text-red-200 p-4 rounded-xl mb-6 text-sm text-center">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/admin/login" class="space-y-6">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Username</label>
            <input type="text" name="username" required
                   class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-all">
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 ml-1">Password</label>
            <input type="password" name="password" required
                   class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-all">
        </div>

        <button type="submit"
                class="w-full bg-gradient-to-r from-blue-600 to-blue-500 hover:from-blue-500 hover:to-blue-400 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-500/20 transition-all uppercase tracking-widest text-sm">
            Entra nel Comando
        </button>
    </form>

    <div class="mt-10 text-center">
        <p class="text-[10px] text-gray-600 uppercase font-bold tracking-tighter">Scommetto Platform v3.0 &copy; <?= date('Y') ?></p>
    </div>
</div>

</body>
</html>
