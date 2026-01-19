@extends('layouts.app')

@section('title', 'Editar ' . $location->name)

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pencil me-2"></i>Editar Local</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('locations.update', $location) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Nome -->
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Nome *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror"
                                   id="name" name="name" value="{{ old('name', $location->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Local pai -->
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Local pai</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror"
                                    id="parent_id" name="parent_id">
                                <option value="">Nenhum (raiz)</option>
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}"
                                            {{ old('parent_id', $location->parent_id) == $loc->id ? 'selected' : '' }}>
                                        {{ $loc->path }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Descricao -->
                        <div class="mb-3">
                            <label for="description" class="form-label">Descricao</label>
                            <textarea class="form-control @error('description') is-invalid @enderror"
                                      id="description" name="description" rows="2">{{ old('description', $location->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Info -->
                        <div class="alert alert-info py-2 mb-3">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                Este local contem {{ $location->total_item_count }} itens no total.
                            </small>
                        </div>

                        <!-- Botoes -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('locations.index') }}" class="btn btn-outline-secondary">
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
