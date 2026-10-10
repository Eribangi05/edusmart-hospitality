import Alpine from 'alpinejs';
import './shared/theme.js';
import { api } from './console/api.js';

window.staff = () => ({
    ctx: null, tenant: '', user: {}, greeting: '', tasks: [], trips: [], rooms: {}, tab: 'tasks', error: '', time: '', date: '', lastType: '', lastAt: '', clockMsg: '',
    prefs: window.UI.load(), accents: Object.entries(window.UI.ACCENTS),
    async init() {
        try {
            this.ctx = await api.get('/me/context');
            this.tenant = this.ctx.tenant.name; this.user = this.ctx.user;
            const h = new Date().getHours();
            this.greeting = `${h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'}, ${this.user.name.split(' ')[0]}`;
            try { const l = JSON.parse(localStorage.getItem('last_clock') || 'null'); if (l) { this.lastType = l.type; this.lastAt = l.at; } } catch (e) { /* ignore */ }
            this.tab = this.tabs()[0]?.k || 'clock';
            await this.load();
        } catch (e) { this.error = e.message; }
        const tick = () => { const d = new Date(); this.time = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }); this.date = d.toLocaleDateString([], { weekday: 'long', day: 'numeric', month: 'long' }); };
        tick(); setInterval(tick, 1000); setInterval(() => this.load(), 45000);
    },
    has(m) { return this.ctx?.modules.includes(m); },
    can(p) { const perms = this.ctx?.permissions || []; return perms.includes('*') || perms.includes(p) || perms.includes(p.split('.')[0] + '.*'); },
    tabs() {
        const t = [];
        if (this.has('housekeeping') && this.can('housekeeping.view')) t.push({ k: 'tasks', l: 'Tasks', i: '🧹', n: this.tasks.filter((x) => x.status !== 'done').length });
        if (this.has('tourism') && this.can('tourism.view')) t.push({ k: 'trips', l: 'Trips', i: '🦍', n: this.trips.length });
        if (this.has('staff')) t.push({ k: 'clock', l: 'Clock', i: '⏱', n: 0 });
        t.push({ k: 'me', l: 'Me', i: '👤', n: 0 });
        return t;
    },
    summary() { return [this.has('housekeeping') && `${this.tasks.length} task${this.tasks.length === 1 ? '' : 's'}`, this.has('tourism') && `${this.trips.length} trip${this.trips.length === 1 ? '' : 's'}`].filter(Boolean).join(' · ') || 'Have a great shift'; },
    async load() {
        try {
            if (this.has('housekeeping') && this.can('housekeeping.view')) {
                this.tasks = (await api.get('/my-tasks')).data;
                if (!Object.keys(this.rooms).length && this.can('rooms.view')) (await api.get('/rooms', { per_page: 200 })).data.forEach((r) => (this.rooms[r.id] = r.number));
            }
            if (this.has('tourism') && this.can('tourism.view')) this.trips = (await api.get('/my-trips')).data;
        } catch (e) { /* offline: keep what is on screen */ }
    },
    roomLabel(t) { return [t.type, t.room_id && this.rooms[t.room_id] ? 'Room ' + this.rooms[t.room_id] : ''].filter(Boolean).join(' · '); },
    async act(t, action) { try { await api.post(`/tasks/${t.id}/${action}`, {}); await this.load(); } catch (e) { this.error = e.firstDetail || e.message; } },
    async clockEvent(type) {
        this.clockMsg = '';
        try {
            const r = await api.post('/attendance', { type, at: new Date().toISOString() });
            this.lastType = type; this.lastAt = new Date().toISOString(); localStorage.setItem('last_clock', JSON.stringify({ type, at: this.lastAt }));
            this.clockMsg = r.data.flagged ? 'Saved. This entry was flagged for review (duplicate or odd time).' : 'Saved ✓';
        } catch (e) { this.clockMsg = ''; this.error = e.code === 'not_found' ? 'Your login is not linked to an employee profile. Ask your manager.' : (e.firstDetail || e.message); }
    },
    lastText() { return this.lastAt ? `Last: clocked ${this.lastType === 'in' ? 'in' : 'out'} at ${new Date(this.lastAt).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : 'No clock events on this device yet'; },
    setPref(p) { this.prefs = window.UI.save(p); },
    async logout() {
        await fetch('/logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]*)/) || [])[1] || ''), Accept: 'application/json' } });
        location.href = '/login';
    },
});

window.Alpine = Alpine;
Alpine.start();
