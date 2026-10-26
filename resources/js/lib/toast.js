const HIDE_AFTER = { ok: 3200, error: 6000 };

/** Short message above the bottom bar. kind: 'ok' | 'error'. */
export function toast(message, kind = 'ok') {
    const wrap = document.getElementById('toastWrap');
    if (!wrap) return;
    wrap.replaceChildren();
    const el = document.createElement('div');
    el.className = 'toast-msg show-in' + (kind === 'error' ? ' error' : '');
    const icon = document.createElement('i');
    icon.className = 'bi ' + (kind === 'error' ? 'bi-exclamation-circle' : 'bi-check2-circle');
    const text = document.createElement('span');
    text.textContent = message;
    el.append(icon, text);
    wrap.append(el);
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => el.remove(), HIDE_AFTER[kind] ?? HIDE_AFTER.ok);
}

/** Session flash messages arrive as data-flash-* on <body>. */
export function showFlash() {
    const { flashSuccess, flashError } = document.body.dataset;
    if (flashSuccess) toast(flashSuccess);
    if (flashError) toast(flashError, 'error');
}
