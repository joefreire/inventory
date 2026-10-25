@extends('layouts.app')

@section('title', 'Cadastrar peça')
@section('back', route('items.index'))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data" id="itemForm" novalidate>
        @csrf

        {{-- Camera primeiro: cada foto nova ja dispara a IA com todas as fotos --}}
        @include('items._photos')

        <div id="aiArea" class="mb-3"></div>

        <input type="hidden" name="ai_description" id="ai_description" value="{{ old('ai_description') }}">
        <input type="hidden" name="ai_metadata" id="ai_metadata" value="{{ old('ai_metadata') }}">

        @include('items._fields')

        <button type="button" class="btn btn-quiet w-100 mb-3" id="suggestFromName">
            <i class="bi bi-stars"></i> Sugerir pelo nome, sem foto
        </button>
    </form>
</div>

<div class="action-dock">
    <div class="inner">
        <button type="submit" form="itemForm" name="proxima" value="1" class="btn btn-quiet" title="Salva e abre outro cadastro na mesma caixa">
            <i class="bi bi-plus-lg"></i> Salvar e próxima
        </button>
        <button type="submit" form="itemForm" class="btn btn-primary"><i class="bi bi-check2"></i> Salvar peça</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
// DOMContentLoaded: o script do seletor de fotos (window.fotosPeca) entra depois deste
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('itemForm');
    const area = document.getElementById('aiArea');
    const $ = id => document.getElementById(id);
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const pct = p => p == null ? '' : `<span class="pct">${Math.round(p * 100)}%</span>`;
    const MAX_IA = {{ \App\Services\Ai\ItemAdvisor::MAX_FOTOS_IA }};
    let rodando = false, deNovo = false, timer = null;

    // O que a IA preencheu. Numa nova analise ela so troca o que ainda e dela:
    // se o dono editou o campo, o valor dele fica.
    const daIa = {};
    function preencher(id, valor) {
        const el = $(id);
        if (valor == null || valor === '') return;
        const atual = el.value.trim();
        if (atual === '' || (daIa[id] !== undefined && atual === String(daIa[id]))) {
            el.value = valor;
            daIa[id] = String(valor);
        }
    }

    // cada foto nova (ou removida) analisa de novo com todas, depois de uma pausa curta
    window.fotosPeca.onChange(fotos => {
        clearTimeout(timer);
        if (fotos.length) timer = setTimeout(() => analisar(true), 500);
    });

    $('suggestFromName').addEventListener('click', () => {
        if (!$('name').value.trim()) {
            $('name').focus();
            return window.toast('Digite o nome da peça primeiro.', 'error');
        }
        analisar(false);
    });

    async function analisar(comFoto) {
        if (rodando) { deNovo = comFoto; return; }
        rodando = true;
        const fotos = comFoto ? window.fotosPeca.files().slice(0, MAX_IA) : [];
        const fd = new FormData();
        fotos.forEach(f => fd.append('photos[]', f));
        // o nome so vai como dica se foi o dono que digitou
        const nome = $('name').value.trim();
        if (nome && nome !== daIa.name) fd.append('name', nome);
        const desc = $('description').value.trim();
        if (desc && desc !== daIa.description) fd.append('description', desc);

        if (fotos.length) window.fotosPeca.setBusy(true, fotos.length > 1 ? `Analisando ${fotos.length} fotos juntas...` : 'Identificando a peça...');
        if (!area.innerHTML) area.innerHTML = '<div class="suggest"><div class="skeleton" style="width:60%"></div><div class="skeleton" style="width:85%"></div><div class="skeleton" style="width:40%"></div></div>';

        try {
            const res = await fetch('{{ route('items.analyze') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const r = await res.json().catch(() => ({}));
            if (!res.ok || !r.success) throw new Error(r.message || 'A IA não respondeu. Preencha à mão ou tente de novo.');
            aplicar(r.data);
        } catch (e) {
            if (area.querySelector('.skeleton')) area.innerHTML = '';
            window.toast(e.message, 'error');
        } finally {
            window.fotosPeca.setBusy(false);
            rodando = false;
            if (deNovo) { deNovo = false; analisar(true); }
        }
    }

    function aplicar(d) {
        preencher('name', d.name);
        preencher('description', d.description);
        if (d.category) preencher('category', d.category.value);
        if (d.quantity && !d.kit) preencher('quantity', d.quantity);
        const loc = d.location;
        if (loc && loc.suggested) preencher('location_id', String(loc.suggested.id));

        // tags: se o campo ainda e o que a IA pos, troca; senao soma
        const nomes = (d.tags || []).map(t => t.name);
        if ($('tags').value.trim() === '' || $('tags').value === daIa.tags) {
            $('tags').value = nomes.join(', ');
            daIa.tags = $('tags').value;
        } else {
            nomes.forEach(n => window.addTag(n));
        }

        $('ai_description').value = d.description || '';
        $('ai_metadata').value = JSON.stringify({ specs: d.specs || {}, visible_text: d.visible_text || '', models: d.models || {}, photos_used: d.photos_used || 0 });

        let html = '';
        if (d.duplicate) {
            const dup = d.duplicate;
            html += `<div class="dup mb-3">
                <strong>Você já tem essa peça?</strong> ${pct(dup.probability)}
                <div class="my-1"><a href="${esc(dup.url)}">${esc(dup.name)}</a>: ${esc(dup.quantity)} un.${dup.location ? ' na ' + esc(dup.location) : ''}</div>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-dark flex-fill" id="somarDup">É a mesma: somar</button>
                    <button type="button" class="btn btn-sm btn-light flex-fill" id="ignorarDup">É outra peça</button>
                </div></div>`;
        }

        const linhas = [];
        if (d.category) linhas.push(`<div>${esc(d.category.label || d.category.value)} ${pct(d.category.probability)}</div>`);
        if (d.kit) {
            linhas.push(`<div class="mt-1">É um kit sortido${d.quantity ? ' com ' + esc(d.quantity) + ' peças' : ''}: deixei quantidade 1 (um kit).</div>`);
        } else if (d.quantity) {
            linhas.push(`<div class="mt-1">Quantidade da embalagem: <b>${esc(d.quantity)}</b></div>`);
        }
        if (loc && loc.suggested) {
            linhas.push(`<div class="mt-1">Guardar em <span class="tape">${esc(loc.suggested.path.split('/').pop())}</span> ${pct(loc.suggested.probability)}</div>`);
        } else if (loc && loc.options && loc.options.length) {
            linhas.push(`<div class="mt-1 small-muted">Nenhuma caixa combina bem com essa peça.</div>`);
        }
        const specs = Object.entries(d.specs || {});
        if (specs.length) {
            linhas.push('<dl class="kv mt-2 mb-0">' + specs.slice(0, 8).map(([k, v]) => `<dt>${esc(k.replace(/_/g, ' '))}</dt><dd>${esc(v)}</dd>`).join('') + '</dl>');
        }
        const base = d.photos_used > 1 ? `Analisei as ${d.photos_used} fotos juntas.` : (d.photos_used === 1 ? 'Uma foto: verso ou etiqueta podem ajudar.' : 'Sem foto, só pelo nome.');
        html += `<div class="suggest"><h3 class="h3">${esc(d.name || 'Sugestão')}</h3>${linhas.join('')}
            <div class="why mt-2">${base} Preenchi os campos abaixo; confira antes de salvar.</div></div>`;
        area.innerHTML = html;

        if (d.duplicate) {
            $('somarDup').addEventListener('click', async () => {
                const n = Math.max(1, Number($('quantity').value) || 1);
                try {
                    await window.api(`/items/${d.duplicate.id}/quantity`, 'PATCH', { delta: n });
                    window.location.href = d.duplicate.url;
                } catch (e) {
                    window.toast(e.message, 'error');
                }
            });
            $('ignorarDup').addEventListener('click', e => e.target.closest('.dup').remove());
        }
    }

    form.addEventListener('submit', e => {
        if (!$('name').value.trim()) {
            e.preventDefault();
            $('name').focus();
            window.toast('Falta o nome da peça.', 'error');
        }
    });
});
</script>
@endpush
