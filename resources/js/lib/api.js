const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

async function send(url, options) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', ...(options.headers ?? {}) },
    });
    let data = {};
    try { data = await res.json(); } catch { /* empty body */ }
    if (!res.ok || data.success === false) {
        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
        throw new Error(data.message && !firstError ? data.message : (firstError ?? `Não deu certo (HTTP ${res.status}). Tente de novo.`));
    }
    return data;
}

/** JSON request with CSRF. Throws Error(message) on failure. */
export const api = (url, method = 'GET', body = undefined) => send(url, {
    method,
    headers: { 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
});

/** multipart/form-data POST (photos). */
export const postForm = (url, formData) => send(url, { method: 'POST', body: formData });
