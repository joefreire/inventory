{{--
    Photos of a part (front, back, label); the first is the main one.
    Behaviour: resources/js/components/photo-picker.js.
    Optional: $savedPhotos (saved photos, when editing) with remove_media[] checkboxes.
--}}
@php
    $savedPhotos = $savedPhotos ?? collect();
    $max = \App\Http\Requests\StoreItemRequest::MAX_PHOTOS;
@endphp

<div class="photo-picker mb-3" id="photoPicker" data-max="{{ $max }}">
    <div class="photo-strip" data-photo-strip>
        @foreach($savedPhotos as $i => $media)
            <div class="photo-tile" data-saved="{{ $media->id }}">
                <img src="{{ $media->getUrl('thumb') }}" alt="Foto {{ $i + 1 }}">
                <input type="checkbox" name="remove_media[]" value="{{ $media->id }}" class="d-none">
                <button type="button" class="tile-x" data-remove aria-label="Tirar esta foto"><i class="bi bi-x-lg"></i></button>
            </div>
        @endforeach
    </div>

    <label class="camera-zone {{ $savedPhotos->isNotEmpty() ? 'd-none' : '' }}" data-camera-zone for="camInput">
        <span class="cam"><i class="bi bi-camera"></i></span>
        <strong>Tirar foto da peça</strong>
        <span class="small-muted px-4 text-center">{{ $dica ?? 'Frente, verso e etiqueta ajudam a IA. Até '.$max.' fotos.' }}</span>
    </label>

    <div class="d-flex gap-2 mt-2">
        <label for="camInput" class="btn btn-quiet flex-fill mb-0">
            <i class="bi bi-camera"></i> <span data-camera-label>{{ $savedPhotos->isNotEmpty() ? 'Mais uma foto' : 'Câmera' }}</span>
        </label>
        <label for="galInput" class="btn btn-quiet flex-fill mb-0">
            <i class="bi bi-images"></i> Galeria
        </label>
    </div>

    <input type="file" id="camInput" accept="image/*" capture="environment" class="d-none" data-photo-source>
    <input type="file" id="galInput" accept="image/*" multiple class="d-none" data-photo-source>
    <input type="file" name="photos[]" multiple class="d-none">
    @error('photos')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @error('photos.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
