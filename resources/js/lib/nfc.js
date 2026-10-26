import { toast } from './toast';

/**
 * Web NFC exists only in Chrome on Android, over HTTPS. On iPhone the system
 * reads the tag (opens the link) and NFC Tools writes it.
 * The code format is NOT parsed here: the server reads whatever was scanned.
 */
export const nfcSupported = 'NDEFReader' in window;

let abortRead = null;

export async function writeUrl(url) {
    const reader = new window.NDEFReader();
    await reader.write({ records: [{ recordType: 'url', data: url }] });
}

function read() {
    return new Promise((resolve, reject) => {
        const reader = new window.NDEFReader();
        const ctrl = new AbortController();
        abortRead = () => { ctrl.abort(); reject(new Error('cancelado')); };
        reader.onreadingerror = () => { ctrl.abort(); reject(new Error('Não consegui ler a tag. Encoste de novo, parado.')); };
        reader.onreading = (event) => {
            ctrl.abort();
            const decoder = new TextDecoder();
            for (const record of event.message.records) {
                if (record.recordType === 'url' || record.recordType === 'text') return resolve(decoder.decode(record.data));
            }
            reject(new Error('Essa tag está vazia. Grave o link da caixa nela primeiro.'));
        };
        reader.scan({ signal: ctrl.signal }).catch(reject);
    });
}

/** "Encoste na tag" sheet while reading; resolves the scanned text or null. */
export async function readWithSheet(title = 'Encoste na tag') {
    const overlay = document.createElement('div');
    overlay.className = 'nfc-overlay';
    overlay.innerHTML = '<div class="sheet"><div class="nfc-ring"><i class="bi bi-broadcast"></i></div>'
        + '<h2 class="h3 mb-1"></h2><p class="small-muted mb-3">Encoste a parte de trás do celular na etiqueta da caixa.</p>'
        + '<button type="button" class="btn btn-quiet w-100">Cancelar</button></div>';
    overlay.querySelector('h2').textContent = title;
    overlay.querySelector('button').addEventListener('click', () => abortRead?.());
    document.body.append(overlay);
    try {
        return await read();
    } catch (e) {
        if (e.message !== 'cancelado') toast(e.message, 'error');
        return null;
    } finally {
        overlay.remove();
    }
}

/** Bottom bar "Ler tag" (Android): read and let the server open the box. */
export function initNfcTab() {
    const tab = document.getElementById('tabNfc');
    const resolveUrl = document.body.dataset.nfcResolveUrl;
    if (!nfcSupported || !tab || !resolveUrl) return;
    document.getElementById('tabTags')?.classList.add('d-none');
    tab.classList.remove('d-none');
    tab.addEventListener('click', async () => {
        const text = await readWithSheet('Abrir caixa');
        if (text) window.location.href = `${resolveUrl}?tag=${encodeURIComponent(text)}`;
    });
}
