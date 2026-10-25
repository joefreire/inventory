@extends('layouts.app')

@section('title', $item->name)

@section('content')
<div class="container py-4">
    <div class="row">
        <!-- Foto -->
        <div class="col-lg-5 mb-4">
            <div class="card">
                @if($item->preview_photo_url)
                    <a href="{{ $item->original_photo_url }}" target="_blank">
                        <img src="{{ $item->preview_photo_url }}" class="card-img-top" alt="{{ $item->name }}">
                    </a>
                @else
                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 300px;">
                        <i class="bi bi-image text-muted" style="font-size: 5rem;"></i>
                    </div>
                @endif
            </div>

            @if($item->getMedia('photos')->count() > 1)
                <div class="row g-2 mt-2">
                    @foreach($item->getMedia('photos') as $media)
                        <div class="col-3">
                            <a href="{{ $media->getUrl() }}" target="_blank">
                                <img src="{{ $media->getUrl('thumb') }}" class="img-fluid rounded" alt="Foto">
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Detalhes -->
        <div class="col-lg-7">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h2 class="mb-1">{{ $item->name }}</h2>
                    @if($item->category)
                        <span class="badge bg-secondary">{{ ucfirst($item->category) }}</span>
                    @endif
                </div>
                <div class="btn-group">
                    <a href="{{ route('items.edit', $item) }}" class="btn btn-outline-primary">
                        <i class="bi bi-pencil me-1"></i>Editar
                    </a>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>

            <!-- Guardar por NFC (Android) -->
            <div class="card mb-3">
                <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                    <span><i class="bi bi-geo-alt me-1"></i>{{ $item->location?->path ?? 'Sem local' }}</span>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-auto d-none" id="nfcMoveBtn">
                        <i class="bi bi-broadcast me-1"></i>Guardar: encostar na caixa
                    </button>
                    <small class="text-muted w-100 d-none" id="nfcMoveHint">
                        No iPhone: encoste o celular na tag da caixa e use "Guardar item aqui".
                    </small>
                    <div class="w-100 small d-none" id="nfcMoveStatus"></div>
                </div>
            </div>

            <!-- Quantidade -->
            @if($item->quantity > 1)
                <div class="alert alert-info py-2">
                    <i class="bi bi-stack me-1"></i>Quantidade: <strong>{{ $item->quantity }}</strong> unidades
                </div>
            @endif

            <!-- Local -->
            @if($item->location)
                <div class="mb-3">
                    <h6 class="text-muted small text-uppercase">Local</h6>
                    <a href="{{ route('locations.show', $item->location) }}" class="text-decoration-none">
                        <i class="bi bi-geo-alt me-1"></i>{{ $item->location->path }}
                    </a>
                </div>
            @endif

            <!-- Descricao -->
            @if($item->description)
                <div class="mb-3">
                    <h6 class="text-muted small text-uppercase">Descricao</h6>
                    <p class="mb-0">{{ $item->description }}</p>
                </div>
            @endif

            <!-- Descricao da IA -->
            @if($item->ai_description && $item->ai_description != $item->description)
                <div class="mb-3">
                    <h6 class="text-muted small text-uppercase">
                        <i class="bi bi-robot me-1"></i>Identificado pela IA
                    </h6>
                    <p class="mb-0 text-muted fst-italic">{{ $item->ai_description }}</p>
                </div>
            @endif

            <!-- Specs da IA -->
            @if($item->ai_metadata && count($item->ai_metadata) > 0)
                <div class="mb-3">
                    <h6 class="text-muted small text-uppercase">Especificacoes</h6>
                    <table class="table table-sm table-borderless mb-0">
                        @foreach($item->ai_metadata as $key => $value)
                            @if($value)
                                <tr>
                                    <td class="text-muted" style="width: 40%;">{{ ucfirst($key) }}</td>
                                    <td>{{ $value }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>
                </div>
            @endif

            <!-- Tags -->
            @if($item->tags->count() > 0)
                <div class="mb-3">
                    <h6 class="text-muted small text-uppercase">Tags</h6>
                    <div>
                        @foreach($item->tags as $tag)
                            <a href="{{ route('items.index', ['tag_id' => $tag->id]) }}"
                               class="badge text-decoration-none me-1" style="background-color: {{ $tag->color }}">
                                {{ $tag->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Notas -->
            @if($item->notes)
                <div class="mb-3">
                    <h6 class="text-muted small text-uppercase">Notas</h6>
                    <p class="mb-0">{{ $item->notes }}</p>
                </div>
            @endif

            <!-- Metadata -->
            <div class="text-muted small mt-4">
                <i class="bi bi-clock me-1"></i>Criado em {{ $item->created_at->format('d/m/Y H:i') }}
                @if($item->updated_at != $item->created_at)
                    <br><i class="bi bi-pencil me-1"></i>Atualizado em {{ $item->updated_at->format('d/m/Y H:i') }}
                @endif
            </div>
        </div>
    </div>

    <!-- Voltar -->
    <div class="mt-4">
        <a href="{{ route('items.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Voltar para lista
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
                <p>Tem certeza que deseja excluir <strong>{{ $item->name }}</strong>?</p>
                <p class="text-muted small">Esta acao nao pode ser desfeita.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('items.destroy', $item) }}" method="POST" class="d-inline">
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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('nfcMoveBtn');
    const status = document.getElementById('nfcMoveStatus');
    if (!window.nfc.supported) {
        document.getElementById('nfcMoveHint').classList.remove('d-none');
        return;
    }
    btn.classList.remove('d-none');
    btn.addEventListener('click', async function() {
        status.className = 'w-100 small text-muted';
        status.textContent = 'Encoste o celular na tag da caixa...';
        btn.disabled = true;
        try {
            const tag = await window.nfc.read();
            const response = await fetch('{{ route('items.nfc-move', $item) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ tag }),
            });
            const result = await response.json();
            status.className = 'w-100 small ' + (result.success ? 'text-success' : 'text-danger');
            status.textContent = result.message;
            if (result.success) setTimeout(() => location.reload(), 800);
        } catch (e) {
            status.className = 'w-100 small text-danger';
            status.textContent = e.message;
        } finally {
            btn.disabled = false;
        }
    });
});
</script>
@endpush
