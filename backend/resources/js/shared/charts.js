// Tiny dependency-free SVG charts. Each returns an SVG string for x-html. Colors follow the accent via currentColor / CSS variables.
const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const fmt = (n) => (n >= 1e6 ? (n / 1e6).toFixed(1) + 'M' : n >= 1e3 ? (n / 1e3).toFixed(n >= 1e4 ? 0 : 1) + 'k' : String(Math.round(n)));

/** Bars with value labels. data: [{label, value}] */
export function bars(data, { height = 180, color = 'var(--color-emerald-500)', yFmt = fmt } = {}) {
    const w = 560, pad = { l: 40, r: 8, t: 14, b: 26 }, iw = w - pad.l - pad.r, ih = height - pad.t - pad.b;
    const max = Math.max(1, ...data.map((d) => d.value));
    const bw = iw / Math.max(data.length, 1);
    const grid = [0, 0.5, 1].map((f) => { const y = pad.t + ih - ih * f; return `<line x1="${pad.l}" x2="${w - pad.r}" y1="${y}" y2="${y}" stroke="currentColor" opacity=".12"/><text x="${pad.l - 6}" y="${y + 3}" text-anchor="end" font-size="10" fill="currentColor" opacity=".6">${yFmt(max * f)}</text>`; }).join('');
    const rects = data.map((d, i) => {
        const h = (d.value / max) * ih, x = pad.l + i * bw + bw * 0.15;
        return `<g><title>${esc(d.label)}: ${esc(yFmt(d.value))}</title><rect x="${x}" y="${pad.t + ih - h}" width="${bw * 0.7}" height="${Math.max(h, 1)}" rx="3" fill="${color}"/>` +
            `<text x="${x + bw * 0.35}" y="${height - 9}" text-anchor="middle" font-size="10" fill="currentColor" opacity=".65">${esc(d.label)}</text></g>`;
    }).join('');
    return `<svg viewBox="0 0 ${w} ${height}" class="w-full" role="img" aria-label="Bar chart">${grid}${rects}</svg>`;
}

/** Smooth-ish area line. data: [{label, value}] */
export function line(data, { height = 160, color = 'var(--color-emerald-500)', suffix = '' } = {}) {
    const w = 560, pad = { l: 36, r: 10, t: 12, b: 24 }, iw = w - pad.l - pad.r, ih = height - pad.t - pad.b;
    const vals = data.map((d) => d.value ?? 0), max = Math.max(1, ...vals);
    const x = (i) => pad.l + (data.length < 2 ? iw / 2 : (i / (data.length - 1)) * iw), y = (v) => pad.t + ih - (v / max) * ih;
    const pts = data.map((d, i) => [x(i), y(d.value ?? 0)]);
    const path = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const area = path + ` L${pts.at(-1)?.[0] ?? 0} ${pad.t + ih} L${pts[0]?.[0] ?? 0} ${pad.t + ih} Z`;
    const step = Math.ceil(data.length / 7);
    const labels = data.map((d, i) => (i % step === 0 ? `<text x="${x(i)}" y="${height - 7}" text-anchor="middle" font-size="10" fill="currentColor" opacity=".65">${esc(d.label)}</text>` : '')).join('');
    const dots = pts.map((p, i) => `<circle cx="${p[0]}" cy="${p[1]}" r="3" fill="${color}"><title>${esc(data[i].label)}: ${data[i].value ?? 0}${suffix}</title></circle>`).join('');
    return `<svg viewBox="0 0 ${w} ${height}" class="w-full" role="img" aria-label="Line chart"><line x1="${pad.l}" x2="${w - pad.r}" y1="${pad.t + ih}" y2="${pad.t + ih}" stroke="currentColor" opacity=".15"/>` +
        `<text x="${pad.l - 6}" y="${pad.t + 4}" text-anchor="end" font-size="10" fill="currentColor" opacity=".6">${fmt(max)}${suffix}</text><path d="${area}" fill="${color}" opacity=".15"/><path d="${path}" fill="none" stroke="${color}" stroke-width="2.5" stroke-linejoin="round"/>${dots}${labels}</svg>`;
}

const PALETTE = ['#059669', '#0ea5e9', '#f59e0b', '#8b5cf6', '#e11d48', '#64748b'];
/** Donut with legend. data: [{label, value}] */
export function donut(data, { size = 150 } = {}) {
    const total = data.reduce((s, d) => s + d.value, 0) || 1, r = 52, c = 2 * Math.PI * r;
    let off = 0;
    const arcs = data.map((d, i) => { const len = (d.value / total) * c; const a = `<circle r="${r}" cx="70" cy="70" fill="none" stroke="${PALETTE[i % PALETTE.length]}" stroke-width="22" stroke-dasharray="${len} ${c - len}" stroke-dashoffset="${-off}" transform="rotate(-90 70 70)"><title>${esc(d.label)}: ${Math.round((d.value / total) * 100)}%</title></circle>`; off += len; return a; }).join('');
    const legend = data.map((d, i) => `<li class="flex items-center gap-2"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:${PALETTE[i % PALETTE.length]}"></span><span class="capitalize">${esc(d.label)}</span><span class="ml-auto opacity-70">${Math.round((d.value / total) * 100)}%</span></li>`).join('');
    return `<div class="flex items-center gap-4"><svg viewBox="0 0 140 140" width="${size}" height="${size}" role="img" aria-label="Donut chart">${arcs}</svg><ul class="min-w-[8rem] flex-1 space-y-1 text-sm">${legend}</ul></div>`;
}

/** Heat strip (e.g. orders by hour). data: array of 24 numbers */
export function heat(hours) {
    const max = Math.max(1, ...hours);
    return `<div class="flex gap-0.5">${hours.map((v, h) => `<div class="flex-1 text-center" title="${h}:00 · ${v} orders"><div class="h-10 rounded" style="background:var(--color-emerald-500);opacity:${(0.12 + 0.88 * (v / max)).toFixed(2)}"></div><div class="mt-1 text-[9px] opacity-60">${h % 3 === 0 ? h : ''}</div></div>`).join('')}</div>`;
}
export { fmt };
