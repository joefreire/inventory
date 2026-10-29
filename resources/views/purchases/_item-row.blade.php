@php
    $media = $line->getFirstMedia('photo');
    $order = $line->order;
@endphp
<div class="row-item">
    @if($media)
        <img src="{{ route('media.show', [$media, 'thumb']) }}" class="thumb" alt="" loading="lazy">
    @else
        <span class="thumb"><i class="bi bi-bag"></i></span>
    @endif
    <span class="grow">
        <span class="name">{{ $line->displayName() }}</span>
        @if($line->name !== null)
            <span><span class="tape">completado pela IA</span></span>
        @endif
        @if($line->variant)
            <span class="small-muted d-block">{{ $line->variant }}</span>
        @endif
        <span class="small-muted d-block">
            Pedido {{ $order->number }}
            @if($order->ordered_at) · {{ $order->ordered_at->format('d/m/Y') }} @endif
            · {{ $order->status->label() }}
        </span>
        @if($line->enrichment_error !== null)
            <span><span class="tape tape-none">IA falhou</span></span>
        @elseif($line->enriched_at === null)
            <span><span class="tape tape-none">IA pendente</span></span>
        @endif
    </span>
    <span class="qty">x{{ $line->quantity }}
        @if(($line->pack_quantity ?? 1) > 1)
            <small>= {{ $line->suggestedQuantity() }} un</small>
        @endif
    </span>
</div>
