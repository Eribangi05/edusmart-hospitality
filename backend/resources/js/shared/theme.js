// Per-user look and feel, applied on every entry (site, console, terminal, kitchen, desk, staff). Stored in this browser only.
const KEY = 'ui.prefs';
export const ACCENTS = { emerald: '#059669', indigo: '#4f46e5', rose: '#e11d48', amber: '#d97706', sky: '#0284c7', violet: '#7c3aed' };
const defaults = { theme: 'auto', accent: 'emerald', density: 'comfortable', textSize: 'md' };

export function load() {
    try { return { ...defaults, ...JSON.parse(localStorage.getItem(KEY) || '{}') }; } catch (e) { return { ...defaults }; }
}

export function apply(p = load()) {
    const root = document.documentElement;
    const dark = p.theme === 'dark' || (p.theme === 'auto' && matchMedia('(prefers-color-scheme: dark)').matches);
    root.classList.toggle('dark', dark);
    root.classList.toggle('compact', p.density === 'compact');
    if (p.accent && p.accent !== 'emerald') root.setAttribute('data-accent', p.accent); else root.removeAttribute('data-accent');
    root.style.fontSize = { sm: '15px', md: '16px', lg: '18px', xl: '20px' }[p.textSize] || '16px';
}

export function save(patch) {
    const p = { ...load(), ...patch };
    try { localStorage.setItem(KEY, JSON.stringify(p)); } catch (e) { /* private mode */ }
    apply(p);
    return p;
}

apply();
matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', () => apply());
window.UI = { load, save, apply, ACCENTS };
