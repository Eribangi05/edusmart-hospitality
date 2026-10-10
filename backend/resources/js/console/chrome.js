import { api } from './api.js';
import { t } from './i18n.js';
import { charts } from './charts-bridge.js';

/** Console chrome: command palette, notification bell, appearance panel, pinned pages. Lives in its own store so every page can use it. */
export function registerChrome(Alpine, NAV) {
    Alpine.store('ui', {
        // ---- command palette
        pal: { open: false, q: '', idx: 0, records: [], busy: false },
        // ---- notifications
        notifs: [], bell: false, notifTimer: null,
        // ---- appearance
        panel: false, prefs: window.UI.load(), accents: Object.entries(window.UI.ACCENTS),
        // ---- workspaces dropdown
        wsMenu: false,
        // ---- pinned pages
        favs: (() => { try { return JSON.parse(localStorage.getItem('ui.fav') || '[]'); } catch (e) { return []; } })(),

        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); this.openPalette(); }
                if (e.key === 'Escape') { this.pal.open = false; this.bell = false; this.panel = false; this.wsMenu = false; }
            });
            this.loadNotifs();
            this.notifTimer = setInterval(() => this.loadNotifs(), 60000);
        },

        // ---- palette
        pages() {
            const app = Alpine.store('app');
            const out = [];
            NAV.forEach((g) => {
                if (g.module && !app.hasModule(g.module)) return;
                (g.items || [{ ...g, label: g.label }]).forEach((i) => { if (app.canAny(i.any || [])) out.push({ kind: 'page', title: t(i.label), group: g.items ? t(g.label) : '', route: i.route }); });
            });
            return out;
        },
        actions() {
            const app = Alpine.store('app');
            const a = [];
            (app.ctx.workspaces || []).forEach((w) => a.push({ kind: 'workspace', title: 'Open ' + w.label, group: t('workspaces'), href: w.url }));
            a.push({ kind: 'action', title: 'Toggle dark mode', group: t('appearance'), run: () => this.setPref({ theme: document.documentElement.classList.contains('dark') ? 'light' : 'dark' }) });
            return a;
        },
        results() {
            const q = this.pal.q.trim().toLowerCase();
            const match = (x) => !q || (x.title + ' ' + (x.group || '')).toLowerCase().includes(q);
            const list = [...this.pages().filter(match), ...this.actions().filter(match)].slice(0, 9);
            return [...list, ...this.pal.records];
        },
        openPalette() { this.pal = { open: true, q: '', idx: 0, records: [], busy: false }; this.$nextTick?.(() => {}); setTimeout(() => document.getElementById('palette-input')?.focus(), 30); },
        async onQuery() {
            this.pal.idx = 0;
            const q = this.pal.q.trim();
            if (q.length < 2) { this.pal.records = []; return; }
            clearTimeout(this._t);
            this._t = setTimeout(async () => {
                try { const r = await api.get('/search', { q }); if (this.pal.q.trim() === q) this.pal.records = r.data.map((x) => ({ kind: 'record', title: x.title, group: x.type + (x.sub ? ' · ' + x.sub : ''), route: x.route })); } catch (e) { /* ignore */ }
            }, 220);
        },
        move(d) { const n = this.results().length; this.pal.idx = (this.pal.idx + d + n) % Math.max(n, 1); },
        choose(i = this.pal.idx) {
            const r = this.results()[i]; if (!r) return;
            this.pal.open = false;
            if (r.href) location.href = r.href; else if (r.run) r.run(); else location.hash = '#/' + r.route;
        },

        // ---- notifications
        async loadNotifs() { try { this.notifs = (await api.get('/notifications')).data; } catch (e) { /* offline */ } },
        sevClass(s) { return { critical: 'bg-red-500', warning: 'bg-amber-500', info: 'bg-sky-500' }[s]; },
        open(n) { this.bell = false; location.hash = '#/' + n.route; },

        // ---- appearance
        setPref(p) { this.prefs = window.UI.save(p); },

        // ---- favourites
        isFav(route) { return this.favs.includes(route); },
        toggleFav(route) {
            this.favs = this.favs.includes(route) ? this.favs.filter((x) => x !== route) : [...this.favs, route].slice(-8);
            try { localStorage.setItem('ui.fav', JSON.stringify(this.favs)); } catch (e) { /* ignore */ }
        },
        favItems() { const all = this.pages(); return this.favs.map((r) => all.find((p) => p.route === r)).filter(Boolean); },
    });
    charts(Alpine);
}
