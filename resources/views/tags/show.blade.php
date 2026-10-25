@extends('layouts.app')

@section('title', 'Tag: ' . $tag->name)
@section('heading', 'Tag')
@section('back', route('tags.index'))

@section('top-actions')
    <a href="{{ route('tags.edit', $tag) }}" class="icon-btn" aria-label="Editar tag"><i class="bi bi-pencil"></i></a>
@endsection

@section('content')
<div class="page">
    <div class="page-head">
        <h2 class="h1"><span class="tag-dot" style="--c: {{ $tag->color }}; font-size: inherit">{{ $tag->name }}</span></h2>
        <p class="small-muted mb-0">{{ $items->total() }} {{ $items->total() === 1 ? 'peça' : 'peças' }}</p>
    </div>
    @if($items->count() > 0)
        <div class="rows">
            @foreach($items as $item)
                <a href="{{ route('items.show', $item) }}" class="row-item">
                    @if($item->main_photo_url)
                        <img src="{{ $item->main_photo_url }}" class="thumb" alt="" loading="lazy">
                    @else
                        <span class="thumb"><i class="bi bi-cpu"></i></span>
                    @endif
                    <span class="grow">
                        <span class="name">{{ $item->name }}</span>
                        @if($item->location)<span><span class="tape">{{ $item->location->name }}</span></span>@endif
                    </span>
                    <span class="qty {{ $item->quantity === 0 ? 'zero' : '' }}">{{ $item->quantity }}<small>un</small></span>
                </a>
            @endforeach
        </div>
        <div class="mt-3">{{ $items->onEachSide(1)->links() }}</div>
    @else
        <p class="small-muted mx-1">Nenhuma peça com esta tag.</p>
    @endif
</div>
@endsection
