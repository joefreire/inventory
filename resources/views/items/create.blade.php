@extends('layouts.app')

@section('title', 'Cadastrar peça')
@section('back', route('items.index'))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data" id="itemForm" novalidate>
        @csrf

        {{-- Camera primeiro: a foto ja dispara a IA --}}
        <label class="camera-zone mb-3" id="cameraZone" for="photoInput">
            <input type="file" name="photo" id="photoInput" accept="image/*" capture="environment" class="d-none">
            <span class="cam"><i class="bi bi-camera"></i></span>
            <strong>Tirar foto da peça</strong>
            <span class="small-muted px-4 text-center">A IA preenche o cadastro e sugere a caixa</span>
        </label>

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
(function () {
    const form = document.getElementById('itemForm');
    const input = document.getElementById('photoInput');
    const zone = document.getElementById('cameraZone');
    const area = document.getElementById('aiArea');
    const $ = id => document.getElementById(id);
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const pct = p => p == null ? '' : `<span class="pct">${Math.round(p * 100)}%</span>`;
    let rodando = false;
    // a IA so mexe na quantidade se o dono ainda nao mexeu
    let qtdTocada = false;
    $('quantity').addEventListener('input', () => { qtdTocada = true; });

    input.addEventListener('change', async () => {
        if (!input.files.length) return;
        const pequena = await window.shrinkImage(input.files[0], 1600);
        const dt = new DataTransfer();
        dt.items.add(pequena);
        input.files = dt.files;

        zone.classList.add('has-photo');
        zone.querySelectorAll('img, .retake, .busy').forEach(e => e.remove());
        const img = document.createElement('img');
        img.src = URL.createObjectURL(pequena);
        img.alt = 'Foto da peça';
        zone.appendChild(img);
        const retake = document.createElement('span');
        retake.className = 'btn btn-quiet btn-sm retake';
        retake.innerHTML = '<i class="bi bi-arrow-repeat"></i> Outra foto';
        zone.appendChild(retake);

        analisar(true);
    });

    $('suggestFromName').addEventListener('click', () => {
        if (!$('name').value.trim()) {
            $('name').focus();
            return window.toast('Digite o nome da peça primeiro.', 'error');
        }
        analisar(false);
    });

    async function analisar(comFoto) {
        if (rodando) return;
        rodando = true;
        const fd = new FormData();
        if (comFoto && input.files.length) fd.append('photo', input.files[0]);
        if ($('name').value.trim()) fd.append('name', $('name').value.trim());
        if ($('description').value.trim()) fd.append('description', $('description').value.trim());

        let busy = null;
        if (comFoto) {
            busy = document.createElement('div');
            busy.className = 'busy';
            busy.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Identificando a peça...';
            zone.appendChild(busy);
        }
        area.innerHTML = '<div class="suggest"><div class="skeleton" style="width:60%"></div><div class="skeleton" style="width:85%"></div><div class="skeleton" style="width:40%"></div></div>';

        try {
            const res = await fetch('{{ route('items.analyze') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': window.csrf, 'Accept': 'application/json' },
                body: fd,
            });
            const r = await res.json().catch(() => ({}));
            if (!res.ok || !r.success) throw new Error(r.message || 'A IA não respondeu. Preencha a mão ou tente de novo.');
            aplicar(r.data);
        } catch (e) {
            area.innerHTML = '';
            window.toast(e.message, 'error');
        } finally {
            busy && busy.remove();
            rodando = false;
        }
    }

    function preencher(id, valor) {
        const el = $(id);
        if (valor && !el.value.trim()) el.value = valor;
    }

    function aplicar(d) {
        preencher('name', d.name);
        preencher('description', d.description);
        if (d.category && !$('category').value) $('category').value = d.category.value;
        if (d.quantity && !qtdTocada && !d.kit) $('quantity').value = d.quantity;
        (d.tags || []).forEach(t => window.addTag(t.name));
        $('ai_description').value = d.description || '';
        $('ai_metadata').value = JSON.stringify({ specs: d.specs || {}, visible_text: d.visible_text || '', models: d.models || {} });

        const loc = d.location;
        if (loc && loc.suggested && !$('location_id').value) $('location_id').value = String(loc.suggested.id);

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
        } else if (d.quantity && !qtdTocada) {
            linhas.push(`<div class="mt-1">Quantidade da embalagem: <b>${esc(d.quantity)}</b></div>`);
        }
        if (loc && loc.suggested) {
            linhas.push(`<div class="mt-1">Guardar em <span class="tape">${esc(loc.suggested.path.split('/').pop())}</span> ${pct(loc.suggested.probability)}</div>`);
        } else if (loc && loc.options && loc.options.length) {
            linhas.push(`<div class="mt-1 small-muted">Nenhuma caixa combina bem com essa peça.</div>`);
        }
        const specs = Object.entries(d.specs || {});
        if (specs.length) {
            linhas.push('<dl class="kv mt-2 mb-0">' + specs.slice(0, 6).map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`).join('') + '</dl>');
        }
        html += `<div class="suggest"><h3 class="h3">${esc(d.name || 'Sugestão')}</h3>${linhas.join('')}
            <div class="why mt-2">Preenchi os campos abaixo. Confira antes de salvar.</div></div>`;
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
})();
</script>
@endpush
