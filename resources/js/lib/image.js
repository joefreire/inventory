/**
 * Shrinks a phone photo before upload (12 MP -> ~300 KB): fast on 4G.
 * Returns the original when it cannot be decoded or would not get smaller.
 */
export function shrinkImage(file, maxSide = 1600) {
    return new Promise((resolve) => {
        if (!file?.type?.startsWith('image/')) return resolve(file);
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const scale = Math.min(1, maxSide / Math.max(img.width, img.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.width * scale);
            canvas.height = Math.round(img.height * scale);
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => {
                URL.revokeObjectURL(url);
                if (!blob || blob.size >= file.size) return resolve(file);
                resolve(new File([blob], (file.name || 'foto').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
            }, 'image/jpeg', 0.85);
        };
        img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
        img.src = url;
    });
}

/** HEIC/HEIF (iPhone format): neither PHP nor most browsers outside Safari read it. */
export function isHeic(file) {
    return /^image\/hei[cf]/.test(file?.type ?? '') || /\.hei[cf]$/i.test(file?.name ?? '');
}
