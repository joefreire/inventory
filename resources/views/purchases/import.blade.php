@extends('layouts.app')

@section('title', 'Importar compras')
@section('back', route('purchases.index'))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('purchases.import.store') }}" method="POST" enctype="multipart/form-data" id="importForm">
        @csrf
        <div class="mb-3">
            <label for="file" class="form-label">Arquivo JSON das compras</label>
            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file" accept=".json,.txt,application/json" required>
            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Reimportar o mesmo pedido atualiza o que já existe, sem duplicar.</div>
        </div>
    </form>
</div>
<div class="action-dock">
    <div class="inner">
        <button type="submit" form="importForm" class="btn btn-primary btn-lg"><i class="bi bi-upload"></i> Importar</button>
    </div>
</div>
@endsection
