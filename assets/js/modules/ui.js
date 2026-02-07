export class UI {
    static showToast(message, type = 'accent') {
        const toast = document.createElement('div');
        toast.className = `fixed bottom-4 right-4 z-50 px-6 py-4 rounded-2xl border font-black uppercase tracking-widest text-[10px] transform transition-all duration-300 translate-y-10 opacity-0 ${type === 'danger' ? 'bg-danger/10 border-danger text-danger' : 'bg-accent/10 border-accent text-accent'}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        requestAnimationFrame(() => toast.classList.remove('translate-y-10', 'opacity-0'));
        setTimeout(() => {
            toast.classList.add('translate-y-10', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    static updateUsage(used, limit) {
        const usageVal = document.getElementById('usage-val');
        const limitVal = document.getElementById('limit-val');
        if (usageVal) usageVal.textContent = used || 0;
        if (limitVal) limitVal.textContent = limit || 75000;
    }

    static createIcons() {
        if (window.lucide) window.lucide.createIcons();
    }
}
