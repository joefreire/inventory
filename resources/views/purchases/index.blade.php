@extends('layouts.app')

@section('title', 'Compras')
@section('back', route('items.index'))

@section('top-actions')
    <a href="{{ route('purchases.import') }}" class="icon-btn" aria-label="Importar compras"><i class="bi bi-upload"></i></a>
@endsection

@section('content')
<div class="page">
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
@endsection
