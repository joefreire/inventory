@extends('layouts.app')

@section('title', 'Editar Tag')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar Tag</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('tags.update', $tag) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Nome -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nome *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $tag->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Cor -->
                        <div class="mb-3">
                            <label for="color" class="form-label">Cor</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color"
                                       id="colorPicker" value="{{ old('color', $tag->color) }}">
                                <input type="text" class="form-control @error('color') is-invalid @enderror"
                                       id="color" name="color" value="{{ old('color', $tag->color) }}"
                                       pattern="^#[0-9A-Fa-f]{6}$">
                                @error('color')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="mb-3">
                            <label class="form-label">Preview</label>
                            <div>
                                <span class="badge fs-6" id="tagPreview" style="background-color: {{ $tag->color }}">
                                    <span id="tagPreviewName">{{ $tag->name }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Info -->
                        <div class="alert alert-info py-2 mb-3">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                Esta tag esta associada a {{ $tag->items()->count() }} itens.
                            </small>
                        </div>

                        <!-- Botoes -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('tags.index') }}" class="btn btn-outline-secondary">
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
const colorPicker = document.getElementById('colorPicker');
const colorInput = document.getElementById('color');
const nameInput = document.getElementById('name');
const tagPreview = document.getElementById('tagPreview');
const tagPreviewName = document.getElementById('tagPreviewName');

colorPicker.addEventListener('input', function() {
    colorInput.value = this.value;
    tagPreview.style.backgroundColor = this.value;
});

colorInput.addEventListener('input', function() {
    if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
        colorPicker.value = this.value;
        tagPreview.style.backgroundColor = this.value;
    }
});

nameInput.addEventListener('input', function() {
    tagPreviewName.textContent = this.value || 'nome-da-tag';
});
</script>
@endpush
