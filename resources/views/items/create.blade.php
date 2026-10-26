@extends('layouts.app')

@section('title', 'Cadastrar peça')
@section('back', route('items.index'))
@section('body-class', 'has-dock')
@section('page', 'item-create')

@section('content')
<div class="page">
    <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data" id="itemForm" novalidate
          data-analyze-url="{{ route('items.analyze') }}" data-max-ai-photos="{{ \App\Ai\ItemAdvisor::MAX_PHOTOS }}">
        @csrf

        {{-- Camera primeiro: cada foto nova ja dispara a IA com todas as fotos --}}
        @include('items._photos')

        <div id="aiArea" class="mb-3"></div>

        <input type="hidden" name="ai_description" id="ai_description" value="{{ old('ai_description') }}">
        <input type="hidden" name="ai_metadata" id="ai_metadata" value="{{ old('ai_metadata') }}">

        @include('items._fields')

        <button type="button" class="btn btn-quiet w-100 mb-3" id="suggestFromName">
            <i class="bi bi-stars"></i> Sugerir pelo nome, sem foto
        </button>
    </form>
</div>

<div class="action-dock">
    <div class="inner">
        <button type="submit" form="itemForm" name="proxima" value="1" class="btn btn-quiet" title="Salva e abre outro cadastro na mesma caixa">
            <i class="bi bi-plus-lg"></i> Salvar e próxima
        </button>
        <button type="submit" form="itemForm" class="btn btn-primary"><i class="bi bi-check2"></i> Salvar peça</button>
    </div>
</div>
@endsection

