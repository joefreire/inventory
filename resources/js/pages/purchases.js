import { api } from '../lib/api';
import { byId, esc } from '../lib/dom';
import { toast } from '../lib/toast';

/** Purchases list: "Juntar a uma peça que já tenho" (search a part, confirm adding the units). */
export default function init() {
    const partsUrl = byId('purchasesPage').dataset.partsUrl;
    const sheet = byId('juntar');
    const search = byId('joinSearch');
    const results = byId('joinResults');
    const form = byId('joinForm');
    const searchStep = byId('joinSearchStep');
    let units = 0;
    let timer = null;
    let seq = 0;

    const showSearch = () => {
        form.classList.add('d-none');
        searchStep.classList.remove('d-none');
    };

    async function load() {
        const mine = ++seq;
        try {
            const { data } = await api(`${partsUrl}?q=${encodeURIComponent(search.value.trim())}`);
            if (mine !== seq) return; // a newer search is on its way
            results.innerHTML = data.length
                ? data.map((p) => `<button type="button" class="sheet-action" data-id="${esc(p.id)}" data-name="${esc(p.name)}">
                    <i class="bi bi-box-seam"></i><span class="text-truncate">${esc(p.name)}</span><span class="small-muted ms-auto">${esc(p.quantity)} un</span></button>`).join('')
                : '<p class="px-3 small-muted">Nenhuma peça com esse nome.</p>';
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    document.querySelectorAll('[data-join]').forEach((button) => {
        button.addEventListener('click', () => {
            form.action = button.dataset.joinUrl;
            units = Number(button.dataset.units);
            search.value = '';
            showSearch();
            load();
        });
    });

    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(load, 250);
    });

    results.addEventListener('click', (e) => {
        const option = e.target.closest('[data-id]');
        if (!option) return;
        byId('joinItemId').value = option.dataset.id;
        byId('joinAsk').textContent = `Somar ${units} ${units === 1 ? 'unidade' : 'unidades'} a ${option.dataset.name}?`;
        searchStep.classList.add('d-none');
        form.classList.remove('d-none');
    });

    byId('joinBack').addEventListener('click', showSearch);
}
