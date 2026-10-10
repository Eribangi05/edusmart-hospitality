import Alpine from 'alpinejs';
import './shared/theme.js';
import { api } from './console/api.js';
import { registerFrontDesk } from './shared/frontdesk.js';

window.desk = () => ({
    tab: 'today', tenant: '', mods: [],
    tabs: [
        { k: 'today', l: 'Today', show: () => true },
        { k: 'timeline', l: 'Timeline', show: () => true },
        { k: 'tours', l: 'Tours', show: () => true },
        { k: 'guests', l: 'Guests', show: () => true },
    ],
    async init() {
        const c = await api.get('/me/context');
        this.tenant = c.tenant.name; this.mods = c.modules;
        if (!this.mods.includes('tourism')) this.tabs = this.tabs.filter((t) => t.k !== 'tours');
        if (!this.mods.includes('rooms')) { this.tabs = this.tabs.filter((t) => !['today', 'timeline', 'guests'].includes(t.k)); this.tab = 'tours'; }
    },
    mod(m) { return this.mods.includes(m); },
    theme() { window.UI.save({ theme: document.documentElement.classList.contains('dark') ? 'light' : 'dark' }); },
});

document.addEventListener('alpine:init', () => registerFrontDesk(Alpine));
window.Alpine = Alpine;
Alpine.start();
