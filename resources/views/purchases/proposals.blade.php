@extends('layouts.app')

@section('title', 'Propostas de ligação')
@section('back', route('purchases.index'))

@section('content')
<div class="page">
    @if($lines->isEmpty())
        <div class="empty">
            <h2 class="h3">Nenhuma proposta para revisar</h2>
            <p>Peça para a IA procurar de qual peça do estoque vem cada compra antiga.</p>
            <form action="{{ route('purchases.proposals.generate') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-stars"></i> Ligar compras antigas às peças</button>
            </form>
        </div>
    @else
        <p class="small-muted mx-1 mb-2">Marcadas serão ligadas à peça, sem mexer na quantidade. Marque "Não é essa" para a IA não propor de novo.</p>
        <form action="{{ route('purchases.proposals.review') }}" method="POST">
            @csrf
            <div class="rows">
                @foreach($lines as $line)
                    @php
                        $purchaseMedia = $line->getFirstMedia('photo');
                        $part = $line->proposedItem;
                        $partMedia = $part?->getFirstMedia('photos');
                    @endphp
                    <div class="row-item">
                        @if($purchaseMedia)
                            <img src="{{ route('media.show', [$purchaseMedia, 'thumb']) }}" class="thumb" alt="Foto da compra" loading="lazy">
                        @else
                            <span class="thumb"><i class="bi bi-bag"></i></span>
                        @endif
                        @if($partMedia)
                            <img src="{{ route('media.show', [$partMedia, 'thumb']) }}" class="thumb" alt="Foto da peça" loading="lazy">
                        @else
                            <span class="thumb"><i class="bi bi-box-seam"></i></span>
                        @endif
                        <span class="grow">
                            <span class="name">{{ $line->displayName() }}</span>
                            <span class="small-muted d-block">é a peça <strong>{{ $part?->name }}</strong> ({{ round(($line->proposal_probability ?? 0) * 100) }}%)</span>
                            <span class="d-flex flex-wrap gap-3 mt-1">
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="confirmar[]" value="{{ $line->id }}" checked>
                                    <span class="form-check-label">Ligar</span>
                                </label>
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="recusar[]" value="{{ $line->id }}">
                                    <span class="form-check-label">Não é essa</span>
                                </label>
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>
            <div class="d-grid mt-3">
                <button type="submit" class="btn btn-primary btn-lg">Aplicar</button>
            </div>
        </form>
    @endif
</div>
@endsection
