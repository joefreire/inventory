{{--
    Fotos da peca: varias da mesma peca (frente, verso, etiqueta). A primeira e a principal.
    Cada foto e reduzida no celular antes de enviar. Expoe window.fotosPeca:
      .files()       -> fotos novas, na ordem
      .onChange(fn)  -> chamado quando entra ou sai foto
    Opcional: $existentes (fotos ja salvas, na edicao), com checkbox remove_media[].
--}}
@php
    $existentes = $existentes ?? collect();
    $max = \App\Http\Requests\StoreItemRequest::MAX_FOTOS;
@endphp

<div class="photo-picker mb-3" id="photoPicker" data-max="{{ $max }}" data-existentes="{{ $existentes->count() }}">
    <div class="photo-strip" id="photoStrip">
        @foreach($existentes as $i => $media)
            <div class="photo-tile" data-existente="{{ $media->id }}">
                <img src="{{ $media->getUrl('thumb') }}" alt="Foto {{ $i + 1 }}">
                @if($i === 0)<span class="badge-main">Principal</span>@endif
                <input type="checkbox" name="remove_media[]" value="{{ $media->id }}" class="d-none">
                <button type="button" class="tile-x" aria-label="Tirar esta foto"><i class="bi bi-x-lg"></i></button>
            </div>
        @endforeach
    </div>

    <label class="camera-zone {{ $existentes->isNotEmpty() ? 'd-none' : '' }}" id="cameraZone" for="camInput">
        <span class="cam"><i class="bi bi-camera"></i></span>
        <strong>Tirar foto da peça</strong>
        <span class="small-muted px-4 text-center">{{ $dica ?? 'Frente, verso e etiqueta ajudam a IA. Até ' . $max . ' fotos.' }}</span>
    </label>

    <div class="d-flex gap-2 mt-2">
        <label for="camInput" class="btn btn-quiet flex-fill mb-0" id="addCam">
            <i class="bi bi-camera"></i> <span>{{ $existentes->isNotEmpty() ? 'Mais uma foto' : 'Câmera' }}</span>
        </label>
        <label for="galInput" class="btn btn-quiet flex-fill mb-0">
            <i class="bi bi-images"></i> Galeria
        </label>
    </div>

    <input type="file" id="camInput" accept="image/*" capture="environment" class="d-none">
    <input type="file" id="galInput" accept="image/*" multiple class="d-none">
    <input type="file" name="photos[]" id="photosInput" multiple class="d-none">
    @error('photos')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    @error('photos.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>

@push('scripts')
<script>
window.fotosPeca = (function () {
    const picker = document.getElementById('photoPicker');
    const strip = document.getElementById('photoStrip');
    const zone = document.getElementById('cameraZone');
    const hidden = document.getElementById('photosInput');
    const max = Number(picker.dataset.max);
    let novas = [];          // File[]
    const ouvintes = [];

    const existentesAtivas = () => [...strip.querySelectorAll('[data-existente]')].filter(t => !t.querySelector('input').checked);

    function sincronizar() {
        const dt = new DataTransfer();
        novas.forEach(f => dt.items.add(f));
        hidden.files = dt.files;
    }

    function render() {
        strip.querySelectorAll('[data-nova]').forEach(t => t.remove());
        novas.forEach((file, i) => {
            const tile = document.createElement('div');
            tile.className = 'photo-tile';
            tile.dataset.nova = i;
            const img = document.createElement('img');
            img.src = file.__url || (file.__url = URL.createObjectURL(file));
            img.alt = 'Foto nova ' + (i + 1);
            tile.appendChild(img);
            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'tile-x';
            x.setAttribute('aria-label', 'Tirar esta foto');
            x.innerHTML = '<i class="bi bi-x-lg"></i>';
            x.onclick = () => { novas.splice(i, 1); render(); avisar(); };
            tile.appendChild(x);
            strip.appendChild(tile);
        });
        // "Principal" na primeira foto que vai ficar
        strip.querySelectorAll('.badge-main').forEach(b => b.remove());
        const primeira = [...strip.children].find(t => !(t.dataset.existente && t.querySelector('input').checked));
        if (primeira) primeira.insertAdjacentHTML('beforeend', '<span class="badge-main">Principal</span>');

        const total = existentesAtivas().length + novas.length;
        zone.classList.toggle('d-none', total > 0);
        strip.classList.toggle('d-none', total === 0 && !strip.querySelector('[data-existente]'));
        document.querySelector('#addCam span').textContent = total > 0 ? 'Mais uma foto' : 'Câmera';
        picker.querySelectorAll('label.btn').forEach(l => l.classList.toggle('disabled', total >= max));
        sincronizar();
    }

    function avisar() { ouvintes.forEach(fn => fn(novas.slice())); }

    async function adicionar(fileList) {
        const livres = max - existentesAtivas().length - novas.length;
        const lista = [...fileList].slice(0, Math.max(0, livres));
        if (fileList.length > lista.length) window.toast('No máximo ' + max + ' fotos por peça.', 'error');
        for (const f of lista) novas.push(await window.shrinkImage(f, 1600));
        render();
        if (lista.length) avisar();
    }

    ['camInput', 'galInput'].forEach(id => {
        const input = document.getElementById(id);
        input.addEventListener('change', () => { if (input.files.length) adicionar(input.files); input.value = ''; });
    });

    // fotos ja salvas: o x marca para remover (e desfaz se tocar de novo)
    strip.querySelectorAll('[data-existente]').forEach(tile => {
        tile.querySelector('.tile-x').addEventListener('click', () => {
            const cb = tile.querySelector('input');
            cb.checked = !cb.checked;
            tile.classList.toggle('removida', cb.checked);
            tile.querySelector('.tile-x').innerHTML = cb.checked ? '<i class="bi bi-arrow-counterclockwise"></i>' : '<i class="bi bi-x-lg"></i>';
            render();
        });
    });

    render();
    return {
        files: () => novas.slice(),
        onChange: fn => ouvintes.push(fn),
        setBusy(on, texto) {
            let b = picker.querySelector('.strip-busy');
            if (!on) return b && b.remove();
            if (!b) { b = document.createElement('div'); b.className = 'strip-busy'; picker.insertBefore(b, strip.nextSibling); }
            b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + texto;
        },
    };
})();
</script>
@endpush
