@extends('layouts.app')

@section('title', 'Novo Item')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Adicionar Novo Item</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data" id="itemForm">
                        @csrf

                        <!-- Upload de foto -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">Foto do Item</label>
                            <div class="upload-zone" id="uploadZone">
                                <input type="file" name="photo" id="photoInput" accept="image/*" capture="environment">
                                <div id="uploadPlaceholder">
                                    <i class="bi bi-camera fs-1 text-muted"></i>
                                    <p class="mb-0 text-muted">Clique ou arraste uma foto aqui</p>
                                    <small class="text-muted">JPG, PNG ou GIF ate 10MB</small>
                                </div>
                                <div id="imagePreview" class="d-none">
                                    <img src="" alt="Preview" class="img-fluid rounded" style="max-height: 300px;">
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-2" id="removePhoto">
                                        <i class="bi bi-trash me-1"></i>Remover
                                    </button>
                                </div>
                            </div>

                            <!-- IA: foto e/ou nome digitado -> sugestoes -->
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-primary" id="analyzeBtn">
                                    <i class="bi bi-robot me-1"></i>Sugerir com IA
                                </button>
                                <small class="text-muted ms-2">Com foto ou so com o nome digitado</small>
                                <div class="ai-loading mt-2" id="aiLoading">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    <span class="text-muted">Identificando e decidindo...</span>
                                </div>
                                <div class="alert alert-warning mt-2 d-none" id="aiDuplicate"></div>
                                <div class="alert alert-info mt-2 d-none" id="aiResult"></div>
                            </div>
                        </div>

                        <hr>

                        <!-- Nome -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nome *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Descricao -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descricao</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="2">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Descricao da IA (hidden, preenchido automaticamente) -->
                        <input type="hidden" name="ai_description" id="ai_description" value="{{ old('ai_description') }}">
                        <input type="hidden" name="ai_metadata" id="ai_metadata" value="{{ old('ai_metadata') }}">

                        <div class="row">
                            <!-- Categoria -->
                            <div class="col-md-6 mb-3">
                                <label for="category" class="form-label">Categoria</label>
                                <input type="text" class="form-control @error('category') is-invalid @enderror"
                                       id="category" name="category" value="{{ old('category') }}"
                                       list="categoryList" placeholder="Ex: capacitor, resistor, ic...">
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
                                       id="quantity" name="quantity" value="{{ old('quantity', 1) }}" min="1">
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
                                <option value="">Selecione um local...</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}" {{ old('location_id', $locationId) == $location->id ? 'selected' : '' }}>
                                        {{ $location->path }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                <a href="{{ route('locations.create') }}" target="_blank">
                                    <i class="bi bi-plus me-1"></i>Criar novo local
                                </a>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" class="form-control @error('tags') is-invalid @enderror"
                                   id="tags" name="tags" value="{{ old('tags') }}"
                                   placeholder="Separe as tags por virgula: eletronica, 5v, smd...">
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
                                      id="notes" name="notes" rows="2" placeholder="Observacoes, onde comprou, etc...">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Botoes -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('items.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Salvar Item
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
document.addEventListener('DOMContentLoaded', function() {
    const uploadZone = document.getElementById('uploadZone');
    const photoInput = document.getElementById('photoInput');
    const uploadPlaceholder = document.getElementById('uploadPlaceholder');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = imagePreview.querySelector('img');
    const removePhotoBtn = document.getElementById('removePhoto');
    const analyzeBtn = document.getElementById('analyzeBtn');
    const aiLoading = document.getElementById('aiLoading');
    const aiResult = document.getElementById('aiResult');

    // Click no upload zone
    uploadZone.addEventListener('click', function(e) {
        if (e.target !== removePhotoBtn && !removePhotoBtn.contains(e.target)) {
            photoInput.click();
        }
    });

    // Drag and drop
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });

    uploadZone.addEventListener('dragleave', function() {
        uploadZone.classList.remove('dragover');
    });

    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            photoInput.files = e.dataTransfer.files;
            handleFileSelect(e.dataTransfer.files[0]);
        }
    });

    // File input change
    photoInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            handleFileSelect(this.files[0]);
        }
    });

    function handleFileSelect(file) {
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecione uma imagem.');
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            uploadPlaceholder.classList.add('d-none');
            imagePreview.classList.remove('d-none');
        };
        reader.readAsDataURL(file);
    }

    // Remove photo
    removePhotoBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        photoInput.value = '';
        uploadPlaceholder.classList.remove('d-none');
        imagePreview.classList.add('d-none');
        aiResult.classList.add('d-none');
    });

    // IA: uma chamada; o servidor usa o modelo de visao (se houver foto) e o de decisao
    const pct = p => p == null ? '' : ` (${Math.round(p * 100)}%)`;
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    analyzeBtn.addEventListener('click', async function() {
        const nameInput = document.getElementById('name');
        if (!photoInput.files.length && !nameInput.value.trim()) {
            showAi('warning', 'Tire uma foto ou digite o nome da peca primeiro.');
            return;
        }

        const formData = new FormData();
        if (photoInput.files.length) formData.append('photo', photoInput.files[0]);
        if (nameInput.value.trim()) formData.append('name', nameInput.value.trim());
        const desc = document.getElementById('description').value.trim();
        if (desc) formData.append('description', desc);

        aiLoading.classList.add('active');
        analyzeBtn.disabled = true;
        aiResult.classList.add('d-none');
        document.getElementById('aiDuplicate').classList.add('d-none');

        try {
            const response = await fetch('{{ route('items.analyze') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: formData
            });
            const result = await response.json();

            if (!result.success) {
                showAi('danger', result.message || 'Nao foi possivel identificar a peca.');
                return;
            }
            applySuggestion(result.data);
        } catch (error) {
            console.error('Erro ao analisar:', error);
            showAi('danger', 'Erro ao conectar com a IA. Tente novamente.');
        } finally {
            aiLoading.classList.remove('active');
            analyzeBtn.disabled = false;
        }
    });

    function showAi(kind, html) {
        aiResult.className = `alert alert-${kind} mt-2`;
        aiResult.innerHTML = html;
    }

    function applySuggestion(data) {
        const setIfEmpty = (id, value) => {
            const el = document.getElementById(id);
            if (value && !el.value.trim()) el.value = value;
        };
        setIfEmpty('name', data.name);
        setIfEmpty('description', data.description);
        if (data.category) setIfEmpty('category', data.category.value);

        const tagNames = (data.tags || []).map(t => t.name);
        tagNames.forEach(addTag);

        document.getElementById('ai_description').value = data.description || '';
        document.getElementById('ai_metadata').value = JSON.stringify({
            specs: data.specs || {},
            visible_text: data.visible_text || '',
            models: data.models || {},
        });

        const linhas = [];
        if (data.category) linhas.push(`<b>Categoria:</b> ${esc(data.category.label || data.category.value)}${pct(data.category.probability)}`);
        if (tagNames.length) {
            linhas.push('<b>Tags:</b> ' + data.tags.map(t => esc(t.name) + (t.existing ? pct(t.probability) : ' (nova)')).join(', '));
        }
        const specs = Object.entries(data.specs || {});
        if (specs.length) linhas.push('<b>Specs:</b> ' + specs.map(([k, v]) => `${esc(k)}: ${esc(v)}`).join(', '));

        const loc = data.location;
        const select = document.getElementById('location_id');
        if (loc && loc.suggested) {
            if (!select.value) select.value = String(loc.suggested.id);
            linhas.push(`<b>Guardar em:</b> ${esc(loc.suggested.path)}${pct(loc.suggested.probability)}`);
        } else if (loc && loc.options && loc.options.length) {
            linhas.push('<b>Nenhuma caixa combina bem.</b> Mais perto: ' + loc.options.map(o => esc(o.path) + pct(o.probability)).join(', '));
        }
        if (data.models) linhas.push(`<small class="text-muted">Modelos: ${esc(data.models.vision || 'sem foto')} + ${esc(data.models.decision)}</small>`);
        showAi('info', linhas.join('<br>'));

        const dup = data.duplicate;
        const dupBox = document.getElementById('aiDuplicate');
        if (dup) {
            dupBox.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i><b>Voce ja tem isso?</b> `
                + `<a href="${esc(dup.url)}" target="_blank">${esc(dup.name)}</a> (${esc(dup.quantity)} un.`
                + `${dup.location ? ', em ' + esc(dup.location) : ''})${pct(dup.probability)}. `
                + 'Se for a mesma peca, abra o item e aumente a quantidade.';
            dupBox.classList.remove('d-none');
        }
    }
});

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
