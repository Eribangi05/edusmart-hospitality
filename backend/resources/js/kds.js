import Alpine from 'alpinejs';
import './shared/theme.js';
import { api } from './console/api.js';

const NEXT = { sent: 'preparing', preparing: 'ready', ready: 'served' };

window.kds = () => ({
    station: 'all', raw: [], tablesById: {}, offline: false, sound: true, clock: '', seen: new Set(), first: true, audio: null, now: Date.now(),
    cols: [
        { k: 'new', label: 'New', cls: 'text-rose-300', action: 'Start cooking', btn: 'bg-rose-600 hover:bg-rose-500' },
        { k: 'cooking', label: 'Cooking', cls: 'text-amber-300', action: 'Mark ready', btn: 'bg-amber-600 hover:bg-amber-500' },
        { k: 'ready', label: 'Ready to serve', cls: 'text-emerald-300', action: 'Served ✓', btn: 'bg-emerald-600 hover:bg-emerald-500' },
    ],
    async init() {
        try { (await api.get('/tables', { per_page: 200 })).data.forEach((t) => (this.tablesById[t.id] = t.name)); } catch (e) { /* optional */ }
        await this.load();
        setInterval(() => this.load(), 5000);
        setInterval(() => { this.now = Date.now(); this.clock = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }); }, 1000);
    },
    async load() {
        const stations = this.station === 'all' ? ['kitchen', 'bar'] : [this.station];
        try {
            const res = await Promise.all(stations.map((s) => api.get('/kitchen/tickets', { station: s })));
            const byOrder = {};
            res.forEach((r) => r.data.forEach((t) => { const x = (byOrder[t.order.id] ||= { order: t.order, items: [] }); x.items.push(...t.items); }));
            this.raw = Object.values(byOrder);
            this.offline = false;
            const ids = new Set(this.raw.flatMap((t) => t.items.filter((i) => i.status === 'sent').map((i) => i.id)));
            if (!this.first && [...ids].some((id) => !this.seen.has(id))) this.beep();
            this.seen = ids; this.first = false;
        } catch (e) { this.offline = true; }
    },
    status(t) { const s = t.items.map((i) => i.status); return s.includes('sent') ? 'new' : s.includes('preparing') ? 'cooking' : 'ready'; },
    tickets(col) { return this.raw.filter((t) => this.status(t) === col).sort((a, b) => this.age(b) - this.age(a)); },
    count(col) { return this.raw.filter((t) => this.status(t) === col).length; },
    age(t) { return this.now - Math.min(...t.items.map((i) => new Date(i.updated_at).getTime())); },
    mins(t) { return Math.max(0, Math.floor(this.age(t) / 60000)); },
    ageClass(t) { const m = this.mins(t); return m >= 20 ? 'border-rose-500 pulse-soft' : m >= 10 ? 'border-amber-400' : 'border-emerald-500'; },
    ageText(t) { const m = this.mins(t); return m >= 20 ? 'text-rose-400' : m >= 10 ? 'text-amber-300' : 'text-slate-200'; },
    where(o) { return [o.table_id ? 'Table ' + (this.tablesById[o.table_id] || '') : o.type.replace('_', ' '), o.customer_name].filter(Boolean).join(' · '); },
    async step(i) { const to = NEXT[i.status]; if (!to) return; try { await api.post(`/order-items/${i.id}/status`, { status: to }); await this.load(); } catch (e) { this.offline = true; } },
    async advance(t) {
        const low = this.status(t) === 'new' ? 'sent' : this.status(t) === 'cooking' ? 'preparing' : 'ready';
        try { await Promise.all(t.items.filter((i) => i.status === low).map((i) => api.post(`/order-items/${i.id}/status`, { status: NEXT[low] }))); await this.load(); } catch (e) { this.offline = true; }
    },
    beep(test = false) {
        if (!this.sound && !test) return;
        try {
            this.audio ||= new (window.AudioContext || window.webkitAudioContext)();
            const o = this.audio.createOscillator(), g = this.audio.createGain();
            o.connect(g); g.connect(this.audio.destination); o.frequency.value = 880; g.gain.setValueAtTime(0.2, this.audio.currentTime); g.gain.exponentialRampToValueAtTime(0.001, this.audio.currentTime + 0.5);
            o.start(); o.stop(this.audio.currentTime + 0.5);
        } catch (e) { /* audio blocked until a click */ }
    },
    fullscreen() { document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen?.(); },
});

window.Alpine = Alpine;
Alpine.start();
