import Alpine from 'alpinejs';
import './shared/theme.js';
import { api, money, uuid } from './console/api.js';

const clockText = () => new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

window.terminal = () => ({
    money, user: '', ctx: null, items: [], cats: [], tables: [], orders: [], shift: null, inHouse: [],
    types: [{ k: 'dine_in', l: 'Dine in' }, { k: 'takeaway', l: 'Takeaway' }, { k: 'room_service', l: 'Room service' }, { k: 'retail', l: 'Retail' }],
    type: 'dine_in', tableId: '', order: null, draft: [], cat: '', q: '', pane: 'menu', error: '', busy: false, clock: clockText(),
    payOpen: false, method: 'cash', tendered: 0, phone: '', reference: '', momoMsg: '', guestQ: '', roomPick: '', due: 0, receipt: null, change: 0,
    discountModal: false, dKind: 'percent', dValue: null, pin: '', code: '', voidLine: null, voidOrderModal: false, reason: '',
    splitModal: false, splitPick: [], shiftModal: false, shiftAmount: null, shiftResult: '', poll: null, scan: '', scanAt: 0,

    async init() {
        try {
            this.ctx = await api.get('/me/context');
            this.user = this.ctx.user.name;
            const [items, cats, tables] = await Promise.all([api.get('/catalog-items', { per_page: 200 }), api.get('/catalog-categories', { per_page: 200 }), api.get('/tables', { per_page: 200 })]);
            this.items = items.data.filter((i) => i.is_active && ['menu', 'drink', 'product', 'service', 'other'].includes(i.kind)).sort((a, b) => a.name.localeCompare(b.name));
            this.cats = cats.data.filter((c) => c.is_active).sort((a, b) => a.position - b.position);
            this.tables = tables.data.filter((t) => t.is_active);
            await Promise.all([this.refresh(), this.loadShift()]);
        } catch (e) { this.error = e.message; }
        setInterval(() => (this.clock = clockText()), 20000);
        this.poll = setInterval(() => this.refresh(), 15000);
        window.addEventListener('keydown', (e) => this.onKey(e));
    },

    // barcode scanners type fast and end with Enter; capture them anywhere on the screen
    onKey(e) {
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) return;
        const now = Date.now();
        if (e.key === 'Enter' && this.scan.length > 3) { this.byBarcode(this.scan); this.scan = ''; return; }
        if (e.key.length === 1) { this.scan = now - this.scanAt > 80 ? e.key : this.scan + e.key; this.scanAt = now; }
        if (e.key === '/' ) { e.preventDefault(); this.$refs.search.focus(); }
    },
    byBarcode(code) { const i = this.items.find((x) => x.barcode === code); if (i) this.add(i); else this.error = 'No item with barcode ' + code; },
    scanOrSearch() { const i = this.items.find((x) => x.barcode === this.q); if (i) { this.add(i); this.q = ''; } },

    theme() { const d = document.documentElement.classList.contains('dark'); window.UI.save({ theme: d ? 'light' : 'dark' }); },
    setType(k) { this.type = k; if (k !== 'dine_in') this.tableId = ''; },
    async refresh() {
        try {
            const r = await api.get('/orders', { per_page: 200 });
            this.orders = r.data.filter((o) => ['open', 'sent'].includes(o.status));
            if (this.order && this.order.status !== 'paid') this.order = (await api.get('/orders/' + this.order.id)).data;
        } catch (e) { /* offline: keep what we have */ }
    },
    async loadShift() { try { this.shift = (await api.get('/shifts/current')).data; } catch (e) { /* module may restrict */ } },

    // ---- tables
    sections() { const m = {}; this.tables.forEach((t) => (m[t.section || 'Tables'] ||= []).push(t)); return Object.entries(m); },
    orderFor(t) { return this.orders.find((o) => o.table_id === t.id); },
    tableClass(t) {
        const o = this.orderFor(t);
        const sel = this.tableId === t.id ? ' ring-2 ring-emerald-500' : '';
        if (!o) return 'border-emerald-200 bg-emerald-50 text-emerald-900' + sel;
        return (o.status === 'sent' ? 'border-amber-300 bg-amber-50 text-amber-900' : 'border-sky-300 bg-sky-50 text-sky-900') + sel;
    },
    tableInfo(t) { const o = this.orderFor(t); return o ? money(o.total) : t.capacity + ' seats'; },
    looseOrders() { return this.orders.filter((o) => !o.table_id); },
    async pickTable(t) {
        this.type = 'dine_in'; this.tableId = t.id; this.draft = []; this.pane = 'menu';
        const o = this.orderFor(t);
        this.order = o ? (await api.get('/orders/' + o.id)).data : null;
    },
    async openOrder(id) { this.draft = []; this.order = (await api.get('/orders/' + id)).data; this.tableId = this.order.table_id || ''; this.type = this.order.type; this.pane = 'ticket'; },
    newTicket() { this.order = null; this.draft = []; this.tableId = ''; this.code = ''; this.receipt = null; this.pane = 'menu'; },
    ticketSubtitle() {
        const t = this.tables.find((x) => x.id === this.tableId);
        return [t ? 'Table ' + t.name : this.types.find((x) => x.k === this.type)?.l, this.order ? this.order.status : 'draft'].filter(Boolean).join(' · ');
    },

    // ---- menu
    shown() { const q = this.q.toLowerCase(); return this.items.filter((i) => (!this.cat || i.category_id === this.cat) && (!q || i.name.toLowerCase().includes(q))); },
    add(i) {
        if (this.order && ['paid', 'void'].includes(this.order.status)) this.newTicket();
        const l = this.draft.find((d) => d.catalog_item_id === i.id && !d.notes);
        if (l) l.quantity++; else this.draft.push({ catalog_item_id: i.id, name: i.name, price: i.price, quantity: 1, notes: '' });
        navigator.vibrate?.(8);
    },
    dec(idx) { const l = this.draft[idx]; if (l.quantity <= 1) this.draft.splice(idx, 1); else l.quantity--; },
    draftTotal() { return this.draft.reduce((s, l) => s + l.price * l.quantity, 0); },

    // ---- ordering
    async persist() {
        const items = this.draft.map((l) => ({ catalog_item_id: l.catalog_item_id, quantity: l.quantity, notes: l.notes || undefined }));
        if (!this.order) this.order = (await api.post('/orders', { id: uuid(), type: this.type, table_id: this.tableId || undefined, items })).data;
        else if (items.length) this.order = (await api.post(`/orders/${this.order.id}/items`, { items })).data;
        this.draft = [];
    },
    async submit(send) {
        this.busy = true; this.error = '';
        try {
            if (this.draft.length || !this.order) await this.persist();
            if (send && this.order.status === 'open') this.order = (await api.post(`/orders/${this.order.id}/send`)).data;
            await this.refresh();
            if (send) this.pane = 'menu';
        } catch (e) { this.error = e.firstDetail || e.message; }
        this.busy = false;
    },
    async applyCode() {
        if (!this.code) return;
        try { if (this.draft.length || !this.order) await this.persist(); this.order = (await api.post(`/orders/${this.order.id}/voucher`, { code: this.code })).data; this.code = ''; } catch (e) { this.error = e.firstDetail || e.message; }
    },
    async applyDiscount() {
        try {
            if (this.draft.length) await this.persist();
            const body = this.dKind === 'percent' ? { percent_bp: Math.round(this.dValue * 100) } : { amount: this.dValue };
            this.order = (await api.post(`/orders/${this.order.id}/discount`, { ...body, manager_pin: this.pin || undefined })).data;
            this.discountModal = false; this.pin = ''; this.dValue = null;
        } catch (e) { this.error = e.firstDetail || e.message; }
    },
    askVoid(l) { this.voidLine = l; this.reason = ''; this.pin = ''; },
    async doVoidLine() {
        try { this.order = (await api.post(`/order-items/${this.voidLine.id}/void`, { reason: this.reason, manager_pin: this.pin || undefined })).data; this.voidLine = null; await this.refresh(); } catch (e) { this.error = e.firstDetail || e.message; this.voidLine = null; }
    },
    async doVoidOrder() {
        try { await api.post(`/orders/${this.order.id}/void`, { reason: this.reason, manager_pin: this.pin || undefined }); this.voidOrderModal = false; this.newTicket(); await this.refresh(); } catch (e) { this.error = e.firstDetail || e.message; this.voidOrderModal = false; }
    },
    splitLines() { return (this.order?.items || []).filter((l) => !l.voided_at); },
    splitOpen() { this.splitPick = []; this.splitModal = true; },
    async doSplit() {
        try { const r = await api.post(`/orders/${this.order.id}/split`, { item_ids: this.splitPick }); this.order = r.data; this.splitModal = false; await this.refresh(); } catch (e) { this.error = e.firstDetail || e.message; this.splitModal = false; }
    },

    // ---- payment
    payMethods() { return (this.ctx?.payment_methods || []).filter((m) => m.type !== 'room_charge' || this.inHouse.length || true); },
    quickAmounts() { const d = this.due; const up = (n) => Math.ceil(d / n) * n; return [...new Set([d, up(500), up(1000), up(5000), up(10000)])].filter((x) => x >= d).slice(0, 5); },
    inHouseFiltered() { const q = this.guestQ.toLowerCase(); return this.inHouse.filter((g) => !q || (g.guest || '').toLowerCase().includes(q) || String(g.room || '').toLowerCase().includes(q)); },
    async openPay() {
        this.busy = true; this.error = '';
        try { if (this.draft.length || !this.order) await this.persist(); await this.refresh(); } catch (e) { this.error = e.firstDetail || e.message; this.busy = false; return; }
        this.busy = false;
        this.due = this.order.balance; this.tendered = this.due; this.method = 'cash'; this.phone = ''; this.reference = ''; this.momoMsg = ''; this.roomPick = ''; this.payOpen = true;
        if (this.ctx.modules.includes('rooms') && this.ctx.permissions.some((p) => p === '*' || p.startsWith('rooms.'))) {
            try { this.inHouse = (await api.get('/desk')).data.in_house; } catch (e) { this.inHouse = []; }
        }
    },
    canConfirm() {
        if (this.method === 'cash') return this.tendered >= this.due;
        if (this.method === 'momo') return this.phone.replace(/\D/g, '').length >= 9;
        if (this.method === 'room_charge') return !!this.roomPick;
        return true;
    },
    async confirmPay() {
        this.busy = true; this.error = '';
        try {
            const id = this.order.id;
            if (this.method === 'room_charge') {
                this.order = (await api.post(`/orders/${id}/charge-room`, { reservation_id: this.roomPick })).data;
            } else {
                const body = { amount: this.due, method_type: this.method, idempotency_key: uuid() };
                if (this.method === 'momo') body.payer_phone = this.phone;
                if (this.reference) body.reference = this.reference;
                const r = await api.post(`/orders/${id}/pay`, body);
                this.order = r.data;
                if (this.method === 'momo' && r.payment.status === 'pending') { await this.waitMomo(r.payment.id); }
            }
            if (this.order.status === 'paid') { this.change = this.method === 'cash' ? Math.max(this.tendered - this.due, 0) : 0; this.receipt = this.order; this.payOpen = false; await this.refresh(); }
        } catch (e) { this.error = e.firstDetail || e.message; }
        this.busy = false;
    },
    async waitMomo(pid) {
        this.momoMsg = 'Waiting for the customer to approve on their phone…';
        for (let i = 0; i < 30; i++) {
            await new Promise((r) => setTimeout(r, 4000));
            const p = (await api.get('/payments/' + pid)).data;
            if (p.status === 'successful') { this.order = (await api.get('/orders/' + this.order.id)).data; return; }
            if (p.status === 'failed') { this.momoMsg = 'The customer did not approve. Try again or take cash.'; this.error = 'MoMo payment failed'; return; }
        }
        this.momoMsg = 'Still pending. You can check again from Orders.';
    },
    printReceipt() {
        const o = this.receipt, c = this.ctx;
        const lines = (o.items || []).filter((l) => !l.voided_at).map((l) => `<tr><td>${Number(l.quantity)}× ${l.name}</td><td style="text-align:right">${money(l.line_total)}</td></tr>`).join('');
        const w = window.open('', '_blank', 'width=360,height=640');
        w.document.write(`<html><body style="font-family:monospace;width:300px;margin:8px auto"><h3 style="text-align:center">${c.tenant.name}</h3><p style="text-align:center">Receipt ${o.number}<br>${new Date().toLocaleString()}</p><hr><table style="width:100%">${lines}</table><hr>
            <table style="width:100%"><tr><td>Tax</td><td style="text-align:right">${money(o.tax)}</td></tr><tr><td><b>TOTAL</b></td><td style="text-align:right"><b>${money(o.total)}</b></td></tr></table><hr><p style="text-align:center">${c.settings['receipt.footer'] || 'Thank you'}</p></body></html>`);
        w.document.close(); w.focus(); w.print();
    },

    // ---- shift
    async doShift() {
        try {
            if (this.shift) { const r = await api.post(`/shifts/${this.shift.id}/close`, { counted_cash: this.shiftAmount }); this.shiftResult = `Expected ${money(r.data.expected_cash)} · counted ${money(r.data.counted_cash)} · variance ${money(r.data.variance)}`; this.shift = null; }
            else { this.shift = (await api.post('/shifts', { opening_float: this.shiftAmount })).data; this.shiftModal = false; }
        } catch (e) { this.error = e.firstDetail || e.message; this.shiftModal = false; }
    },
});

window.Alpine = Alpine;
Alpine.start();
