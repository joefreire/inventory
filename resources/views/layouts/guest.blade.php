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
    <script src="/theme.js"></script>
    @vite('resources/js/app.js')
</head>
<body class="guest">
    <header class="guest-top">
        <img src="/icons/icon.svg" alt="" width="52" height="52">
        <h1>Inventário de peças</h1>
        <p>Onde está cada peça, quantas sobraram e o que serve para o projeto.</p>
    </header>
    <main class="guest-body">
        @yield('content')
    </main>
</body>
</html>
