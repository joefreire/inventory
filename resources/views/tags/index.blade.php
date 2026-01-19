@extends('layouts.app')

@section('title', 'Tags')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-tags me-2"></i>Tags</h2>
        <a href="{{ route('tags.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nova Tag
        </a>
    </div>

    @if($tags->count() > 0)
        <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
            @foreach($tags as $tag)
                <div class="col">
                    <div class="card h-100">
                        <div class="card-body text-center">
                            <a href="{{ route('tags.show', $tag) }}" class="text-decoration-none">
                                <span class="badge fs-6 mb-2" style="background-color: {{ $tag->color }}">
                                    {{ $tag->name }}
                                </span>
                            </a>
                            <p class="text-muted small mb-0">{{ $tag->items_count }} itens</p>
                        </div>
                        <div class="card-footer bg-white border-0 text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('tags.edit', $tag) }}" class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="d-inline"
                                      onsubmit="return confirm('Excluir a tag {{ $tag->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Excluir">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $tags->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-tag fs-1 text-muted"></i>
            <p class="text-muted mt-2">Nenhuma tag cadastrada</p>
            <a href="{{ route('tags.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Criar primeira tag
            </a>
        </div>
    @endif
</div>
@endsection
