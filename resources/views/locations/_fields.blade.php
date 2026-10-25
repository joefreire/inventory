{{-- Campos da caixa (cadastro e edicao). Espera $locations e, opcional, $location / $parent. --}}
@php $location = $location ?? null; @endphp

<div class="mb-3">
    <label for="name" class="form-label">Nome na etiqueta</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
           value="{{ old('name', $location?->name) }}" required maxlength="60" autocomplete="off" placeholder="Ex.: Caixa ESPs">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">E o nome que vai na fita da caixa. Curto ajuda a ler de longe.</div>
</div>

<div class="mb-3">
    <label for="parent_id" class="form-label">Dentro de</label>
    <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
        <option value="">Nenhum lugar (fica solta)</option>
        @foreach($locations as $loc)
            <option value="{{ $loc->id }}" {{ (string) old('parent_id', $location?->parent_id ?? ($parent->id ?? null)) === (string) $loc->id ? 'selected' : '' }}>
                {{ $loc->path }}
            </option>
        @endforeach
    </select>
    @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">Ex.: a gaveta dentro do armário da oficina.</div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">Onde fica</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
              rows="2" placeholder="Ex.: prateleira de cima, lado esquerdo">{{ old('description', $location?->description) }}</textarea>
    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
