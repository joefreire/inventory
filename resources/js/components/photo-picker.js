import { isHeic, shrinkImage } from '../lib/image';
import { toast } from '../lib/toast';

/**
 * Photos of a part (front, back, label). The first one is the main photo.
 * Markup: resources/views/items/_photos.blade.php (#photoPicker, data-max).
 * Saved photos (edit) are tiles with data-saved="{mediaId}" and a hidden
 * remove_media[] checkbox; new photos go in the hidden input photos[].
 */
export class PhotoPicker {
    constructor(root) {
        this.root = root;
        this.strip = root.querySelector('[data-photo-strip]');
        this.zone = root.querySelector('[data-camera-zone]');
        this.hidden = root.querySelector('input[name="photos[]"]');
        this.max = Number(root.dataset.max);
        this.files = [];
        this.listeners = [];

        for (const input of root.querySelectorAll('[data-photo-source]')) {
            input.addEventListener('change', () => {
                if (input.files.length) this.add(input.files);
                input.value = '';
            });
        }
        for (const tile of this.strip.querySelectorAll('[data-saved]')) {
            tile.querySelector('[data-remove]').addEventListener('click', () => this.toggleSaved(tile));
        }
        this.render();
    }

    onChange(fn) { this.listeners.push(fn); }

    newFiles() { return this.files.slice(); }

    keptSaved() {
        return [...this.strip.querySelectorAll('[data-saved]')].filter((t) => !t.querySelector('input').checked);
    }

    async add(fileList) {
        const free = this.max - this.keptSaved().length - this.files.length;
        const accepted = [...fileList].slice(0, Math.max(0, free));
        if (fileList.length > accepted.length) toast(`No máximo ${this.max} fotos por peça.`, 'error');
        for (const file of accepted) {
            const photo = await shrinkImage(file, 1600);
            // the browser could not convert it to JPEG and the server cannot read HEIC
            if (isHeic(photo)) {
                toast('Foto HEIC não abre aqui. Tire pela câmera do app ou salve como JPG.', 'error');
                continue;
            }
            this.files.push(photo);
        }
        this.render();
        if (accepted.length) this.emit();
    }

    remove(index) {
        this.files.splice(index, 1);
        this.render();
        this.emit();
    }

    toggleSaved(tile) {
        const box = tile.querySelector('input');
        box.checked = !box.checked;
        tile.classList.toggle('removida', box.checked);
        tile.querySelector('[data-remove]').innerHTML = box.checked ? '<i class="bi bi-arrow-counterclockwise"></i>' : '<i class="bi bi-x-lg"></i>';
        this.render();
    }

    setBusy(on, text = '') {
        let bar = this.root.querySelector('.strip-busy');
        if (!on) { bar?.remove(); return; }
        if (!bar) {
            bar = document.createElement('div');
            bar.className = 'strip-busy';
            this.strip.after(bar);
        }
        bar.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ';
        bar.append(text);
    }

    emit() { this.listeners.forEach((fn) => fn(this.newFiles())); }

    render() {
        this.strip.querySelectorAll('[data-new]').forEach((t) => t.remove());
        this.files.forEach((file, i) => {
            file.previewUrl ??= URL.createObjectURL(file);
            const tile = document.createElement('div');
            tile.className = 'photo-tile';
            tile.dataset.new = String(i);
            const img = document.createElement('img');
            img.src = file.previewUrl;
            img.alt = `Foto nova ${i + 1}`;
            const x = document.createElement('button');
            x.type = 'button';
            x.className = 'tile-x';
            x.setAttribute('aria-label', 'Tirar esta foto');
            x.innerHTML = '<i class="bi bi-x-lg"></i>';
            x.addEventListener('click', () => this.remove(i));
            tile.append(img, x);
            this.strip.append(tile);
        });

        this.strip.querySelectorAll('.badge-main').forEach((b) => b.remove());
        const first = [...this.strip.children].find((t) => !(t.dataset.saved && t.querySelector('input').checked));
        first?.insertAdjacentHTML('beforeend', '<span class="badge-main">Principal</span>');

        const total = this.keptSaved().length + this.files.length;
        this.zone.classList.toggle('d-none', total > 0);
        const label = this.root.querySelector('[data-camera-label]');
        if (label) label.textContent = total > 0 ? 'Mais uma foto' : 'Câmera';
        this.root.querySelectorAll('label.btn').forEach((l) => l.classList.toggle('disabled', total >= this.max));

        const transfer = new DataTransfer();
        this.files.forEach((f) => transfer.items.add(f));
        this.hidden.files = transfer.files;
    }
}
