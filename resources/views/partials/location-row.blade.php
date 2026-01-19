<div class="list-group-item d-flex justify-content-between align-items-center"
     style="padding-left: {{ 1.5 + ($depth * 1.5) }}rem;">
    <div>
        @if($location->children->count() > 0)
            <i class="bi bi-folder2 me-2 text-warning"></i>
        @else
            <i class="bi bi-folder me-2 text-muted"></i>
        @endif
        <a href="{{ route('locations.show', $location) }}" class="text-decoration-none">
            {{ $location->name }}
        </a>
        @if($location->description)
            <small class="text-muted ms-2">- {{ Str::limit($location->description, 50) }}</small>
        @endif
    </div>
    <div>
        <span class="badge bg-secondary me-2">{{ $location->total_item_count }} itens</span>
        <div class="btn-group btn-group-sm">
            <a href="{{ route('locations.create', ['parent_id' => $location->id]) }}"
               class="btn btn-outline-success" title="Adicionar sub-local">
                <i class="bi bi-plus"></i>
            </a>
            <a href="{{ route('locations.edit', $location) }}" class="btn btn-outline-primary" title="Editar">
                <i class="bi bi-pencil"></i>
            </a>
            <form action="{{ route('locations.destroy', $location) }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Excluir {{ $location->name }}? Todos os sub-locais tambem serao excluidos.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger" title="Excluir">
                    <i class="bi bi-trash"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@foreach($location->children as $child)
    @include('partials.location-row', ['location' => $child, 'depth' => $depth + 1])
@endforeach
