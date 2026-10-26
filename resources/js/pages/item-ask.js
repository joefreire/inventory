/** The search takes a few seconds: show it is working. */
export default function init() {
    const form = document.getElementById('askForm');
    form?.addEventListener('submit', () => {
        const field = form.querySelector('.search-field');
        if (!field.querySelector('.spinner-border')) field.insertAdjacentHTML('beforeend', '<span class="spinner-border spinner-border-sm text-muted search-spinner"></span>');
    });
}
