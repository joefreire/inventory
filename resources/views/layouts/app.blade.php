<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0e3b2e" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#13241d" media="(prefers-color-scheme: dark)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Inventário">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

    <title>@yield('title', 'Inventario') · Inventário</title>

    {{-- theme before the first paint; a file, so the CSP can forbid inline scripts --}}
    <script src="/theme.js"></script>
    @vite('resources/js/app.js')
</head>
<body class="@yield('body-class')" data-page="@yield('page')"
      data-nfc-resolve-url="{{ route('nfc.resolve') }}"
      @if(session('success')) data-flash-success="{{ session('success') }}" @endif
      @if(session('error')) data-flash-error="{{ session('error') }}" @endif>
    <header class="app-top">
        @hasSection('back')
            <a href="@yield('back')" class="icon-btn" aria-label="Voltar"><i class="bi bi-chevron-left"></i></a>
        @else
            <span class="icon-btn" aria-hidden="true"><img src="/icons/icon.svg" alt="" width="28" height="28"></span>
        @endif
        <h1 class="title">@yield('heading', View::getSection('title', 'Inventário'))</h1>
        @yield('top-actions')
    </header>

    <main>
        @yield('content')
    </main>

    @php
        $aba = match (true) {
            request()->routeIs('items.ask') => 'ask',
            request()->routeIs('items.create') => 'add',
            request()->routeIs('items.*') => 'items',
            request()->routeIs('locations.*') => 'boxes',
            default => '',
        };
    @endphp
    <nav class="app-tabbar" aria-label="Navegação principal">
        <a href="{{ route('items.index') }}" class="{{ $aba === 'items' ? 'active' : '' }}">
            <i class="bi bi-cpu"></i><span>Peças</span>
        </a>
        <a href="{{ route('locations.index') }}" class="{{ $aba === 'boxes' ? 'active' : '' }}">
            <i class="bi bi-box-seam"></i><span>Caixas</span>
        </a>
        <a href="{{ route('items.create') }}" class="add" aria-label="Cadastrar peça">
            <span class="disc"><i class="bi bi-camera"></i></span><span>Cadastrar</span>
        </a>
        <a href="{{ route('items.ask') }}" class="{{ $aba === 'ask' ? 'active' : '' }}">
            <i class="bi bi-chat-square-text"></i><span>Perguntar</span>
        </a>
        <button type="button" id="tabNfc" class="d-none" aria-label="Ler tag de caixa">
            <i class="bi bi-broadcast"></i><span>Ler tag</span>
        </button>
        <a href="{{ route('tags.index') }}" id="tabTags" class="{{ request()->routeIs('tags.*') ? 'active' : '' }}">
            <i class="bi bi-tags"></i><span>Tags</span>
        </a>
    </nav>

    <div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

</body>
</html>
