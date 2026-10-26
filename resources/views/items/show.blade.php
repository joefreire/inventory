@extends('layouts.app')

@section('title', $item->name)
@section('heading', 'Peça')
@php
    // Volta para onde veio (lista filtrada, caixa, busca), menos telas de formulario.
    $previousUrl = url()->previous();
    $backUrl = ($previousUrl !== url()->current() && str_starts_with($previousUrl, url('/')) && !preg_match('#/(edit|create)(\?|$)#', $previousUrl))
        ? $previousUrl : route('items.index');
@endphp
@section('back', $backUrl)

@section('top-actions')
    <button type="button" class="icon-btn" data-bs-toggle="offcanvas" data-bs-target="#acoes" aria-label="Mais ações">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
@endsection

@php $specs = $item->specList(); @endphp
@section('page', 'item-show')

@section('content')
<div class="page" id="itemPage"
     data-quantity-url="{{ route('items.quantity', $item) }}"
     data-move-url="{{ route('items.move', $item) }}"
     data-nfc-move-url="{{ route('items.nfc-move', $item) }}">
    @php $photos = $item->getMedia('photos'); @endphp
    @if($photos->isNotEmpty())
        <div class="gallery" id="gallery">
            @foreach($photos as $i => $media)
                <a href="{{ $media->getUrl() }}" target="_blank" rel="noopener">
                    <img src="{{ $media->getUrl('preview') }}" class="hero-photo" alt="Foto {{ $i + 1 }} de {{ $item->name }}" @if($i > 0) loading="lazy" @endif>
                </a>
            @endforeach
        </div>
        @if($photos->count() > 1)
            <div class="gallery-dots" id="galleryDots" aria-hidden="true">
                @foreach($photos as $i => $media)<span class="{{ $i === 0 ? 'on' : '' }}"></span>@endforeach
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
    <p class="small-muted mx-1 mt-2 mb-0 d-none" id="nfcMoveHint">
        Sem NFC no navegador (iPhone): encoste o celular na tag da caixa e use "Guardar item aqui".
    </p>

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
        <p class="mx-1" style="white-space: pre-line; max-width: 62ch">{{ $item->notes }}</p>
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
              data-confirm="Excluir {{ $item->name }}? Não dá para desfazer.">
            @csrf
            @method('DELETE')
            <button type="submit" class="sheet-action danger"><i class="bi bi-trash"></i> Excluir peça</button>
        </form>
    </div>
</div>
@endsection

