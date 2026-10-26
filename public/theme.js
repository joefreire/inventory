// Light/dark from the system, before the first paint (blocking on purpose: no flash).
// A file, not inline, so the CSP can forbid inline scripts.
(function () {
    var media = window.matchMedia('(prefers-color-scheme: dark)');
    var apply = function () { document.documentElement.setAttribute('data-bs-theme', media.matches ? 'dark' : 'light'); };
    apply();
    if (media.addEventListener) media.addEventListener('change', apply);
})();
