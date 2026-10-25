{{-- Campos da peca, usados no cadastro e na edicao. Espera $locations, $tags e, na edicao, $item. --}}
@php
    $item = $item ?? null;
    $categorias = config('ai.categories');
    $categoriaAtual = old('category', $item?->category);
@endphp

<div class="mb-3">
    <label for="name" class="form-label">Nome</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
           value="{{ old('name', $item?->name) }}" required autocomplete="off" placeholder="Ex.: Resistor 10k 1/4W">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-2 mb-3">
    <div class="col-7">
        <label for="category" class="form-label">Categoria</label>
        <select class="form-select @error('category') is-invalid @enderror" id="category" name="category">
            <option value="">Escolher...</option>
            @foreach($categorias as $chave => $rotulo)
                <option value="{{ $chave }}" {{ $categoriaAtual === $chave ? 'selected' : '' }}>{{ \Illuminate\Support\Str::before($rotulo, ' (') }}</option>
            @endforeach
            @if($categoriaAtual && !isset($categorias[$categoriaAtual]))
                <option value="{{ $categoriaAtual }}" selected>{{ $categoriaAtual }}</option>
            @endif
        </select>
        @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-5">
        <label for="quantity" class="form-label">Quantidade</label>
        <input type="number" class="form-control @error('quantity') is-invalid @enderror" id="quantity" name="quantity"
               value="{{ old('quantity', $item?->quantity ?? 1) }}" min="{{ $item ? 0 : 1 }}" inputmode="numeric" pattern="[0-9]*">
        @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label for="location_id" class="form-label">Caixa</label>
    <select class="form-select @error('location_id') is-invalid @enderror" id="location_id" name="location_id">
        <option value="">Sem caixa</option>
        @foreach($locations as $location)
            <option value="{{ $location->id }}" {{ (string) old('location_id', $locationId ?? $item?->location_id) === (string) $location->id ? 'selected' : '' }}>
                {{ $location->path }}
            </option>
        @endforeach
    </select>
    @error('location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text"><a href="{{ route('locations.create') }}" target="_blank">Criar caixa nova</a></div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">O que é</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
              rows="3" placeholder="Para que serve, de onde veio...">{{ old('description', $item?->description) }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="tags" class="form-label">Tags</label>
    <input type="text" class="form-control @error('tags') is-invalid @enderror" id="tags" name="tags"
           value="{{ old('tags', $item ? $item->tags->pluck('name')->implode(', ') : '') }}"
           placeholder="separadas por virgula" autocomplete="off" autocapitalize="none">
    @error('tags')<div class="invalid-feedback">{{ $message }}</div>@enderror
    @if($tags->isNotEmpty())
        <div class="chips mt-2" aria-label="Tags que já existem">
            @foreach($tags->take(20) as $tag)
                <button type="button" class="chip" data-add-tag="{{ $tag->name }}">
                    <span class="dot" style="background: {{ $tag->color }}"></span>{{ $tag->name }}
                </button>
            @endforeach
        </div>
    @endif
</div>

<div class="mb-3">
    <label for="notes" class="form-label">Notas</label>
    <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes"
              rows="2" placeholder="Onde comprou, defeito, projeto...">{{ old('notes', $item?->notes) }}</textarea>
    @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@push('scripts')
<script>
window.addTag = function (nome) {
    const input = document.getElementById('tags');
    const atuais = input.value.split(',').map(t => t.trim()).filter(Boolean);
    if (!atuais.some(t => t.toLowerCase() === nome.toLowerCase())) atuais.push(nome);
    input.value = atuais.join(', ');
};
document.querySelectorAll('[data-add-tag]').forEach(b => b.addEventListener('click', () => window.addTag(b.dataset.addTag)));
</script>
@endpush
