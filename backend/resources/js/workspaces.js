import Alpine from 'alpinejs';
import './shared/theme.js';
import { api } from './console/api.js';

window.launcher = () => ({
    items: [], tenant: '', greeting: '', loading: true,
    async init() {
        try {
            const c = await api.get('/me/context');
            this.items = c.workspaces; this.tenant = c.tenant.name;
            const h = new Date().getHours();
            this.greeting = `${h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'}, ${c.user.name.split(' ')[0]}`;
        } catch (e) { /* api redirects to login on 401 */ }
        this.loading = false;
    },
    icon: (k) => ({ dashboard: '📊', pos: '🧾', kds: '🍳', desk: '🛎', staff: '📱' }[k] || '▦'),
    async logout() {
        await fetch('/logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]*)/) || [])[1] || ''), Accept: 'application/json' } });
        location.href = '/login';
    },
});

window.Alpine = Alpine;
Alpine.start();
