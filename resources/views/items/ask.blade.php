@extends('layouts.app')

@section('title', 'Perguntar ao inventário')
@section('heading', 'Perguntar')
@section('page', 'item-ask')

@section('content')
<div class="page">
    <form action="{{ route('items.ask') }}" method="GET" class="search-bar" id="askForm">
        <div class="search-field">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control" value="{{ $question }}" @if($question === '') autofocus @endif
                   placeholder="O que você precisa fazer?" enterkeyhint="search" autocomplete="off" aria-label="Pergunta">
        </div>
    </form>

    @if($question === '')
        <div class="mt-3">
            <h2 class="h2 mb-2">Descreva o que precisa</h2>
            <p class="lead-text mb-3">A IA olha cada peça do inventário e mostra as que servem, mesmo sem o nome certo.</p>
            <div class="d-flex flex-column gap-2">
                @foreach(['algo pra medir temperatura', 'ligar um motor 12V pelo ESP32', 'tenho rele de 5V?', 'fonte pra fita de LED'] as $exemplo)
                    <a href="{{ route('items.ask', ['q' => $exemplo]) }}" class="chip align-self-start">{{ $exemplo }}</a>
                @endforeach
            </div>
        </div>
    @elseif($error)
        <div class="panel mt-2">
            <h2 class="h3">A IA não respondeu</h2>
            <p class="small-muted mb-2">{{ $error }}</p>
            <a href="{{ route('items.index', ['search' => $question]) }}" class="btn btn-quiet">Buscar pelo texto</a>
        </div>
    @elseif($results->isEmpty())
        <div class="empty">
            <h2 class="h3">Nada no inventário serve para isso</h2>
            <p>Talvez ainda não esteja cadastrado. Tente descrever de outro jeito ou cadastre a peça quando chegar.</p>
        </div>
    @else
        <p class="small-muted mx-1 mb-2">{{ $results->count() }} {{ $results->count() === 1 ? 'peça serve' : 'peças servem' }}, da mais provável para a menos</p>
        <div class="rows">
            @foreach($results as $r)
                <a href="{{ route('items.show', $r['item']) }}" class="row-item">
                    @if($r['item']->main_photo_url)
                        <img src="{{ $r['item']->main_photo_url }}" class="thumb" alt="" loading="lazy">
                    @else
                        <span class="thumb"><i class="bi bi-cpu"></i></span>
                    @endif
                    <span class="grow">
                        <span class="name">{{ $r['item']->name }}</span>
                        @if($r['item']->location)
                            <span><span class="tape">{{ $r['item']->location->name }}</span></span>
                        @endif
                    </span>
                    <span class="text-end">
                        <span class="qty d-block">{{ $r['item']->quantity }}<small>un</small></span>
                        <span class="pct">{{ round($r['probability'] * 100) }}%</span>
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection

