@extends('layouts.app')

@section('title', 'Compras')
@section('back', route('items.index'))
@section('page', 'purchases')

@section('top-actions')
    <a href="{{ route('purchases.import') }}" class="icon-btn" aria-label="Importar compras"><i class="bi bi-upload"></i></a>

@endsection

@section('content')
<div class="page" id="purchasesPage" data-parts-url="{{ route('purchases.parts') }}">
    @error('item_id')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
    <form class="search-bar" action="{{ route('purchases.index') }}" method="GET" role="search">
        <input type="hidden" name="mostrar" value="{{ $filter->show }}">
        <div class="search-field">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control" value="{{ $filter->search }}"
                   placeholder="Buscar compra..." enterkeyhint="search" autocomplete="off" aria-label="Buscar">
        </div>
    </form>

    <div class="chips" aria-label="Mostrar">
        @foreach(['pendentes' => 'Pendentes', 'pecas' => 'Peças', 'todos' => 'Todos'] as $key => $label)
            <a href="{{ route('purchases.index', array_merge(request()->except('mostrar', 'page'), ['mostrar' => $key])) }}"
               class="chip {{ $filter->show === $key ? 'active' : '' }}">{{ $label }}@if($key === 'pendentes') <span class="small-muted">{{ $pendingCount }}</span>@endif</a>
        @endforeach
    </div>

    <div class="d-flex flex-wrap gap-2 mb-2">
        @if($proposalCount > 0)
            <a href="{{ route('purchases.proposals') }}" class="btn btn-sm btn-primary"><i class="bi bi-link-45deg"></i> Revisar {{ $proposalCount }} {{ $proposalCount === 1 ? 'proposta' : 'propostas' }}</a>
        @endif
        <form action="{{ route('purchases.proposals.generate') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-sm btn-quiet"><i class="bi bi-stars"></i> Ligar compras antigas às peças</button>
        </form>
    </div>

    <p class="small-muted mx-1 mb-2">
        {{ $lines->total() }} {{ $lines->total() === 1 ? 'item' : 'itens' }}
        @if($filter->search) para "{{ $filter->search }}" @endif
    </p>

    @if($lines->count() > 0)
        <div class="rows">
            @foreach($lines as $line)
                @include('purchases._item-row', ['line' => $line])
            @endforeach
        </div>
        <div class="mt-3">{{ $lines->onEachSide(1)->links() }}</div>
    @else
        <div class="empty">
            @if($filter->search || $filter->show !== 'pendentes')
                <h2 class="h3">Nada por aqui</h2>
                <p>Nenhuma compra com esse filtro.</p>
                <a href="{{ route('purchases.index', ['mostrar' => 'todos']) }}" class="btn btn-quiet">Ver todos</a>
            @else
                <h2 class="h3">Nenhuma compra pendente</h2>
                <p>Importe o JSON dos pedidos para ver o que chegou e ainda não está no estoque.</p>
                <a href="{{ route('purchases.import') }}" class="btn btn-primary btn-lg"><i class="bi bi-upload"></i> Importar compras</a>
            @endif
        </div>
    @endif
</div>
{{-- Juntar a uma peca que ja existe --}}
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="juntar" aria-labelledby="juntarTitulo">
    <div class="grabber"></div>
    <div class="offcanvas-header pb-1">
        <h2 class="h3 mb-0" id="juntarTitulo">Juntar a qual peça?</h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
    </div>
    <div class="offcanvas-body p-0">
        <div id="joinSearchStep">
            <div class="px-3 pb-2">
                <input type="search" class="form-control" id="joinSearch" placeholder="Buscar peça..." autocomplete="off" aria-label="Buscar peça">
            </div>
            <div id="joinResults"></div>
        </div>
        <form method="POST" id="joinForm" class="px-3 d-none">
            @csrf
            <input type="hidden" name="item_id" id="joinItemId">
            <p id="joinAsk" class="mb-3"></p>
            <div class="d-grid gap-2 pb-3">
                <button type="submit" name="somar" value="1" class="btn btn-primary">Somar</button>
                <button type="submit" name="somar" value="0" class="btn btn-quiet">Só ligar, sem somar</button>
                <button type="button" class="btn btn-link" id="joinBack">Escolher outra peça</button>
            </div>
        </form>
    </div>
</div>
@endsection
