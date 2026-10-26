import { api } from '../lib/api';
import { byId } from '../lib/dom';
import { nfcSupported, writeUrl } from '../lib/nfc';
import { toast } from '../lib/toast';

/** Box page: copy/write the NFC link, store an existing part here. */
export default function init() {
    const page = byId('locationPage');
    const url = byId('nfcUrl').value;

    byId('nfcCopyBtn').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url);
            toast('Link copiado.');
        } catch {
            byId('nfcUrl').select();
            toast('Selecione e copie o link.', 'error');
        }
    });

    if (nfcSupported) {
        const button = byId('nfcWriteBtn');
        button.classList.remove('d-none');
        byId('nfcHelpNoWrite').classList.add('d-none');
        button.addEventListener('click', async () => {
            button.disabled = true;
            toast('Encoste a tag vazia na parte de trás do celular.');
            try {
                await writeUrl(url);
                toast('Tag gravada. Encoste de novo para testar.');
            } catch (e) {
                toast('Não gravou: ' + e.message, 'error');
            } finally {
                button.disabled = false;
            }
        });
    }

    const filter = byId('filtroGuardar');
    filter?.addEventListener('input', () => {
        const q = filter.value.trim().toLowerCase();
        document.querySelectorAll('#guardarItem [data-move]').forEach((b) => b.classList.toggle('d-none', q !== '' && !b.dataset.nome.includes(q)));
    });

    document.querySelectorAll('#guardarItem [data-move]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await api(button.dataset.move, 'PATCH', { location_id: Number(page.dataset.locationId) });
                window.location.reload();
            } catch (e) {
                toast(e.message, 'error');
            }
        });
    });
}
