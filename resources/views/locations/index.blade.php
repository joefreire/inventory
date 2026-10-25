@extends('layouts.app')

@section('title', 'Caixas')

@section('top-actions')
    <a href="{{ route('locations.create') }}" class="icon-btn" aria-label="Nova caixa"><i class="bi bi-plus-lg"></i></a>
@endsection

@section('content')
<div class="page">
    @if($locations->count() > 0)
        <div class="rows">
            @foreach($locations as $location)
                @include('partials.location-row', ['location' => $location, 'depth' => 0])
            @endforeach
        </div>
        <p class="small-muted mx-1 mt-3">Cada caixa tem um link para gravar na tag NFC. Abra a caixa para gravar.</p>
    @else
        <div class="empty">
            <h2 class="h3">Nenhuma caixa ainda</h2>
            <p>Crie uma caixa para cada lugar onde você guarda peças. Depois grave a tag NFC dela e cole na caixa.</p>
            <a href="{{ route('locations.create') }}" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg"></i> Nova caixa</a>
        </div>
    @endif
</div>
@endsection
