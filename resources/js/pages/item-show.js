import { api } from '../lib/api';
import { byId } from '../lib/dom';
import { nfcSupported, readWithSheet } from '../lib/nfc';
import { toast } from '../lib/toast';

/** Part page: gallery dots, +/- quantity, change box, store by NFC. */
export default function init() {
    const page = byId('itemPage');
    const { quantityUrl, moveUrl, nfcMoveUrl } = page.dataset;

    initGallery();
    initStepper(quantityUrl);
    initMoveSheet(moveUrl);
    initNfcMove(nfcMoveUrl);
}

function initGallery() {
    const gallery = byId('gallery');
    const dots = byId('galleryDots');
    if (!gallery || !dots) return;
    gallery.addEventListener('scroll', () => {
        const index = Math.round(gallery.scrollLeft / gallery.clientWidth);
        dots.querySelectorAll('span').forEach((d, i) => d.classList.toggle('on', i === index));
    }, { passive: true });
}

function initStepper(url) {
    const value = byId('qtyValue');
    const label = byId('qtyLabel');
    let queue = Promise.resolve();

    document.querySelectorAll('.stepper [data-delta]').forEach((button) => {
        button.addEventListener('click', () => {
            const delta = Number(button.dataset.delta);
            const before = Number(value.textContent);
            if (before + delta < 0) return;
            // shows at once; the server confirms (queued so quick taps keep their order)
            value.textContent = before + delta;
            navigator.vibrate?.(8);
            queue = queue
                .then(() => api(url, 'PATCH', { delta }))
                .then((r) => {
                    value.textContent = r.quantity;
                    label.textContent = r.quantity === 1 ? 'unidade' : 'unidades';
                })
                .catch((e) => { value.textContent = before; toast(e.message, 'error'); });
        });
    });
}

function initMoveSheet(url) {
    document.querySelectorAll('#trocarCaixa [data-location]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await api(url, 'PATCH', { location_id: Number(button.dataset.location) });
                window.location.reload();
            } catch (e) {
                toast(e.message, 'error');
            }
        });
    });
}

function initNfcMove(url) {
    const button = byId('nfcMoveBtn');
    if (!nfcSupported) {
        byId('nfcMoveHint')?.classList.remove('d-none');
        return;
    }
    button.classList.remove('d-none');
    button.addEventListener('click', async () => {
        const tag = await readWithSheet('Guardar nesta caixa');
        if (!tag) return;
        try {
            const r = await api(url, 'POST', { tag });
            toast(r.message);
            setTimeout(() => window.location.reload(), 700);
        } catch (e) {
            toast(e.message, 'error');
        }
    });
}
