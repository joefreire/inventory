@extends('layouts.app')

@section('title', $item->name)
@section('heading', 'Peça')
@php
    // Volta para onde veio (lista filtrada, caixa, busca), menos telas de formulario.
    $anterior = url()->previous();
    $voltar = ($anterior !== url()->current() && str_starts_with($anterior, url('/')) && !preg_match('#/(edit|create)(\?|$)#', $anterior))
        ? $anterior : route('items.index');
@endphp
@section('back', $voltar)

@section('top-actions')
    <button type="button" class="icon-btn" data-bs-toggle="offcanvas" data-bs-target="#acoes" aria-label="Mais ações">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
@endsection

@php $specs = $item->specList(); @endphp

@section('content')
<div class="page">
    @php $fotos = $item->getMedia('photos'); @endphp
    @if($fotos->isNotEmpty())
        <div class="gallery" id="gallery">
            @foreach($fotos as $i => $media)
                <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener">
                    <img src="{{ $media->getUrl('preview') }}" class="hero-photo" alt="Foto {{ $i + 1 }} de {{ $item->name }}" @if($i > 0) loading="lazy" @endif>
                </a>
            @endforeach
        </div>
        @if($fotos->count() > 1)
            <div class="gallery-dots" id="galleryDots" aria-hidden="true">
                @foreach($fotos as $i => $media)<span class="{{ $i === 0 ? 'on' : '' }}"></span>@endforeach
            </div>
        @endif
    @else
        <a href="{{ route('items.edit', $item) }}" class="hero-empty compact">
            <i class="bi bi-camera"></i> Adicionar foto
        </a>
    @endif

    <div class="page-head mt-3">
        <h2 class="h1 mb-2">{{ $item->name }}</h2>
        <div class="d-flex flex-wrap gap-3 align-items-center">
            @if($item->category)
                <a href="{{ route('items.index', ['category' => $item->category]) }}" class="small-muted">
                    {{ \Illuminate\Support\Str::before(config('ai.categories.' . $item->category, $item->category), ' (') }}
                </a>
            @endif
            @foreach($item->tags as $tag)
                <a href="{{ route('items.index', ['tag_id' => $tag->id]) }}" class="tag-dot" style="--c: {{ $tag->color }}">{{ $tag->name }}</a>
            @endforeach
        </div>
    </div>

    {{-- Quantidade: o uso mais frequente na bancada --}}
    <div class="stepper" role="group" aria-label="Quantidade">
        <button type="button" data-delta="-1" aria-label="Tirar uma"><i class="bi bi-dash-lg"></i></button>
        <div class="value" aria-live="polite">
            <b id="qtyValue">{{ $item->quantity }}</b>
            <span id="qtyLabel">{{ $item->quantity === 1 ? 'unidade' : 'unidades' }}</span>
        </div>
        <button type="button" data-delta="1" aria-label="Somar uma"><i class="bi bi-plus-lg"></i></button>
    </div>

    {{-- Onde esta --}}
    <h3 class="group-title">Onde está</h3>
    <div class="rows">
        @if($item->location)
            <a href="{{ route('locations.show', $item->location) }}" class="row-item">
                <span class="grow">
                    <span><span class="tape tape-lg">{{ $item->location->name }}</span></span>
                    @if($item->location->parent_id)
                        <span class="sub">{{ $item->location->path }}</span>
                    @endif
                </span>
                <i class="bi bi-chevron-right end"></i>
            </a>
        @else
            <div class="row-item"><span class="grow"><span class="sub">Ainda sem caixa.</span></span></div>
        @endif
    </div>
    <div class="d-flex gap-2 mt-2">
        <button type="button" class="btn btn-quiet flex-fill" data-bs-toggle="offcanvas" data-bs-target="#trocarCaixa">
            <i class="bi bi-arrow-left-right"></i> Trocar caixa
        </button>
        <button type="button" class="btn btn-outline-primary flex-fill d-none" id="nfcMoveBtn">
            <i class="bi bi-broadcast"></i> Encostar na caixa
        </button>
    </div>

    @if($item->description)
        <h3 class="group-title">O que é</h3>
        <p class="mx-1" style="max-width: 62ch">{{ $item->description }}</p>
    @endif

    @if($specs)
        <h3 class="group-title">Especificações</h3>
        <div class="panel">
            <dl class="kv">
                @foreach($specs as $chave => $valor)
                    <dt>{{ $chave }}</dt><dd>{{ $valor }}</dd>
                @endforeach
            </dl>
        </div>
    @endif

    @if($item->notes)
        <h3 class="group-title">Notas</h3>
        <p class="mx-1" style="white-space: pré-line; max-width: 62ch">{{ $item->notes }}</p>
    @endif

    <p class="small-muted mx-1 mt-4">
        Cadastrada em {{ $item->created_at->format('d/m/Y') }}@if($item->updated_at->ne($item->created_at)), alterada em {{ $item->updated_at->format('d/m/Y H:i') }}@endif.
    </p>
</div>

{{-- Trocar de caixa --}}
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="trocarCaixa" aria-labelledby="trocarTitulo">
    <div class="grabber"></div>
    <div class="offcanvas-header pb-1">
        <h2 class="h3 mb-0" id="trocarTitulo">Guardar em</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body p-0">
        @forelse($locations as $loc)
            <button type="button" class="sheet-action" data-location="{{ $loc->id }}" {{ $item->location_id === $loc->id ? 'disabled' : '' }}>
                <i class="bi {{ $item->location_id === $loc->id ? 'bi-check2' : 'bi-box-seam' }}"></i>
                <span class="text-truncate">{{ $loc->path }}</span>
            </button>
        @empty
            <p class="px-3 small-muted">Nenhuma caixa cadastrada.</p>
        @endforelse
        <a href="{{ route('locations.create') }}" class="sheet-action"><i class="bi bi-plus-lg"></i> Nova caixa</a>
    </div>
</div>

{{-- Mais acoes --}}
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="acoes" aria-label="Ações da peça">
    <div class="grabber"></div>
    <div class="offcanvas-body p-0 py-2">
        <a href="{{ route('items.edit', $item) }}" class="sheet-action"><i class="bi bi-pencil"></i> Editar peça</a>
        <form action="{{ route('items.destroy', $item) }}" method="POST"
              onsubmit="return confirm('Excluir {{ addslashes($item->name) }}? Não dá para desfazer.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="sheet-action danger"><i class="bi bi-trash"></i> Excluir peça</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // bolinhas da galeria acompanham a foto visivel
    const gal = document.getElementById('gallery'), dots = document.getElementById('galleryDots');
    if (gal && dots) {
        gal.addEventListener('scroll', () => {
            const i = Math.round(gal.scrollLeft / gal.clientWidth);
            dots.querySelectorAll('span').forEach((d, j) => d.classList.toggle('on', i === j));
        }, { passive: true });
    }

    const qtyValue = document.getElementById('qtyValue');
    const qtyLabel = document.getElementById('qtyLabel');
    let pendente = Promise.resolve();

    document.querySelectorAll('.stepper [data-delta]').forEach(btn => {
        btn.addEventListener('click', () => {
            const delta = Number(btn.dataset.delta);
            const antes = Number(qtyValue.textContent);
            if (antes + delta < 0) return;
            // mostra na hora; o servidor confirma em seguida (fila para não embaralhar toques rapidos)
            qtyValue.textContent = antes + delta;
            if (navigator.vibrate) navigator.vibrate(8);
            pendente = pendente.then(() => window.api('{{ route('items.quantity', $item) }}', 'PATCH', { delta }))
                .then(r => {
                    qtyValue.textContent = r.quantity;
                    qtyLabel.textContent = r.quantity === 1 ? 'unidade' : 'unidades';
                })
                .catch(e => { qtyValue.textContent = antes; window.toast(e.message, 'error'); });
        });
    });

    document.querySelectorAll('#trocarCaixa [data-location]').forEach(btn => {
        btn.addEventListener('click', async () => {
            try {
                await window.api('{{ route('items.move', $item) }}', 'PATCH', { location_id: Number(btn.dataset.location) });
                window.location.reload();
            } catch (e) {
                window.toast(e.message, 'error');
            }
        });
    });

    if (window.nfc.supported) {
        const nfcBtn = document.getElementById('nfcMoveBtn');
        nfcBtn.classList.remove('d-none');
        nfcBtn.addEventListener('click', async () => {
            const tag = await window.nfc.readWithSheet('Guardar nesta caixa');
            if (!tag) return;
            try {
                const r = await window.api('{{ route('items.nfc-move', $item) }}', 'POST', { tag });
                window.toast(r.message);
                setTimeout(() => window.location.reload(), 700);
            } catch (e) {
                window.toast(e.message, 'error');
            }
        });
    }
})();
</script>
@endpush
