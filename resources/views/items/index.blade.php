@extends('layouts.app')

@section('title', 'Peças')

@php
    $activeLocation = is_int($filter->location) ? $locationsFlat->firstWhere('id', $filter->location) : null;
    $activeTag = $filter->tagId ? $tags->firstWhere('id', $filter->tagId) : null;
    $hasFilter = request()->hasAny(['location_id', 'tag_id', 'category']);
@endphp

@section('top-actions')
    <button type="button" class="icon-btn" data-bs-toggle="offcanvas" data-bs-target="#filtros" aria-label="Filtrar">
        <i class="bi {{ $hasFilter ? 'bi-funnel-fill' : 'bi-funnel' }}"></i>
    </button>
@endsection

@section('content')
<div class="page">
    <form class="search-bar" action="{{ route('items.index') }}" method="GET" role="search">
        @foreach(request()->only('location_id', 'tag_id', 'category') as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <div class="search-field">
            <i class="bi bi-search"></i>
            <input type="search" name="search" class="form-control" value="{{ request('search') }}"
                   placeholder="Buscar peça, valor, modelo..." enterkeyhint="search" autocomplete="off" aria-label="Buscar">
        </div>
    </form>

    @if($locationsFlat->isNotEmpty())
        <div class="chips" aria-label="Filtrar por caixa">
            <a href="{{ route('items.index', request()->except('location_id', 'page')) }}" class="chip {{ !request('location_id') ? 'active' : '' }}">Todas</a>
            @if($withoutLocationCount > 0)
                <a href="{{ route('items.index', array_merge(request()->except('location_id', 'page'), ['location_id' => \App\Queries\ItemFilter::NO_LOCATION])) }}"
                   class="chip {{ $filter->location === \App\Queries\ItemFilter::NO_LOCATION ? 'active' : '' }}">Sem caixa <span class="small-muted">{{ $withoutLocationCount }}</span></a>
            @endif
            @foreach($locationsFlat as $loc)
                <a href="{{ route('items.index', array_merge(request()->except('location_id', 'page'), ['location_id' => $loc->id])) }}"
                   class="chip {{ (int) request('location_id') === $loc->id ? 'active' : '' }}">{{ $loc->name }}</a>
            @endforeach
        </div>
    @endif

    @if($activeTag || request('category'))
        <div class="d-flex flex-wrap gap-2 mb-2">
            @if($activeTag)
                <a href="{{ route('items.index', request()->except('tag_id', 'page')) }}" class="chip active">
                    {{ $activeTag->name }} <i class="bi bi-x-lg"></i>
                </a>
            @endif
            @if(request('category'))
                <a href="{{ route('items.index', request()->except('category', 'page')) }}" class="chip active">
                    {{ \Illuminate\Support\Str::before(config('ai.categories.' . request('category'), request('category')), ' (') }} <i class="bi bi-x-lg"></i>
                </a>
            @endif
        </div>
    @endif

    <p class="small-muted mx-1 mb-2">
        {{ $items->total() }} {{ $items->total() === 1 ? 'peça' : 'peças' }}
        @if(request('search')) para "{{ request('search') }}" @endif
        @if($activeLocation) em {{ $activeLocation->name }} @endif
    </p>

    @if($items->count() > 0)
        <div class="rows">
            @foreach($items as $item)
                <a href="{{ route('items.show', $item) }}" class="row-item">
                    @if($item->main_photo_url)
                        <img src="{{ $item->main_photo_url }}" class="thumb" alt="" loading="lazy">
                    @else
                        <span class="thumb"><i class="bi bi-cpu"></i></span>
                    @endif
                    <span class="grow">
                        <span class="name">{{ $item->name }}</span>
                        @if($item->location)
                            <span><span class="tape">{{ $item->location->name }}</span></span>
                        @else
                            <span><span class="tape tape-none">Sem caixa</span></span>
                        @endif
                    </span>
                    <span class="qty {{ $item->quantity === 0 ? 'zero' : '' }}">{{ $item->quantity }}<small>un</small></span>
                </a>
            @endforeach
        </div>

        <div class="mt-3">{{ $items->onEachSide(1)->links() }}</div>
    @else
        <div class="empty">
            @if(request()->hasAny(['search', 'location_id', 'tag_id', 'category']))
                <h2 class="h3">Nada com esse filtro</h2>
                <p>Tente outra palavra ou pergunte em linguagem normal, tipo "algo pra medir temperatura".</p>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('items.ask', ['q' => request('search')]) }}" class="btn btn-primary"><i class="bi bi-chat-square-text"></i> Perguntar</a>
                    <a href="{{ route('items.index') }}" class="btn btn-quiet">Limpar filtros</a>
                </div>
            @else
                <h2 class="h3">Nenhuma peça ainda</h2>
                <p>Tire uma foto da primeira peça: a IA preenche nome, categoria e sugere a caixa.</p>
                <a href="{{ route('items.create') }}" class="btn btn-primary btn-lg"><i class="bi bi-camera"></i> Cadastrar peça</a>
            @endif
        </div>
    @endif
</div>

{{-- Filtros: folha inferior --}}
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="filtros" aria-labelledby="filtrosTitulo">
    <div class="grabber"></div>
    <div class="offcanvas-header pb-1">
        <h2 class="h3 mb-0" id="filtrosTitulo">Filtrar</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body pt-1">
        @if($categories->isNotEmpty())
            <h3 class="group-title mt-1">Categoria</h3>
            <div class="d-flex flex-wrap gap-2">
                @foreach($categories as $category)
                    <a href="{{ route('items.index', array_merge(request()->except('category', 'page'), ['category' => $category])) }}"
                       class="chip {{ request('category') === $category ? 'active' : '' }}">{{ \Illuminate\Support\Str::before(config('ai.categories.' . $category, $category), ' (') }}</a>
                @endforeach
            </div>
        @endif

        @if($tags->isNotEmpty())
            <h3 class="group-title">Tag</h3>
            <div class="d-flex flex-wrap gap-2">
                @foreach($tags as $tag)
                    <a href="{{ route('items.index', array_merge(request()->except('tag_id', 'page'), ['tag_id' => $tag->id])) }}"
                       class="chip {{ (int) request('tag_id') === $tag->id ? 'active' : '' }}">
                        <span class="dot" style="background: {{ $tag->color }}"></span>{{ $tag->name }}
                        <span class="small-muted">{{ $tag->items_count }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        @if($hasFilter || request('search'))
            <a href="{{ route('items.index') }}" class="btn btn-quiet w-100 mt-4">Limpar filtros</a>
        @endif
    </div>
</div>
@endsection
