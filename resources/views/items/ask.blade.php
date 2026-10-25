@extends('layouts.app')

@section('title', 'Perguntar ao inventario')

@section('content')
<div class="container py-4">
    <h2 class="mb-3"><i class="bi bi-robot me-2"></i>Perguntar ao inventario</h2>

    <form action="{{ route('items.ask') }}" method="GET" class="mb-4">
        <div class="input-group input-group-lg">
            <input type="search" name="q" class="form-control" value="{{ $pergunta }}" autofocus
                   placeholder="Ex.: tenho algo pra medir temperatura? o que liga um motor 12V?">
            <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
        </div>
        <div class="form-text">O modelo de decisao avalia cada item do inventario contra a pergunta.</div>
    </form>

    @if($error)
        <div class="alert alert-danger">{{ $error }}</div>
    @elseif($pergunta !== '' && $results->isEmpty())
        <div class="alert alert-secondary">Nada no inventario parece servir para isso.</div>
    @endif

    @if($results->isNotEmpty())
        <div class="list-group">
            @foreach($results as $r)
                <a href="{{ route('items.show', $r['item']) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                    @if($r['item']->main_photo_url)
                        <img src="{{ $r['item']->main_photo_url }}" alt="" width="56" height="56" class="rounded object-fit-cover">
                    @else
                        <i class="bi bi-box fs-3 text-muted"></i>
                    @endif
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $r['item']->name }}</div>
                        <small class="text-muted">
                            {{ $r['item']->quantity }} un.
                            @if($r['item']->location) &middot; {{ $r['item']->location->path }} @endif
                        </small>
                    </div>
                    <span class="badge bg-primary">{{ round($r['probability'] * 100) }}%</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
