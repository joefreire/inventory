@extends('layouts.app')

@section('title', $location->name)

@section('content')
<div class="container py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('locations.index') }}">Locais</a></li>
            @foreach($location->ancestors() as $ancestor)
                <li class="breadcrumb-item">
                    <a href="{{ route('locations.show', $ancestor) }}">{{ $ancestor->name }}</a>
                </li>
            @endforeach
            <li class="breadcrumb-item active">{{ $location->name }}</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <i class="bi bi-folder2 me-2 text-warning"></i>{{ $location->name }}
            </h2>
            @if($location->description)
                <p class="text-muted mb-0">{{ $location->description }}</p>
            @endif
        </div>
        <div class="btn-group">
            <a href="{{ route('items.create', ['location_id' => $location->id]) }}" class="btn btn-success">
                <i class="bi bi-plus-lg me-1"></i>Novo Item
            </a>
            <a href="{{ route('locations.create', ['parent_id' => $location->id]) }}" class="btn btn-outline-primary">
                <i class="bi bi-folder-plus me-1"></i>Sub-local
            </a>
            <a href="{{ route('locations.edit', $location) }}" class="btn btn-outline-secondary">
                <i class="bi bi-pencil"></i>
            </a>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>

    <!-- Sub-locais -->
    @if($location->children->count() > 0)
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-folder me-2"></i>Sub-locais</h6>
            </div>
            <div class="list-group list-group-flush">
                @foreach($location->children as $child)
                    <a href="{{ route('locations.show', $child) }}"
                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span>
                            <i class="bi bi-folder me-2"></i>{{ $child->name }}
                        </span>
                        <span class="badge bg-secondary">{{ $child->total_item_count }} itens</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Itens neste local -->
    <h5 class="mb-3">
        <i class="bi bi-box me-2"></i>Itens neste local
        <span class="badge bg-secondary">{{ $items->total() }}</span>
    </h5>

    @if($items->count() > 0)
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 g-3">
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
                            @if($item->quantity > 1)
                                <small class="text-muted">
                                    <i class="bi bi-stack"></i> {{ $item->quantity }}x
                                </small>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    @else
        <div class="text-center py-4 text-muted">
            <i class="bi bi-inbox fs-1"></i>
            <p class="mt-2">Nenhum item neste local</p>
        </div>
    @endif
</div>

<!-- Modal de exclusao -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmar Exclusao</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja excluir <strong>{{ $location->name }}</strong>?</p>
                @if($location->items->count() > 0 || $location->children->count() > 0)
                    <div class="alert alert-warning py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Este local contem {{ $location->items->count() }} itens e {{ $location->children->count() }} sub-locais.
                    </div>
                @endif
                <p class="text-muted small">Esta acao nao pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('locations.destroy', $location) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Excluir
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
