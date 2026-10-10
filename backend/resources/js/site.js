import Alpine from 'alpinejs';
import './shared/theme.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

window.site = () => ({
    menu: false,
    dark: document.documentElement.classList.contains('dark'),
    toggleTheme() {
        const p = window.UI.save({ theme: this.dark ? 'light' : 'dark' });
        this.dark = document.documentElement.classList.contains('dark');
        return p;
    },
});

window.contactForm = () => ({
    f: { name: '', email: '', phone: '', company: '', interest: '', message: '' }, busy: false, done: '', error: '',
    async submit() {
        this.busy = true; this.error = ''; this.done = '';
        const res = await fetch('/contact', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(this.f) });
        const data = await res.json().catch(() => ({}));
        this.busy = false;
        if (res.ok) { this.done = data.message; this.f = { name: '', email: '', phone: '', company: '', interest: '', message: '' }; } else this.error = Object.values(data.errors || {})[0]?.[0] || data.message || 'Something went wrong.';
    },
});

window.Alpine = Alpine;
Alpine.start();
