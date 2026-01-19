@extends('layouts.app')

@section('title', 'Tag: ' . $tag->name)

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">
                <span class="badge fs-4" style="background-color: {{ $tag->color }}">{{ $tag->name }}</span>
            </h2>
            <p class="text-muted mb-0">{{ $items->total() }} {{ $items->total() == 1 ? 'item' : 'itens' }} com esta tag</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('tags.edit', $tag) }}" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i>Editar
            </a>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>

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
                            @if($item->location)
                                <small class="text-muted d-block text-truncate">
                                    <i class="bi bi-geo-alt"></i> {{ $item->location->name }}
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
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-1"></i>
            <p class="mt-2">Nenhum item com esta tag</p>
        </div>
    @endif

    <div class="mt-4">
        <a href="{{ route('tags.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar para tags
        </a>
    </div>
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
                <p>Tem certeza que deseja excluir a tag <span class="badge" style="background-color: {{ $tag->color }}">{{ $tag->name }}</span>?</p>
                @if($items->total() > 0)
                    <div class="alert alert-warning py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Esta tag esta associada a {{ $items->total() }} {{ $items->total() == 1 ? 'item' : 'itens' }}.
                    </div>
                @endif
                <p class="text-muted small">Esta acao nao pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="d-inline">
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
