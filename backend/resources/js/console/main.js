import Alpine from 'alpinejs';
import { api, onApiEvent, money, num, today, addDays, uuid, download } from './api.js';
import { t, setLocale, setOverrides, getLocale } from './i18n.js';
import { schemas, catalogFields, MENU_KINDS, TOUR_KINDS } from './schemas.js';
import { registerPages } from './pages.js';
import { registerSettings } from './settings.js';
import { registerChrome } from './chrome.js';
import { registerFrontDesk } from '../shared/frontdesk.js';
import '../shared/theme.js';

window.Alpine = Alpine;

// ---- navigation (module = tenant module that must be on; any = permissions of which one is enough) ----
export const NAV = [
    { id: 'dashboard', label: 'dashboard', icon: '▦', route: '', any: [] },
    { group: 'rooms', label: 'rooms', module: 'rooms', items: [
        { label: 'board', route: 'rooms/board', any: ['rooms.view'] },
        { label: 'reservations', route: 'rooms/reservations', any: ['rooms.view'] },
        { label: 'timeline', route: 'rooms/timeline', any: ['rooms.view'] },
        { label: 'guests', route: 'crud/guests', any: ['rooms.view', 'tourism.view'] },
        { label: 'room_types', route: 'crud/room-types', any: ['rooms.view'] },
        { label: 'rooms', route: 'crud/rooms', any: ['rooms.view'] },
        { label: 'rate_plans', route: 'crud/rate-plans', any: ['rooms.view'] },
    ] },
    { group: 'pos', label: 'pos', module: 'pos', items: [
        { label: 'new_order', route: 'pos/new', any: ['pos.create'] },
        { label: 'orders', route: 'pos/orders', any: ['pos.view'] },
        { label: 'kitchen', route: 'pos/kitchen', any: ['pos.view'] },
        { label: 'menu', route: 'menu', any: ['pos.view'] },
        { label: 'categories', route: 'crud/catalog-categories', any: ['pos.view'] },
        { label: 'tables', route: 'crud/tables', any: ['pos.view'] },
        { label: 'shifts', route: 'pos/shifts', any: ['pos.view'] },
        { label: 'vouchers', route: 'crud/vouchers', any: ['pos.view'] },
    ] },
    { group: 'tours', label: 'tours', module: 'tourism', items: [
        { label: 'bookings', route: 'tours/bookings', any: ['tourism.view'] },
        { label: 'departures', route: 'tours/departures', any: ['tourism.view'] },
        { label: 'calendar', route: 'tours/calendar', any: ['tourism.view'] },
        { label: 'services', route: 'tours/services', any: ['tourism.view'] },
        { label: 'resources', route: 'crud/tourism-resources', any: ['tourism.view'] },
    ] },
    { group: 'inventory', label: 'inventory', module: 'inventory', items: [
        { label: 'stock', route: 'inventory/stock', any: ['inventory.view'] },
        { label: 'items', route: 'crud/inventory-items', any: ['inventory.view'] },
        { label: 'recipes', route: 'crud/recipes', any: ['inventory.view'] },
        { label: 'suppliers', route: 'crud/suppliers', any: ['inventory.view'] },
        { label: 'purchase_orders', route: 'inventory/po', any: ['inventory.view'] },
    ] },
    { group: 'staff', label: 'staff', module: 'staff', items: [
        { label: 'employees', route: 'crud/employees', any: ['staff.view'] },
        { label: 'attendance', route: 'staff/attendance', any: ['staff.attendance_manage'] },
        { label: 'leave', route: 'crud/leave-requests', any: ['staff.view'] },
        { label: 'payroll', route: 'staff/payroll', any: ['staff.payroll_export'] },
    ] },
    { group: 'housekeeping', label: 'housekeeping', module: 'housekeeping', items: [
        { label: 'tasks', route: 'crud/tasks', any: ['housekeeping.view'] },
    ] },
    { group: 'finance', label: 'finance', module: 'finance', items: [
        { label: 'reports', route: 'finance/reports', any: ['finance.view'] },
        { label: 'expenses', route: 'crud/expenses', any: ['finance.view'] },
    ] },
    { group: 'settings', label: 'settings', items: [
        { label: 'company', route: 'crud/properties', any: ['core.settings'] },
        { label: 'modules', route: 'settings/modules', any: ['core.settings'] },
        { label: 'users', route: 'settings/users', any: ['core.users'] },
        { label: 'roles', route: 'settings/roles', any: ['core.roles'] },
        { label: 'taxes', route: 'crud/tax-rates', any: ['core.settings'] },
        { label: 'payment_methods', route: 'crud/payment-methods', any: ['core.settings'] },
        { label: 'custom_fields', route: 'crud/custom-fields', any: ['core.settings'] },
        { label: 'labels', route: 'settings/labels', any: ['core.settings'] },
        { label: 'devices', route: 'settings/devices', any: ['core.users'] },
        { label: 'audit', route: 'settings/audit', any: ['core.audit'] },
        { label: 'billing', route: 'settings/billing', any: ['core.settings'] },
    ] },
];

document.addEventListener('alpine:init', () => {
    Alpine.store('app', {
        ctx: null, ready: false, route: { page: 'dashboard', params: {}, path: '' }, propertyId: '', toasts: [], nav: false, locale: 'en', banner: '',
        async init() {
            onApiEvent('suspended', () => this.toast(t('read_only'), 'error'));
            try {
                this.ctx = await api.get('/me/context');
            } catch (e) { return; }
            this.locale = localStorage.getItem('locale') || this.ctx.locale || 'en';
            this.applyLocale();
            this.propertyId = localStorage.getItem('property') || '';
            if (this.ctx.tenant.status === 'suspended') this.banner = t('read_only');
            else if (this.ctx.tenant.status === 'grace') this.banner = t('grace');
            this.ready = true;
            window.addEventListener('hashchange', () => this.resolve());
            this.resolve();
        },
        applyLocale() { setLocale(this.locale); setOverrides(this.ctx.settings?.terminology); this.locale = getLocale(); },
        setLocale(l) { this.locale = l; localStorage.setItem('locale', l); this.applyLocale(); this.ready = false; Alpine.nextTick(() => (this.ready = true)); },
        setProperty(id) { this.propertyId = id; localStorage.setItem('property', id); location.reload(); },
        t(k) { void this.locale; return t(k); },
        can(p) { const perms = this.ctx?.permissions || []; if (perms.includes('*') || perms.includes(p)) return true; return perms.includes(p.split('.')[0] + '.*'); },
        canAny(list) { return !list.length || list.some((p) => this.can(p)); },
        hasModule(m) { return !m || this.ctx?.modules?.includes(m); },
        visibleNav() {
            return NAV.map((g) => g.items
                ? { ...g, items: g.items.filter((i) => this.canAny(i.any)) }
                : g).filter((g) => (!g.module || this.hasModule(g.module)) && (!g.items || g.items.length));
        },
        setting(k, d = null) { const v = this.ctx?.settings?.[k]; return v === undefined || v === null ? d : v; },
        resolve() {
            const path = (location.hash || '#/').replace(/^#\//, '').split('?')[0];
            const seg = path.split('/');
            let page = 'dashboard';
            const params = {};
            if (path === '') page = 'dashboard';
            else if (seg[0] === 'crud') { page = 'crud'; params.resource = seg[1]; }
            else if (path === 'menu') page = 'menu';
            else if (path === 'tours/services') page = 'services';
            else page = path.replace(/\//g, '.');
            this.route = { page, params, path };
            this.nav = false;
            window.scrollTo(0, 0);
        },
        toast(msg, kind = 'info') { const id = Date.now() + Math.random(); this.toasts.push({ id, msg, kind }); setTimeout(() => (this.toasts = this.toasts.filter((x) => x.id !== id)), 5000); },
        async logout() {
            await fetch('/logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]*)/) || [])[1] || ''), Accept: 'application/json' } });
            location.href = '/login';
        },
        err(e) { this.toast(e.firstDetail || e.message || 'Error', 'error'); },
    });
});

Object.assign(window, { api, money, num, today, addDays, uuid, download, T: t });

// ---- generic CRUD component ----
document.addEventListener('alpine:init', () => {
    registerPages(Alpine, { schemas, catalogFields, MENU_KINDS, TOUR_KINDS });
    registerSettings(Alpine);
    registerFrontDesk(Alpine);
    registerChrome(Alpine, NAV);
});

Alpine.start();
