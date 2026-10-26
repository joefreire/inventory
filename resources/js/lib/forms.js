/**
 * Behaviour every form gets:
 * - data-confirm="..." asks before submitting (delete buttons);
 * - one submit only: buttons lock after the first tap (no part saved twice).
 */
export function initForms() {
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
            return;
        }
        if (e.defaultPrevented) return;
        if (form.dataset.sending) { e.preventDefault(); return; }
        form.dataset.sending = '1';
        for (const button of document.querySelectorAll('button[type="submit"]')) {
            if (button.form !== form) continue;
            // after the submit event, so the clicked button's name/value still go
            setTimeout(() => { button.disabled = true; }, 0);
            if (button === e.submitter) button.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm"></span>');
        }
    });
}
