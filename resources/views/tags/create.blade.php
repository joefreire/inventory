@extends('layouts.app')

@section('title', 'Nova tag')
@section('back', route('tags.index'))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('tags.store') }}" method="POST" id="tagForm">
        @csrf
        @include('tags._fields')
    </form>
</div>
<div class="action-dock">
    <div class="inner">
        <button type="submit" form="tagForm" class="btn btn-primary btn-lg"><i class="bi bi-check2"></i> Criar tag</button>
    </div>
</div>
@endsection
