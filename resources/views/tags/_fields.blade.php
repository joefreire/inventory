@php $tag = $tag ?? null; $color = old('color', $tag?->color ?? '#2f7d5b'); @endphp
<div class="mb-3">
    <label for="name" class="form-label">Nome</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
           value="{{ old('name', $tag?->name) }}" required maxlength="50" autocomplete="off" autocapitalize="none" placeholder="Ex.: 5v, smd, wifi">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
<div class="mb-3">
    <label for="color" class="form-label">Cor</label>
    <div class="d-flex gap-2 align-items-center">
        <input type="color" class="form-control form-control-color" id="colorPicker" value="{{ $color }}" style="width: 64px; min-height: 50px" aria-label="Escolher cor">
        <input type="text" class="form-control @error('color') is-invalid @enderror" id="color" name="color" value="{{ $color }}" pattern="#[0-9a-fA-F]{6}" autocapitalize="none">
    </div>
    @error('color')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
