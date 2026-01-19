@extends('layouts.app')

@section('title', 'Itens')

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar com filtros -->
        <div class="col-lg-3 col-md-4 sidebar py-3">
            <!-- Locais -->
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small fw-bold mb-2">
                    <i class="bi bi-folder me-1"></i>Locais
                </h6>
                <div class="list-group list-group-flush location-tree">
                    <a href="{{ route('items.index', request()->except('location_id')) }}"
                       class="list-group-item list-group-item-action {{ !request('location_id') ? 'active' : '' }}">
                        <i class="bi bi-house me-2"></i>Todos os itens
                        <span class="badge bg-secondary float-end">{{ \App\Models\Item::count() }}</span>
                    </a>
                    @include('partials.location-tree', ['locations' => $locations, 'depth' => 0])
                </div>
            </div>

            <!-- Tags -->
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small fw-bold mb-2">
                    <i class="bi bi-tags me-1"></i>Tags
                </h6>
                <div class="d-flex flex-wrap gap-1">
                    @foreach($tags as $tag)
                        <a href="{{ route('items.index', array_merge(request()->except('tag_id'), ['tag_id' => $tag->id])) }}"
                           class="badge text-decoration-none {{ request('tag_id') == $tag->id ? 'border border-dark' : '' }}"
                           style="background-color: {{ $tag->color }}">
                            {{ $tag->name }} ({{ $tag->items_count }})
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Categorias -->
            @if($categories->count() > 0)
            <div class="mb-4">
                <h6 class="text-muted text-uppercase small fw-bold mb-2">
                    <i class="bi bi-collection me-1"></i>Categorias
                </h6>
                <div class="list-group list-group-flush">
                    @foreach($categories as $category)
                        <a href="{{ route('items.index', array_merge(request()->except('category'), ['category' => $category])) }}"
                           class="list-group-item list-group-item-action py-2 {{ request('category') == $category ? 'active' : '' }}">
                            {{ ucfirst($category) }}
                        </a>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Limpar filtros -->
            @if(request()->hasAny(['search', 'location_id', 'tag_id', 'category']))
                <a href="{{ route('items.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-circle me-1"></i>Limpar filtros
                </a>
            @endif
        </div>

        <!-- Conteudo principal -->
        <div class="col-lg-9 col-md-8 py-3">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-0">
                        @if(request('search'))
                            Resultados para "{{ request('search') }}"
                        @elseif(request('location_id'))
                            {{ \App\Models\Location::find(request('location_id'))?->name ?? 'Local' }}
                        @elseif(request('tag_id'))
                            Tag: {{ \App\Models\Tag::find(request('tag_id'))?->name ?? 'Tag' }}
                        @elseif(request('category'))
                            Categoria: {{ ucfirst(request('category')) }}
                        @else
                            Todos os Itens
                        @endif
                    </h4>
                    <small class="text-muted">{{ $items->total() }} {{ $items->total() == 1 ? 'item' : 'itens' }}</small>
                </div>
                <a href="{{ route('items.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Novo Item
                </a>
            </div>

            <!-- Grid de itens -->
            @if($items->count() > 0)
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-3 row-cols-lg-4 row-cols-xl-5 g-3">
                    @foreach($items as $item)
                        <div class="col">
                            <div class="card item-card h-100">
                                <a href="{{ route('items.show', $item) }}" class="text-decoration-none">
                                    @if($item->main_photo_url)
                                        <img src="{{ $item->main_photo_url }}" class="card-img-top item-photo" alt="{{ $item->name }}">
                                    @else
                                        <div class="item-photo-placeholder">
                                            <i class="bi bi-image fs-1"></i>
                                        </div>
                                    @endif
                                </a>
                                <div class="card-body p-2">
                                    <h6 class="card-title mb-1 text-truncate">
                                        <a href="{{ route('items.show', $item) }}" class="text-decoration-none text-dark">
                                            {{ $item->name }}
                                        </a>
                                    </h6>
                                    @if($item->location)
                                        <small class="text-muted d-block text-truncate">
                                            <i class="bi bi-geo-alt"></i> {{ $item->location->name }}
                                        </small>
                                    @endif
                                    @if($item->quantity > 1)
                                        <small class="text-muted">
                                            <i class="bi bi-stack"></i> {{ $item->quantity }}x
                                        </small>
                                    @endif
                                </div>
                                @if($item->tags->count() > 0)
                                    <div class="card-footer bg-white border-0 p-2 pt-0">
                                        @foreach($item->tags->take(3) as $tag)
                                            <span class="badge" style="background-color: {{ $tag->color }}; font-size: 0.65rem;">
                                                {{ $tag->name }}
                                            </span>
                                        @endforeach
                                        @if($item->tags->count() > 3)
                                            <span class="badge bg-secondary" style="font-size: 0.65rem;">+{{ $item->tags->count() - 3 }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Paginacao -->
                <div class="mt-4">
                    {{ $items->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-inbox fs-1 text-muted"></i>
                    <p class="text-muted mt-2">Nenhum item encontrado</p>
                    <a href="{{ route('items.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Adicionar primeiro item
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
