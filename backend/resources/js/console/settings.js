import { api, money, uuid } from './api.js';
import { t, dictionaries } from './i18n.js';

const store = () => window.Alpine.store('app');

export function registerSettings(Alpine) {
    Alpine.data('modulesPage', () => ({
        rows: [], loading: true, busy: '',
        async init() { await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/modules')).data; } catch (e) { store().err(e); } this.loading = false; },
        async toggle(m) {
            this.busy = m.key;
            try {
                await api.put('/modules/' + m.key, { enabled: !m.enabled });
                store().ctx = await api.get('/me/context'); // refresh navigation
                await this.load();
            } catch (e) { store().err(e); }
            this.busy = '';
        },
        desc(k) { return { rooms: 'Room types, rates, reservations, check-in/out, folios, night audit.', pos: 'Restaurant, bar and counter sales, kitchen screen, shifts.', tourism: 'Tours, activities, transfers, rentals, packages, departures, guides and vehicles.', inventory: 'Stock ledger, suppliers, purchase orders, recipes, stocktakes.', shop: 'Retail extras: barcode, returns, price lists.', staff: 'Employees, roster, attendance, leave, payroll export.', housekeeping: 'Room status, cleaning and maintenance tasks.', finance: 'Reports, expenses and cash reconciliation.' }[k] || ''; },
    }));

    Alpine.data('usersPage', () => ({
        rows: [], roles: [], loading: true, form: null, err: '',
        async init() { this.roles = (await api.get('/roles')).data; await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/users')).data; } catch (e) { store().err(e); } this.loading = false; },
        newForm() { this.err = ''; this.form = { id: null, name: '', email: '', phone: '', password: '', pin: '', locale: 'en', roles: [], is_active: true }; },
        edit(u) { this.err = ''; this.form = { id: u.id, name: u.name, email: u.email, phone: u.phone || '', password: '', pin: '', locale: u.locale || 'en', roles: u.roles.map((r) => r.id), is_active: u.is_active }; },
        async save() {
            const f = this.form; this.err = '';
            try {
                const body = { name: f.name, phone: f.phone || null, locale: f.locale, roles: f.roles, is_active: f.is_active };
                if (f.password) body.password = f.password;
                if (f.pin) body.pin = f.pin;
                if (f.id) await api.patch('/users/' + f.id, body); else await api.post('/users', { ...body, email: f.email, password: f.password });
                this.form = null; await this.load();
            } catch (e) { this.err = e.firstDetail || e.message; }
        },
        toggleRole(id) { const r = this.form.roles; const i = r.indexOf(id); i >= 0 ? r.splice(i, 1) : r.push(id); },
    }));

    Alpine.data('rolesPage', () => ({
        roles: [], catalog: [], loading: true, sel: null, perms: new Set(), newName: '', err: '',
        async init() { await this.load(); },
        async load() { this.loading = true; const r = await api.get('/roles'); this.roles = r.data; this.catalog = r.catalog; this.loading = false; },
        modules() { const m = {}; this.catalog.forEach((p) => { const [mod, act] = p.split('.'); (m[mod] ||= []).push(act); }); return Object.entries(m); },
        pick(r) { this.sel = r; this.perms = new Set(r.permissions); this.err = ''; },
        has(p) { return this.perms.has('*') || this.perms.has(p); },
        flip(p) { if (this.sel.key === 'owner') return; this.perms.has(p) ? this.perms.delete(p) : this.perms.add(p); this.perms = new Set(this.perms); },
        async save() { try { await api.patch('/roles/' + this.sel.id, { permissions: [...this.perms] }); store().toast('✓', 'ok'); await this.load(); this.sel = this.roles.find((r) => r.id === this.sel.id); } catch (e) { this.err = e.firstDetail || e.message; } },
        async create() {
            if (!this.newName) return;
            try { const r = await api.post('/roles', { key: this.newName.toLowerCase().replace(/[^a-z0-9]+/g, '_'), name: this.newName, permissions: ['core.view'] }); this.newName = ''; await this.load(); this.pick(this.roles.find((x) => x.id === r.data.id)); } catch (e) { this.err = e.firstDetail || e.message; }
        },
        async remove() { if (!confirm(t('delete') + '?')) return; try { await api.del('/roles/' + this.sel.id); this.sel = null; await this.load(); } catch (e) { store().err(e); } },
    }));

    Alpine.data('labelsPage', () => ({
        prefs: {}, term: {}, keys: Object.keys(dictionaries.en), locales: ['en', 'fr', 'rw'], newKey: 'guests', busy: false,
        init() {
            const s = store().ctx.settings;
            this.prefs = { 'rooms.checkin_time': s['rooms.checkin_time'] ?? '14:00', 'rooms.checkout_time': s['rooms.checkout_time'] ?? '11:00', 'rooms.require_id_on_checkin': !!(s['rooms.require_id_on_checkin'] ?? true),
                'pos.tips_enabled': !!(s['pos.tips_enabled'] ?? true), 'pos.service_charge_bp': (s['pos.service_charge_bp'] ?? 0) / 100, 'pos.require_manager_pin_for_void': !!(s['pos.require_manager_pin_for_void'] ?? true),
                'tourism.deposit_percent': s['tourism.deposit_percent'] ?? 30, 'receipt.footer': s['receipt.footer'] ?? '' };
            this.term = JSON.parse(JSON.stringify(s.terminology && !Array.isArray(s.terminology) ? s.terminology : {}));
        },
        addTerm() { if (!this.term[this.newKey]) this.term[this.newKey] = { en: '', fr: '', rw: '' }; },
        removeTerm(k) { delete this.term[k]; this.term = { ...this.term }; },
        async save() {
            this.busy = true;
            try {
                const clean = {};
                for (const [k, v] of Object.entries(this.term)) { const o = Object.fromEntries(Object.entries(v).filter(([, x]) => x)); if (Object.keys(o).length) clean[k] = o; }
                await api.put('/settings', { values: { ...this.prefs, 'pos.service_charge_bp': Math.round(Number(this.prefs['pos.service_charge_bp']) * 100), 'tourism.deposit_percent': Number(this.prefs['tourism.deposit_percent']), terminology: Object.keys(clean).length ? clean : {} } });
                store().ctx = await api.get('/me/context'); store().applyLocale(); store().toast('✓', 'ok');
            } catch (e) { store().err(e); }
            this.busy = false;
        },
        dflt(k) { return dictionaries.en[k]; },
    }));

    Alpine.data('devicesPage', () => ({
        rows: [], loading: true,
        async init() { await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/devices')).data; } catch (e) { store().err(e); } this.loading = false; },
        async revoke(d) { if (!confirm('Revoke "' + d.name + '"? The app on this device will be signed out immediately.')) return; try { await api.post(`/devices/${d.id}/revoke`); await this.load(); } catch (e) { store().err(e); } },
    }));

    Alpine.data('auditPage', () => ({
        rows: [], page: 1, last: 1, action: '', loading: true,
        async init() { await this.load(); },
        async load(p = 1) { this.loading = true; try { const r = await api.get('/audit-log', { page: p, action: this.action || undefined }); this.rows = r.data; this.page = r.current_page; this.last = r.last_page; } catch (e) { store().err(e); } this.loading = false; },
        short(o) { return o ? JSON.stringify(o).slice(0, 120) : ''; },
    }));

    Alpine.data('billingPage', () => ({
        b: null, loading: true, money, phone: '', busy: false, poll: null,
        async init() { await this.load(); },
        async load() { this.loading = true; try { this.b = (await api.get('/billing')).data; } catch (e) { store().err(e); } this.loading = false; },
        async choose(plan) { try { await api.post('/billing/plan', { plan_id: plan.id }); await this.load(); } catch (e) { store().err(e); } },
        async pay(inv) {
            if (!this.phone) return store().toast('Enter the MTN MoMo number', 'error');
            this.busy = true;
            try {
                await api.post(`/billing/invoices/${inv.id}/pay`, { phone: this.phone });
                store().toast('Approve the payment on your phone…', 'info');
                let n = 0;
                this.poll = setInterval(async () => {
                    const r = (await api.get('/billing/invoices/' + inv.id)).data;
                    if (r.status === 'paid' || r.payment_status === 'failed' || ++n > 30) { clearInterval(this.poll); this.busy = false; await this.load(); if (r.status === 'paid') { store().toast('Payment received ✓', 'ok'); location.reload(); } else if (r.payment_status === 'failed') store().toast('Payment failed', 'error'); }
                }, 4000);
            } catch (e) { this.busy = false; store().err(e); }
        },
    }));
}
