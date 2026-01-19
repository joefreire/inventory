@extends('layouts.app')

@section('title', 'Novo Local')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-folder-plus me-2"></i>Novo Local</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('locations.store') }}" method="POST">
                        @csrf

                        <!-- Nome -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nome *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name') }}" required
                                   placeholder="Ex: Escritorio, Gaveta 1, Caixa Azul...">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Local pai -->
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Local pai (opcional)</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror"
                                    id="parent_id" name="parent_id">
                                <option value="">Nenhum (raiz)</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->id }}"
                                            {{ old('parent_id', $parent?->id) == $location->id ? 'selected' : '' }}>
                                        {{ $location->path }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Selecione um local pai para criar uma hierarquia
                            </div>
                        </div>

                        <!-- Descricao -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descricao</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="2"
                                      placeholder="Descricao opcional do local">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Botoes -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i>Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>Criar Local
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
