@extends('layouts.app')

@section('title', 'Cadastrar peça')
@section('back', route('items.index'))
@section('body-class', 'has-dock')
@section('page', 'item-create')

@section('content')
<div class="page">
    <form action="{{ route('items.store') }}" method="POST" enctype="multipart/form-data" id="itemForm" novalidate
          data-analyze-url="{{ route('items.analyze') }}" data-max-ai-photos="{{ \App\Ai\ItemAdvisor::MAX_PHOTOS }}"
          data-join-url-template="{{ route('purchases.items.join', ['purchaseItem' => '__ID__']) }}">
        @csrf

        @if($purchase)
            @php $official = $purchase->getFirstMedia('photo'); @endphp
            <input type="hidden" name="purchase_item_id" value="{{ $purchase->id }}">
            <div class="panel mb-3">
                <div class="d-flex gap-3 align-items-center">
                    @if($official)
                        <img src="{{ route('media.show', [$official, 'thumb']) }}" class="thumb" alt="Foto do anúncio" width="64" height="64">
                    @endif
                    <div class="small-muted">
                        @if($official)<div>Foto do anúncio (usada se você não tirar outra)</div>@endif
                        <div>Da compra {{ $purchase->order->number }}, x{{ $purchase->quantity }}@if(($purchase->pack_quantity ?? 1) > 1) de {{ $purchase->pack_quantity }}@endif</div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Camera primeiro: cada foto nova ja dispara a IA com todas as fotos --}}
        @include('items._photos')

        <div id="aiArea" class="mb-3"></div>

        <input type="hidden" name="ai_description" id="ai_description" value="{{ old('ai_description') }}">
        <input type="hidden" name="ai_metadata" id="ai_metadata" value="{{ old('ai_metadata', $purchase && $purchase->specs ? json_encode(['specs' => $purchase->specs], JSON_UNESCAPED_UNICODE) : '') }}">

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

