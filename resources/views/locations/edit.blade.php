@extends('layouts.app')

@section('title', 'Editar caixa')
@section('back', route('locations.show', $location))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('locations.update', $location) }}" method="POST" id="locationForm">
        @csrf
        @method('PUT')
        @include('locations._fields', ['location' => $location])
    </form>

    <form action="{{ route('locations.destroy', $location) }}" method="POST" class="mt-4"
          data-confirm="Excluir a caixa {{ $location->name }}? As caixas dentro dela também saem; as peças ficam sem caixa.">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-quiet text-danger w-100"><i class="bi bi-trash"></i> Excluir caixa</button>
    </form>
</div>
<div class="action-dock">
    <div class="inner">
        <a href="{{ route('locations.show', $location) }}" class="btn btn-quiet">Cancelar</a>
        <button type="submit" form="locationForm" class="btn btn-primary"><i class="bi bi-check2"></i> Salvar alterações</button>
    </div>
</div>
@endsection
