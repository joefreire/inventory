/** HTML-escapes any value before it goes into a template string. */
export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

export const byId = (id) => document.getElementById(id);

/** "92%" label for a 0..1 probability, or '' when absent. */
export const pct = (p) => (p == null ? '' : `<span class="pct">${Math.round(p * 100)}%</span>`);
