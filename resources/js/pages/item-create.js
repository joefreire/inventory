import { PhotoPicker } from '../components/photo-picker';
import { TagInput } from '../components/tag-input';
import { api, postForm } from '../lib/api';
import { byId, esc, pct } from '../lib/dom';
import { toast } from '../lib/toast';

/**
 * Create page: every photo added (or removed) runs the AI again with all photos.
 * A new analysis only replaces what the AI itself filled in: a field the owner
 * edited keeps the owner's value.
 */
export default function init() {
    const form = byId('itemForm');
    const area = byId('aiArea');
    const picker = new PhotoPicker(byId('photoPicker'));
    const tags = new TagInput(byId('tags'));
    const analyzeUrl = form.dataset.analyzeUrl;
    const maxAiPhotos = Number(form.dataset.maxAiPhotos);

    const filledByAi = {};
    let running = false;
    let again = false;
    let timer = null;

    const fill = (id, value) => {
        const el = byId(id);
        if (value == null || value === '') return;
        const current = el.value.trim();
        if (current === '' || (filledByAi[id] !== undefined && current === String(filledByAi[id]))) {
            el.value = value;
            filledByAi[id] = String(value);
        }
    };

    picker.onChange((files) => {
        clearTimeout(timer);
        if (files.length) timer = setTimeout(() => analyze(true), 500);
    });

    byId('suggestFromName').addEventListener('click', () => {
        if (!byId('name').value.trim()) {
            byId('name').focus();
            toast('Digite o nome da peça primeiro.', 'error');
            return;
        }
        analyze(false);
    });

    form.addEventListener('submit', (e) => {
        if (!byId('name').value.trim()) {
            e.preventDefault();
            byId('name').focus();
            toast('Falta o nome da peça.', 'error');
        }
    });

    async function analyze(withPhotos) {
        if (running) { again = withPhotos; return; }
        running = true;
        const photos = withPhotos ? picker.newFiles().slice(0, maxAiPhotos) : [];
        const data = new FormData();
        photos.forEach((f) => data.append('photos[]', f));
        // the name only goes as a hint when the owner typed it
        const name = byId('name').value.trim();
        if (name && name !== filledByAi.name) data.append('name', name);
        const description = byId('description').value.trim();
        if (description && description !== filledByAi.description) data.append('description', description);

        if (photos.length) picker.setBusy(true, photos.length > 1 ? `Analisando ${photos.length} fotos juntas...` : 'Identificando a peça...');
        if (!area.innerHTML) area.innerHTML = '<div class="suggest"><div class="skeleton w-60"></div><div class="skeleton w-85"></div><div class="skeleton w-40"></div></div>';

        try {
            const response = await postForm(analyzeUrl, data);
            apply(response.data);
        } catch (e) {
            if (area.querySelector('.skeleton')) area.innerHTML = '';
            toast(e.message || 'A IA não respondeu. Preencha à mão ou tente de novo.', 'error');
        } finally {
            picker.setBusy(false);
            running = false;
            if (again) { again = false; analyze(true); }
        }
    }

    function apply(d) {
        fill('name', d.name);
        fill('description', d.description);
        if (d.category) fill('category', d.category.value);
        if (d.quantity && !d.kit) fill('quantity', d.quantity);
        if (d.location?.suggested) fill('location_id', String(d.location.suggested.id));

        // tags: while the field is still what the AI wrote, replace; otherwise add
        const names = (d.tags || []).map((t) => t.name);
        if (tags.input.value.trim() === '' || tags.input.value === filledByAi.tags) {
            tags.set(names);
            filledByAi.tags = tags.input.value;
        } else {
            names.forEach((n) => tags.add(n));
        }

        byId('ai_description').value = d.description || '';
        byId('ai_metadata').value = JSON.stringify({ specs: d.specs || {}, visible_text: d.visible_text || '', models: d.models || {}, photos_used: d.photos_used || 0 });

        area.innerHTML = renderPurchase(d.purchase) + renderDuplicate(d.duplicate) + renderSuggestion(d);
        bindPurchase(d.purchase);
        bindDuplicate(d.duplicate);
    }

    // the form may already come from a purchase line (hidden field filled): nothing to suggest then
    function renderPurchase(p) {
        const field = form.querySelector('[name="purchase_item_id"]');
        if (!p || field?.value) return '';
        const date = p.ordered_at ? ` (${esc(p.ordered_at.split('-').reverse().join('/'))})` : '';
        return `<div class="dup mb-3" data-purchase-card>
            <strong>Parece o item da compra?</strong> ${pct(p.probability)}
            <div class="d-flex gap-2 align-items-center my-1">
                ${p.photo_url ? `<img src="${esc(p.photo_url)}" class="thumb" alt="Foto do anúncio" width="48" height="48">` : ''}
                <div>Parece o item <b>${esc(p.name)}</b> do pedido ${esc(p.order_number)}${date}</div>
            </div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-dark flex-fill" data-purchase="yes">É este</button>
                <button type="button" class="btn btn-sm btn-light flex-fill" data-purchase="no">Não é</button>
            </div></div>`;
    }

    function bindPurchase(p) {
        const card = area.querySelector('[data-purchase-card]');
        if (!p || !card) return;
        card.querySelector('[data-purchase="yes"]').addEventListener('click', () => {
            let field = form.querySelector('[name="purchase_item_id"]');
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = 'purchase_item_id';
                form.appendChild(field);
            }
            field.value = String(p.id);
            byId('quantity').value = p.suggested_quantity;
            card.innerHTML = `<strong>Ligada à compra ${esc(p.order_number)}.</strong> Ao salvar, a peça entra com ${esc(p.suggested_quantity)} un.`;
        });
        card.querySelector('[data-purchase="no"]').addEventListener('click', () => card.remove());
    }

    function renderDuplicate(dup) {
        if (!dup) return '';
        return `<div class="dup mb-3">
            <strong>Você já tem essa peça?</strong> ${pct(dup.probability)}
            <div class="my-1"><a href="${esc(dup.url)}">${esc(dup.name)}</a>: ${esc(dup.quantity)} un.${dup.location ? ' na ' + esc(dup.location) : ''}</div>
            <div class="d-flex gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-dark flex-fill" data-dup="add">É a mesma: somar</button>
                <button type="button" class="btn btn-sm btn-light flex-fill" data-dup="ignore">É outra peça</button>
            </div></div>`;
    }

    function bindDuplicate(dup) {
        if (!dup) return;
        area.querySelector('[data-dup="add"]').addEventListener('click', async () => {
            // from a purchase: join the line to the part (adds quantity x pack and
            // links it), so it never stays pending to be added again later
            const purchaseId = form.querySelector('[name="purchase_item_id"]')?.value;
            if (purchaseId) {
                joinPurchase(purchaseId, dup.id);
                return;
            }
            const units = Math.max(1, Number(byId('quantity').value) || 1);
            try {
                await api(dup.quantity_url, 'PATCH', { delta: units });
                window.location.href = dup.url;
            } catch (e) {
                toast(e.message, 'error');
            }
        });
        area.querySelector('[data-dup="ignore"]').addEventListener('click', (e) => e.target.closest('.dup').remove());
    }

    function joinPurchase(purchaseId, itemId) {
        const join = document.createElement('form');
        join.method = 'POST';
        join.action = form.dataset.joinUrlTemplate.replace('__ID__', encodeURIComponent(purchaseId));
        const fields = { _token: form.querySelector('[name="_token"]').value, item_id: itemId, somar: 1 };
        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value);
            join.appendChild(input);
        });
        document.body.appendChild(join);
        join.submit();
    }

    function renderSuggestion(d) {
        const lines = [];
        if (d.category) lines.push(`<div>${esc(d.category.label || d.category.value)} ${pct(d.category.probability)}</div>`);
        if (d.kit) {
            lines.push(`<div class="mt-1">É um kit sortido${d.quantity ? ' com ' + esc(d.quantity) + ' peças' : ''}: deixei quantidade 1 (um kit).</div>`);
        } else if (d.quantity) {
            lines.push(`<div class="mt-1">Quantidade da embalagem: <b>${esc(d.quantity)}</b></div>`);
        }
        const loc = d.location;
        if (loc?.suggested) {
            lines.push(`<div class="mt-1">Guardar em <span class="tape">${esc(loc.suggested.path.split('/').pop())}</span> ${pct(loc.suggested.probability)}</div>`);
        } else if (loc?.options?.length) {
            lines.push('<div class="mt-1 small-muted">Nenhuma caixa combina bem com essa peça.</div>');
        }
        const specs = Object.entries(d.specs || {});
        if (specs.length) {
            lines.push('<dl class="kv mt-2 mb-0">' + specs.slice(0, 8).map(([k, v]) => `<dt>${esc(k.replace(/_/g, ' '))}</dt><dd>${esc(v)}</dd>`).join('') + '</dl>');
        }
        const basis = d.photos_used > 1 ? `Analisei as ${d.photos_used} fotos juntas.` : (d.photos_used === 1 ? 'Uma foto: verso ou etiqueta podem ajudar.' : 'Sem foto, só pelo nome.');
        return `<div class="suggest"><h3 class="h3">${esc(d.name || 'Sugestão')}</h3>${lines.join('')}
            <div class="why mt-2">${basis} Preenchi os campos abaixo; confira antes de salvar.</div></div>`;
    }
}
