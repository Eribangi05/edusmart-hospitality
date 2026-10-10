import Alpine from 'alpinejs';
import { api } from './console/api.js';
import './shared/theme.js';

window.platform = () => ({
    tab: 'overview', contacts: [], metrics: null, tenants: [], tenant: null, plans: [], audit: [], q: '', status: '', error: '', needs2fa: false, secret: '', uri: '', code: '', planId: '', reason: '',
    async init() {
        try { await this.load(); } catch (e) { if (e.code === 'two_factor_required') this.needs2fa = true; else this.error = e.message; }
    },
    async load() {
        this.metrics = (await api.get('/admin/metrics')).data;
        await Promise.all([this.loadTenants(), this.loadPlans()]);
    },
    async go(t) { this.tab = t; if (t === 'audit') this.audit = (await api.get('/admin/audit')).data; if (t === 'contacts') this.contacts = (await api.get('/admin/contacts')).data; },
    async handled(m) { await api.post(`/admin/contacts/${m.id}/handled`); this.contacts = (await api.get('/admin/contacts')).data; },
    cards() { const m = this.metrics; return m ? [['Companies', m.tenants_total], ['Active', m.tenants.active || 0], ['On trial', m.tenants.trial || 0], ['Suspended', m.tenants.suspended || 0], ['MRR (RWF)', Number(m.mrr).toLocaleString()], ['Collected (RWF)', Number(m.revenue_collected).toLocaleString()], ['Users', m.users], ['Signups 30d', m.signups_30d]] : []; },
    async loadTenants() { this.tenants = (await api.get('/admin/tenants', { q: this.q, status: this.status })).data; },
    async loadPlans() { this.plans = (await api.get('/admin/plans')).data; },
    async openTenant(id) { this.tenant = (await api.get('/admin/tenants/' + id)).data; this.planId = this.tenant.plan_id; this.reason = ''; },
    async setStatus(s) { await api.post(`/admin/tenants/${this.tenant.id}/status`, { status: s }); await this.openTenant(this.tenant.id); await this.loadTenants(); },
    async setPlan() { await api.post(`/admin/tenants/${this.tenant.id}/plan`, { plan_id: this.planId }); await this.openTenant(this.tenant.id); await this.loadTenants(); },
    async markPaid(i) { const reference = prompt('Payment reference (e.g. MoMo transaction id)'); if (!reference) return; await api.post(`/admin/invoices/${i.id}/mark-paid`, { reference }); await this.openTenant(this.tenant.id); },
    async savePlan(p) { await api.patch('/admin/plans/' + p.id, { price_monthly: p.price_monthly }); },
    async impersonate() {
        const r = await api.post(`/admin/tenants/${this.tenant.id}/impersonate`, { reason: this.reason });
        alert('Impersonation token (valid 30 min, audited):\n\n' + r.access_token + '\n\nUse it as a Bearer token against /api/v1, e.g. in the Android app debug login or an API client.');
    },
    async setup2fa() { const r = await api.post('/admin/2fa/setup'); this.secret = r.secret; this.uri = r.uri; },
    async confirm2fa() { await api.post('/admin/2fa/confirm', { code: this.code }); this.needs2fa = false; await this.load(); },
    async logout() {
        await fetch('/logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]*)/) || [])[1] || ''), Accept: 'application/json' } });
        location.href = '/login';
    },
});

window.Alpine = Alpine;
Alpine.start();
