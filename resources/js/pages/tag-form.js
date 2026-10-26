/** Color picker and text field stay in sync. */
export default function init() {
    const picker = document.getElementById('colorPicker');
    const text = document.getElementById('color');
    if (!picker || !text) return;
    picker.addEventListener('input', () => { text.value = picker.value; });
    text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value; });
}
