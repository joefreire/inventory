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

    <!-- Tag NFC da caixa -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-broadcast me-2"></i>Tag NFC</div>
                <div class="card-body">
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" class="form-control" id="nfcUrl" value="{{ $location->nfc_url }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="nfcCopyBtn">
                            <i class="bi bi-clipboard"></i> Copiar
                        </button>
                    </div>
                    @unless(config('app.nfc_base_url'))
                        <div class="alert alert-warning py-1 px-2 small mb-2">
                            NFC_BASE_URL nao definido: este link e so de teste. Defina o endereco fixo no .env antes de gravar tags.
                        </div>
                    @endunless
                    <button type="button" class="btn btn-primary btn-sm d-none" id="nfcWriteBtn">
                        <i class="bi bi-pencil-square me-1"></i>Gravar na tag
                    </button>
                    <div class="small mt-2" id="nfcStatus"></div>
                    <div class="small text-muted mt-2" id="nfcHelp">
                        Encostar o celular na tag abre esta caixa (Android e iPhone).
                        <span id="nfcHelpNoWrite">Para gravar: no Android, abra esta pagina no Chrome com HTTPS e use
                        "Gravar na tag"; no iPhone, copie o link e grave no app NFC Tools
                        (Gravar &gt; Adicionar registro &gt; URL).</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-box-arrow-in-down me-2"></i>Guardar item aqui</div>
                <div class="card-body">
                    @if($otherItems->isNotEmpty())
                        <div class="input-group input-group-sm">
                            <select class="form-select" id="storeItemSelect">
                                <option value="">Escolha um item...</option>
                                @foreach($otherItems as $other)
                                    <option value="{{ route('items.move', $other) }}">
                                        {{ $other->name }}{{ $other->location ? ' (em ' . $other->location->path . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <button class="btn btn-success" type="button" id="storeItemBtn">Guardar</button>
                        </div>
                        <div class="small mt-2" id="storeItemStatus"></div>
                    @else
                        <p class="text-muted small mb-0">Todos os itens ja estao aqui.</p>
                    @endif
                    <a href="{{ route('items.create', ['location_id' => $location->id]) }}" class="btn btn-link btn-sm px-0 mt-2">
                        <i class="bi bi-camera me-1"></i>Cadastrar peca nova nesta caixa
                    </a>
                </div>
            </div>
        </div>
    </div>

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

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const url = document.getElementById('nfcUrl').value;
    const status = document.getElementById('nfcStatus');
    const say = (kind, text) => { status.className = 'small mt-2 text-' + kind; status.textContent = text; };

    document.getElementById('nfcCopyBtn').addEventListener('click', async function() {
        try {
            await navigator.clipboard.writeText(url);
            say('success', 'Link copiado.');
        } catch (e) {
            document.getElementById('nfcUrl').select();
            say('muted', 'Selecione e copie o link.');
        }
    });

    if (window.nfc.supported) {
        const btn = document.getElementById('nfcWriteBtn');
        btn.classList.remove('d-none');
        document.getElementById('nfcHelpNoWrite').classList.add('d-none');
        btn.addEventListener('click', async function() {
            btn.disabled = true;
            say('muted', 'Encoste a tag vazia no celular...');
            try {
                await window.nfc.write(url);
                say('success', 'Tag gravada. Encoste de novo para testar.');
            } catch (e) {
                say('danger', 'Nao gravou: ' + e.message);
            } finally {
                btn.disabled = false;
            }
        });
    }

    const storeBtn = document.getElementById('storeItemBtn');
    if (storeBtn) {
        storeBtn.addEventListener('click', async function() {
            const moveUrl = document.getElementById('storeItemSelect').value;
            const out = document.getElementById('storeItemStatus');
            if (!moveUrl) return;
            storeBtn.disabled = true;
            try {
                const response = await fetch(moveUrl, {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ location_id: {{ $location->id }} }),
                });
                const result = await response.json();
                out.className = 'small mt-2 text-' + (result.success ? 'success' : 'danger');
                out.textContent = result.message || 'Erro ao guardar.';
                if (result.success) setTimeout(() => location.reload(), 600);
            } catch (e) {
                out.className = 'small mt-2 text-danger';
                out.textContent = 'Erro ao guardar.';
            } finally {
                storeBtn.disabled = false;
            }
        });
    }
});
</script>
@endpush
