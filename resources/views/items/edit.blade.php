@extends('layouts.app')

@section('title', 'Editar ' . $item->name)

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar Item</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('items.update', $item) }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <!-- Foto atual -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Foto do Item</label>
                            @if($item->main_photo_url)
                                <div class="mb-2" id="currentPhoto">
                                    <img src="{{ $item->preview_photo_url }}" class="img-fluid rounded" style="max-height: 200px;">
                                    <div class="form-check mt-2">
                                        <input type="checkbox" class="form-check-input" id="remove_photo" name="remove_photo" value="1">
                                        <label class="form-check-label text-danger" for="remove_photo">
                                            <i class="bi bi-trash me-1"></i>Remover foto atual
                                        </label>
                                    </div>
                                </div>
                            @endif
                            <input type="file" class="form-control @error('photo') is-invalid @enderror"
                                   name="photo" accept="image/*">
                            <div class="form-text">Selecione uma nova foto para substituir a atual</div>
                            @error('photo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr>

                        <!-- Nome -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nome *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $item->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Descricao -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descricao</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="2">{{ old('description', $item->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Descricao da IA -->
                        <input type="hidden" name="ai_description" value="{{ $item->ai_description }}">

                        <div class="row">
                            <!-- Categoria -->
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Categoria</label>
                                <input type="text" class="form-control @error('category') is-invalid @enderror"
                                       id="category" name="category" value="{{ old('category', $item->category) }}"
                                       list="categoryList">
                                <datalist id="categoryList">
                                    <option value="resistor">
                                    <option value="capacitor">
                                    <option value="ic">
                                    <option value="conector">
                                    <option value="modulo">
                                    <option value="sensor">
                                    <option value="led">
                                    <option value="transistor">
                                    <option value="diodo">
                                    <option value="indutor">
                                    <option value="rele">
                                    <option value="fusivel">
                                    <option value="cristal">
                                    <option value="outro">
                                </datalist>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Quantidade -->
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Quantidade</label>
                                <input type="number" class="form-control @error('quantity') is-invalid @enderror"
                                       id="quantity" name="quantity" value="{{ old('quantity', $item->quantity) }}" min="1">
                                @error('quantity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Local -->
                        <div class="mb-3">
                            <label for="location_id" class="form-label">Local</label>
                            <select class="form-select @error('location_id') is-invalid @enderror"
                                    id="location_id" name="location_id">
                                <option value="">Sem local definido</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}"
                                            {{ old('location_id', $item->location_id) == $location->id ? 'selected' : '' }}>
                                        {{ $location->path }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Tags -->
                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" class="form-control @error('tags') is-invalid @enderror"
                                   id="tags" name="tags"
                                   value="{{ old('tags', $item->tags->pluck('name')->implode(', ')) }}"
                                   placeholder="Separe as tags por virgula">
                            @error('tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($tags->count() > 0)
                                <div class="form-text">
                                    Tags existentes:
                                    @foreach($tags->take(10) as $tag)
                                        <span class="badge tag-badge" style="background-color: {{ $tag->color }}"
                                              onclick="addTag('{{ $tag->name }}')">{{ $tag->name }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Notas -->
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notas adicionais</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror"
                                      id="notes" name="notes" rows="2">{{ old('notes', $item->notes) }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Botoes -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('items.show', $item) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Salvar Alteracoes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function addTag(tagName) {
    const tagsInput = document.getElementById('tags');
    const currentTags = tagsInput.value.split(',').map(t => t.trim()).filter(t => t);

    if (!currentTags.includes(tagName)) {
        currentTags.push(tagName);
        tagsInput.value = currentTags.join(', ');
    }
}
</script>
@endpush
