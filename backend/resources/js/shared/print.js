import { money } from '../console/api.js';

const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

/** Opens a clean, printable invoice/bill in a new window. doc: {business, title, number, date, party, lines:[{d,q,a}], totals:[{l,a,bold}], footer} */
export function printDoc(doc) {
    const rows = (doc.lines || []).map((l) => `<tr><td>${esc(l.d)}</td><td class="r">${esc(l.q ?? '')}</td><td class="r">${l.a === null || l.a === undefined ? '' : money(l.a)}</td></tr>`).join('');
    const totals = (doc.totals || []).map((t) => `<tr class="${t.bold ? 'b' : ''}"><td colspan="2" class="r">${esc(t.l)}</td><td class="r">${money(t.a)}</td></tr>`).join('');
    const w = window.open('', '_blank', 'width=820,height=900');
    if (!w) return false;
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(doc.title)} ${esc(doc.number)}</title><style>
      body{font-family:system-ui,Segoe UI,Arial,sans-serif;margin:32px;color:#0f172a} h1{margin:0;font-size:22px} .muted{color:#64748b;font-size:13px}
      table{width:100%;border-collapse:collapse;margin-top:24px} th{text-align:left;border-bottom:2px solid #0f172a;padding:6px 4px;font-size:12px;text-transform:uppercase}
      td{padding:7px 4px;border-bottom:1px solid #e2e8f0;font-size:14px} .r{text-align:right} tr.b td{font-weight:700;font-size:16px;border-top:2px solid #0f172a}
      .head{display:flex;justify-content:space-between;align-items:flex-start}.foot{margin-top:40px;text-align:center}
      @media print{button{display:none}}</style></head><body>
      <div class="head"><div><h1>${esc(doc.business)}</h1><p class="muted">${esc(doc.sub || '')}</p></div>
      <div style="text-align:right"><h1>${esc(doc.title)}</h1><p class="muted">No. ${esc(doc.number)}<br>${esc(doc.date)}</p></div></div>
      <p style="margin-top:24px"><b>${esc(doc.partyLabel || 'Billed to')}:</b> ${esc(doc.party || '')}<br><span class="muted">${esc(doc.partySub || '')}</span></p>
      <table><thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead><tbody>${rows}${totals}</tbody></table>
      <p class="foot muted">${esc(doc.footer || 'Thank you / Murakoze / Merci')}</p><p class="foot"><button onclick="window.print()">Print</button></p></body></html>`);
    w.document.close();
    setTimeout(() => w.print(), 350);
    return true;
}
