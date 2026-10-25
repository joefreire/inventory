@extends('layouts.app')

@section('title', 'Editar peça')
@section('back', route('items.show', $item))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('items.update', $item) }}" method="POST" enctype="multipart/form-data" id="itemForm">
        @csrf
        @method('PUT')

        @include('items._photos', ['existentes' => $item->getMedia('photos'), 'dica' => 'Adicione frente, verso ou etiqueta.'])

        @include('items._fields', ['item' => $item])
    </form>
</div>

<div class="action-dock">
    <div class="inner">
        <a href="{{ route('items.show', $item) }}" class="btn btn-quiet">Cancelar</a>
        <button type="submit" form="itemForm" class="btn btn-primary"><i class="bi bi-check2"></i> Salvar alterações</button>
    </div>
</div>
@endsection

