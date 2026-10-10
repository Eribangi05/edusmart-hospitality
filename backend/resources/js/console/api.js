// Thin fetch wrapper for the cookie-authenticated console. Same API as the Android app.
const xsrf = () => decodeURIComponent((document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || '');

export class ApiError extends Error {
    constructor(status, body) {
        const e = body?.error;
        super(e?.message || body?.message || `Request failed (${status})`);
        this.status = status;
        this.code = e?.code || (status === 422 ? 'validation_failed' : 'error');
        this.details = e?.details || null;
    }
    /** First validation message, if any. */
    get firstDetail() {
        if (!this.details) return null;
        const v = Object.values(this.details)[0];
        return Array.isArray(v) ? v[0] : String(v);
    }
}

const listeners = { unauthorized: () => (location.href = '/login'), suspended: () => {} };
export const onApiEvent = (name, fn) => (listeners[name] = fn);

async function request(method, path, { params, body } = {}) {
    let url = '/api/v1' + path;
    if (params) {
        const qs = new URLSearchParams();
        for (const [k, v] of Object.entries(params)) {
            if (v === undefined || v === null || v === '') continue;
            if (typeof v === 'object') for (const [k2, v2] of Object.entries(v)) qs.append(`${k}[${k2}]`, v2);
            else qs.append(k, v);
        }
        const s = qs.toString();
        if (s) url += '?' + s;
    }
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
        body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (res.status === 204) return null;
    const ct = res.headers.get('content-type') || '';
    const data = ct.includes('json') ? await res.json().catch(() => ({})) : await res.text();
    if (!res.ok) {
        if (res.status === 401) listeners.unauthorized();
        if (res.status === 402) listeners.suspended();
        throw new ApiError(res.status, data);
    }
    return data;
}

export const api = {
    get: (p, params) => request('GET', p, { params }),
    post: (p, body = {}) => request('POST', p, { body }),
    put: (p, body = {}) => request('PUT', p, { body }),
    patch: (p, body = {}) => request('PATCH', p, { body }),
    del: (p) => request('DELETE', p),
    csv: async (p, params) => {
        const qs = new URLSearchParams({ ...params, format: 'csv' });
        const res = await fetch(`/api/v1${p}?${qs}`, { credentials: 'same-origin', headers: { Accept: 'text/csv' } });
        return res.text();
    },
};

export const money = (n) => 'RWF ' + new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Number(n || 0));
export const num = (n) => new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(n || 0));
export const today = () => new Date().toISOString().slice(0, 10);
export const addDays = (d, n) => { const x = new Date(d); x.setDate(x.getDate() + n); return x.toISOString().slice(0, 10); };
export const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = (Math.random() * 16) | 0; return (c === 'x' ? r : (r & 3) | 8).toString(16); }));
export const download = (name, text, type = 'text/csv') => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([text], { type }));
    a.download = name;
    a.click();
    URL.revokeObjectURL(a.href);
};
