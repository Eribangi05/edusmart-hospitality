import Alpine from 'alpinejs';

const token = () => document.querySelector('meta[name="csrf-token"]').content;

async function post(url, body) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token() },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
}

function message(data) {
    const first = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
    return first || data?.error?.message || data?.message || 'Something went wrong. Please try again.';
}

window.loginForm = () => ({
    email: '', password: '', code: '', twoFactor: false, error: '', busy: false,
    async submit() {
        this.busy = true; this.error = '';
        const { status, data } = await post('/login', { email: this.email, password: this.password, code: this.code || undefined });
        this.busy = false;
        if (status === 202) { this.twoFactor = true; return; }
        if (data.redirect) { location.href = data.redirect; return; }
        this.error = message(data);
    },
});

window.registerForm = () => ({
    f: { company: '', property_type: 'hotel', locale: 'en', name: '', phone: '', email: '', password: '', modules: ['rooms', 'pos'] },
    error: '', busy: false,
    async submit() {
        this.busy = true; this.error = '';
        const { data } = await post('/register', this.f);
        this.busy = false;
        if (data.redirect) { location.href = data.redirect; return; }
        this.error = message(data);
    },
});

window.forgotForm = () => ({
    email: '', done: false, error: '', busy: false,
    async submit() {
        this.busy = true; this.error = '';
        const { status, data } = await post('/forgot-password', { email: this.email });
        this.busy = false;
        if (status === 200) this.done = true; else this.error = message(data);
    },
});

window.resetForm = (token, email) => ({
    token, email, password: '', error: '', busy: false,
    async submit() {
        this.busy = true; this.error = '';
        const { data } = await post('/reset-password', { token: this.token, email: this.email, password: this.password });
        this.busy = false;
        if (data.redirect) { location.href = data.redirect; return; }
        this.error = message(data);
    },
});

window.Alpine = Alpine;
Alpine.start();
