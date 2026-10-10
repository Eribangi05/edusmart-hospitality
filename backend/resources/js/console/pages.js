import { api, money, num, today, addDays, uuid, download } from './api.js';
import { t } from './i18n.js';

const store = () => window.Alpine.store('app');
const refCache = {};

/** Load a list of records for select boxes (cached per resource+filter). */
async function refList(resource, filter) {
    const key = resource + JSON.stringify(filter || {});
    if (!refCache[key]) {
        const path = resource === 'users' ? '/users' : '/' + resource;
        const res = await api.get(path, { per_page: 200, filter });
        refCache[key] = res.data;
    }
    return refCache[key];
}
export const clearRefs = () => Object.keys(refCache).forEach((k) => delete refCache[k]);
const label = (r) => r?.name || r?.number || r?.title || r?.email || (r?.id ? String(r.id).slice(0, 8) : '');

export function registerPages(Alpine, { schemas, catalogFields, MENU_KINDS, TOUR_KINDS }) {
    // ------------------------------------------------------------ generic CRUD
    Alpine.data('crud', (resource, opts = {}) => ({
        resource, rows: [], meta: { page: 1, total: 0 }, q: '', loading: true, form: null, editing: null, refs: {}, saving: false, error: '',
        get schema() {
            if (opts.schema) return opts.schema;
            return schemas[this.resource] || { title: this.resource, fields: [], columns: ['name'], entity: this.resource };
        },
        get s() { return store(); },
        get customFields() { return (this.s.ctx.custom_fields || []).filter((c) => c.entity === this.schema.entity); },
        get canWrite() {
            const sc = this.schema;
            return sc.fixed ? this.s.can(sc.fixed) : this.s.can((sc.perm || 'core') + '.create');
        },
        get canDelete() { const sc = this.schema; return sc.fixed ? this.s.can(sc.fixed) : this.s.can((sc.perm || 'core') + '.delete'); },
        get cols() { return this.schema.columns.map((c) => (typeof c === 'string' ? { k: c } : c)); },
        title() { return t(this.schema.title); },
        async init() { await this.load(); },
        async load(page = 1) {
            this.loading = true;
            try {
                const params = { q: this.q, page, per_page: opts.kinds ? 200 : 25, filter: opts.filter };
                if (this.s.propertyId && ['room-types', 'rooms', 'tables', 'catalog-items'].includes(this.resource)) params.filter = { ...(opts.filter || {}), property_id: this.s.propertyId };
                const res = await api.get('/' + this.resource, params);
                this.rows = opts.kinds ? res.data.filter((r) => opts.kinds.includes(r.kind)) : res.data;
                this.meta = res.meta;
                for (const c of this.cols.filter((c) => c.type === 'ref')) this.refs[c.ref] = await refList(c.ref);
            } catch (e) { this.s.err(e); }
            this.loading = false;
        },
        async openForm(row = null) {
            this.error = '';
            this.editing = row?.id || null;
            const f = {};
            const fields = this.allFields();
            for (const fl of fields) {
                if (fl.type === 'ref') this.refs[fl.ref] = await refList(fl.ref, fl.refFilter);
                const v = fl.k.startsWith('meta.') ? row?.meta?.[fl.k.slice(5)] : row?.[fl.k];
                f[fl.k] = this.toForm(fl, v, row);
            }
            this.form = f;
        },
        allFields() {
            const base = opts.fields || this.schema.fields;
            const custom = this.customFields.map((c) => ({
                k: 'meta.' + c.key, req: c.required, custom: true, lbl: c.label?.[this.s.locale] || c.label?.en || c.key,
                type: { text: 'text', textarea: 'textarea', number: 'number', date: 'date', select: 'select', multiselect: 'tags', boolean: 'bool' }[c.type] || 'text',
                opts: (c.options || []).map((o) => ({ value: o, label: o })),
            }));
            return [...base, ...custom];
        },
        toForm(fl, v, row) {
            if (v === undefined || v === null) return fl.type === 'bool' ? !!fl.def : fl.type === 'i18n' ? { en: '', fr: '', rw: '' } : fl.type === 'json' ? '' : fl.type === 'tags' ? '' : fl.def ?? '';
            if (fl.type === 'bp') return v / 100;
            if (fl.type === 'tags') return Array.isArray(v) ? v.join(', ') : v;
            if (fl.type === 'json') return JSON.stringify(v, null, 1);
            if (fl.type === 'datetime') return String(v).slice(0, 16).replace(' ', 'T');
            if (fl.type === 'date') return String(v).slice(0, 10);
            return v;
        },
        fromForm() {
            const out = {};
            const meta = {};
            for (const fl of this.allFields()) {
                let v = this.form[fl.k];
                if (v === '' || v === undefined) v = null;
                if (v !== null) {
                    if (fl.type === 'number' || fl.type === 'money') v = Number(v);
                    if (fl.type === 'bp') v = Math.round(Number(v) * 100);
                    if (fl.type === 'tags') v = String(v).split(',').map((x) => x.trim()).filter(Boolean).map((x) => (fl.numeric ? Number(x) : x));
                    if (fl.type === 'json') v = JSON.parse(v);
                }
                if (fl.k.startsWith('meta.')) { if (v !== null) meta[fl.k.slice(5)] = v; } else if (v !== null || this.editing) out[fl.k] = v;
            }
            if (Object.keys(meta).length || this.customFields.length) out.meta = meta;
            if (opts.defaults) Object.assign(out, opts.defaults);
            return out;
        },
        async save() {
            this.saving = true; this.error = '';
            try {
                const body = this.fromForm();
                if (this.editing) await api.patch(`/${this.resource}/${this.editing}`, body); else await api.post(`/${this.resource}`, body);
                clearRefs();
                this.form = null;
                this.s.toast(t('save') + ' ✓', 'ok');
                await this.load(this.meta.page);
            } catch (e) { this.error = e.firstDetail || e.message; }
            this.saving = false;
        },
        async remove(row) {
            if (!confirm(t('delete') + '?')) return;
            try { await api.del(`/${this.resource}/${row.id}`); clearRefs(); await this.load(this.meta.page); } catch (e) { this.s.err(e); }
        },
        cell(row, c) {
            let v = row[c.k];
            if (v === null || v === undefined) return '';
            if (c.type === 'money') return money(v);
            if (c.type === 'bp') return (v / 100) + '%';
            if (c.type === 'bool') return v ? '✓' : '';
            if (c.type === 'i18n') return v?.[this.s.locale] || v?.en || '';
            if (c.type === 'ref') return label((this.refs[c.ref] || []).find((x) => x.id === v));
            if (Array.isArray(v)) return v.join(', ');
            return String(v).replace(/_/g, ' ').slice(0, 60);
        },
        fl(f) { return f.lbl || t(f.label || f.k) !== (f.label || f.k) ? f.lbl || t(f.label || f.k) : (f.label || f.k).replace(/^meta\./, '').replace(/_/g, ' '); },
        ref(f) { return this.refs[f.ref] || []; },
        refLabel: label,
    }));

    window.catalogOpts = (which) => {
        const kinds = which === 'tour' ? TOUR_KINDS : MENU_KINDS;
        return { kinds, fields: catalogFields(kinds), defaults: {}, schema: { title: which === 'tour' ? 'services' : 'menu', perm: which === 'tour' ? 'tourism' : 'pos', entity: 'catalog_item',
            fields: catalogFields(kinds), columns: ['name', 'kind', { k: 'price', type: 'money' }, 'pricing_model', 'station', { k: 'is_active', type: 'bool' }] } };
    };
    Alpine.data('catalogPage', (kinds) => ({
        init() {},
        kinds, MENU_KINDS, TOUR_KINDS,
        schema: { title: kinds === TOUR_KINDS ? 'services' : 'menu', perm: kinds === TOUR_KINDS ? 'tourism' : 'pos', entity: 'catalog_item', fields: catalogFields(kinds),
            columns: ['name', 'kind', { k: 'price', type: 'money' }, 'pricing_model', 'station', { k: 'is_active', type: 'bool' }] },
    }));

    // ------------------------------------------------------------ dashboard
    Alpine.data('dashboardPage', () => ({
        d: null, date: today(), loading: true, money, num,
        async init() { await this.load(); },
        async load() {
            this.loading = true;
            try { this.d = (await api.get('/reports/dashboard', { date: this.date, property_id: store().propertyId })).data; } catch (e) { store().err(e); }
            this.loading = false;
        },
        methods() { return Object.entries(this.d?.collected?.by_method || {}); },
    }));

    // ------------------------------------------------------------ room board
    Alpine.data('roomBoardPage', () => ({
        rooms: [], date: today(), loading: true, sel: null, money,
        async init() { await this.load(); },
        async load() {
            this.loading = true;
            try { this.rooms = (await api.get('/room-board', { date: this.date, property_id: store().propertyId })).data; } catch (e) { store().err(e); }
            this.loading = false;
        },
        color(r) {
            if (r.housekeeping_status === 'out_of_order') return 'border-slate-400 bg-slate-100 text-slate-500';
            if (r.occupancy === 'occupied') return 'border-rose-300 bg-rose-50';
            if (r.occupancy === 'reserved') return 'border-amber-300 bg-amber-50';
            return r.housekeeping_status === 'dirty' ? 'border-orange-300 bg-orange-50' : 'border-emerald-300 bg-emerald-50';
        },
        counts() { const c = { vacant: 0, occupied: 0, reserved: 0, dirty: 0 }; this.rooms.forEach((r) => { c[r.occupancy]++; if (r.housekeeping_status === 'dirty') c.dirty++; }); return c; },
        async setStatus(r, s) { try { await api.post(`/rooms/${r.id}/status`, { housekeeping_status: s }); this.sel = null; await this.load(); } catch (e) { store().err(e); } },
    }));

    // ------------------------------------------------------------ reservations
    Alpine.data('reservationsPage', () => ({
        rows: [], q: '', status: '', loading: true, money, view: null, form: null, quote: null, types: [], methods: [], guests: [], formErr: '', busy: false,
        async init() {
            this.types = (await refList('room-types')); this.methods = store().ctx.payment_methods;
            await this.load();
        },
        async load() {
            this.loading = true;
            try { this.rows = (await api.get('/reservations', { q: this.q, status: this.status, property_id: store().propertyId, per_page: 100 })).data; } catch (e) { store().err(e); }
            this.loading = false;
        },
        typeName(id) { return label(this.types.find((x) => x.id === id)); },
        newForm() { this.formErr = ''; this.quote = null; this.form = { room_type_id: this.types[0]?.id || '', check_in: today(), check_out: addDays(today(), 1), adults: 2, children: 0, guest: { name: '', phone: '', email: '', nationality: '' }, notes: '', source: 'walk_in' }; this.check(); },
        async check() {
            const f = this.form; if (!f.room_type_id || !f.check_in || !f.check_out || f.check_out <= f.check_in) { this.quote = null; return; }
            try { this.quote = (await api.get('/availability', { room_type_id: f.room_type_id, check_in: f.check_in, check_out: f.check_out })).data; } catch (e) { this.quote = null; this.formErr = e.message; }
        },
        async create() {
            this.busy = true; this.formErr = '';
            try { const r = await api.post('/reservations', { ...this.form, id: uuid() }); this.form = null; await this.load(); await this.open(r.data.id); } catch (e) { this.formErr = e.firstDetail || e.message; }
            this.busy = false;
        },
        async open(id) {
            try {
                const r = await api.get('/reservations/' + id);
                const rooms = (await api.get('/rooms', { per_page: 200, filter: { room_type_id: r.data.room_type_id } })).data;
                this.view = { ...r, rooms, pay: { amount: '', method_type: 'cash', payer_phone: '' }, charge: { description: '', amount: '' }, idNumber: '' };
            } catch (e) { store().err(e); }
        },
        async act(action, body = {}) {
            try { await api.post(`/reservations/${this.view.data.id}/${action}`, body); store().toast('✓', 'ok'); await this.load(); await this.open(this.view.data.id); } catch (e) { store().err(e); }
        },
        async checkIn() { await this.act('check-in', this.view.idNumber ? { guest: { id_number: this.view.idNumber } } : {}); },
        async pay() {
            try { await api.post(`/folios/${this.view.folio.id}/payments`, { ...this.view.pay, amount: Number(this.view.pay.amount), payer_phone: this.view.pay.payer_phone || undefined }); await this.open(this.view.data.id); } catch (e) { store().err(e); }
        },
        async charge() {
            try { await api.post(`/folios/${this.view.folio.id}/entries`, { type: 'charge', description: this.view.charge.description, amount: Number(this.view.charge.amount) }); await this.open(this.view.data.id); } catch (e) { store().err(e); }
        },
        async voidEntry(e) { const reason = prompt(t('delete') + ': reason'); if (!reason) return; try { await api.post(`/folio-entries/${e.id}/void`, { reason }); await this.open(this.view.data.id); } catch (x) { store().err(x); } },
        badge(s) { return { confirmed: 'bg-sky-100 text-sky-800', checked_in: 'bg-emerald-100 text-emerald-800', checked_out: 'bg-slate-100 text-slate-700', cancelled: 'bg-red-100 text-red-700', no_show: 'bg-red-100 text-red-700', pending: 'bg-amber-100 text-amber-800' }[s] || 'bg-slate-100'; },
    }));

    // ------------------------------------------------------------ POS: orders list
    Alpine.data('ordersPage', () => ({
        rows: [], status: '', loading: true, money, view: null, methods: [],
        async init() { this.methods = store().ctx.payment_methods; await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/orders', { status: this.status, per_page: 100, property_id: store().propertyId })).data; } catch (e) { store().err(e); } this.loading = false; },
        async open(id) { try { this.view = { ...(await api.get('/orders/' + id)).data, pay: { amount: '', method_type: 'cash', payer_phone: '' }, pin: '', reason: '' }; this.view.pay.amount = this.view.balance; } catch (e) { store().err(e); } },
        async pay() { try { await api.post(`/orders/${this.view.id}/pay`, { ...this.view.pay, amount: Number(this.view.pay.amount), payer_phone: this.view.pay.payer_phone || undefined, idempotency_key: uuid() }); await this.open(this.view.id); await this.load(); } catch (e) { store().err(e); } },
        async refreshPayment(p) { try { await api.get('/payments/' + p.id); await this.open(this.view.id); await this.load(); } catch (e) { store().err(e); } },
        async voidOrder() { if (!this.view.reason) return store().toast('Reason required', 'error'); try { await api.post(`/orders/${this.view.id}/void`, { reason: this.view.reason, manager_pin: this.view.pin || undefined }); this.view = null; await this.load(); } catch (e) { store().err(e); } },
        async voidItem(i) { const reason = prompt('Reason?'); if (!reason) return; try { await api.post(`/order-items/${i.id}/void`, { reason, manager_pin: this.view.pin || undefined }); await this.open(this.view.id); await this.load(); } catch (e) { store().err(e); } },
        badge(s) { return { open: 'bg-amber-100 text-amber-800', sent: 'bg-sky-100 text-sky-800', paid: 'bg-emerald-100 text-emerald-800', void: 'bg-red-100 text-red-700' }[s] || ''; },
    }));

    // ------------------------------------------------------------ POS: take an order
    Alpine.data('newOrderPage', () => ({
        items: [], cats: [], tables: [], cat: '', q: '', cart: [], tableId: '', type: 'dine_in', customer: '', busy: false, money, order: null, methods: [], pay: { method_type: 'cash', payer_phone: '' }, shift: null,
        async init() {
            const [it, ct, tb, sh] = await Promise.all([refList('catalog-items'), refList('catalog-categories'), refList('tables'), api.get('/shifts/current').catch(() => ({ data: null }))]);
            this.items = it.filter((i) => i.is_active && ['menu', 'drink', 'product', 'service', 'other'].includes(i.kind)); this.cats = ct; this.tables = tb; this.methods = store().ctx.payment_methods.filter((m) => m.type !== 'room_charge'); this.shift = sh.data;
        },
        get shown() { return this.items.filter((i) => (!this.cat || i.category_id === this.cat) && (!this.q || i.name.toLowerCase().includes(this.q.toLowerCase()) || (i.barcode && i.barcode === this.q))); },
        add(i) { const l = this.cart.find((c) => c.catalog_item_id === i.id && !c.notes); if (l) l.quantity++; else this.cart.push({ catalog_item_id: i.id, name: i.name, price: i.price, quantity: 1, notes: '' }); },
        dec(l) { l.quantity--; if (l.quantity <= 0) this.cart = this.cart.filter((x) => x !== l); },
        get estimate() { return this.cart.reduce((s, l) => s + l.price * l.quantity, 0); },
        async submit(sendNow) {
            if (!this.cart.length && !this.order) return;
            this.busy = true;
            try {
                const items = this.cart.map((l) => ({ catalog_item_id: l.catalog_item_id, quantity: l.quantity, notes: l.notes || undefined }));
                if (this.order) { if (items.length) this.order = (await api.post(`/orders/${this.order.id}/items`, { items })).data; } else this.order = (await api.post('/orders', { id: uuid(), type: this.type, table_id: this.tableId || undefined, customer_name: this.customer || undefined, property_id: store().propertyId || undefined, items })).data;
                this.cart = [];
                if (sendNow) { const r = await api.post(`/orders/${this.order.id}/send`); this.order = r.data; store().toast('Sent to kitchen/bar', 'ok'); }
            } catch (e) { store().err(e); }
            this.busy = false;
        },
        async settle() {
            this.busy = true;
            try { const r = await api.post(`/orders/${this.order.id}/pay`, { amount: this.order.balance, ...this.pay, payer_phone: this.pay.payer_phone || undefined, idempotency_key: uuid() }); this.order = r.data; if (this.order.status === 'paid') { store().toast(t('paid') + ' ✓', 'ok'); } } catch (e) { store().err(e); }
            this.busy = false;
        },
        async refreshPay() { const p = this.order.payments.find((x) => x.status === 'pending'); if (!p) return; try { await api.get('/payments/' + p.id); this.order = (await api.get('/orders/' + this.order.id)).data; } catch (e) { store().err(e); } },
        reset() { this.order = null; this.cart = []; this.tableId = ''; this.customer = ''; },
        async openShift() { const v = prompt('Opening cash float (RWF)?', '0'); if (v === null) return; try { this.shift = (await api.post('/shifts', { opening_float: Number(v) })).data; } catch (e) { store().err(e); } },
    }));

    Alpine.data('kitchenPage', () => ({
        station: 'kitchen', tickets: [], timer: null, money,
        async init() { await this.load(); this.timer = setInterval(() => this.load(), 8000); },
        destroy() { clearInterval(this.timer); },
        async load() { try { this.tickets = (await api.get('/kitchen/tickets', { station: this.station })).data; } catch (e) { /* offline: keep last */ } },
        async mark(i, status) { try { await api.post(`/order-items/${i.id}/status`, { status }); await this.load(); } catch (e) { store().err(e); } },
        age(ts) { const m = Math.round((Date.now() - new Date(ts).getTime()) / 60000); return m + ' min'; },
    }));

    Alpine.data('shiftsPage', () => ({
        shift: null, money, counted: '', float: '', rows: [],
        async init() { await this.load(); },
        async load() {
            this.shift = (await api.get('/shifts/current')).data;
            if (store().can('finance.view') && store().hasModule('finance')) this.rows = (await api.get('/reports/cash', { from: addDays(today(), -30), to: today() })).data;
        },
        async open() { try { this.shift = (await api.post('/shifts', { opening_float: Number(this.float || 0) })).data; } catch (e) { store().err(e); } },
        async close() { try { await api.post(`/shifts/${this.shift.id}/close`, { counted_cash: Number(this.counted) }); store().toast('✓', 'ok'); await this.load(); } catch (e) { store().err(e); } },
    }));

    // ------------------------------------------------------------ tourism
    Alpine.data('bookingsPage', () => ({
        rows: [], q: '', status: '', loading: true, money, view: null, form: null, quote: null, services: [], departures: [], resources: [], methods: [], formErr: '', busy: false,
        async init() { this.methods = store().ctx.payment_methods; await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/bookings', { q: this.q, status: this.status, per_page: 100 })).data; } catch (e) { store().err(e); } this.loading = false; },
        async newForm() {
            this.formErr = ''; this.quote = null;
            this.services = (await refList('catalog-items')).filter((i) => i.is_active && TOUR_KINDS.includes(i.kind));
            this.form = { catalog_item_id: this.services[0]?.id || '', departure_id: '', date: today(), pax_adults: 2, pax_children: 0, units: 1, customer_name: '', customer_phone: '', pickup_location: '', notes: '', addons: [] };
            await this.onService();
        },
        get service() { return this.services.find((s) => s.id === this.form?.catalog_item_id); },
        async onService() {
            const f = this.form; f.departure_id = '';
            const s = this.service;
            this.departures = s ? (await api.get('/tourism/departures', { catalog_item_id: s.id, status: 'open', from: today() })).data.filter((d) => d.available > 0) : [];
            await this.requote();
        },
        async requote() {
            const f = this.form; if (!f.catalog_item_id) return;
            if (f.departure_id) f.date = this.departures.find((d) => d.id === f.departure_id)?.date?.slice(0, 10) || f.date;
            try { this.quote = (await api.post('/tourism/quote', { catalog_item_id: f.catalog_item_id, departure_id: f.departure_id || undefined, pax_adults: Number(f.pax_adults), pax_children: Number(f.pax_children), units: Number(f.units) })).data; this.formErr = ''; } catch (e) { this.quote = null; this.formErr = e.firstDetail || e.message; }
        },
        async create() {
            this.busy = true;
            try { const f = this.form; const r = await api.post('/bookings', { ...f, id: uuid(), departure_id: f.departure_id || undefined, pax_adults: Number(f.pax_adults), pax_children: Number(f.pax_children), units: Number(f.units), addons: undefined }); this.form = null; await this.load(); await this.open(r.data.id); } catch (e) { this.formErr = e.firstDetail || e.message; }
            this.busy = false;
        },
        async open(id) {
            try {
                const b = (await api.get('/bookings/' + id)).data;
                this.resources = await refList('tourism-resources');
                this.view = { ...b, pay: { amount: b.balance, method_type: 'cash', payer_phone: '' }, resource_id: '' };
            } catch (e) { store().err(e); }
        },
        async status(to) { try { await api.post(`/bookings/${this.view.id}/status/${to}`); await this.load(); await this.open(this.view.id); } catch (e) { store().err(e); } },
        async assign() { try { await api.post(`/bookings/${this.view.id}/assign`, { resource_id: this.view.resource_id }); await this.open(this.view.id); } catch (e) { store().err(e); } },
        async pay() { try { await api.post(`/bookings/${this.view.id}/payments`, { ...this.view.pay, amount: Number(this.view.pay.amount), payer_phone: this.view.pay.payer_phone || undefined, idempotency_key: uuid() }); await this.load(); await this.open(this.view.id); } catch (e) { store().err(e); } },
        resName(id) { return label(this.resources.find((r) => r.id === id)); },
        badge(s) { return { inquiry: 'bg-slate-100', confirmed: 'bg-sky-100 text-sky-800', paid: 'bg-emerald-100 text-emerald-800', in_progress: 'bg-indigo-100 text-indigo-800', completed: 'bg-slate-200', cancelled: 'bg-red-100 text-red-700', no_show: 'bg-red-100 text-red-700' }[s] || ''; },
    }));

    Alpine.data('departuresPage', () => ({
        rows: [], from: today(), loading: true, manifest: null, tours: [], form: null, err: '',
        async init() { this.tours = (await refList('catalog-items')).filter((i) => TOUR_KINDS.includes(i.kind)); await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/tourism/departures', { from: this.from })).data; } catch (e) { store().err(e); } this.loading = false; },
        tourName(id) { return label(this.tours.find((t) => t.id === id)); },
        newForm() { this.err = ''; this.form = { catalog_item_id: this.tours[0]?.id, date: today(), start_time: '08:00', capacity: 8, meeting_point: '' }; },
        async save() { try { await api.post('/tour-departures', { ...this.form, capacity: Number(this.form.capacity) }); this.form = null; await this.load(); } catch (e) { this.err = e.firstDetail || e.message; } },
        async showManifest(d) { try { this.manifest = (await api.get(`/tourism/departures/${d.id}/manifest`)).data; } catch (e) { store().err(e); } },
        async setStatus(d, s) { try { await api.patch('/tour-departures/' + d.id, { status: s }); await this.load(); } catch (e) { store().err(e); } },
        print() { window.print(); },
    }));

    // ------------------------------------------------------------ inventory
    Alpine.data('stockPage', () => ({
        rows: [], loading: true, lowOnly: false, adj: null, count: null, counts: {}, num,
        async init() { await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/stock', { low_only: this.lowOnly ? 1 : undefined, property_id: store().propertyId })).data; } catch (e) { store().err(e); } this.loading = false; },
        openAdjust(r) { this.adj = { item: r, type: 'purchase', quantity: '', note: '', unit_cost: r.cost }; },
        async saveAdjust() { try { await api.post('/stock/adjust', { inventory_item_id: this.adj.item.id, type: this.adj.type, quantity: Number(this.adj.quantity), note: this.adj.note || undefined, unit_cost: Number(this.adj.unit_cost) || undefined, idempotency_key: uuid() }); this.adj = null; await this.load(); } catch (e) { store().err(e); } },
        startCount() { this.counts = {}; this.rows.forEach((r) => (this.counts[r.id] = r.quantity)); this.count = true; },
        async saveCount() { try { await api.post('/stock/stocktake', { counts: this.rows.map((r) => ({ inventory_item_id: r.id, counted: Number(this.counts[r.id]) })) }); this.count = null; await this.load(); store().toast('✓', 'ok'); } catch (e) { store().err(e); } },
    }));

    Alpine.data('poPage', () => ({
        rows: [], loading: true, form: null, items: [], suppliers: [], money, recv: null,
        async init() { this.items = await refList('inventory-items'); this.suppliers = await refList('suppliers'); await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/purchase-orders')).data; } catch (e) { store().err(e); } this.loading = false; },
        newForm() { this.form = { supplier_id: '', expected_on: '', status: 'ordered', lines: [{ inventory_item_id: this.items[0]?.id, quantity: 1, unit_cost: this.items[0]?.cost || 0 }] }; },
        addLine() { this.form.lines.push({ inventory_item_id: this.items[0]?.id, quantity: 1, unit_cost: this.items[0]?.cost || 0 }); },
        async save() { try { await api.post('/purchase-orders', { ...this.form, supplier_id: this.form.supplier_id || undefined, expected_on: this.form.expected_on || undefined, lines: this.form.lines.map((l) => ({ ...l, quantity: Number(l.quantity), unit_cost: Number(l.unit_cost) })) }); this.form = null; await this.load(); } catch (e) { store().err(e); } },
        startReceive(po) { this.recv = { po, key: uuid().slice(0, 8), qty: Object.fromEntries(po.lines.map((l) => [l.inventory_item_id, Math.max(l.quantity - (l.received || 0), 0)])) }; },
        async receive() { try { await api.post(`/purchase-orders/${this.recv.po.id}/receive`, { receipt_key: this.recv.key, receipts: Object.entries(this.recv.qty).filter(([, q]) => Number(q) > 0).map(([id, q]) => ({ inventory_item_id: id, quantity: Number(q) })) }); this.recv = null; await this.load(); } catch (e) { store().err(e); } },
        itemName(id) { return label(this.items.find((i) => i.id === id)); },
    }));

    // ------------------------------------------------------------ staff
    Alpine.data('attendancePage', () => ({
        rows: [], from: addDays(today(), -7), to: today(), flagged: false, emps: [], loading: true,
        async init() { this.emps = await refList('employees'); await this.load(); },
        async load() { this.loading = true; try { this.rows = (await api.get('/attendance', { from: this.from, to: this.to + ' 23:59:59', flagged: this.flagged ? 1 : undefined })).data; } catch (e) { store().err(e); } this.loading = false; },
        emp(id) { return label(this.emps.find((e) => e.id === id)); },
    }));

    Alpine.data('payrollPage', () => ({
        from: today().slice(0, 8) + '01', to: today(), rows: [], total: 0, money, loading: false,
        async run() { this.loading = true; try { const r = await api.get('/payroll', { from: this.from, to: this.to }); this.rows = r.data; this.total = r.total; } catch (e) { store().err(e); } this.loading = false; },
        async csv() { download(`payroll_${this.from}_${this.to}.csv`, await api.csv('/payroll', { from: this.from, to: this.to })); },
    }));

    // ------------------------------------------------------------ reports
    Alpine.data('reportsPage', () => ({
        tab: 'sales', group: 'day', from: today().slice(0, 8) + '01', to: today(), rows: [], summary: null, profit: null, loading: false, money, num,
        async init() { await this.run(); },
        async run() {
            this.loading = true; const pid = store().propertyId || undefined;
            try {
                if (this.tab === 'sales') this.rows = (await api.get('/reports/sales', { from: this.from, to: this.to, group: this.group, property_id: pid })).data;
                else if (this.tab === 'occupancy') { const r = await api.get('/reports/occupancy', { from: this.from, to: this.to, property_id: pid }); this.rows = r.data; this.summary = r.summary; }
                else if (this.tab === 'profit') this.profit = (await api.get('/reports/profit', { from: this.from, to: this.to, property_id: pid })).data;
                else if (this.tab === 'cash') { const r = await api.get('/reports/cash', { from: this.from, to: this.to }); this.rows = r.data; this.summary = { variance: r.variance_total }; }
            } catch (e) { store().err(e); }
            this.loading = false;
        },
        setTab(t2) { this.tab = t2; this.rows = []; this.run(); },
        max() { return Math.max(1, ...this.rows.map((r) => Number(r.amount ?? r.room_revenue ?? 0))); },
        async csv() { const p = this.tab === 'occupancy' ? '/reports/occupancy' : '/reports/sales'; download(`${this.tab}_${this.from}_${this.to}.csv`, await api.csv(p, { from: this.from, to: this.to, group: this.group })); },
    }));
}
