@extends('layouts.guest')

@section('content')
<form method="POST" action="{{ route('login') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label">E-mail</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror"
               required autofocus autocomplete="username" inputmode="email" autocapitalize="none">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Senha</label>
        <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror"
               required autocomplete="current-password">
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="form-check mb-4">
        {{-- phone: do not ask for the password every time --}}
        <input id="remember" type="checkbox" name="remember" value="1" class="form-check-input" checked>
        <label for="remember" class="form-check-label">Continuar conectado neste celular</label>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-100">Entrar</button>
</form>
@endsection
