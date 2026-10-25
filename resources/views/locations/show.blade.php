@extends('layouts.app')

@section('title', $location->name)
@section('heading', 'Caixa')
@section('back', $location->parent ? route('locations.show', $location->parent) : route('locations.index'))

@section('top-actions')
    <button type="button" class="icon-btn" data-bs-toggle="offcanvas" data-bs-target="#acoesCaixa" aria-label="Mais ações">
        <i class="bi bi-three-dots-vertical"></i>
    </button>
@endsection

@section('content')
<div class="page">
    <div class="page-head">
        @if($location->ancestors())
            <p class="small-muted mb-1">
                @foreach($location->ancestors() as $ancestor)
                    <a href="{{ route('locations.show', $ancestor) }}" class="text-muted">{{ $ancestor->name }}</a> /
                @endforeach
            </p>
        @endif
        <h2 class="mb-1"><span class="tape tape-lg">{{ $location->name }}</span></h2>
        @if($location->description)
            <p class="small-muted mb-0 mt-2">{{ $location->description }}</p>
        @endif
    </div>

    <div class="d-flex gap-2 mb-2">
        <a href="{{ route('items.create', ['location_id' => $location->id]) }}" class="btn btn-primary flex-fill">
            <i class="bi bi-camera"></i> Cadastrar aqui
        </a>
        <button type="button" class="btn btn-quiet flex-fill" data-bs-toggle="offcanvas" data-bs-target="#guardarItem">
            <i class="bi bi-box-arrow-in-down"></i> Guardar item aqui
        </button>
    </div>

    @if($location->children->isNotEmpty())
        <h3 class="group-title">Dentro desta caixa</h3>
        <div class="rows">
            @foreach($location->children as $child)
                <a href="{{ route('locations.show', $child) }}" class="row-item">
                    <span class="grow"><span><span class="tape">{{ $child->name }}</span></span></span>
                    <span class="qty">{{ $child->items->count() }}<small>pc</small></span>
                </a>
            @endforeach
        </div>
    @endif

    <h3 class="group-title">{{ $items->total() }} {{ $items->total() === 1 ? 'peça' : 'peças' }}</h3>
    @if($items->count() > 0)
        <div class="rows">
            @foreach($items as $item)
                <a href="{{ route('items.show', $item) }}" class="row-item">
                    @if($item->main_photo_url)
                        <img src="{{ $item->main_photo_url }}" class="thumb" alt="" loading="lazy">
                    @else
                        <span class="thumb"><i class="bi bi-cpu"></i></span>
                    @endif
                    <span class="grow"><span class="name">{{ $item->name }}</span></span>
                    <span class="qty {{ $item->quantity === 0 ? 'zero' : '' }}">{{ $item->quantity }}<small>un</small></span>
                </a>
            @endforeach
        </div>
        <div class="mt-3">{{ $items->onEachSide(1)->links() }}</div>
    @else
        <p class="small-muted mx-1">Caixa vazia. Cadastre uma peça nova aqui ou guarde uma que já existe.</p>
    @endif

    {{-- Tag NFC --}}
    <h3 class="group-title">Tag NFC</h3>
    <div class="panel">
        @unless(config('app.nfc_base_url'))
            <div class="alert alert-warning py-2 small">
                NFC_BASE_URL não definido: este link e só de teste. Defina o endereço fixo no .env antes de gravar tags.
            </div>
        @endunless
        <p class="mb-2">Encostar o celular na tag abre esta caixa, no Android e no iPhone.</p>
        <button type="button" class="btn btn-primary w-100 mb-2 d-none" id="nfcWriteBtn">
            <i class="bi bi-broadcast"></i> Gravar na tag
        </button>
        <div class="search-field">
            <input type="text" class="form-control" id="nfcUrl" value="{{ $location->nfc_url }}" readonly aria-label="Link da tag">
        </div>
        <button type="button" class="btn btn-quiet w-100 mt-2" id="nfcCopyBtn"><i class="bi bi-clipboard"></i> Copiar link</button>
        <p class="small-muted mt-2 mb-0" id="nfcHelpNoWrite">
            No iPhone: copie o link e grave no app NFC Tools (Gravar, Adicionar registro, URL).
            No Android, o botão Gravar na tag aparece no Chrome.
        </p>
    </div>
</div>

{{-- Guardar item que ja existe --}}
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="guardarItem" aria-labelledby="guardarTitulo">
    <div class="grabber"></div>
    <div class="offcanvas-header pb-1">
        <h2 class="h3 mb-0" id="guardarTitulo">Guardar em {{ $location->name }}</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="px-3 pb-2">
        <div class="search-field">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control" id="filtroGuardar" placeholder="Filtrar peças" autocomplete="off" aria-label="Filtrar peças">
        </div>
    </div>
    <div class="offcanvas-body p-0">
        @forelse($otherItems as $other)
            <button type="button" class="sheet-action" data-move="{{ route('items.move', $other) }}" data-nome="{{ \Illuminate\Support\Str::lower($other->name) }}">
                <i class="bi bi-cpu"></i>
                <span class="text-truncate flex-fill">{{ $other->name }}</span>
                <span class="small-muted text-truncate" style="max-width: 40%">{{ $other->location?->name ?? 'sem caixa' }}</span>
            </button>
        @empty
            <p class="px-3 small-muted">Todas as peças já estão nesta caixa.</p>
        @endforelse
    </div>
</div>

{{-- Mais acoes --}}
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="acoesCaixa" aria-label="Ações da caixa">
    <div class="grabber"></div>
    <div class="offcanvas-body p-0 py-2">
        <a href="{{ route('locations.edit', $location) }}" class="sheet-action"><i class="bi bi-pencil"></i> Editar caixa</a>
        <a href="{{ route('locations.create', ['parent_id' => $location->id]) }}" class="sheet-action"><i class="bi bi-box-seam"></i> Nova caixa dentro desta</a>
        <a href="{{ route('items.index', ['location_id' => $location->id]) }}" class="sheet-action"><i class="bi bi-search"></i> Buscar nesta caixa</a>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const url = document.getElementById('nfcUrl').value;

    document.getElementById('nfcCopyBtn').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(url);
            window.toast('Link copiado.');
        } catch (e) {
            document.getElementById('nfcUrl').select();
            window.toast('Selecione e copie o link.', 'error');
        }
    });

    if (window.nfc.supported) {
        const btn = document.getElementById('nfcWriteBtn');
        btn.classList.remove('d-none');
        document.getElementById('nfcHelpNoWrite').classList.add('d-none');
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            window.toast('Encoste a tag vazia na parte de trás do celular.');
            try {
                await window.nfc.write(url);
                window.toast('Tag gravada. Encoste de novo para testar.');
            } catch (e) {
                window.toast('Não gravou: ' + e.message, 'error');
            } finally {
                btn.disabled = false;
            }
        });
    }

    const filtro = document.getElementById('filtroGuardar');
    filtro && filtro.addEventListener('input', () => {
        const q = filtro.value.trim().toLowerCase();
        document.querySelectorAll('#guardarItem [data-move]').forEach(b => {
            b.classList.toggle('d-none', q && !b.dataset.nome.includes(q));
        });
    });

    document.querySelectorAll('#guardarItem [data-move]').forEach(b => {
        b.addEventListener('click', async () => {
            try {
                await window.api(b.dataset.move, 'PATCH', { location_id: {{ $location->id }} });
                window.location.reload();
            } catch (e) {
                window.toast(e.message, 'error');
            }
        });
    });
})();
</script>
@endpush
