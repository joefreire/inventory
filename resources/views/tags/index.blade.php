@extends('layouts.app')

@section('title', 'Tags')

@section('top-actions')
    <a href="{{ route('tags.create') }}" class="icon-btn" aria-label="Nova tag"><i class="bi bi-plus-lg"></i></a>
@endsection

@section('content')
<div class="page">
    @if($tags->count() > 0)
        <div class="rows">
            @foreach($tags as $tag)
                <a href="{{ route('tags.show', $tag) }}" class="row-item">
                    <span class="grow"><span class="tag-dot" style="--c: {{ $tag->color }}">{{ $tag->name }}</span></span>
                    <span class="qty">{{ $tag->items_count }}<small>pc</small></span>
                </a>
            @endforeach
        </div>
    @else
        <div class="empty">
            <h2 class="h3">Nenhuma tag ainda</h2>
            <p>As tags aparecem sozinhas quando a IA cadastra peças. Também dá para criar na mão.</p>
            <a href="{{ route('tags.create') }}" class="btn btn-primary btn-lg"><i class="bi bi-plus-lg"></i> Nova tag</a>
        </div>
    @endif
</div>
@endsection
