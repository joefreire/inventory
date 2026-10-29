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
        @if($line->item_id !== null)
            <a href="{{ route('items.show', $line->item_id) }}" class="small-muted d-block">Já está no estoque</a>
        @elseif($line->dismissed_at !== null)
            <form action="{{ route('purchases.items.undismiss', $line) }}" method="POST" class="mt-1">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-sm btn-quiet">Voltar a pendente</button>
            </form>
        @else
            <span class="d-flex flex-wrap gap-2 mt-2">
                <a href="{{ route('items.create', ['compra' => $line->id]) }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Cadastrar peça</a>
                <button type="button" class="btn btn-sm btn-quiet" data-join data-bs-toggle="offcanvas" data-bs-target="#juntar"
                        data-join-url="{{ route('purchases.items.join', $line) }}"
                        data-units="{{ $line->suggestedQuantity() }}">Juntar a uma peça que já tenho</button>
                <form action="{{ route('purchases.items.dismiss', $line) }}" method="POST" class="d-inline">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn btn-sm btn-quiet">Não guardo</button>
                </form>
            </span>
        @endif
    </span>
    <span class="qty">x{{ $line->quantity }}
        @if(($line->pack_quantity ?? 1) > 1)
            <small>= {{ $line->suggestedQuantity() }} un</small>
        @endif
    </span>
</div>
