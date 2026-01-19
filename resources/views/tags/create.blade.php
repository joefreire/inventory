@extends('layouts.app')

@section('title', 'Nova Tag')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-tag me-2"></i>Nova Tag</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('tags.store') }}" method="POST">
                        @csrf

                        <!-- Nome -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nome *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}" required
                                   placeholder="Ex: smd, 5v, arduino...">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Cor -->
                        <div class="mb-3">
                            <label for="color" class="form-label">Cor</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color"
                                       id="colorPicker" value="{{ old('color', '#0d6efd') }}">
                                <input type="text" class="form-control @error('color') is-invalid @enderror"
                                       id="color" name="color" value="{{ old('color', '#0d6efd') }}"
                                       pattern="^#[0-9A-Fa-f]{6}$" placeholder="#0d6efd">
                                @error('color')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">
                                Cores sugeridas:
                                @foreach(['#0d6efd', '#198754', '#dc3545', '#ffc107', '#0dcaf0', '#6f42c1', '#d63384', '#fd7e14'] as $c)
                                    <span class="badge" style="background-color: {{ $c }}; cursor: pointer;"
                                          onclick="setColor('{{ $c }}')">&nbsp;</span>
                                @endforeach
                            </div>
                        </div>

                        <!-- Preview -->
                        <div class="mb-3">
                            <label class="form-label">Preview</label>
                            <div>
                                <span class="badge fs-6" id="tagPreview" style="background-color: {{ old('color', '#0d6efd') }}">
                                    <span id="tagPreviewName">{{ old('name', 'nome-da-tag') }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Botoes -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('tags.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Criar Tag
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

function setColor(color) {
    colorInput.value = color;
    colorPicker.value = color;
    tagPreview.style.backgroundColor = color;
}
</script>
@endpush
