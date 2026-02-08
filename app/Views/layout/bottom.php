<?php
// app/Views/layout/bottom.php
?>
        </main>
    </div>

    <!-- Bottom Navigation Mobile -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 glass border-t border-white/10 px-6 py-3 flex justify-between items-center z-50">
        <a href="/dashboard" class="flex flex-col items-center gap-1 text-slate-500 hover:text-accent">
            <i data-lucide="home" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Home</span>
        </a>
        <a href="/countries" class="flex flex-col items-center gap-1 text-slate-500 hover:text-accent">
            <i data-lucide="globe" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Nazioni</span>
        </a>
        <a href="/leagues" class="flex flex-col items-center gap-1 text-slate-500 hover:text-accent">
            <i data-lucide="trophy" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Leghe</span>
        </a>
        <a href="/seasons" class="flex flex-col items-center gap-1 text-slate-500 hover:text-accent">
            <i data-lucide="calendar" class="w-6 h-6"></i>
            <span class="text-[10px] font-bold uppercase tracking-widest">Stagioni</span>
        </a>
    </nav>

    <script>
        // Minimal JS for Theme Toggle & basic UI
        const themeToggle = document.getElementById('theme-toggle');
        const html = document.documentElement;

        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            html.classList.add('dark');
        } else {
            html.classList.remove('dark');
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                html.classList.toggle('dark');
                localStorage.setItem('theme', html.classList.contains('dark') ? 'dark' : 'light');
            });
        }

        // Re-init Icons on content load or HTMX swap
        document.body.addEventListener('htmx:afterSwap', function (evt) {
            if (window.lucide) lucide.createIcons();
        });

        // Initial Icons
        if (window.lucide) lucide.createIcons();
    </script>
</body>
</html>
