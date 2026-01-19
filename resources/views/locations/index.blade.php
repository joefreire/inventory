@extends('layouts.app')

@section('title', 'Locais')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-folder me-2"></i>Locais</h2>
        <a href="{{ route('locations.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Novo Local
        </a>
    </div>

    @if($locations->count() > 0)
        <div class="card">
            <div class="list-group list-group-flush">
                @foreach($locations as $location)
                    @include('partials.location-row', ['location' => $location, 'depth' => 0])
                @endforeach
            </div>
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-folder-plus fs-1 text-muted"></i>
            <p class="text-muted mt-2">Nenhum local cadastrado</p>
            <a href="{{ route('locations.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Criar primeiro local
            </a>
        </div>
    @endif
</div>
@endsection
