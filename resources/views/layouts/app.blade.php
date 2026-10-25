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

    {{-- Tema claro/escuro pelo sistema, antes de pintar (sem piscar) --}}
    <script>
        (function () {
            var m = window.matchMedia('(prefers-color-scheme: dark)');
            var set = function () { document.documentElement.setAttribute('data-bs-theme', m.matches ? 'dark' : 'light'); };
            set(); m.addEventListener && m.addEventListener('change', set);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/css/app.css?v={{ filemtime(public_path('css/app.css')) }}" rel="stylesheet">
    @stack('styles')
</head>
<body class="@yield('body-class')">
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
        <button type="button" id="tabNfc" class="d-none">
            <i class="bi bi-broadcast"></i><span>Ler tag</span>
        </button>
        <a href="{{ route('tags.index') }}" id="tabTags" class="{{ request()->routeIs('tags.*') ? 'active' : '' }}">
            <i class="bi bi-tags"></i><span>Tags</span>
        </a>
    </nav>

    <div class="toast-wrap" id="toastWrap" aria-live="polite"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    window.csrf = document.querySelector('meta[name="csrf-token"]').content;

    // Aviso curto acima da barra inferior
    window.toast = function (message, kind) {
        var wrap = document.getElementById('toastWrap');
        wrap.innerHTML = '';
        var el = document.createElement('div');
        el.className = 'toast-msg show-in' + (kind === 'error' ? ' error' : '');
        el.innerHTML = '<i class="bi ' + (kind === 'error' ? 'bi-exclamation-circle' : 'bi-check2-circle') + '"></i>';
        var span = document.createElement('span');
        span.textContent = message;
        el.appendChild(span);
        wrap.appendChild(el);
        clearTimeout(window.__toastT);
        window.__toastT = setTimeout(function () { el.remove(); }, kind === 'error' ? 6000 : 3200);
    };

    // fetch JSON com CSRF
    window.api = async function (url, method, body) {
        var res = await fetch(url, {
            method: method || 'GET',
            headers: { 'X-CSRF-TOKEN': window.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: body ? JSON.stringify(body) : undefined,
        });
        var data = {};
        try { data = await res.json(); } catch (e) {}
        if (!res.ok || data.success === false) {
            throw new Error(data.message || 'Não deu certo (HTTP ' + res.status + '). Tente de novo.');
        }
        return data;
    };

    // Reduz a foto no celular antes de enviar (12 MP viram ~300 KB): upload rapido no 4G.
    window.shrinkImage = function (file, maxSide) {
        maxSide = maxSide || 1600;
        return new Promise(function (resolve) {
            if (!file || !file.type || !file.type.startsWith('image/')) return resolve(file);
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () {
                var scale = Math.min(1, maxSide / Math.max(img.width, img.height));
                var canvas = document.createElement('canvas');
                canvas.width = Math.round(img.width * scale);
                canvas.height = Math.round(img.height * scale);
                canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
                canvas.toBlob(function (blob) {
                    URL.revokeObjectURL(url);
                    if (!blob || blob.size >= file.size) return resolve(file);
                    resolve(new File([blob], (file.name || 'foto').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }));
                }, 'image/jpeg', 0.85);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    };

    // Tags NFC: Web NFC só existe no Chrome do Android e só em HTTPS.
    // No iPhone a tag e lida pelo proprio sistema (abre o link) e gravada pelo app NFC Tools.
    window.nfc = {
        supported: 'NDEFReader' in window,
        async write(url) {
            const reader = new NDEFReader();
            await reader.write({ records: [{ recordType: 'url', data: url }] });
        },
        read() {
            return new Promise(async (resolve, reject) => {
                const reader = new NDEFReader();
                const ctrl = new AbortController();
                window.nfc._abort = () => { ctrl.abort(); reject(new Error('cancelado')); };
                reader.onreadingerror = () => { ctrl.abort(); reject(new Error('Não consegui ler a tag. Encoste de novo, parado.')); };
                reader.onreading = event => {
                    ctrl.abort();
                    const decoder = new TextDecoder();
                    for (const record of event.message.records) {
                        if (record.recordType === 'url' || record.recordType === 'text') {
                            return resolve(decoder.decode(record.data));
                        }
                    }
                    reject(new Error('Essa tag está vazia. Grave o link da caixa nela primeiro.'));
                };
                try { await reader.scan({ signal: ctrl.signal }); } catch (e) { reject(e); }
            });
        },
        // Painel "Encoste a tag" enquanto le; devolve o texto lido ou null se cancelado.
        async readWithSheet(message) {
            const overlay = document.createElement('div');
            overlay.className = 'nfc-overlay';
            overlay.innerHTML = '<div class="sheet"><div class="nfc-ring"><i class="bi bi-broadcast"></i></div>'
                + '<h2 class="h3 mb-1"></h2><p class="small-muted mb-3">Encoste a parte de trás do celular na etiqueta da caixa.</p>'
                + '<button type="button" class="btn btn-quiet w-100">Cancelar</button></div>';
            overlay.querySelector('h2').textContent = message || 'Encoste na tag';
            document.body.appendChild(overlay);
            overlay.querySelector('button').onclick = () => window.nfc._abort && window.nfc._abort();
            try {
                return await window.nfc.read();
            } catch (e) {
                if (e.message !== 'cancelado') window.toast(e.message, 'error');
                return null;
            } finally {
                overlay.remove();
            }
        },
        codeFrom(text) {
            const m = String(text || '').trim().match(/\/n\/([a-z0-9]{4,16})\/?(?:[?#].*)?$/i);
            return m ? m[1].toLowerCase() : null;
        },
    };

    // Aba "Ler tag" (Android): le a tag e abre a caixa. No iPhone a aba vira "Tags".
    if (window.nfc.supported) {
        document.getElementById('tabTags').classList.add('d-none');
        var tabNfc = document.getElementById('tabNfc');
        tabNfc.classList.remove('d-none');
        tabNfc.addEventListener('click', async function () {
            const text = await window.nfc.readWithSheet('Abrir caixa');
            if (!text) return;
            const code = window.nfc.codeFrom(text);
            if (!code) return window.toast('Essa tag não e de nenhuma caixa do inventário.', 'error');
            window.location.href = '/n/' + code;
        });
    }

    @if(session('success'))
        window.toast(@json(session('success')));
    @endif
    @if(session('error'))
        window.toast(@json(session('error')), 'error');
    @endif
    </script>
    @stack('scripts')
</body>
</html>
