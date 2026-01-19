<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Inventario') }} - @yield('title', 'Home')</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .location-tree .list-group-item {
            border: none;
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
        .location-tree .list-group-item:hover {
            background: #e9ecef;
        }
        .location-tree .list-group-item.active {
            background: #0d6efd;
            color: white;
        }
        .item-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .item-photo {
            height: 150px;
            object-fit: cover;
            background: #f0f0f0;
        }
        .item-photo-placeholder {
            height: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #adb5bd;
        }
        .tag-badge {
            cursor: pointer;
        }
        .upload-zone {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
        }
        .upload-zone:hover, .upload-zone.dragover {
            border-color: #0d6efd;
            background: #f0f7ff;
        }
        .upload-zone input[type="file"] {
            display: none;
        }
        .ai-loading {
            display: none;
        }
        .ai-loading.active {
            display: block;
        }
        .depth-1 { padding-left: 1.5rem !important; }
        .depth-2 { padding-left: 2.5rem !important; }
        .depth-3 { padding-left: 3.5rem !important; }
        .depth-4 { padding-left: 4.5rem !important; }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="{{ route('items.index') }}">
                <i class="bi bi-box-seam me-2"></i>Inventario
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('items.*') ? 'active' : '' }}" href="{{ route('items.index') }}">
                            <i class="bi bi-grid me-1"></i>Itens
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('locations.*') ? 'active' : '' }}" href="{{ route('locations.index') }}">
                            <i class="bi bi-folder me-1"></i>Locais
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('tags.*') ? 'active' : '' }}" href="{{ route('tags.index') }}">
                            <i class="bi bi-tags me-1"></i>Tags
                        </a>
                    </li>
                </ul>
                <form class="d-flex me-2" action="{{ route('items.index') }}" method="GET">
                    <div class="input-group">
                        <input type="search" name="search" class="form-control" placeholder="Buscar itens..."
                               value="{{ request('search') }}" aria-label="Buscar">
                        <button class="btn btn-light" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                <a href="{{ route('items.create') }}" class="btn btn-success">
                    <i class="bi bi-plus-lg me-1"></i>Adicionar
                </a>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Main Content -->
    <main>
        @yield('content')
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    @stack('scripts')
</body>
</html>
