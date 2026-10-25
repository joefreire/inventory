@extends('layouts.app')

@section('title', 'Editar peça')
@section('back', route('items.show', $item))
@section('body-class', 'has-dock')

@section('content')
<div class="page">
    <form action="{{ route('items.update', $item) }}" method="POST" enctype="multipart/form-data" id="itemForm">
        @csrf
        @method('PUT')

        <label class="camera-zone mb-2 {{ $item->preview_photo_url ? 'has-photo' : '' }}" id="cameraZone" for="photoInput">
            <input type="file" name="photo" id="photoInput" accept="image/*" capture="environment" class="d-none">
            @if($item->preview_photo_url)
                <img src="{{ $item->preview_photo_url }}" alt="Foto atual" id="photoPreview">
                <span class="btn btn-quiet btn-sm retake"><i class="bi bi-camera"></i> Trocar foto</span>
            @else
                <span class="cam"><i class="bi bi-camera"></i></span>
                <strong>Tirar foto da peça</strong>
            @endif
        </label>
        @if($item->preview_photo_url)
            <div class="form-check mb-3 mx-1">
                <input type="checkbox" class="form-check-input" id="remove_photo" name="remove_photo" value="1">
                <label class="form-check-label" for="remove_photo">Tirar a foto</label>
            </div>
        @endif
        @error('photo')<div class="text-danger small mb-2">{{ $message }}</div>@enderror

        @include('items._fields', ['item' => $item])
    </form>
</div>

<div class="action-dock">
    <div class="inner">
        <a href="{{ route('items.show', $item) }}" class="btn btn-quiet">Cancelar</a>
        <button type="submit" form="itemForm" class="btn btn-primary"><i class="bi bi-check2"></i> Salvar alterações</button>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const input = document.getElementById('photoInput');
    const zone = document.getElementById('cameraZone');
    input.addEventListener('change', async () => {
        if (!input.files.length) return;
        const pequena = await window.shrinkImage(input.files[0], 1600);
        const dt = new DataTransfer();
        dt.items.add(pequena);
        input.files = dt.files;
        zone.classList.add('has-photo');
        let img = zone.querySelector('img');
        if (!img) { img = document.createElement('img'); zone.appendChild(img); }
        img.src = URL.createObjectURL(pequena);
        const remover = document.getElementById('remove_photo');
        if (remover) remover.checked = false;
    });
})();
</script>
@endpush
