import { api, money, today, addDays, uuid } from '../console/api.js';
import { printDoc } from './print.js';

const store = () => window.Alpine.store('desk') || window.Alpine.store('app');
const err = (e) => e?.firstDetail || e?.message || 'Something went wrong';
const fmtDay = (d) => new Date(d + 'T00:00:00').toLocaleDateString([], { weekday: 'short', day: 'numeric' });
const monthName = (y, m) => new Date(y, m, 1).toLocaleDateString([], { month: 'long', year: 'numeric' });

/** Components shared by the front desk workspace and the manager console. */
export function registerFrontDesk(Alpine) {
    // ---------------------------------------------------------------- reservation drawer + invoice
    Alpine.data('resDrawer', () => ({
        v: null, rooms: [], methods: [], busy: false, msg: '', money,
        async open(id) {
            this.msg = '';
            const c = (await api.get('/me/context'));
            this.methods = c.payment_methods.filter((m) => m.type !== 'room_charge');
            this.ctx = c;
            const r = await api.get('/reservations/' + id);
            const rooms = (await api.get('/rooms', { per_page: 200, filter: { room_type_id: r.data.room_type_id } })).data;
            this.v = { ...r, rooms, pay: { amount: '', method_type: 'cash', payer_phone: '' }, charge: { description: '', amount: '' }, idNumber: '' };
            if (r.folio) this.v.pay.amount = Math.max(r.folio.balance, 0) || '';
        },
        close() { this.v = null; },
        async act(path, body = {}) {
            this.busy = true; this.msg = '';
            try { await api.post(`/reservations/${this.v.data.id}/${path}`, body); await this.open(this.v.data.id); this.$dispatch('changed'); } catch (e) { this.msg = err(e); }
            this.busy = false;
        },
        async checkIn() { await this.act('check-in', this.v.idNumber ? { guest: { id_number: this.v.idNumber } } : {}); },
        async checkOut(force = false) { await this.act('check-out', force ? { force: true } : {}); },
        async pay() {
            try { await api.post(`/folios/${this.v.folio.id}/payments`, { ...this.v.pay, amount: Number(this.v.pay.amount), payer_phone: this.v.pay.payer_phone || undefined, idempotency_key: uuid() }); await this.open(this.v.data.id); this.$dispatch('changed'); } catch (e) { this.msg = err(e); }
        },
        async addCharge() {
            try { await api.post(`/folios/${this.v.folio.id}/entries`, { type: 'charge', description: this.v.charge.description, amount: Number(this.v.charge.amount) }); await this.open(this.v.data.id); } catch (e) { this.msg = err(e); }
        },
        async voidEntry(e) { const reason = prompt('Reason for voiding?'); if (!reason) return; try { await api.post(`/folio-entries/${e.id}/void`, { reason }); await this.open(this.v.data.id); } catch (x) { this.msg = err(x); } },
        can(p) { const perms = this.ctx?.permissions || []; return perms.includes('*') || perms.includes(p); },
        printInvoice() {
            const f = this.v.folio, r = this.v.data, g = this.v.guest;
            printDoc({ business: this.ctx.tenant.name, title: 'Guest invoice', number: f?.number || r.number, date: new Date().toLocaleDateString(), party: g?.name, partySub: [g?.phone, g?.email].filter(Boolean).join(' · '),
                sub: `Stay ${r.check_in} to ${r.check_out} · ${r.number}`,
                lines: (f?.entries || []).filter((e) => !e.voided_at).map((e) => ({ d: e.description, q: Number(e.quantity) === 1 ? '' : Number(e.quantity), a: e.amount })),
                totals: [{ l: 'Charges', a: f?.charges || 0 }, { l: 'Payments', a: -(f?.payments || 0) }, { l: 'Balance due', a: f?.balance || 0, bold: true }], footer: this.ctx.settings['receipt.footer'] });
        },
        badge(s) { return { confirmed: 'bg-sky-100 text-sky-800', checked_in: 'bg-emerald-100 text-emerald-800', checked_out: 'bg-slate-100 text-slate-700', cancelled: 'bg-red-100 text-red-700', no_show: 'bg-red-100 text-red-700', pending: 'bg-amber-100 text-amber-800' }[s] || 'bg-slate-100'; },
    }));

    // ---------------------------------------------------------------- new reservation modal
    Alpine.data('newRes', () => ({
        f: null, types: [], quote: null, err: '', busy: false, money,
        async start(prefill = {}) {
            this.err = ''; this.quote = null;
            this.types = (await api.get('/room-types', { per_page: 100 })).data.filter((t) => t.is_active);
            const ci = prefill.check_in || today();
            this.f = { room_type_id: prefill.room_type_id || this.types[0]?.id || '', room_id: prefill.room_id || '', check_in: ci, check_out: prefill.check_out || addDays(ci, 1), adults: 2, children: 0, guest_id: prefill.guest_id || '', guest: { name: prefill.guest_name || '', phone: '', email: '', nationality: '' }, notes: '', source: 'walk_in' };
            await this.check();
        },
        async check() {
            const f = this.f; this.quote = null; this.err = '';
            if (!f.room_type_id || f.check_out <= f.check_in) return;
            try { this.quote = (await api.get('/availability', { room_type_id: f.room_type_id, check_in: f.check_in, check_out: f.check_out })).data; } catch (e) { this.err = err(e); }
        },
        async create() {
            this.busy = true; this.err = '';
            try { const body = { ...this.f, id: uuid(), room_id: this.f.room_id || undefined }; if (this.f.guest_id) delete body.guest; else delete body.guest_id; const r = await api.post('/reservations', body); const id = r.data.id; this.f = null; this.$dispatch('created', { id }); } catch (e) { this.err = err(e); }
            this.busy = false;
        },
    }));

    // ---------------------------------------------------------------- today lists
    Alpine.data('deskToday', () => ({
        d: null, loading: true, money, sub: 'arrivals',
        async init() { await this.load(); setInterval(() => this.load(), 60000); },
        async load() { try { this.d = (await api.get('/desk')).data; } catch (e) { /* keep */ } this.loading = false; },
        list() { return this.d?.[this.sub] || []; },
        counts() { return { arrivals: this.d?.arrivals.length || 0, in_house: this.d?.in_house.length || 0, departures: this.d?.departures.length || 0, upcoming: this.d?.upcoming.length || 0 }; },
    }));

    // ---------------------------------------------------------------- timeline (rooms x days)
    Alpine.data('timeline', () => ({
        t: null, from: today(), days: 14, loading: true, money,
        async init() { await this.load(); },
        async load() { this.loading = true; try { this.t = (await api.get('/rooms/timeline', { from: this.from, days: this.days })).data; } catch (e) { /* keep */ } this.loading = false; },
        shift(n) { this.from = addDays(this.from, n); this.load(); },
        goToday() { this.from = today(); this.load(); },
        dayList() { return Array.from({ length: this.days }, (_, i) => addDays(this.from, i)); },
        label: fmtDay,
        isToday: (d) => d === today(),
        isWeekend: (d) => [0, 6].includes(new Date(d + 'T00:00:00').getDay()),
        // grid columns are 1-based; clip bars to the visible window
        span(r) {
            const start = Math.max(0, Math.round((new Date(r.check_in) - new Date(this.from)) / 864e5));
            const end = Math.min(this.days, Math.round((new Date(r.check_out) - new Date(this.from)) / 864e5));
            return `grid-column: ${start + 1} / ${Math.max(end, start + 1) + 1}`;
        },
        barClass(r) { return { checked_in: 'bg-emerald-500 text-white', confirmed: 'bg-sky-500 text-white', pending: 'bg-amber-400 text-slate-900', checked_out: 'bg-slate-300 text-slate-700' }[r.status] || 'bg-slate-300'; },
        hk(room) { return { dirty: 'bg-orange-400', inspected: 'bg-sky-400', out_of_order: 'bg-slate-500', clean: 'bg-emerald-400' }[room.housekeeping_status]; },
        occupancyPct(day) {
            const total = this.t?.rooms.length || 0; if (!total) return 0;
            const n = this.t.rooms.filter((rm) => rm.reservations.some((r) => r.check_in <= day && r.check_out > day)).length;
            return Math.round((100 * n) / total);
        },
    }));

    // ---------------------------------------------------------------- tours calendar
    Alpine.data('tourCalendar', () => ({
        y: new Date().getFullYear(), m: new Date().getMonth(), departures: [], bookings: [], sel: today(), loading: true, manifest: null, money, services: {},
        async init() { try { (await api.get('/catalog-items', { per_page: 200 })).data.forEach((i) => (this.services[i.id] = i.name)); } catch (e) { /* optional */ } await this.load(); },
        title() { return monthName(this.y, this.m); },
        range() { const a = new Date(this.y, this.m, 1), b = new Date(this.y, this.m + 1, 0); const f = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; return [f(a), f(b)]; },
        async load() {
            this.loading = true; const [from, to] = this.range();
            try {
                this.departures = (await api.get('/tourism/departures', { from, to })).data;
                this.bookings = (await api.get('/bookings', { from, to, per_page: 200 })).data.filter((b) => !['cancelled', 'no_show'].includes(b.status));
            } catch (e) { /* keep */ }
            this.loading = false;
        },
        move(n) { const d = new Date(this.y, this.m + n, 1); this.y = d.getFullYear(); this.m = d.getMonth(); this.load(); },
        cells() {
            const first = new Date(this.y, this.m, 1), pad = (first.getDay() + 6) % 7, n = new Date(this.y, this.m + 1, 0).getDate(), out = [];
            for (let i = 0; i < pad; i++) out.push(null);
            for (let d = 1; d <= n; d++) out.push(`${this.y}-${String(this.m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`);
            return out;
        },
        deps(day) { return this.departures.filter((d) => d.date.slice(0, 10) === day); },
        bks(day) { return this.bookings.filter((b) => b.date.slice(0, 10) === day); },
        pax(day) { return this.bks(day).reduce((s, b) => s + b.pax_adults + b.pax_children, 0); },
        isToday: (d) => d === today(),
        name(id) { return this.services[id] || 'Tour'; },
        async showManifest(d) { try { this.manifest = (await api.get(`/tourism/departures/${d.id}/manifest`)).data; } catch (e) { /* ignore */ } },
        printManifest() {
            const m = this.manifest;
            printDoc({ business: 'Tour manifest', title: this.name(m.departure.catalog_item_id), number: m.departure.date.slice(0, 10) + ' ' + (m.departure.start_time || ''), date: `${m.departure.booked}/${m.departure.capacity} seats`, partyLabel: 'Meeting point', party: m.departure.meeting_point || '—',
                lines: [...m.bookings.map((b) => ({ d: `${b.number} · ${b.customer_name} ${b.customer_phone || ''}`, q: b.pax, a: null })), ...m.participants.map((p) => ({ d: `  ${p.name} ${p.nationality || ''} ${p.id_number || ''}`, q: '', a: null }))], totals: [], footer: m.resources.map((r) => r.name).join(' · ') });
        },
    }));

    // ---------------------------------------------------------------- guests: search + 360 profile
    Alpine.data('guestLookup', () => ({
        q: '', rows: [], p: null, loading: false, money,
        async search() { if (this.q.length < 2) { this.rows = []; return; } this.loading = true; try { this.rows = (await api.get('/guests', { q: this.q, per_page: 20 })).data; } catch (e) { /* ignore */ } this.loading = false; },
        async open(id) { try { this.p = (await api.get(`/guests/${id}/profile`)).data; } catch (e) { /* ignore */ } },
        tierClass(t) { return { Platinum: 'bg-violet-100 text-violet-800', Gold: 'bg-amber-100 text-amber-800', Silver: 'bg-slate-200 text-slate-700', New: 'bg-emerald-100 text-emerald-800' }[t]; },
    }));
}
