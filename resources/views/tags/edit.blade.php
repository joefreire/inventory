@extends('layouts.app')

@section('title', 'Editar tag')
@section('back', route('tags.show', $tag))
@section('body-class', 'has-dock')
@section('page', 'tag-form')

@section('content')
<div class="page">
    <form action="{{ route('tags.update', $tag) }}" method="POST" id="tagForm">
        @csrf
        @method('PUT')
        @include('tags._fields', ['tag' => $tag])
    </form>
    <p class="small-muted mx-1">Usada em {{ $tag->items()->count() }} peças.</p>
    <form action="{{ route('tags.destroy', $tag) }}" method="POST" class="mt-4"
          data-confirm="Excluir a tag {{ $tag->name }}? As peças continuam, só perdem a tag.">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-quiet text-danger w-100"><i class="bi bi-trash"></i> Excluir tag</button>
    </form>
</div>
<div class="action-dock">
    <div class="inner">
        <a href="{{ route('tags.show', $tag) }}" class="btn btn-quiet">Cancelar</a>
        <button type="submit" form="tagForm" class="btn btn-primary"><i class="bi bi-check2"></i> Salvar alterações</button>
    </div>
</div>
@endsection
