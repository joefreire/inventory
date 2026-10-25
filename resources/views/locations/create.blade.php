@extends('layouts.app')

@section('title', 'Nova caixa')
@section('back', $parent ? route('locations.show', $parent) : route('locations.index'))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('locations.store') }}" method="POST" id="locationForm">
        @csrf
        @include('locations._fields')
    </form>
</div>
<div class="action-dock">
    <div class="inner">
        <button type="submit" form="locationForm" class="btn btn-primary btn-lg"><i class="bi bi-check2"></i> Criar caixa</button>
    </div>
</div>
@endsection
