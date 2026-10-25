<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e3b2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Inventário">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <title>Entrar · Inventário</title>
    <script>
        document.documentElement.setAttribute('data-bs-theme', matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/css/app.css?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    <style>
        body { padding: 0; min-height: 100dvh; display: flex; flex-direction: column; }
        .guest-top { background: var(--ink); color: #fff; padding: calc(40px + env(safe-area-inset-top, 0px)) 24px 28px; }
        :root[data-bs-theme="dark"] .guest-top { background: var(--surface); color: var(--text); }
        .guest-top h1 { font-size: 2.2rem; font-family: var(--font-narrow); font-weight: 800; margin: 14px 0 4px; }
        .guest-top p { opacity: .8; margin: 0; max-width: 32ch; }
        .guest-body { flex: 1; padding: 24px; max-width: 480px; width: 100%; margin: 0 auto; }
    </style>
</head>
<body>
    <header class="guest-top">
        <img src="/icons/icon.svg" alt="" width="52" height="52">
        <h1>Inventário de peças</h1>
        <p>Onde está cada peça, quantas sobraram e o que serve para o projeto.</p>
    </header>
    <main class="guest-body">
        {{ $slot }}
    </main>
</body>
</html>
